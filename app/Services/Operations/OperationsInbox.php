<?php

namespace App\Services\Operations;

use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Arma los datos de la bandeja de operaciones: pedidos visibles, agenda, cola de acciones y feed.
 */
class OperationsInbox
{
    public function __construct(
        private readonly OperationsAgenda $operationsAgenda,
        private readonly NextActionQueue $nextActionQueue,
        private readonly OperationsOrderScope $orderScope,
        private readonly CustomerContextBuilder $customerContexts,
        private readonly OperationsOrderSerializer $orderSerializer,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function snapshot(Order $order): array
    {
        $order = $this->orderScope->scopedQuery()
            ->with([
                'branch:id,name',
                'customer:id,name,phone',
                'incomingMessage:id,status,received_at',
                'possibleDuplicateOf:id,customer_id,status,created_at',
                'fulfillmentPlan',
                'orderStatusHistories' => fn ($query) => $query->orderBy('created_at'),
                'notificationLogs' => fn ($query) => $query->orderByDesc('created_at'),
                'orderItems' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
                'orderItems.product:id,name',
            ])
            ->withCount([
                'orderItems as recognized_order_items_count' => fn ($query) => $query->whereNotNull('product_id'),
            ])
            ->whereKey($order->getKey())
            ->firstOrFail();

        $customerContexts = $this->customerContexts->buildCustomerContexts(collect([$order]));

        return $this->orderSerializer->serializeOrder($order, $customerContexts[(int) $order->customer_id] ?? null);
    }

    /**
     * @return array{
     *     orders: Collection<int, Order>,
     *     ordersData: array<int, array<string, mixed>>,
     *     feedData: array<string, mixed>,
     *     filters: array<string, mixed>,
     *     selectedOrderId: int,
     *     selectedOrder: array<string, mixed>|null
     * }
     */
    public function buildOperationsPayload(Request $request): array
    {
        $filters = $this->validateFilters($request);
        $baseQuery = $this->buildInboxQuery($filters);

        $visibleOrders = (clone $baseQuery)->get();
        $customerContexts = $this->customerContexts->buildCustomerContexts($visibleOrders);

        $ordersData = $visibleOrders
            ->map(function (Order $order) use ($customerContexts): array {
                return $this->orderSerializer->serializeOrder($order, $customerContexts[(int) $order->customer_id] ?? null);
            })
            ->values()
            ->all();

        $agendaData = $this->operationsAgenda->build(collect($ordersData), now());
        $queueData = $this->nextActionQueue->build(collect($ordersData), now());

        $selectedOrderId = $this->resolveSelectedOrderId($request, $ordersData);
        $selectedOrder = collect($ordersData)->firstWhere('id', $selectedOrderId) ?? ($ordersData[0] ?? null);

        return [
            'ordersData' => $ordersData,
            'agendaData' => $agendaData,
            'queueData' => $queueData,
            'feedData' => $this->buildFeedPayloadFromOrders(collect($ordersData), $agendaData, $queueData),
            'filters' => $filters,
            'selectedOrderId' => $selectedOrderId,
            'selectedOrder' => $selectedOrder,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildFeedPayload(Request $request): array
    {
        $filters = $this->validateFilters($request);
        $baseQuery = $this->buildInboxQuery($filters);
        $visibleOrders = (clone $baseQuery)->get();
        $customerContexts = $this->customerContexts->buildCustomerContexts($visibleOrders);
        $ordersData = $visibleOrders
            ->map(function (Order $order) use ($customerContexts): array {
                return $this->orderSerializer->serializeOrder($order, $customerContexts[(int) $order->customer_id] ?? null);
            })
            ->values();
        $agendaData = $this->operationsAgenda->build($ordersData, now());
        $queueData = $this->nextActionQueue->build($ordersData, now());

        return $this->buildFeedPayloadFromOrders($ordersData, $agendaData, $queueData);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function buildInboxQuery(array $filters): Builder
    {
        $query = $this->orderScope->scopedQuery()
            ->with([
                'branch:id,name',
                'customer:id,name,phone',
                'incomingMessage:id,status,received_at',
                'possibleDuplicateOf:id,customer_id,status,created_at',
                'fulfillmentPlan:id,order_id,requested_date,requested_time_window,delivery_method,payment_method,pickup_branch_id,delivery_address,delivery_notes,priority_score,priority_level,priority_reason,commitment_date,commitment_time,sla_minutes,remaining_sla_minutes,risk_level,risk_reason,decision_version,planner_confidence,planner_notes,metadata_json',
                'orderStatusHistories' => fn ($query) => $query->orderBy('created_at'),
                'notificationLogs' => fn ($query) => $query->orderByDesc('created_at'),
                'orderItems' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
                'orderItems.product:id,name',
            ])
            ->withCount([
                'orderItems as recognized_order_items_count' => fn ($query) => $query->whereNotNull('product_id'),
            ]);

        return $query
            ->orderByRaw(
                "CASE
                    WHEN status = '".Order::STATUS_PENDING_REVIEW."' AND reviewed_at IS NULL THEN 0
                    WHEN status = '".Order::STATUS_PENDING_REVIEW."' THEN 1
                    WHEN status = '".Order::STATUS_PREPARING."' THEN 2
                    WHEN status = '".Order::STATUS_READY_FOR_DISPATCH."' THEN 3
                    WHEN status = '".Order::STATUS_DISPATCHED."' THEN 4
                    ELSE 5
                END"
            )
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $orders
     * @param  array{sections:array<int, array<string, mixed>>, metrics:array<string, mixed>}  $agendaData
     * @param  array{sections:array<int, array<string, mixed>>, metrics:array<string, mixed>}  $queueData
     * @return array<string, mixed>
     */
    private function buildFeedPayloadFromOrders(Collection $orders, array $agendaData, array $queueData): array
    {
        $latestOrderId = (int) ($orders->max('id') ?? 0);
        $pendingReviewCount = $orders->where('status', Order::STATUS_PENDING_REVIEW)->count();
        $confirmedCount = $orders->where('status', Order::STATUS_CONFIRMED)->count();
        $preparingCount = $orders->where('status', Order::STATUS_PREPARING)->count();
        $readyForDispatchCount = $orders->where('status', Order::STATUS_READY_FOR_DISPATCH)->count();
        $dispatchedCount = $orders->where('status', Order::STATUS_DISPATCHED)->count();

        return [
            'latest_order_id' => $latestOrderId,
            'server_time' => now()->toIso8601String(),
            'agenda' => $agendaData,
            'next_action_queue' => $queueData,
            'counts' => [
                'pending_review' => $pendingReviewCount,
                'confirmed' => $confirmedCount,
                'preparing' => $preparingCount,
                'ready_for_dispatch' => $readyForDispatchCount,
                'dispatched' => $dispatchedCount,
            ],
            'inbox' => $orders->values()->all(),
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
}
