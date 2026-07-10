<?php

namespace App\Services\Fulfillment;

use App\Models\FulfillmentPlan;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class FulfillmentDecisionEngine
{
    /**
     * @return array{
     *     priority_score: int,
     *     priority_level: string,
     *     priority_reason: string|null,
     *     commitment_date: string|null,
     *     commitment_time: string|null,
     *     remaining_sla_minutes: int|null,
     *     risk_level: string,
     *     risk_reason: string,
     *     decision_version: string
     * }
     */
    public function evaluate(FulfillmentPlan $plan): array
    {
        $priority = $this->calculatePriority($plan);
        $commitment = $this->calculateCommitment($plan);
        $remainingSlaMinutes = $this->calculateSLA($plan, $commitment);
        $risk = $this->calculateRisk($plan, $remainingSlaMinutes, $commitment);

        return array_merge($priority, $commitment, [
            'remaining_sla_minutes' => $remainingSlaMinutes,
            'risk_level' => $risk['risk_level'],
            'risk_reason' => $risk['risk_reason'],
            'decision_version' => (string) config('fulfillment.decision_version', 'v1'),
        ]);
    }

    /**
     * @return array{priority_score:int, priority_level:string, priority_reason:?string}
     */
    public function calculatePriority(FulfillmentPlan $plan): array
    {
        $timezone = $this->timezone();
        $now = CarbonImmutable::now($timezone)->startOfDay();
        $requestedDate = $this->requestedDate($plan);
        $deliveryMethod = $this->deliveryMethod($plan);
        $priorityLevel = $this->stringValue($plan->priority_level);
        $priorityReason = $this->stringValue($plan->priority_reason);
        $confidence = $this->plannerConfidence($plan);
        $customerIsVip = $this->customerIsVip($plan);
        $duplicateDetected = $this->duplicateDetected($plan);

        $weights = (array) config('fulfillment.priority.weights', []);
        $score = (int) config('fulfillment.priority.base_score', 40);
        $reasons = [];

        $reasons[] = 'Base ' . $score;

        if ($requestedDate !== null && $requestedDate->equalTo($now)) {
            $score += (int) ($weights['today'] ?? 30);
            $reasons[] = 'today +' . (int) ($weights['today'] ?? 30);
        } elseif ($requestedDate !== null && $requestedDate->equalTo($now->addDay())) {
            $score += (int) ($weights['tomorrow'] ?? 0);
            $reasons[] = 'tomorrow +' . (int) ($weights['tomorrow'] ?? 0);
        }

        if ($priorityLevel === 'urgent') {
            $score += (int) ($weights['urgent_phrase'] ?? 40);
            $reasons[] = 'urgent phrase +' . (int) ($weights['urgent_phrase'] ?? 40);
        }

        if ($customerIsVip) {
            $score += (int) ($weights['vip'] ?? 15);
            $reasons[] = 'VIP +' . (int) ($weights['vip'] ?? 15);
        }

        if ($deliveryMethod === 'delivery') {
            $score += (int) ($weights['delivery'] ?? 10);
            $reasons[] = 'delivery +' . (int) ($weights['delivery'] ?? 10);
        } elseif ($deliveryMethod === 'pickup') {
            $score += (int) ($weights['pickup'] ?? 0);
            $reasons[] = 'pickup +' . (int) ($weights['pickup'] ?? 0);
        } elseif ($deliveryMethod === 'express') {
            $score += (int) ($weights['express'] ?? 20);
            $reasons[] = 'express +' . (int) ($weights['express'] ?? 20);
        }

        if ($duplicateDetected) {
            $score += (int) ($weights['duplicate'] ?? 5);
            $reasons[] = 'duplicate +' . (int) ($weights['duplicate'] ?? 5);
        }

        $score += $this->confidenceModifier($confidence);

        $score = max(0, min(100, $score));
        $level = $this->priorityLevelFromScore($score);

        return [
            'priority_score' => $score,
            'priority_level' => $level,
            'priority_reason' => implode('; ', $reasons) . '.',
        ];
    }

    /**
     * @return array{commitment_date:?string, commitment_time:?string}
     */
    public function calculateCommitment(FulfillmentPlan $plan): array
    {
        $timezone = $this->timezone();
        $requestedDate = $this->requestedDate($plan);
        $requestedTimeWindow = $this->requestedTimeWindow($plan);
        $explicitTime = $this->explicitCommitmentTime($plan);

        if ($requestedDate === null && $requestedTimeWindow === null && $explicitTime === null) {
            return [
                'commitment_date' => null,
                'commitment_time' => null,
            ];
        }

        $commitmentDate = $requestedDate ?? CarbonImmutable::now($timezone)->startOfDay();
        $commitmentTime = $explicitTime ?? $this->defaultCommitmentTime($requestedTimeWindow);

        return [
            'commitment_date' => $commitmentDate->toDateString(),
            'commitment_time' => $commitmentTime,
        ];
    }

    /**
     * @param  array{commitment_date:?string, commitment_time:?string}  $commitment
     */
    public function calculateSLA(FulfillmentPlan $plan, array $commitment = []): ?int
    {
        $commitmentDate = $commitment['commitment_date'] ?? $this->commitmentDate($plan);
        $commitmentTime = $commitment['commitment_time'] ?? $this->commitmentTime($plan);

        if ($commitmentDate === null || $commitmentTime === null) {
            return null;
        }

        $timezone = $this->timezone();
        $commitmentAt = CarbonImmutable::parse($commitmentDate . ' ' . $commitmentTime, $timezone);
        $now = CarbonImmutable::now($timezone);

        return (int) $now->diffInMinutes($commitmentAt, false);
    }

    /**
     * @param  array{commitment_date:?string, commitment_time:?string}  $commitment
     * @return array{risk_level:string, risk_reason:string}
     */
    public function calculateRisk(FulfillmentPlan $plan, ?int $remainingSlaMinutes = null, array $commitment = []): array
    {
        if ($remainingSlaMinutes === null) {
            return [
                'risk_level' => 'low',
                'risk_reason' => 'Sin compromiso de tiempo calculable.',
            ];
        }

        if ($remainingSlaMinutes < 0) {
            return [
                'risk_level' => 'critical',
                'risk_reason' => 'SLA vencido por ' . abs($remainingSlaMinutes) . ' minuto(s).',
            ];
        }

        if ($remainingSlaMinutes < (int) config('fulfillment.risk.high_under_minutes', 60)) {
            return [
                'risk_level' => 'high',
                'risk_reason' => 'SLA restante de ' . $remainingSlaMinutes . ' minuto(s).',
            ];
        }

        $commitmentDate = $commitment['commitment_date'] ?? $this->commitmentDate($plan);
        $today = CarbonImmutable::now($this->timezone())->startOfDay()->toDateString();

        if ($commitmentDate !== null && $commitmentDate === $today && $remainingSlaMinutes < (int) config('fulfillment.risk.medium_today_under_minutes', 180)) {
            return [
                'risk_level' => 'medium',
                'risk_reason' => 'Compromiso para hoy con ' . $remainingSlaMinutes . ' minuto(s) restantes.',
            ];
        }

        return [
            'risk_level' => 'low',
            'risk_reason' => 'Dentro del margen operativo esperado.',
        ];
    }

    private function timezone(): string
    {
        $configured = config('fulfillment.timezone');

        return is_string($configured) && $configured !== '' ? $configured : (string) config('app.timezone', 'UTC');
    }

    private function requestedDate(FulfillmentPlan $plan): ?CarbonImmutable
    {
        if ($plan->requested_date instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($plan->requested_date)->startOfDay();
        }

        $intentDate = data_get($plan->metadata_json ?? [], 'fulfillment_intent.requested_date');

        if (is_string($intentDate) && $intentDate !== '') {
            return CarbonImmutable::parse($intentDate, $this->timezone())->startOfDay();
        }

        return null;
    }

    private function requestedTimeWindow(FulfillmentPlan $plan): ?string
    {
        $value = $this->stringValue($plan->requested_time_window);

        if ($value !== null) {
            return $value;
        }

        $intentWindow = data_get($plan->metadata_json ?? [], 'fulfillment_intent.requested_time_window');

        return is_string($intentWindow) && $intentWindow !== '' ? $intentWindow : null;
    }

    private function commitmentDate(FulfillmentPlan $plan): ?string
    {
        $commitmentDate = $plan->commitment_date;

        if ($commitmentDate instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($commitmentDate)->toDateString();
        }

        return is_string($commitmentDate) && $commitmentDate !== '' ? CarbonImmutable::parse($commitmentDate, $this->timezone())->toDateString() : null;
    }

    private function commitmentTime(FulfillmentPlan $plan): ?string
    {
        $commitmentTime = $plan->commitment_time;

        return is_string($commitmentTime) && $commitmentTime !== '' ? $commitmentTime : null;
    }

    private function defaultCommitmentTime(?string $requestedTimeWindow): ?string
    {
        $defaults = (array) config('fulfillment.commitment.default_times', []);

        return match ($requestedTimeWindow) {
            'morning' => (string) ($defaults['morning'] ?? '11:00'),
            'afternoon' => (string) ($defaults['afternoon'] ?? '16:00'),
            'evening' => (string) ($defaults['evening'] ?? '19:00'),
            default => (string) ($defaults['anytime'] ?? '17:00'),
        };
    }

    private function explicitCommitmentTime(FulfillmentPlan $plan): ?string
    {
        $intent = (array) data_get($plan->metadata_json ?? [], 'fulfillment_intent.metadata', []);
        $specificMentions = array_values(array_filter((array) ($intent['specific_time_mentions'] ?? []), static fn ($value): bool => is_string($value) && $value !== ''));

        foreach ($specificMentions as $mention) {
            $resolved = $this->parseSpecificTimeMention($mention);

            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }

    private function parseSpecificTimeMention(string $mention): ?string
    {
        $normalized = Str::ascii(mb_strtolower(trim($mention)));
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        if ($normalized === '') {
            return null;
        }

        $patterns = [
            '/\b(?:antes de(?: las)?|before)\s+(?:las?\s+)?(?<hour>\d{1,2})(?::(?<minute>\d{2}))?\s*(?<meridiem>am|pm)?\b/u',
            '/\b(?:a|para|por)\s+las?\s+(?<hour>\d{1,2})(?::(?<minute>\d{2}))?\s*(?<meridiem>am|pm)?\b/u',
            '/\b(?<hour>\d{1,2}):(?<minute>\d{2})\s*(?<meridiem>am|pm)?\b/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $normalized, $matches) !== 1) {
                continue;
            }

            $hour = (int) ($matches['hour'] ?? 0);
            $minute = (int) ($matches['minute'] ?? 0);
            $meridiem = strtolower((string) ($matches['meridiem'] ?? ''));

            if ($meridiem === 'pm' && $hour < 12) {
                $hour += 12;
            } elseif ($meridiem === 'am' && $hour === 12) {
                $hour = 0;
            }

            $hour = max(0, min(23, $hour));
            $minute = max(0, min(59, $minute));

            return sprintf('%02d:%02d:00', $hour, $minute);
        }

        return null;
    }

    private function plannerConfidence(FulfillmentPlan $plan): int
    {
        return max(0, min(100, (int) ($plan->planner_confidence ?? 0)));
    }

    private function confidenceModifier(int $confidence): int
    {
        if (! (bool) config('fulfillment.priority.confidence.enabled', false)) {
            return 0;
        }

        $weight = (int) config('fulfillment.priority.confidence.weight', 20);

        return (int) round((($confidence - 50) / 50) * $weight);
    }

    private function priorityLevelFromScore(int $score): string
    {
        $thresholds = (array) config('fulfillment.priority.thresholds', []);

        if ($score <= (int) ($thresholds['low_max'] ?? 30)) {
            return 'low';
        }

        if ($score <= (int) ($thresholds['normal_max'] ?? 60)) {
            return 'normal';
        }

        if ($score <= (int) ($thresholds['high_max'] ?? 85)) {
            return 'high';
        }

        return 'urgent';
    }

    private function customerIsVip(FulfillmentPlan $plan): bool
    {
        $order = $plan->relationLoaded('order') ? $plan->order : $plan->order()->with('customer')->first();
        $customer = $order?->customer;

        if ($customer === null) {
            return false;
        }

        $organizationId = (int) ($plan->organization_id ?? $order->organization_id ?? 0);
        $threshold = (int) config('fulfillment.priority.vip_min_orders', 20);

        $orderCount = $customer->orders()
            ->where('organization_id', $organizationId)
            ->count();

        return $orderCount >= $threshold;
    }

    private function duplicateDetected(FulfillmentPlan $plan): bool
    {
        $order = $plan->relationLoaded('order') ? $plan->order : $plan->order()->first();

        if ($order === null) {
            return false;
        }

        if ($order->possible_duplicate_of_order_id !== null) {
            return true;
        }

        return (float) ($order->duplicate_score ?? 0) >= 75;
    }

    private function deliveryMethod(FulfillmentPlan $plan): ?string
    {
        $value = $this->stringValue($plan->delivery_method);

        if ($value !== null) {
            return $value;
        }

        $intentMethod = data_get($plan->metadata_json ?? [], 'fulfillment_intent.delivery_method');

        return is_string($intentMethod) && $intentMethod !== '' ? $intentMethod : null;
    }

    private function stringValue(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
