<?php

namespace App\Services\Operations;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderNotificationLog;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Contexto del cliente (historial, favoritos, segmento) que acompaña cada pedido en operaciones.
 */
class CustomerContextBuilder
{
    public function __construct(
        private readonly OrderDisplayLabels $labels,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function buildCustomerContexts(Collection $orders): array
    {
        $customerIds = $orders
            ->pluck('customer_id')
            ->filter()
            ->map(fn ($customerId) => (int) $customerId)
            ->unique()
            ->values();

        if ($customerIds->isEmpty()) {
            return [];
        }

        $customers = Customer::query()
            ->whereIn('id', $customerIds)
            ->withCount('orders')
            ->get()
            ->keyBy('id');

        $orderHistory = Order::query()
            ->whereIn('customer_id', $customerIds)
            ->select([
                'id',
                'customer_id',
                'status',
                'source_channel',
                'created_at',
                'possible_duplicate_of_order_id',
                'reviewed_at',
                'parser_confidence',
                'raw_message_text',
            ])
            ->with([
                'orderItems' => fn ($query) => $query
                    ->select([
                        'id',
                        'order_id',
                        'product_id',
                        'quantity',
                        'unit',
                        'raw_text',
                        'sort_order',
                    ])
                    ->with(['product:id,name'])
                    ->orderBy('sort_order')
                    ->orderBy('id'),
                'possibleDuplicateOf:id,customer_id,status,created_at',
                'fulfillmentPlan',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy('customer_id');

        $notificationCounts = OrderNotificationLog::query()
            ->whereIn('customer_id', $customerIds)
            ->whereIn('status', [OrderNotificationLog::STATUS_QUEUED, OrderNotificationLog::STATUS_FAILED])
            ->selectRaw('customer_id, COUNT(*) as total')
            ->groupBy('customer_id')
            ->pluck('total', 'customer_id');

        $contexts = [];

        foreach ($customers as $customer) {
            $history = $orderHistory->get($customer->id, collect());
            $baseContext = $this->buildBaseCustomerContext(
                customer: $customer,
                history: $history,
                openNotifications: (int) ($notificationCounts->get($customer->id) ?? 0),
            );

            $contexts[$customer->id] = $baseContext;
        }

        return $contexts;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildBaseCustomerContext(Customer $customer, Collection $history, int $openNotifications): array
    {
        $lastOrder = $history->first();
        $favoriteProducts = $this->favoriteProducts($history);
        $favoriteChannel = $this->favoriteChannel($history);
        $segment = $this->customerSegment((int) $customer->orders_count, $lastOrder?->created_at);
        $recentActivity = $history
            ->take(5)
            ->map(function (Order $order): array {
                return [
                    'label' => 'Pedido #'.$order->id,
                    'status' => $this->labels->statusLabel($order->status),
                    'channel' => $this->labels->channelLabel($order->source_channel),
                    'elapsed' => $this->labels->elapsedLabel($order->created_at),
                    'duplicate' => $order->possible_duplicate_of_order_id !== null,
                ];
            })
            ->values()
            ->all();

        return [
            'name' => $customer->name,
            'phone' => $customer->phone,
            'total_orders' => (int) $customer->orders_count,
            'favorite_products' => $favoriteProducts,
            'favorite_channel' => $favoriteChannel,
            'last_order' => $lastOrder !== null ? [
                'id' => $lastOrder->id,
                'label' => 'Pedido #'.$lastOrder->id,
                'elapsed' => $this->labels->elapsedLabel($lastOrder->created_at),
                'status' => $this->labels->statusLabel($lastOrder->status),
            ] : null,
            'segment' => $segment,
            'open_notifications' => $openNotifications,
            'recent_activity' => $recentActivity,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function favoriteProducts(Collection $history): array
    {
        $groups = [];

        foreach ($history as $order) {
            foreach ($order->orderItems as $item) {
                $key = $item->product_id !== null
                    ? 'product:'.$item->product_id
                    : 'raw:'.Str::of((string) $item->raw_text)->squish()->lower()->toString();

                if ($key === 'raw:') {
                    $key = 'raw:sin-texto';
                }

                if (! isset($groups[$key])) {
                    $groups[$key] = [
                        'label' => $this->labels->resolveItemLabel($item->product_id, $item->raw_text, $item->product?->name),
                        'count' => 0,
                    ];
                }

                $groups[$key]['count']++;
            }
        }

        uasort($groups, static function (array $left, array $right): int {
            if ($left['count'] === $right['count']) {
                return strcmp($left['label'], $right['label']);
            }

            return $right['count'] <=> $left['count'];
        });

        return array_slice(
            array_map(
                static fn (array $entry): string => $entry['label'].' x'.$entry['count'],
                array_values($groups),
            ),
            0,
            3,
        );
    }

    /**
     * @return array{name: string, percentage: float}
     */
    private function favoriteChannel(Collection $history): array
    {
        if ($history->isEmpty()) {
            return [
                'name' => 'Unknown',
                'percentage' => 0.0,
            ];
        }

        $counts = [];

        foreach ($history as $order) {
            $channel = $this->labels->channelLabel($order->source_channel);
            $counts[$channel] = ($counts[$channel] ?? 0) + 1;
        }

        arsort($counts);
        $favoriteChannel = array_key_first($counts) ?? 'Unknown';

        return [
            'name' => $favoriteChannel,
            'percentage' => round(((int) $counts[$favoriteChannel] / $history->count()) * 100, 2),
        ];
    }

    private function customerSegment(int $totalOrders, mixed $lastOrderAt): string
    {
        if ($totalOrders === 0 || $lastOrderAt === null) {
            return 'Inactive';
        }

        $lastSeen = $lastOrderAt instanceof CarbonInterface ? $lastOrderAt : Carbon::parse($lastOrderAt);
        $thresholds = config('customer_insights.segment_thresholds', []);
        $inactiveDays = (int) ($thresholds['inactive_days'] ?? 90);
        $vipMinOrders = (int) ($thresholds['vip_min_orders'] ?? 20);
        $frequentMinOrders = (int) ($thresholds['frequent_min_orders'] ?? 3);
        $newMaxOrders = (int) ($thresholds['new_max_orders'] ?? 2);

        if ($lastSeen->lessThanOrEqualTo(now()->subDays($inactiveDays))) {
            return 'Inactive';
        }

        if ($totalOrders >= $vipMinOrders) {
            return 'VIP';
        }

        if ($totalOrders >= $frequentMinOrders) {
            return 'Frequent';
        }

        if ($totalOrders <= $newMaxOrders) {
            return 'New';
        }

        return 'Frequent';
    }
}
