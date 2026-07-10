<?php

namespace App\Services\Fulfillment;

use App\Services\Fulfillment\DTO\FulfillmentIntent;
use App\Models\FulfillmentPlan;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

class FulfillmentPlannerService
{
    public function __construct(
        private readonly FulfillmentIntentParser $intentParser,
    ) {
    }

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

    public function parseIntentFromMessage(Order $order, string $message): FulfillmentPlan
    {
        $plan = $this->getOrCreatePlan($order);

        try {
            $intent = $this->intentParser->parse($order, $message);
        } catch (\Throwable $exception) {
            Log::warning('Fulfillment intent parsing failed.', [
                'order_id' => $order->id,
                'exception' => $exception->getMessage(),
            ]);

            return $plan->refresh();
        }

        $planMetadata = $plan->metadata_json ?? [];
        $intentPayload = $intent->toArray();
        $planMetadata['fulfillment_intent'] = array_replace_recursive(
            (array) ($planMetadata['fulfillment_intent'] ?? []),
            $intentPayload,
        );

        $updates = [
            'metadata_json' => $planMetadata,
        ];

        $this->applyIntentValue($plan, $updates, 'requested_date', $intent->requested_date);
        $this->applyIntentValue($plan, $updates, 'requested_time_window', $intent->requested_time_window);
        $this->applyIntentValue($plan, $updates, 'delivery_method', $intent->delivery_method);
        $this->applyIntentValue($plan, $updates, 'payment_method', $intent->payment_method);
        $this->applyIntentValue($plan, $updates, 'delivery_address', $intent->delivery_address);
        $this->applyIntentValue($plan, $updates, 'priority_level', $intent->priority_level);
        $this->applyIntentValue($plan, $updates, 'priority_reason', $intent->priority_reason);

        if (! $this->isManuallyConfirmed($plan, 'planner_confidence')) {
            $updates['planner_confidence'] = $intent->confidence;
        }

        if (! $this->isManuallyConfirmed($plan, 'priority_score')) {
            $updates['priority_score'] = $this->resolvePriorityScore($intent->priority_level);
        }

        if (! $this->isManuallyConfirmed($plan, 'planner_notes')) {
            $updates['planner_notes'] = $this->mergePlannerNotes(
                $plan->planner_notes,
                $this->buildPlannerNotes($intent),
            );
        }

        $plan->forceFill($updates)->save();

        return $plan->refresh();
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

    private function getOrCreatePlan(Order $order): FulfillmentPlan
    {
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
                'priority_score' => (int) config('fulfillment.defaults.priority_score', 0),
                'priority_level' => config('fulfillment.defaults.priority_level', 'normal'),
                'priority_reason' => null,
                'commitment_date' => null,
                'commitment_time' => null,
                'sla_minutes' => (int) config('fulfillment.defaults.sla_minutes', 0),
                'planner_confidence' => 0,
                'planner_notes' => null,
                'metadata_json' => [],
            ],
        );
    }

    private function applyIntentValue(FulfillmentPlan $plan, array &$updates, string $field, mixed $value): void
    {
        if ($value === null || $this->isManuallyConfirmed($plan, $field)) {
            return;
        }

        $updates[$field] = $value;
    }

    private function isManuallyConfirmed(FulfillmentPlan $plan, string $field): bool
    {
        $metadata = $plan->metadata_json ?? [];

        if (($metadata['manual_confirmation'][$field] ?? false) === true) {
            return true;
        }

        if (in_array($field, (array) ($metadata['manual_confirmed_fields'] ?? []), true)) {
            return true;
        }

        if (($metadata['manual_confirmed'][$field] ?? false) === true) {
            return true;
        }

        if (($metadata[$field . '_manual_confirmed'] ?? false) === true) {
            return true;
        }

        return false;
    }

    private function resolvePriorityScore(?string $priorityLevel): int
    {
        return match ($priorityLevel) {
            'urgent' => (int) config('fulfillment.priority_scores.urgent', 95),
            'high' => (int) config('fulfillment.priority_scores.high', 70),
            default => (int) config('fulfillment.priority_scores.normal', 40),
        };
    }

    private function buildPlannerNotes(FulfillmentIntent $intent): ?string
    {
        $notes = [];

        if ($intent->delivery_method === 'unknown' && ($intent->metadata['delivery_method_matches'] ?? []) !== []) {
            $notes[] = 'Entrega ambigua: se detectaron indicios de pickup y delivery.';
        }

        if (($intent->metadata['payment_method_matches'] ?? []) !== [] && count($intent->metadata['payment_method_matches']) > 1) {
            $notes[] = 'Pago ambiguo: se detectaron multiples metodos de pago.';
        }

        if (($intent->metadata['specific_time_mentions'] ?? []) !== []) {
            $notes[] = 'Horario especifico detectado: ' . implode(', ', (array) $intent->metadata['specific_time_mentions']) . '.';
        }

        return $notes !== [] ? implode(' ', $notes) : null;
    }

    private function mergePlannerNotes(?string $existingNotes, ?string $newNotes): ?string
    {
        $notes = array_values(array_filter(array_map('trim', [
            $existingNotes,
            $newNotes,
        ]), static fn (string $value): bool => $value !== ''));

        $notes = array_values(array_unique($notes));

        return $notes !== [] ? implode(' ', $notes) : null;
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
