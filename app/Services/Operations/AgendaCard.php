<?php

namespace App\Services\Operations;

use Carbon\CarbonInterface;
use Illuminate\Support\Str;

class AgendaCard
{
    /**
     * @param  array<string, mixed>  $order
     */
    public function __construct(
        private readonly array $order,
        private readonly CarbonInterface $referenceTime,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $commitmentDate = $this->stringValue($this->order['commitment_date'] ?? null);
        $commitmentTime = $this->stringValue($this->order['commitment_time'] ?? null);
        $remainingSlaMinutes = $this->intValue($this->order['remaining_sla_minutes'] ?? null);
        $riskLevel = $this->stringValue($this->order['risk_level'] ?? null) ?? 'low';
        $priorityLevel = $this->stringValue($this->order['priority_level'] ?? null) ?? 'normal';
        $deliveryMethod = $this->stringValue($this->order['delivery_method'] ?? null) ?? 'unknown';
        $paymentMethod = $this->stringValue($this->order['payment_method'] ?? null) ?? 'unknown';
        $parserConfidence = $this->floatValue($this->order['parser_confidence'] ?? null);

        return array_merge($this->order, [
            'commitment_date_label' => $commitmentDate !== null ? $this->formatDateLabel($commitmentDate) : 'Sin compromiso',
            'commitment_time_label' => $commitmentTime !== null ? $this->formatTimeLabel($commitmentTime) : 'Sin hora',
            'time_window_label' => $this->timeWindowLabel(),
            'delivery_label' => $this->deliveryLabel($deliveryMethod),
            'payment_label' => $this->paymentLabel($paymentMethod),
            'priority_badge' => $this->priorityBadge($priorityLevel, $remainingSlaMinutes, $riskLevel),
            'risk_badge' => $this->riskBadge($riskLevel, $remainingSlaMinutes),
            'sla_label' => $this->slaLabel($remainingSlaMinutes),
            'workflow_status_label' => $this->stringValue($this->order['status_label'] ?? null) ?? 'Sin estado',
            'workflow_status_tone' => $this->stringValue($this->order['status_tone'] ?? null) ?? 'bg-slate-100 text-slate-700 ring-1 ring-slate-200',
            'vip_badge' => (bool) ($this->order['vip'] ?? false),
            'duplicate_badge' => (bool) ($this->order['duplicate'] ?? false),
            'channel_icon' => $this->channelIcon(),
            'parser_confidence_label' => $parserConfidence !== null ? number_format($parserConfidence, 2) : 'Sin dato',
            'smart_labels' => $this->smartLabels(),
            'summary' => $this->summary(),
            'accent_class' => $this->accentClass($riskLevel, $priorityLevel),
            'sort_bucket' => $this->sortBucket(),
            'time_window_rank' => $this->timeWindowRank(),
        ]);
    }

    public function isCritical(): bool
    {
        return $this->stringValue($this->order['risk_level'] ?? null) === 'critical';
    }

    public function isDueSoon(): bool
    {
        return $this->stringValue($this->order['risk_level'] ?? null) === 'high';
    }

    public function isCompletedToday(): bool
    {
        return $this->isDispatched() && $this->hasDateFor($this->todayString());
    }

    public function isToday(): bool
    {
        return $this->hasDateFor($this->todayString()) && ! $this->isCritical() && ! $this->isDueSoon() && ! $this->isCompletedToday();
    }

    public function isTomorrow(): bool
    {
        return $this->hasDateFor($this->tomorrowString()) && ! $this->isCritical() && ! $this->isDueSoon() && ! $this->isCompletedToday();
    }

    public function hasCommitment(): bool
    {
        return $this->stringValue($this->order['commitment_date'] ?? null) !== null;
    }

    public function isNoCommitment(): bool
    {
        return ! $this->hasCommitment();
    }

    public function isDispatched(): bool
    {
        return $this->stringValue($this->order['status'] ?? null) === 'dispatched';
    }

    public function customerName(): string
    {
        return $this->stringValue($this->order['customer_name'] ?? null) ?? 'Sin cliente';
    }

    public function sortKey(): array
    {
        $remainingSlaMinutes = $this->intValue($this->order['remaining_sla_minutes'] ?? null);

        return [
            $remainingSlaMinutes === null ? PHP_INT_MAX : $remainingSlaMinutes,
            $this->timeWindowRank(),
            $this->commitmentMinutes(),
            mb_strtolower($this->customerName()),
            (int) ($this->order['id'] ?? 0),
        ];
    }

    private function summary(): string
    {
        $summary = $this->stringValue($this->order['summary'] ?? null);

        if ($summary !== null) {
            return $summary;
        }

        $preview = $this->stringValue($this->order['preview'] ?? null);

        return $preview !== null ? Str::limit($preview, 120) : 'Sin resumen';
    }

    private function deliveryLabel(string $deliveryMethod): string
    {
        return match ($deliveryMethod) {
            'pickup' => 'Pickup',
            'delivery' => 'Delivery',
            'express' => 'Express',
            'third_party' => 'Terceros',
            default => Str::headline($deliveryMethod),
        };
    }

    private function paymentLabel(string $paymentMethod): string
    {
        return match ($paymentMethod) {
            'sinpe' => 'SINPE',
            'cash' => 'Cash',
            'card' => 'Card',
            'transfer' => 'Transferencia',
            default => Str::headline($paymentMethod),
        };
    }

    private function priorityBadge(string $priorityLevel, ?int $remainingSlaMinutes, string $riskLevel): array
    {
        if ($riskLevel === 'critical' || ($remainingSlaMinutes !== null && $remainingSlaMinutes < 0)) {
            return [
                'label' => 'Urgente',
                'tone' => 'bg-rose-50 text-rose-800 ring-1 ring-rose-100',
            ];
        }

        if ($priorityLevel === 'urgent' || $riskLevel === 'high') {
            return [
                'label' => 'Alta',
                'tone' => 'bg-amber-50 text-amber-800 ring-1 ring-amber-100',
            ];
        }

        if ($priorityLevel === 'low') {
            return [
                'label' => 'Baja',
                'tone' => 'bg-slate-100 text-slate-700 ring-1 ring-slate-200',
            ];
        }

        return [
            'label' => 'Normal',
            'tone' => 'bg-blue-50 text-blue-800 ring-1 ring-blue-100',
        ];
    }

    private function riskBadge(string $riskLevel, ?int $remainingSlaMinutes): array
    {
        return match ($riskLevel) {
            'critical' => [
                'label' => 'SLA Expired',
                'tone' => 'bg-rose-50 text-rose-800 ring-1 ring-rose-100',
            ],
            'high' => [
                'label' => $remainingSlaMinutes !== null ? 'Due in ' . $remainingSlaMinutes . ' min' : 'High risk',
                'tone' => 'bg-orange-50 text-orange-800 ring-1 ring-orange-100',
            ],
            'medium' => [
                'label' => 'Medium',
                'tone' => 'bg-amber-50 text-amber-800 ring-1 ring-amber-100',
            ],
            default => [
                'label' => 'Normal',
                'tone' => 'bg-slate-100 text-slate-700 ring-1 ring-slate-200',
            ],
        };
    }

    private function slaLabel(?int $remainingSlaMinutes): string
    {
        if ($remainingSlaMinutes === null) {
            return 'Sin SLA';
        }

        if ($remainingSlaMinutes < 0) {
            return 'SLA Expired';
        }

        if ($remainingSlaMinutes < 60) {
            return 'Due in ' . $remainingSlaMinutes . ' min';
        }

        return 'Due in ' . (int) round($remainingSlaMinutes / 60) . ' h';
    }

    private function channelIcon(): string
    {
        return match (strtolower((string) ($this->order['channel_key'] ?? $this->order['source_channel'] ?? ''))) {
            'whatsapp' => 'WA',
            'telegram' => 'TG',
            default => 'CH',
        };
    }

    private function smartLabels(): array
    {
        $labels = [
            $this->deliveryLabel((string) ($this->order['delivery_method'] ?? 'unknown')),
            $this->paymentLabel((string) ($this->order['payment_method'] ?? 'unknown')),
        ];

        if (($this->order['vip'] ?? false) === true) {
            $labels[] = 'VIP';
        }

        if (($this->order['duplicate'] ?? false) === true) {
            $labels[] = 'Duplicado';
        }

        if (($this->order['remaining_sla_minutes'] ?? null) !== null) {
            $labels[] = $this->slaLabel($this->intValue($this->order['remaining_sla_minutes']));
        }

        return array_values(array_filter($labels, static fn ($value): bool => is_string($value) && $value !== ''));
    }

    private function accentClass(string $riskLevel, string $priorityLevel): string
    {
        if ($riskLevel === 'critical') {
            return 'border-l-4 border-l-rose-500';
        }

        if ($riskLevel === 'high' || $priorityLevel === 'urgent') {
            return 'border-l-4 border-l-orange-400';
        }

        if ($priorityLevel === 'low') {
            return 'border-l-4 border-l-slate-300';
        }

        return 'border-l-4 border-l-blue-500';
    }

    private function timeWindowLabel(): string
    {
        $explicitWindow = $this->stringValue($this->order['requested_time_window'] ?? null);

        if ($explicitWindow !== null) {
            return Str::headline(str_replace('_', ' ', $explicitWindow));
        }

        $commitmentTime = $this->stringValue($this->order['commitment_time'] ?? null);

        if ($commitmentTime === null) {
            return 'Anytime';
        }

        $hour = (int) substr($commitmentTime, 0, 2);

        return match (true) {
            $hour < 10 => 'Morning',
            $hour < 12 => 'Before Noon',
            $hour < 17 => 'Afternoon',
            $hour < 20 => 'Evening',
            default => 'Anytime',
        };
    }

    private function timeWindowRank(): int
    {
        return match ($this->timeWindowLabel()) {
            'Morning' => 0,
            'Before Noon' => 1,
            'Afternoon' => 2,
            'Evening' => 3,
            default => 4,
        };
    }

    private function sortBucket(): string
    {
        if ($this->isCritical()) {
            return 'critical';
        }

        if ($this->isDueSoon()) {
            return 'due_soon';
        }

        if ($this->isCompletedToday()) {
            return 'completed';
        }

        if ($this->isToday()) {
            return 'today';
        }

        if ($this->isTomorrow()) {
            return 'tomorrow';
        }

        return 'no_commitment';
    }

    private function commitmentMinutes(): int
    {
        $time = $this->stringValue($this->order['commitment_time'] ?? null);

        if ($time === null || ! preg_match('/^(?<hour>\d{2}):(?<minute>\d{2})/', $time, $matches)) {
            return PHP_INT_MAX;
        }

        return ((int) $matches['hour']) * 60 + (int) $matches['minute'];
    }

    private function hasDateFor(string $date): bool
    {
        return $this->stringValue($this->order['commitment_date'] ?? null) === $date;
    }

    private function todayString(): string
    {
        return $this->referenceTime->toDateString();
    }

    private function tomorrowString(): string
    {
        return $this->referenceTime->addDay()->toDateString();
    }

    private function formatDateLabel(string $date): string
    {
        return $date;
    }

    private function formatTimeLabel(string $time): string
    {
        return preg_match('/^\d{2}:\d{2}/', $time) === 1 ? substr($time, 0, 5) : $time;
    }

    private function stringValue(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function intValue(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function floatValue(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
