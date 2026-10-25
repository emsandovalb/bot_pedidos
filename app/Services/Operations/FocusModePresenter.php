<?php

namespace App\Services\Operations;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderNotificationLog;
use App\Services\OrderWorkflowActionPresenter;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class FocusModePresenter
{
    public function __construct(
        private readonly NextActionQueue $nextActionQueue,
        private readonly OrderWorkflowActionPresenter $orderWorkflowActionPresenter,
        private readonly OrderDisplayLabels $labels,
        private readonly OperationsOrderScope $orderScope,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Request $request): array
    {
        $referenceTime = now();
        $orders = $this->visibleOrders();
        $customerContexts = $this->buildCustomerContexts($orders);

        $ordersData = $orders
            ->map(function (Order $order) use ($customerContexts): array {
                return $this->serializeOrder($order, $customerContexts[(int) $order->customer_id] ?? null);
            })
            ->values();

        $feedData = $this->buildFeedPayload($ordersData, $referenceTime);
        $queueData = $feedData['next_action_queue'] ?? ['sections' => [], 'metrics' => []];
        $recommendedOrder = $this->recommendedOrder($ordersData, $queueData);
        $upcomingOrders = $this->upcomingOrders($queueData);
        $nextScheduledOrder = $upcomingOrders[0] ?? null;
        $completedOrders = $this->completedOrders($ordersData, $referenceTime);

        return [
            'serverTime' => $referenceTime->toIso8601String(),
            'visibleName' => 'Mi Jornada',
            'mainQuestion' => '¿Qué debo hacer ahora?',
            'orders' => $ordersData->all(),
            'feedData' => $feedData,
            'queueData' => $queueData,
            'summary' => $this->summary($queueData, $referenceTime),
            'recommendedOrder' => $recommendedOrder,
            'recommendedOrderId' => $recommendedOrder['id'] ?? null,
            'upcomingOrders' => $upcomingOrders,
            'nextScheduledOrder' => $nextScheduledOrder,
            'completedSummary' => [
                'count' => count($completedOrders),
                'orders' => $completedOrders,
            ],
        ];
    }

    private function visibleOrders(): Collection
    {
        return $this->orderScope->scopedQuery()
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
            ])
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
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $orders
     * @return array<string, mixed>
     */
    private function buildFeedPayload(Collection $orders, CarbonInterface $referenceTime): array
    {
        $queueData = $this->nextActionQueue->build($orders, $referenceTime);

        return [
            'latest_order_id' => (int) ($orders->max('id') ?? 0),
            'server_time' => $referenceTime->toIso8601String(),
            'next_action_queue' => $queueData,
            'counts' => [
                'pending_review' => $orders->where('status', Order::STATUS_PENDING_REVIEW)->count(),
                'confirmed' => $orders->where('status', Order::STATUS_CONFIRMED)->count(),
                'preparing' => $orders->where('status', Order::STATUS_PREPARING)->count(),
                'ready_for_dispatch' => $orders->where('status', Order::STATUS_READY_FOR_DISPATCH)->count(),
                'dispatched' => $orders->where('status', Order::STATUS_DISPATCHED)->count(),
            ],
            'inbox' => $orders->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(array $queueData, CarbonInterface $referenceTime): array
    {
        $metrics = $queueData['metrics'] ?? [];

        return [
            'greeting' => $this->greetingLabel($referenceTime),
            'date' => $this->dateLabel($referenceTime),
            'orders_today' => (int) ($metrics['orders_today'] ?? 0),
            'urgent_orders' => (int) ($metrics['urgent_orders'] ?? 0),
            'deliveries' => (int) ($metrics['deliveries'] ?? 0),
            'pickups' => (int) ($metrics['pickups'] ?? 0),
            'completed_today' => (int) ($metrics['completed_today'] ?? 0),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $orders
     * @param  array<string, mixed>  $queueData
     * @return array<string, mixed>|null
     */
    private function recommendedOrder(Collection $orders, array $queueData): ?array
    {
        $doNowSection = collect($queueData['sections'] ?? [])->firstWhere('key', 'do_now');
        $recommended = collect($doNowSection['cards'] ?? [])->first();

        if (! is_array($recommended)) {
            return null;
        }

        $order = $orders->firstWhere('id', $recommended['id'] ?? null) ?? $recommended;
        $status = (string) ($order['status'] ?? '');

        if (! in_array($status, [
            Order::STATUS_PENDING_REVIEW,
            Order::STATUS_CONFIRMED,
            Order::STATUS_PREPARING,
            Order::STATUS_READY_FOR_DISPATCH,
        ], true)) {
            return null;
        }

        return $this->buildFocusOrderCard($order);
    }

    /**
     * @param  array<string, mixed>  $queueData
     * @return array<int, array<string, mixed>>
     */
    private function upcomingOrders(array $queueData): array
    {
        $nextSection = collect($queueData['sections'] ?? [])->firstWhere('key', 'next');

        if (! is_array($nextSection)) {
            return [];
        }

        return collect($nextSection['groups'] ?? [])
            ->flatMap(static fn (array $group): Collection => collect($group['cards'] ?? []))
            ->values()
            ->take(5)
            ->map(fn (array $card): array => $this->buildUpcomingRow($card))
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $orders
     * @return array<int, array<string, mixed>>
     */
    private function completedOrders(Collection $orders, CarbonInterface $referenceTime): array
    {
        $today = $referenceTime->toDateString();

        return $orders
            ->filter(function (array $order) use ($today): bool {
                $dispatchedAt = $this->stringValue($order['dispatched_at'] ?? null);

                return ($order['status'] ?? null) === Order::STATUS_DISPATCHED
                    && $dispatchedAt !== null
                    && substr($dispatchedAt, 0, 10) === $today;
            })
            ->sortByDesc(fn (array $order): int => $this->timestampValue($order['dispatched_at'] ?? null))
            ->take(5)
            ->map(fn (array $order): array => $this->buildCompletedRow($order))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $order
     * @return array<string, mixed>
     */
    private function buildFocusOrderCard(array $order): array
    {
        $items = $this->itemLines($order);

        return [
            'id' => (int) ($order['id'] ?? 0),
            'customer_name' => (string) ($order['customer_name'] ?? 'Sin cliente'),
            'order_number' => 'Pedido #'.(int) ($order['id'] ?? 0),
            'channel_label' => $this->channelLabel($order['source_channel'] ?? $order['channel_key'] ?? null),
            'focus_state' => $this->focusState($order['status'] ?? null),
            'recommended_action_label' => $this->recommendedActionLabel($order),
            'workspace_action_label' => $this->workspaceActionLabel($order),
            'priority_label' => $this->priorityLabel($order),
            'commitment_label' => $this->commitmentDisplay($order)['label'],
            'commitment_display' => $this->commitmentDisplay($order),
            'delivery_label' => $this->deliveryLabel($order['delivery_method'] ?? null),
            'payment_label' => $this->paymentLabel($order['payment_method'] ?? null),
            'summary' => (string) ($order['summary'] ?? $order['preview'] ?? 'Sin resumen'),
            'item_lines' => array_slice($items, 0, 3),
            'more_items_label' => count($items) > 3 ? '+ '.(count($items) - 3).' artículos más' : null,
            'status' => (string) ($order['status'] ?? ''),
            'status_label' => (string) ($order['status_label'] ?? ''),
            'primary_action' => $this->focusPrimaryAction($order),
            'show_url' => (string) ($order['show_url'] ?? route('orders.show', (int) ($order['id'] ?? 0))),
            'items' => $items,
            'fulfillment' => [
                'label' => $this->commitmentDisplay($order)['label'],
                'date' => $this->commitmentDisplay($order)['date_label'],
                'time' => $this->commitmentDisplay($order)['time_label'],
                'date_value' => $this->commitmentDisplay($order)['date_value'],
                'time_value' => $this->commitmentDisplay($order)['time_value'],
                'delivery' => $this->deliveryLabel($order['delivery_method'] ?? null),
                'payment' => $this->paymentLabel($order['payment_method'] ?? null),
                'address' => $this->addressLabel($order),
            ],
            'requested_date' => $order['requested_date'] ?? $order['fulfillment_plan']['requested_date'] ?? null,
            'requested_time_window' => $order['requested_time_window'] ?? $order['fulfillment_plan']['requested_time_window'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $order
     * @return array<string, mixed>|null
     */
    private function focusPrimaryAction(array $order): ?array
    {
        $status = (string) ($order['status'] ?? '');
        $orderId = (int) ($order['id'] ?? 0);

        if (! in_array($status, [
            Order::STATUS_PENDING_REVIEW,
            Order::STATUS_CONFIRMED,
            Order::STATUS_PREPARING,
            Order::STATUS_READY_FOR_DISPATCH,
        ], true)) {
            return null;
        }

        if ($orderId <= 0) {
            return null;
        }

        $workflowOrder = new Order;
        $workflowOrder->setAttribute($workflowOrder->getKeyName(), $orderId);
        $workflowOrder->status = $status;
        $workflowOrder->exists = true;

        $workflow = $this->orderWorkflowActionPresenter->present($workflowOrder);

        $primary = $workflow['primary_action'] ?? null;

        if (! is_array($primary)) {
            return null;
        }

        $primary['label'] = $this->workspaceActionLabel($order) ?? (string) ($primary['label'] ?? 'Continuar');

        return $primary;
    }

    /**
     * @param  array<string, mixed>  $order
     */
    private function recommendedActionLabel(array $order): string
    {
        return match ((string) ($order['status'] ?? '')) {
            Order::STATUS_PENDING_REVIEW => 'Revisar pedido',
            Order::STATUS_CONFIRMED => 'Empezar preparación',
            Order::STATUS_PREPARING => 'Seguir preparando',
            Order::STATUS_READY_FOR_DISPATCH => $this->readyActionLabel($order),
            Order::STATUS_DISPATCHED => 'Pedido completado',
            Order::STATUS_CANCELLED => 'Pedido finalizado',
            Order::STATUS_REJECTED => 'Pedido finalizado',
            default => 'Abrir pedido',
        };
    }

    /**
     * @param  array<string, mixed>  $order
     */
    private function workspaceActionLabel(array $order): string
    {
        return match ((string) ($order['status'] ?? '')) {
            Order::STATUS_PENDING_REVIEW => 'Confirmar pedido',
            Order::STATUS_CONFIRMED => 'Empezar preparación',
            Order::STATUS_PREPARING => 'Marcar como listo',
            Order::STATUS_READY_FOR_DISPATCH => $this->readyActionLabel($order),
            default => 'Continuar',
        };
    }

    /**
     * @param  array<string, mixed>  $order
     */
    private function readyActionLabel(array $order): string
    {
        return match (strtolower((string) ($order['delivery_method'] ?? ''))) {
            'pickup' => 'Entregar al cliente',
            default => 'Despachar pedido',
        };
    }

    private function focusState(mixed $status): string
    {
        return match ((string) $status) {
            Order::STATUS_PENDING_REVIEW => 'REVIEW',
            Order::STATUS_CONFIRMED => 'RECOMMENDED',
            Order::STATUS_PREPARING => 'PREPARING',
            Order::STATUS_READY_FOR_DISPATCH => 'READY',
            Order::STATUS_DISPATCHED => 'COMPLETED',
            Order::STATUS_CANCELLED,
            Order::STATUS_REJECTED => 'COMPLETED',
            default => 'NO WORK',
        };
    }

    /**
     * @param  array<string, mixed>  $order
     * @return array<string, mixed>
     */
    private function buildUpcomingRow(array $order): array
    {
        $items = $this->itemLines($order);
        $commitment = $this->commitmentDisplay($order);

        return [
            'id' => (int) ($order['id'] ?? 0),
            'customer_name' => (string) ($order['customer_name'] ?? 'Sin cliente'),
            'item_summary' => (string) ($order['summary'] ?? $order['preview'] ?? 'Sin resumen'),
            'commitment_label' => $commitment['label'],
            'commitment_display' => $commitment,
            'date_time_label' => $commitment['label'],
            'delivery_label' => $this->deliveryLabel($order['delivery_method'] ?? null),
            'payment_label' => $this->paymentLabel($order['payment_method'] ?? null),
            'channel_label' => $this->channelLabel($order['source_channel'] ?? $order['channel_key'] ?? null),
            'item_lines' => array_slice($items, 0, 2),
            'show_url' => (string) ($order['show_url'] ?? route('orders.show', (int) ($order['id'] ?? 0))),
        ];
    }

    /**
     * @param  array<string, mixed>  $order
     * @return array<string, mixed>
     */
    private function buildCompletedRow(array $order): array
    {
        return [
            'id' => (int) ($order['id'] ?? 0),
            'customer_name' => (string) ($order['customer_name'] ?? 'Sin cliente'),
            'dispatched_at' => $this->stringValue($order['dispatched_at'] ?? null),
            'dispatched_label' => $this->dispatchedLabel($order['dispatched_at'] ?? null),
            'item_summary' => (string) ($order['summary'] ?? $order['preview'] ?? 'Sin resumen'),
            'show_url' => (string) ($order['show_url'] ?? route('orders.show', (int) ($order['id'] ?? 0))),
        ];
    }

    /**
     * @param  array<string, mixed>  $order
     * @return array<int, array<string, mixed>>
     */
    private function itemLines(array $order): array
    {
        return collect($order['items'] ?? [])
            ->map(function (array $item): array {
                $quantity = is_numeric($item['quantity'] ?? null) ? (float) $item['quantity'] : 1.0;
                $unit = trim((string) ($item['unit'] ?? ''));
                $notes = trim((string) ($item['notes'] ?? ''));

                return [
                    'id' => $item['id'] ?? null,
                    'quantity' => $quantity,
                    'quantity_label' => rtrim(rtrim(number_format($quantity, 2, '.', ''), '0'), '.'),
                    'name' => (string) ($item['name'] ?? $item['product_name'] ?? $item['raw_text'] ?? 'Sin descripción'),
                    'unit' => $unit !== '' ? $unit : null,
                    'notes' => $notes !== '' ? $notes : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $order
     */
    private function priorityLabel(array $order): string
    {
        $risk = (string) ($order['risk_level'] ?? '');
        $priority = (string) ($order['priority_level'] ?? '');
        $remaining = $order['remaining_sla_minutes'] ?? null;
        $commitmentDate = $this->stringValue($order['commitment_date'] ?? null);

        if ($risk === 'critical' || $risk === 'high' || $priority === 'urgent' || (is_numeric($remaining) && (int) $remaining < 0)) {
            return 'Urgente';
        }

        if ($commitmentDate === null) {
            return 'Sin fecha';
        }

        if ($commitmentDate === now()->toDateString()) {
            return 'Para hoy';
        }

        return 'Próximo';
    }

    /**
     * @param  array<string, mixed>  $order
     */
    private function commitmentLabel(array $order): string
    {
        $commitmentDate = $this->stringValue($order['commitment_date'] ?? null);
        $commitmentTime = $this->stringValue($order['commitment_time'] ?? null);

        if ($commitmentDate === null) {
            return 'Sin fecha confirmada';
        }

        $today = now()->toDateString();
        $tomorrow = now()->copy()->addDay()->toDateString();

        if ($commitmentDate === $today) {
            if ($commitmentTime === null) {
                return 'Hoy sin hora definida';
            }

            return $this->hourValue($commitmentTime) < 14
                ? 'Hoy antes de las 2:00 p. m.'
                : 'Hoy sin hora definida';
        }

        if ($commitmentDate === $tomorrow) {
            return 'Mañana por la mañana';
        }

        return Carbon::parse($commitmentDate)->locale('es')->isoFormat('dddd D [de] MMMM');
    }

    /**
     * @param  array<string, mixed>  $order
     */
    private function fulfillmentDateLabel(array $order): string
    {
        return $this->commitmentDisplay($order)['date_label'];
    }

    /**
     * @param  array<string, mixed>  $order
     */
    private function fulfillmentTimeLabel(array $order): string
    {
        return $this->commitmentDisplay($order)['time_label'];
    }

    /**
     * @param  array<string, mixed>  $order
     */
    private function addressLabel(array $order): string
    {
        $deliveryMethod = $this->stringValue($order['delivery_method'] ?? null);
        $pickupBranch = $this->stringValue($order['fulfillment_plan']['pickup_branch'] ?? null);
        $address = $this->stringValue($order['fulfillment_plan']['delivery_address'] ?? null);

        if ($deliveryMethod === 'pickup') {
            return $pickupBranch !== null ? $pickupBranch : 'Sucursal por confirmar';
        }

        if ($address !== null) {
            return $address;
        }

        return 'Sin dirección confirmada';
    }

    private function deliveryLabel(?string $deliveryMethod): string
    {
        return match (strtolower((string) $deliveryMethod)) {
            'pickup' => 'Retiro en tienda',
            'delivery' => 'Entrega',
            'express' => 'Express',
            default => 'Por confirmar',
        };
    }

    private function paymentLabel(?string $paymentMethod): string
    {
        return match (strtolower((string) $paymentMethod)) {
            'sinpe' => 'SINPE',
            'cash' => 'Efectivo',
            'transfer' => 'Transferencia',
            'card' => 'Tarjeta',
            'credit' => 'Crédito',
            default => 'Por confirmar',
        };
    }

    private function channelLabel(?string $channel): string
    {
        return match (strtolower((string) $channel)) {
            'whatsapp' => 'WhatsApp',
            'telegram' => 'Telegram',
            'instagram' => 'Instagram',
            default => 'Otro',
        };
    }

    private function greetingLabel(CarbonInterface $referenceTime): string
    {
        $hour = (int) $referenceTime->format('H');

        return match (true) {
            $hour < 12 => 'Buenos días',
            $hour < 19 => 'Buenas tardes',
            default => 'Buenas noches',
        };
    }

    private function dateLabel(CarbonInterface $referenceTime): string
    {
        return $referenceTime->locale('es')->isoFormat('dddd D [de] MMMM');
    }

    private function dispatchedLabel(mixed $value): string
    {
        if ($value instanceof CarbonInterface) {
            return $value->locale('es')->isoFormat('D [de] MMMM, HH:mm');
        }

        if (! is_string($value) || trim($value) === '') {
            return 'Sin fecha';
        }

        return Carbon::parse($value)->locale('es')->isoFormat('D [de] MMMM, HH:mm');
    }

    private function hourValue(string $time): int
    {
        if (preg_match('/^(?<hour>\d{2}):(?<minute>\d{2})/', $time, $matches) !== 1) {
            return 0;
        }

        return (int) $matches['hour'];
    }

    private function timestampValue(mixed $value): int
    {
        if ($value instanceof CarbonInterface) {
            return $value->getTimestamp();
        }

        if (! is_string($value) || trim($value) === '') {
            return 0;
        }

        return Carbon::parse($value)->getTimestamp();
    }

    private function stringValue(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param  array<string, mixed>|Order  $order
     * @return array<int, string>
     */
    private function itemSummary(Order|array $order): array
    {
        $items = $order instanceof Order ? $order->orderItems : ($order['items'] ?? []);

        return collect($items)
            ->map(function ($item): string {
                if ($item instanceof OrderItem) {
                    $quantity = (float) $item->quantity;
                    $name = $this->labels->resolveItemLabel($item->product_id, $item->raw_text, $item->product?->name);
                    $unit = $item->unit;
                    $notes = $item->notes;
                } else {
                    $quantity = is_numeric($item['quantity'] ?? null) ? (float) $item['quantity'] : 1.0;
                    $name = (string) ($item['name'] ?? $item['product_name'] ?? $item['raw_text'] ?? 'Sin descripción');
                    $unit = $item['unit'] ?? null;
                    $notes = $item['notes'] ?? null;
                }

                $parts = [rtrim(rtrim(number_format($quantity, 2, '.', ''), '0'), '.').' x '.$name];

                if (is_string($unit) && trim($unit) !== '') {
                    $parts[] = trim($unit);
                }

                if (is_string($notes) && trim($notes) !== '') {
                    $parts[] = trim($notes);
                }

                return implode(' · ', $parts);
            })
            ->filter()
            ->values()
            ->all();
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

        $notificationCounts = OrderNotificationLog::query()
            ->whereIn('customer_id', $customerIds)
            ->whereIn('status', [OrderNotificationLog::STATUS_QUEUED, OrderNotificationLog::STATUS_FAILED])
            ->selectRaw('customer_id, COUNT(*) as total')
            ->groupBy('customer_id')
            ->pluck('total', 'customer_id');

        $contexts = [];

        foreach ($customers as $customer) {
            $contexts[$customer->id] = [
                'name' => $customer->name,
                'phone' => $customer->phone,
                'total_orders' => (int) $customer->orders_count,
                'open_notifications' => (int) ($notificationCounts->get($customer->id) ?? 0),
            ];
        }

        return $contexts;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeOrder(Order $order, ?array $customerContext): array
    {
        $workflow = $this->orderWorkflowActionPresenter->present($order);
        $commitment = $this->commitmentDisplay([
            'requested_date' => $order->fulfillmentPlan?->requested_date?->toDateString(),
            'commitment_date' => $order->fulfillmentPlan?->commitment_date?->toDateString(),
            'requested_time_window' => $order->fulfillmentPlan?->requested_time_window,
            'commitment_time' => $order->fulfillmentPlan?->commitment_time,
        ]);

        return [
            'id' => $order->id,
            'status' => $order->status,
            'status_label' => $this->labels->statusLabel($order->status),
            'source_channel' => $order->source_channel,
            'channel_key' => $order->source_channel,
            'channel' => $this->channelLabel($order->source_channel),
            'customer_name' => $order->customer?->name ?? 'Sin cliente',
            'customer_phone' => $order->customer?->phone ?? 'Sin telefono',
            'branch_name' => $order->branch?->name ?? 'Sin sucursal',
            'created_at_iso' => $order->created_at?->toIso8601String(),
            'created_at_label' => $order->created_at?->format('d/m/Y H:i') ?? 'Sin fecha',
            'elapsed_label' => $this->labels->elapsedLabel($order->created_at),
            'preview' => Str::limit($order->raw_message_text ?? 'Sin mensaje original', 120),
            'original_message' => $order->raw_message_text ?? 'Sin mensaje original',
            'summary' => $this->productSummary($order),
            'notes' => $order->notes,
            'dispatched_at' => $order->dispatched_at?->toIso8601String(),
            'items_count' => $order->orderItems->count(),
            'recognized_items_count' => (int) ($order->recognized_order_items_count ?? 0),
            'unread' => $order->reviewed_at === null,
            'duplicate' => $order->possible_duplicate_of_order_id !== null,
            'vip' => false,
            'parser_confidence' => $order->parser_confidence !== null ? (float) $order->parser_confidence : null,
            'delivery_method' => $order->fulfillmentPlan?->delivery_method,
            'payment_method' => $order->fulfillmentPlan?->payment_method,
            'requested_date' => $order->fulfillmentPlan?->requested_date?->toDateString(),
            'commitment_date' => $order->fulfillmentPlan?->commitment_date?->toDateString(),
            'commitment_time' => $order->fulfillmentPlan?->commitment_time,
            'commitment_display' => $commitment,
            'commitment_label' => $commitment['label'],
            'remaining_sla_minutes' => $order->fulfillmentPlan?->remaining_sla_minutes,
            'risk_level' => $order->fulfillmentPlan?->risk_level,
            'priority_level' => $order->fulfillmentPlan?->priority_level,
            'priority_score' => $order->fulfillmentPlan?->priority_score,
            'priority_reason' => $order->fulfillmentPlan?->priority_reason,
            'risk_reason' => $order->fulfillmentPlan?->risk_reason,
            'requested_time_window' => $order->fulfillmentPlan?->requested_time_window,
            'decision_version' => $order->fulfillmentPlan?->decision_version,
            'planner_confidence' => $order->fulfillmentPlan?->planner_confidence,
            'planner_notes' => $order->fulfillmentPlan?->planner_notes,
            'sla_minutes' => $order->fulfillmentPlan?->sla_minutes,
            'metadata_json' => $order->fulfillmentPlan?->metadata_json,
            'items' => $order->orderItems
                ->map(function ($item): array {
                    return [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'product_name' => $item->product?->name,
                        'quantity' => (float) $item->quantity,
                        'unit' => $item->unit,
                        'name' => $this->labels->resolveItemLabel($item->product_id, $item->raw_text, $item->product?->name),
                        'raw_text' => $item->raw_text,
                        'notes' => $item->notes,
                    ];
                })
                ->values()
                ->all(),
            'fulfillment_plan' => [
                'requested_date' => $order->fulfillmentPlan?->requested_date?->toDateString(),
                'requested_time_window' => $order->fulfillmentPlan?->requested_time_window,
                'delivery_method' => $order->fulfillmentPlan?->delivery_method,
                'payment_method' => $order->fulfillmentPlan?->payment_method,
                'pickup_branch' => $order->fulfillmentPlan?->pickupBranch?->name,
                'delivery_address' => $order->fulfillmentPlan?->delivery_address,
                'delivery_notes' => $order->fulfillmentPlan?->delivery_notes,
            ],
            'parser_details' => [
                'parser_confidence' => $order->parser_confidence !== null ? (float) $order->parser_confidence : null,
                'decision_version' => $order->fulfillmentPlan?->decision_version,
                'planner_confidence' => $order->fulfillmentPlan?->planner_confidence,
                'planner_notes' => $order->fulfillmentPlan?->planner_notes,
            ],
            'duplicate_analysis' => [
                'possible_duplicate_of' => $order->possible_duplicate_of_order_id,
                'duplicate_score' => $order->duplicate_score !== null ? (float) $order->duplicate_score : null,
                'duplicate_reason' => $order->duplicate_reason,
                'duplicate_checked_at' => $order->duplicate_checked_at?->toIso8601String(),
            ],
            'notification_history' => $order->notificationLogs
                ->map(function ($log): array {
                    return [
                        'channel' => $log->channel,
                        'event' => $log->event,
                        'status' => $log->status,
                        'reason' => $log->reason,
                        'message_body' => $log->message_body,
                        'evaluated_at' => $log->evaluated_at?->toIso8601String(),
                        'sent_at' => $log->sent_at?->toIso8601String(),
                    ];
                })
                ->values()
                ->all(),
            'customer_context' => $customerContext ?? [
                'name' => $order->customer?->name ?? 'Sin cliente',
                'phone' => $order->customer?->phone ?? 'Sin telefono',
                'total_orders' => 0,
                'open_notifications' => 0,
            ],
            'focus_state' => $this->focusState($order->status),
            'primary_action' => $workflow['primary_action'] ?? null,
            'secondary_actions' => $workflow['secondary_actions'] ?? [],
            'terminal_message' => $workflow['terminal_message'] ?? null,
            'update_url' => route('orders.update', $order),
            'show_url' => route('orders.show', $order),
        ];
    }

    /**
     * @param  array<string, mixed>  $order
     * @return array{label:string,date_label:string,time_label:string,time_window_label:?string,date_value:?string,time_value:?string}
     */
    private function commitmentDisplay(array $order): array
    {
        return app(CommitmentDisplayFormatter::class)->format(
            requestedDate: $this->stringValue($order['requested_date'] ?? null),
            commitmentDate: $this->stringValue($order['commitment_date'] ?? null),
            requestedTimeWindow: $this->stringValue($order['requested_time_window'] ?? null),
            commitmentTime: $this->stringValue($order['commitment_time'] ?? null),
        );
    }

    private function productSummary(Order $order): string
    {
        $parts = $this->itemSummary($order);

        return $parts !== [] ? implode(', ', array_slice($parts, 0, 3)) : 'Sin resumen';
    }
}
