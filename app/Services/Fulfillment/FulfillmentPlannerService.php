<?php

namespace App\Services\Fulfillment;

use App\Models\FulfillmentPlan;
use App\Models\Order;

class FulfillmentPlannerService
{
    public function createDefaultPlan(Order $order): FulfillmentPlan
    {
        $priority = $this->calculatePriority($order);
        $commitment = $this->calculateCommitment($order);

        return FulfillmentPlan::query()->firstOrCreate(
            ['order_id' => $order->id],
            [
                'organization_id' => $order->organization_id,
                'requested_date' => null,
                'requested_time_window' => null,
                'delivery_method' => $this->defaultDeliveryMethod(),
                'payment_method' => $this->defaultPaymentMethod(),
                'pickup_branch_id' => $order->branch_id,
                'delivery_address' => null,
                'delivery_notes' => null,
                'priority_score' => $priority['priority_score'],
                'priority_level' => $priority['priority_level'],
                'priority_reason' => $priority['priority_reason'],
                'commitment_date' => $commitment['commitment_date'],
                'commitment_time' => $commitment['commitment_time'],
                'sla_minutes' => $commitment['sla_minutes'],
                'planner_confidence' => 0,
                'planner_notes' => null,
                'metadata_json' => [],
            ],
        );
    }

    /**
     * @return array{priority_score:int, priority_level:?string, priority_reason:?string}
     */
    public function calculatePriority(?Order $order = null): array
    {
        return [
            'priority_score' => (int) config('fulfillment.defaults.priority_score', 0),
            'priority_level' => config('fulfillment.defaults.priority_level', 'normal'),
            'priority_reason' => null,
        ];
    }

    /**
     * @return array{commitment_date:?string, commitment_time:?string, sla_minutes:int}
     */
    public function calculateCommitment(?Order $order = null): array
    {
        return [
            'commitment_date' => null,
            'commitment_time' => null,
            'sla_minutes' => (int) config('fulfillment.defaults.sla_minutes', 0),
        ];
    }

    public function updatePlanner(FulfillmentPlan $plan): FulfillmentPlan
    {
        $priority = $this->calculatePriority($plan->order);
        $commitment = $this->calculateCommitment($plan->order);

        $plan->forceFill([
            'priority_score' => $priority['priority_score'],
            'priority_level' => $priority['priority_level'],
            'priority_reason' => $priority['priority_reason'],
            'commitment_date' => $commitment['commitment_date'],
            'commitment_time' => $commitment['commitment_time'],
            'sla_minutes' => $commitment['sla_minutes'],
            'planner_confidence' => 0,
        ])->save();

        return $plan->refresh();
    }

    private function defaultDeliveryMethod(): string
    {
        return (string) config('fulfillment.defaults.delivery_method', 'unknown');
    }

    private function defaultPaymentMethod(): string
    {
        return (string) config('fulfillment.defaults.payment_method', 'unknown');
    }
}
