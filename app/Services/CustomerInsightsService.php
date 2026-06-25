<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CustomerInsightsService
{
    public function calculate(Customer $customer): CustomerInsights
    {
        $orders = $customer->orders()
            ->select([
                'id',
                'customer_id',
                'status',
                'source_channel',
                'created_at',
            ])
            ->with([
                'orderItems' => fn ($query) => $query
                    ->select([
                        'id',
                        'order_id',
                        'product_id',
                        'raw_text',
                        'quantity',
                    ])
                    ->with([
                        'product:id,name',
                    ])
                    ->orderBy('sort_order')
                    ->orderBy('id'),
            ])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $totalOrders = $orders->count();
        $firstOrderDate = $this->toCarbonDate($orders->first()?->created_at);
        $lastOrderDate = $this->toCarbonDate($orders->last()?->created_at);
        $averageDays = $this->calculateAverageDaysBetweenOrders($orders);
        $favoriteProducts = $this->calculateFavoriteProducts($orders);
        $favoriteChannel = $this->calculateFavoriteChannel($orders);
        $favoriteHour = $this->calculateFavoriteHour($orders);
        $activeDays = $this->calculateActiveDays($orders);
        $inactiveDays = array_values(array_diff($this->weekDays(), $activeDays));
        $averageItems = $this->calculateAverageItemsPerOrder($orders);
        $averageTicketItems = $this->calculateAverageTicketItemsPerOrder($orders);

        return new CustomerInsights(
            segment: $this->calculateSegment($totalOrders, $lastOrderDate),
            favorite_products: $favoriteProducts,
            favorite_channel: $favoriteChannel,
            favorite_hour: $favoriteHour,
            average_days: $averageDays,
            inactive_days: $inactiveDays,
            total_orders: $totalOrders,
            completed_orders: $orders->where('status', Order::STATUS_DISPATCHED)->count(),
            cancelled_orders: $orders->where('status', Order::STATUS_CANCELLED)->count(),
            average_items: $averageItems,
            average_ticket_items: $averageTicketItems,
            active_days: $activeDays,
            first_order_date: $firstOrderDate,
            last_order_date: $lastOrderDate,
        );
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return array<int, array{product: string, times_ordered: int}>
     */
    private function calculateFavoriteProducts(Collection $orders): array
    {
        $groups = [];
        $limit = (int) config('customer_insights.favorite_products_limit', 5);

        foreach ($orders as $order) {
            foreach ($order->orderItems as $item) {
                $key = $item->product_id !== null
                    ? 'product:' . $item->product_id
                    : 'raw:' . Str::of((string) $item->raw_text)->squish()->lower()->toString();

                if ($key === 'raw:') {
                    $key = 'raw:sin-texto';
                }

                if (! isset($groups[$key])) {
                    $groups[$key] = [
                        'product' => $this->resolveFavoriteProductLabel($item),
                        'times_ordered' => 0,
                    ];
                }

                $groups[$key]['times_ordered']++;
            }
        }

        uasort($groups, static function (array $left, array $right): int {
            if ($left['times_ordered'] === $right['times_ordered']) {
                return strcmp($left['product'], $right['product']);
            }

            return $right['times_ordered'] <=> $left['times_ordered'];
        });

        return array_slice(array_values($groups), 0, $limit);
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return array{name: string, percentage: float}
     */
    private function calculateFavoriteChannel(Collection $orders): array
    {
        if ($orders->isEmpty()) {
            return [
                'name' => 'Unknown',
                'percentage' => 0.0,
            ];
        }

        $labels = [
            'whatsapp' => 'WhatsApp',
            'telegram' => 'Telegram',
            'instagram' => 'Instagram',
        ];

        $priorities = [
            'WhatsApp' => 0,
            'Telegram' => 1,
            'Instagram' => 2,
            'Unknown' => 3,
        ];

        $counts = [];

        foreach ($orders as $order) {
            $channel = $labels[strtolower((string) $order->source_channel)] ?? 'Unknown';
            $counts[$channel] = ($counts[$channel] ?? 0) + 1;
        }

        $favoriteChannel = 'Unknown';
        $favoriteCount = 0;

        foreach ($priorities as $channel => $priority) {
            $count = (int) ($counts[$channel] ?? 0);

            if (
                $count > $favoriteCount
                || ($count === $favoriteCount && $priority < ($priorities[$favoriteChannel] ?? PHP_INT_MAX))
            ) {
                $favoriteChannel = $channel;
                $favoriteCount = $count;
            }
        }

        return [
            'name' => $favoriteChannel,
            'percentage' => round(($favoriteCount / $orders->count()) * 100, 2),
        ];
    }

    /**
     * @param  Collection<int, Order>  $orders
     */
    private function calculateFavoriteHour(Collection $orders): ?string
    {
        if ($orders->isEmpty()) {
            return null;
        }

        $counts = [];

        foreach ($orders as $order) {
            $hour = $this->toCarbonDate($order->created_at)?->format('H:00');

            if ($hour === null) {
                continue;
            }

            $counts[$hour] = ($counts[$hour] ?? 0) + 1;
        }

        if ($counts === []) {
            return null;
        }

        ksort($counts);

        $favoriteHour = null;
        $favoriteCount = -1;

        foreach ($counts as $hour => $count) {
            if ($count > $favoriteCount) {
                $favoriteHour = $hour;
                $favoriteCount = $count;
            }
        }

        return $favoriteHour;
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return array<int, string>
     */
    private function calculateActiveDays(Collection $orders): array
    {
        if ($orders->isEmpty()) {
            return [];
        }

        $weekDays = $this->weekDays();
        $priorityMap = array_flip($weekDays);
        $counts = array_fill_keys($weekDays, 0);

        foreach ($orders as $order) {
            $day = $this->toCarbonDate($order->created_at)?->format('l');

            if ($day !== null && array_key_exists($day, $counts)) {
                $counts[$day]++;
            }
        }

        $activeDays = array_values(array_filter($weekDays, static fn (string $day): bool => ($counts[$day] ?? 0) > 0));

        usort($activeDays, static function (string $left, string $right) use ($counts, $priorityMap): int {
            $countComparison = ($counts[$right] ?? 0) <=> ($counts[$left] ?? 0);

            if ($countComparison !== 0) {
                return $countComparison;
            }

            return ($priorityMap[$left] ?? PHP_INT_MAX) <=> ($priorityMap[$right] ?? PHP_INT_MAX);
        });

        return $activeDays;
    }

    /**
     * @param  Collection<int, Order>  $orders
     */
    private function calculateAverageDaysBetweenOrders(Collection $orders): ?float
    {
        if ($orders->count() < 2) {
            return null;
        }

        $dates = $orders
            ->pluck('created_at')
            ->filter()
            ->map(fn ($date) => $this->toCarbonDate($date))
            ->filter()
            ->values();

        if ($dates->count() < 2) {
            return null;
        }

        $intervals = [];

        for ($index = 1; $index < $dates->count(); $index++) {
            /** @var CarbonInterface $previous */
            $previous = $dates[$index - 1];
            /** @var CarbonInterface $current */
            $current = $dates[$index];

            $intervals[] = $previous->diffInSeconds($current) / 86400;
        }

        return round(array_sum($intervals) / count($intervals), 2);
    }

    /**
     * @param  Collection<int, Order>  $orders
     */
    private function calculateAverageItemsPerOrder(Collection $orders): ?float
    {
        if ($orders->isEmpty()) {
            return null;
        }

        $totalItems = 0;

        foreach ($orders as $order) {
            $totalItems += $order->orderItems->count();
        }

        return round($totalItems / $orders->count(), 2);
    }

    /**
     * @param  Collection<int, Order>  $orders
     */
    private function calculateAverageTicketItemsPerOrder(Collection $orders): ?float
    {
        if ($orders->isEmpty()) {
            return null;
        }

        $totalTicketItems = 0.0;

        foreach ($orders as $order) {
            $totalTicketItems += $order->orderItems->sum(static fn (OrderItem $item): float => (float) $item->quantity);
        }

        return round($totalTicketItems / $orders->count(), 2);
    }

    private function calculateSegment(int $totalOrders, ?CarbonInterface $lastOrderDate): string
    {
        $thresholds = config('customer_insights.segment_thresholds', []);
        $inactiveDays = (int) ($thresholds['inactive_days'] ?? 90);
        $vipMinOrders = (int) ($thresholds['vip_min_orders'] ?? 20);
        $frequentMinOrders = (int) ($thresholds['frequent_min_orders'] ?? 3);
        $newMaxOrders = (int) ($thresholds['new_max_orders'] ?? 2);

        if ($totalOrders === 0 || $lastOrderDate === null) {
            return 'INACTIVE';
        }

        if ($lastOrderDate->lessThanOrEqualTo(now()->subDays($inactiveDays))) {
            return 'INACTIVE';
        }

        if ($totalOrders >= $vipMinOrders) {
            return 'VIP';
        }

        if ($totalOrders >= $frequentMinOrders) {
            return 'FREQUENT';
        }

        if ($totalOrders <= $newMaxOrders) {
            return 'NEW';
        }

        return 'FREQUENT';
    }

    private function resolveFavoriteProductLabel(OrderItem $item): string
    {
        if ($item->product_id !== null) {
            return $item->product?->name ?? ('Product #' . $item->product_id);
        }

        $rawText = Str::of((string) $item->raw_text)->squish()->toString();

        return $rawText !== '' ? $rawText : 'Sin texto';
    }

    /**
     * @param  mixed  $date
     */
    private function toCarbonDate(mixed $date): ?CarbonInterface
    {
        if ($date instanceof CarbonInterface) {
            return $date;
        }

        if ($date === null) {
            return null;
        }

        return Carbon::parse($date);
    }

    /**
     * @return array<int, string>
     */
    private function weekDays(): array
    {
        return [
            'Monday',
            'Tuesday',
            'Wednesday',
            'Thursday',
            'Friday',
            'Saturday',
            'Sunday',
        ];
    }
}
