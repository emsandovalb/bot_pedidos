<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderNotificationLog;
use App\Services\OrderWorkflowActionPresenter;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class OperationsController extends Controller
{
    public function __construct(
        private readonly OrderWorkflowActionPresenter $orderWorkflowActionPresenter,
    ) {
    }

    private const STATUS_FILTERS = [
        'all',
        'nuevos',
        'en_revision',
        'preparando',
        'listos',
        'despachados',
    ];

    private const CHANNEL_FILTERS = [
        'whatsapp',
        'telegram',
    ];

    private const PRIORITY_FILTERS = [
        'urgent',
        'duplicate',
        'vip',
    ];

    public function index(Request $request): View
    {
        $payload = $this->buildOperationsPayload($request);

        return view('operations.index', [
            'ordersData' => $payload['ordersData'],
            'feedData' => $payload['feedData'],
            'filters' => $payload['filters'],
            'selectedOrderId' => $payload['selectedOrderId'],
            'selectedOrder' => $payload['selectedOrder'],
            'statusFilters' => self::STATUS_FILTERS,
            'channelFilters' => self::CHANNEL_FILTERS,
            'priorityFilters' => self::PRIORITY_FILTERS,
        ]);
    }

    public function feed(Request $request): JsonResponse
    {
        return response()->json($this->buildFeedPayload($request));
    }

    public function snapshot(Order $order): JsonResponse
    {
        $order = $this->scopedQuery()
            ->with([
                'branch:id,name',
                'customer:id,name,phone',
                'incomingMessage:id,status,received_at',
                'possibleDuplicateOf:id,customer_id,status,created_at',
                'fulfillmentPlan',
                'orderItems' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
                'orderItems.product:id,name',
            ])
            ->withCount([
                'orderItems as recognized_order_items_count' => fn ($query) => $query->whereNotNull('product_id'),
            ])
            ->whereKey($order->getKey())
            ->firstOrFail();

        $customerContexts = $this->buildCustomerContexts(collect([$order]));
        $serializedOrder = $this->serializeOrder($order, $customerContexts[(int) $order->customer_id] ?? null);

        return response()->json($serializedOrder);
    }

    /**
     * @return array{
     *     orders: \Illuminate\Support\Collection<int, Order>,
     *     ordersData: array<int, array<string, mixed>>,
     *     feedData: array<string, mixed>,
     *     filters: array<string, mixed>,
     *     selectedOrderId: int,
     *     selectedOrder: array<string, mixed>|null
     * }
     */
    private function buildOperationsPayload(Request $request): array
    {
        $filters = $this->validateFilters($request);
        $baseQuery = $this->buildInboxQuery($filters);

        $visibleOrders = (clone $baseQuery)->get();
        $customerContexts = $this->buildCustomerContexts($visibleOrders);

        $ordersData = $visibleOrders
            ->map(function (Order $order) use ($customerContexts): array {
                return $this->serializeOrder($order, $customerContexts[(int) $order->customer_id] ?? null);
            })
            ->values()
            ->all();

        $selectedOrderId = $this->resolveSelectedOrderId($request, $ordersData);
        $selectedOrder = collect($ordersData)->firstWhere('id', $selectedOrderId) ?? ($ordersData[0] ?? null);

        return [
            'ordersData' => $ordersData,
            'feedData' => $this->buildFeedPayloadFromQuery($baseQuery, $visibleOrders, $customerContexts),
            'filters' => $filters,
            'selectedOrderId' => $selectedOrderId,
            'selectedOrder' => $selectedOrder,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFeedPayload(Request $request): array
    {
        $filters = $this->validateFilters($request);
        $baseQuery = $this->buildInboxQuery($filters);
        $visibleOrders = (clone $baseQuery)->get();
        $customerContexts = $this->buildCustomerContexts($visibleOrders);

        return $this->buildFeedPayloadFromQuery($baseQuery, $visibleOrders, $customerContexts);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function buildInboxQuery(array $filters): Builder
    {
        $query = $this->scopedQuery()
            ->with([
                'branch:id,name',
                'customer:id,name,phone',
                'incomingMessage:id,status,received_at',
                'possibleDuplicateOf:id,customer_id,status,created_at',
                'fulfillmentPlan',
                'orderItems' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
                'orderItems.product:id,name',
            ])
            ->withCount([
                'orderItems as recognized_order_items_count' => fn ($query) => $query->whereNotNull('product_id'),
            ]);

        $this->applySearchFilter($query, (string) ($filters['search'] ?? ''));
        $this->applyQuickFilters(
            $query,
            (string) ($filters['status'] ?? 'all'),
            (string) ($filters['channel'] ?? ''),
            (string) ($filters['priority'] ?? ''),
        );

        return $query
            ->orderByRaw(
                "CASE
                    WHEN status = '" . Order::STATUS_PENDING_REVIEW . "' AND reviewed_at IS NULL THEN 0
                    WHEN status = '" . Order::STATUS_PENDING_REVIEW . "' THEN 1
                    WHEN status = '" . Order::STATUS_PREPARING . "' THEN 2
                    WHEN status = '" . Order::STATUS_READY_FOR_DISPATCH . "' THEN 3
                    WHEN status = '" . Order::STATUS_DISPATCHED . "' THEN 4
                    ELSE 5
                END"
            )
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    /**
     * @param  Collection<int, Order>  $visibleOrders
     * @param  array<int, array<string, mixed>>  $customerContexts
     * @return array<string, mixed>
     */
    private function buildFeedPayloadFromQuery(Builder $query, Collection $visibleOrders, array $customerContexts): array
    {
        $latestOrderId = (int) (clone $query)->max('id');
        $pendingReviewCount = (clone $query)->where('status', Order::STATUS_PENDING_REVIEW)->count();
        $confirmedCount = (clone $query)->where('status', Order::STATUS_CONFIRMED)->count();
        $preparingCount = (clone $query)->where('status', Order::STATUS_PREPARING)->count();
        $readyForDispatchCount = (clone $query)->where('status', Order::STATUS_READY_FOR_DISPATCH)->count();
        $dispatchedCount = (clone $query)->where('status', Order::STATUS_DISPATCHED)->count();

        return [
            'latest_order_id' => $latestOrderId,
            'server_time' => now()->toIso8601String(),
            'counts' => [
                'pending_review' => $pendingReviewCount,
                'confirmed' => $confirmedCount,
                'preparing' => $preparingCount,
                'ready_for_dispatch' => $readyForDispatchCount,
                'dispatched' => $dispatchedCount,
            ],
            'inbox' => $visibleOrders
                ->map(function (Order $order) use ($customerContexts): array {
                    return $this->serializeInboxOrder($order, $customerContexts[(int) $order->customer_id] ?? null);
                })
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateFilters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'customer' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:32'],
            'channel' => ['nullable', 'string', 'max:32'],
            'priority' => ['nullable', 'string', 'max:32'],
            'vip' => ['nullable', 'boolean'],
            'duplicates' => ['nullable', 'boolean'],
            'order' => ['nullable', 'integer'],
        ]);
    }

    private function scopedQuery(): Builder
    {
        $user = auth()->user();

        return Order::query()->where(function (Builder $query) use ($user): void {
            if ($user?->canViewAllBranchesForRead()) {
                if ($user?->organization_id) {
                    $query->where('organization_id', $user->organization_id);

                    return;
                }

                $query->whereRaw('1 = 0');

                return;
            }

            $branchIds = $user?->visibleBranchIds() ?? [];

            if ($branchIds === []) {
                $query->whereRaw('1 = 0');

                return;
            }

            $query->whereIn('branch_id', $branchIds);
        });
    }

    private function applySearchFilter(Builder $query, string $search): void
    {
        $search = trim($search);

        if ($search === '') {
            return;
        }

        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';

        $query->where(function (Builder $searchQuery) use ($like, $search): void {
            $searchQuery
                ->where('orders.id', is_numeric($search) ? (int) $search : -1)
                ->orWhere('raw_message_text', 'like', $like)
                ->orWhere('external_message_id', 'like', $like)
                ->orWhereHas('customer', function (Builder $customerQuery) use ($like): void {
                    $customerQuery
                        ->where('name', 'like', $like)
                        ->orWhere('phone', 'like', $like);
                })
                ->orWhereHas('branch', function (Builder $branchQuery) use ($like): void {
                    $branchQuery->where('name', 'like', $like);
                });
        });
    }

    private function applyQuickFilters(Builder $query, string $status, string $channel, string $priority): void
    {
        if (in_array($status, self::STATUS_FILTERS, true) && $status !== 'all') {
            match ($status) {
                'nuevos' => $query->where('status', Order::STATUS_PENDING_REVIEW)->whereNull('reviewed_at'),
                'en_revision' => $query->where('status', Order::STATUS_PENDING_REVIEW)->whereNotNull('reviewed_at'),
                'preparando' => $query->where('status', Order::STATUS_PREPARING),
                'listos' => $query->where('status', Order::STATUS_READY_FOR_DISPATCH),
                'despachados' => $query->where('status', Order::STATUS_DISPATCHED),
                default => null,
            };
        }

        if (in_array($channel, self::CHANNEL_FILTERS, true)) {
            $query->where('source_channel', $channel);
        }

        if ($priority === 'duplicate') {
            $query->whereNotNull('possible_duplicate_of_order_id');
        } elseif ($priority === 'urgent') {
            $query->where(function (Builder $urgentQuery): void {
                $urgentQuery
                    ->where('status', Order::STATUS_PENDING_REVIEW)
                    ->where(function (Builder $stateQuery): void {
                        $stateQuery
                            ->whereNull('parser_confidence')
                            ->orWhere('parser_confidence', '<', 0.5)
                            ->orWhere('created_at', '<=', now()->subMinutes(20));
                    });
            });
        } elseif ($priority === 'vip') {
            $query->whereExists(function ($vipQuery): void {
                $vipQuery
                    ->selectRaw('1')
                    ->from('orders as vip_orders')
                    ->whereColumn('vip_orders.customer_id', 'orders.customer_id')
                    ->groupBy('vip_orders.customer_id')
                    ->havingRaw('COUNT(*) >= 20');
            });
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildCustomerContexts(Collection $orders): array
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
                    'label' => 'Pedido #' . $order->id,
                    'status' => $this->statusLabel($order->status),
                    'channel' => $this->channelLabel($order->source_channel),
                    'elapsed' => $this->elapsedLabel($order->created_at),
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
                'label' => 'Pedido #' . $lastOrder->id,
                'elapsed' => $this->elapsedLabel($lastOrder->created_at),
                'status' => $this->statusLabel($lastOrder->status),
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
                    ? 'product:' . $item->product_id
                    : 'raw:' . Str::of((string) $item->raw_text)->squish()->lower()->toString();

                if ($key === 'raw:') {
                    $key = 'raw:sin-texto';
                }

                if (! isset($groups[$key])) {
                    $groups[$key] = [
                        'label' => $this->resolveItemLabel($item->product_id, $item->raw_text, $item->product?->name),
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
                static fn (array $entry): string => $entry['label'] . ' x' . $entry['count'],
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
            $channel = $this->channelLabel($order->source_channel);
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

    /**
     * @return array<int, string>
     */
    private function currentAlerts(array $customerContext, Order $order): array
    {
        $alerts = [];

        if ($customerContext['segment'] === 'VIP') {
            $alerts[] = 'Cliente VIP';
        }

        if ($customerContext['open_notifications'] > 0) {
            $alerts[] = $customerContext['open_notifications'] . ' notificacion(es) abiertas';
        }

        if ($order->possible_duplicate_of_order_id !== null) {
            $alerts[] = 'Posible duplicado';
        }

        if ($order->parser_confidence !== null && (float) $order->parser_confidence < 0.5) {
            $alerts[] = 'Confianza baja';
        }

        if ($order->status === Order::STATUS_PENDING_REVIEW && $order->created_at !== null && $order->created_at->lt(now()->subMinutes(20))) {
            $alerts[] = 'Pedido envejecido';
        }

        return $alerts;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeOrder(Order $order, ?array $customerContext): array
    {
        $workflow = $this->orderWorkflowActionPresenter->present($order);

        $customerContext ??= [
            'name' => $order->customer?->name ?? 'Sin cliente',
            'phone' => $order->customer?->phone ?? 'Sin telefono',
            'total_orders' => 0,
            'favorite_products' => [],
            'favorite_channel' => ['name' => 'Unknown', 'percentage' => 0.0],
            'last_order' => null,
            'segment' => 'Inactive',
            'open_notifications' => 0,
            'recent_activity' => [],
        ];

        $enrichedCustomerContext = $customerContext;
        $enrichedCustomerContext['current_order'] = [
            'id' => $order->id,
            'status' => $this->statusLabel($order->status),
            'channel' => $this->channelLabel($order->source_channel),
            'elapsed' => $this->elapsedLabel($order->created_at),
            'preview' => Str::limit($order->raw_message_text ?? 'Sin mensaje original', 120),
            'possible_duplicate' => $order->possibleDuplicateOf !== null,
        ];
        $enrichedCustomerContext['current_alerts'] = $this->currentAlerts($customerContext, $order);

        return [
            'id' => $order->id,
            'status' => $order->status,
            'status_label' => $this->statusLabel($order->status),
            'status_tone' => $this->statusTone($order->status),
            'source_channel' => $order->source_channel,
            'channel' => $this->channelLabel($order->source_channel),
            'channel_key' => $order->source_channel,
            'created_at' => $order->created_at?->toIso8601String(),
            'received_at' => $order->incomingMessage?->received_at?->toIso8601String(),
            'customer_name' => $order->customer?->name ?? 'Sin cliente',
            'customer' => $order->customer?->name ?? 'Sin cliente',
            'customer_phone' => $order->customer?->phone ?? 'Sin telefono',
            'phone' => $order->customer?->phone ?? 'Sin telefono',
            'branch_name' => $order->branch?->name ?? 'Sin sucursal',
            'branch' => $order->branch?->name ?? 'Sin sucursal',
            'elapsed_label' => $this->elapsedLabel($order->created_at),
            'created_at_label' => $order->created_at?->format('d/m/Y H:i') ?? 'Sin fecha',
            'preview' => Str::limit($order->raw_message_text ?? 'Sin mensaje original', 120),
            'original_message' => $order->raw_message_text ?? 'Sin mensaje original',
            'created_at_iso' => $order->created_at?->toIso8601String(),
            'items_count' => $order->orderItems->count(),
            'recognized_items_count' => (int) ($order->recognized_order_items_count ?? 0),
            'unread' => $order->reviewed_at === null,
            'duplicate' => $order->possible_duplicate_of_order_id !== null,
            'possible_duplicate' => $order->possible_duplicate_of_order_id !== null,
            'vip' => ($customerContext['segment'] ?? 'Inactive') === 'VIP',
            'parser_confidence' => $order->parser_confidence !== null ? (float) $order->parser_confidence : null,
            'open_notifications' => (int) ($customerContext['open_notifications'] ?? 0),
            'allowed_actions' => $workflow['allowed_actions'],
            'primary_action' => $workflow['primary_action'],
            'secondary_actions' => $workflow['secondary_actions'],
            'terminal_message' => $workflow['terminal_message'],
            'items' => $order->orderItems
                ->map(function ($item): array {
                    return [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'product_name' => $item->product?->name,
                        'quantity' => (float) $item->quantity,
                        'unit' => $item->unit,
                        'name' => $this->resolveItemLabel($item->product_id, $item->raw_text, $item->product?->name),
                        'raw_text' => $item->raw_text,
                        'notes' => $item->notes,
                    ];
                })
                ->values()
                ->all(),
            'update_url' => route('orders.update', $order),
            'show_url' => route('orders.show', $order),
            'customer_context' => $enrichedCustomerContext,
            'recent_activity' => $enrichedCustomerContext['recent_activity'] ?? [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeInboxOrder(Order $order, ?array $customerContext): array
    {
        $customerContext ??= [
            'segment' => 'Inactive',
            'name' => $order->customer?->name ?? 'Sin cliente',
            'phone' => $order->customer?->phone ?? 'Sin telefono',
            'total_orders' => 0,
            'favorite_products' => [],
            'favorite_channel' => ['name' => 'Unknown', 'percentage' => 0.0],
            'last_order' => null,
            'open_notifications' => 0,
            'recent_activity' => [],
        ];

        return [
            'id' => $order->id,
            'status' => $order->status,
            'status_label' => $this->statusLabel($order->status),
            'status_tone' => $this->statusTone($order->status),
            'channel' => $this->channelLabel($order->source_channel),
            'channel_key' => $order->source_channel,
            'customer_name' => $order->customer?->name ?? 'Sin cliente',
            'customer_phone' => $order->customer?->phone ?? 'Sin telefono',
            'branch_name' => $order->branch?->name ?? 'Sin sucursal',
            'elapsed_label' => $this->elapsedLabel($order->created_at),
            'created_at_label' => $order->created_at?->format('d/m/Y H:i') ?? 'Sin fecha',
            'preview' => Str::limit($order->raw_message_text ?? 'Sin mensaje original', 120),
            'created_at_iso' => $order->created_at?->toIso8601String(),
            'items_count' => $order->orderItems->count(),
            'recognized_items_count' => (int) ($order->recognized_order_items_count ?? 0),
            'unread' => $order->reviewed_at === null,
            'duplicate' => $order->possible_duplicate_of_order_id !== null,
            'vip' => ($customerContext['segment'] ?? 'Inactive') === 'VIP',
            'parser_confidence' => $order->parser_confidence !== null ? (float) $order->parser_confidence : null,
            'update_url' => route('orders.update', $order),
            'show_url' => route('orders.show', $order),
        ];
    }

    private function resolveSelectedOrderId(Request $request, array $ordersData): int
    {
        $requestedOrderId = $request->integer('order');

        if ($requestedOrderId !== null) {
            foreach ($ordersData as $order) {
                if ((int) ($order['id'] ?? 0) === $requestedOrderId) {
                    return $requestedOrderId;
                }
            }
        }

        return (int) ($ordersData[0]['id'] ?? 0);
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            Order::STATUS_PENDING_REVIEW => 'Nuevos',
            Order::STATUS_CONFIRMED => 'Confirmado',
            Order::STATUS_PREPARING => 'Preparando',
            Order::STATUS_READY_FOR_DISPATCH => 'Listo',
            Order::STATUS_DISPATCHED => 'Despachado',
            Order::STATUS_CANCELLED => 'Cancelado',
            Order::STATUS_REJECTED => 'Rechazado',
            default => Str::headline(str_replace('_', ' ', $status)),
        };
    }

    private function statusTone(string $status): string
    {
        return match ($status) {
            Order::STATUS_PENDING_REVIEW => 'bg-amber-50 text-amber-800 ring-1 ring-amber-100',
            Order::STATUS_CONFIRMED => 'bg-blue-50 text-blue-800 ring-1 ring-blue-100',
            Order::STATUS_PREPARING => 'bg-violet-50 text-violet-800 ring-1 ring-violet-100',
            Order::STATUS_READY_FOR_DISPATCH => 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-100',
            Order::STATUS_DISPATCHED => 'bg-slate-100 text-slate-700 ring-1 ring-slate-200',
            Order::STATUS_CANCELLED => 'bg-red-50 text-red-800 ring-1 ring-red-100',
            Order::STATUS_REJECTED => 'bg-slate-100 text-slate-700 ring-1 ring-slate-200',
            default => 'bg-slate-100 text-slate-700 ring-1 ring-slate-200',
        };
    }

    private function channelLabel(?string $channel): string
    {
        return match (strtolower((string) $channel)) {
            'whatsapp' => 'WhatsApp',
            'telegram' => 'Telegram',
            default => $channel !== null && $channel !== '' ? Str::headline($channel) : 'Sin canal',
        };
    }

    private function elapsedLabel(mixed $date): string
    {
        if (! $date instanceof CarbonInterface) {
            return 'Sin fecha';
        }

        $minutes = $date->diffInMinutes(now());

        if ($minutes < 60) {
            return 'Hace ' . max(1, (int) $minutes) . ' min';
        }

        $hours = intdiv((int) $minutes, 60);

        if ($hours < 24) {
            return 'Hace ' . $hours . ' h';
        }

        $days = intdiv($hours, 24);

        return 'Hace ' . $days . ' d';
    }

    private function resolveItemLabel(?int $productId, ?string $rawText, ?string $productName): string
    {
        if ($productName !== null && $productName !== '') {
            return $productName;
        }

        if ($productId !== null) {
            return 'Producto #' . $productId;
        }

        $label = Str::of((string) $rawText)->squish()->toString();

        return $label !== '' ? $label : 'Sin texto';
    }
}
