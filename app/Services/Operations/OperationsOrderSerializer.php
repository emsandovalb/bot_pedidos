<?php

namespace App\Services\Operations;

use App\Models\Order;
use App\Services\OrderWorkflowActionPresenter;
use Illuminate\Support\Str;

/**
 * Convierte un pedido en el arreglo que consumen la bandeja, el feed y el snapshot de operaciones.
 */
class OperationsOrderSerializer
{
    public function __construct(
        private readonly OrderWorkflowActionPresenter $orderWorkflowActionPresenter,
        private readonly OrderDisplayLabels $labels,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function serializeOrder(Order $order, ?array $customerContext): array
    {
        $workflow = $this->orderWorkflowActionPresenter->present($order);
        $commitment = $this->commitmentDisplay($order);

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
            'status' => $this->labels->statusLabel($order->status),
            'channel' => $this->labels->channelLabel($order->source_channel),
            'elapsed' => $this->labels->elapsedLabel($order->created_at),
            'preview' => Str::limit($order->raw_message_text ?? 'Sin mensaje original', 120),
            'possible_duplicate' => $order->possibleDuplicateOf !== null,
        ];
        $enrichedCustomerContext['current_alerts'] = $this->currentAlerts($customerContext, $order);

        return [
            'id' => $order->id,
            'status' => $order->status,
            'status_label' => $this->labels->statusLabel($order->status),
            'status_tone' => $this->labels->statusTone($order->status),
            'source_channel' => $order->source_channel,
            'channel' => $this->labels->channelLabel($order->source_channel),
            'channel_key' => $order->source_channel,
            'created_at' => $order->created_at?->toIso8601String(),
            'received_at' => $order->incomingMessage?->received_at?->toIso8601String(),
            'customer_name' => $order->customer?->name ?? 'Sin cliente',
            'customer' => $order->customer?->name ?? 'Sin cliente',
            'customer_phone' => $order->customer?->phone ?? 'Sin telefono',
            'phone' => $order->customer?->phone ?? 'Sin telefono',
            'branch_name' => $order->branch?->name ?? 'Sin sucursal',
            'branch' => $order->branch?->name ?? 'Sin sucursal',
            'elapsed_label' => $this->labels->elapsedLabel($order->created_at),
            'created_at_label' => $order->created_at?->format('d/m/Y H:i') ?? 'Sin fecha',
            'preview' => Str::limit($order->raw_message_text ?? 'Sin mensaje original', 120),
            'original_message' => $order->raw_message_text ?? 'Sin mensaje original',
            'summary' => $this->productSummary($order),
            'notes' => $order->notes,
            'created_at_iso' => $order->created_at?->toIso8601String(),
            'dispatched_at' => $order->dispatched_at?->toIso8601String(),
            'items_count' => $order->orderItems->count(),
            'recognized_items_count' => (int) ($order->recognized_order_items_count ?? 0),
            'unread' => $order->reviewed_at === null,
            'duplicate' => $order->possible_duplicate_of_order_id !== null,
            'possible_duplicate' => $order->possible_duplicate_of_order_id !== null,
            'vip' => ($customerContext['segment'] ?? 'Inactive') === 'VIP',
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
            'timeline' => $order->orderStatusHistories
                ->map(function ($history): array {
                    return [
                        'from_status' => $history->from_status,
                        'to_status' => $history->to_status,
                        'changed_via' => $history->changed_via,
                        'reason' => $history->reason,
                        'created_at' => $history->created_at?->toIso8601String(),
                    ];
                })
                ->values()
                ->all(),
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
                        'name' => $this->labels->resolveItemLabel($item->product_id, $item->raw_text, $item->product?->name),
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
     * @return array<int, string>
     */
    private function currentAlerts(array $customerContext, Order $order): array
    {
        $alerts = [];

        if ($customerContext['segment'] === 'VIP') {
            $alerts[] = 'Cliente VIP';
        }

        if ($customerContext['open_notifications'] > 0) {
            $alerts[] = $customerContext['open_notifications'].' notificacion(es) abiertas';
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
     * @param  array<string, mixed>|Order  $order
     * @return array{label:string,date_label:string,time_label:string,time_window_label:?string,date_value:?string,time_value:?string}
     */
    private function commitmentDisplay(array|Order $order): array
    {
        $requestedDate = $order instanceof Order
            ? $order->fulfillmentPlan?->requested_date?->toDateString()
            : ($order['requested_date'] ?? $order['fulfillment_plan']['requested_date'] ?? null);
        $commitmentDate = $order instanceof Order
            ? $order->fulfillmentPlan?->commitment_date?->toDateString()
            : ($order['commitment_date'] ?? $order['fulfillment_plan']['commitment_date'] ?? null);
        $requestedTimeWindow = $order instanceof Order
            ? $order->fulfillmentPlan?->requested_time_window
            : ($order['requested_time_window'] ?? $order['fulfillment_plan']['requested_time_window'] ?? null);
        $commitmentTime = $order instanceof Order
            ? $order->fulfillmentPlan?->commitment_time
            : ($order['commitment_time'] ?? $order['fulfillment_plan']['commitment_time'] ?? null);

        return app(CommitmentDisplayFormatter::class)->format(
            requestedDate: is_string($requestedDate) ? $requestedDate : null,
            commitmentDate: is_string($commitmentDate) ? $commitmentDate : null,
            requestedTimeWindow: is_string($requestedTimeWindow) ? $requestedTimeWindow : null,
            commitmentTime: is_string($commitmentTime) ? $commitmentTime : null,
        );
    }

    private function productSummary(Order $order): string
    {
        $parts = $order->orderItems
            ->take(3)
            ->map(function ($item): string {
                $label = $this->labels->resolveItemLabel($item->product_id, $item->raw_text, $item->product?->name);
                $quantity = (float) ($item->quantity ?? 1);

                return $quantity > 1 ? $quantity.' x '.$label : $label;
            })
            ->filter(static fn (string $value): bool => $value !== '')
            ->values()
            ->all();

        return $parts !== [] ? implode(', ', $parts) : 'Sin resumen';
    }
}
