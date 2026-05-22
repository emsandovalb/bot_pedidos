<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Draw;
use App\Models\IntakeRequest;
use App\Models\NumberLimit;
use App\Models\Organization;

class NumberLimitService
{
    public function currentConfirmedAmount(Organization $organization, Branch $branch, ?Draw $draw, string $number): float
    {
        return $this->currentRequestAmount($organization, $branch, $draw, $number, [
            IntakeRequest::STATUS_CONFIRMED,
        ]);
    }

    public function currentRequestAmount(Organization $organization, Branch $branch, ?Draw $draw, string $number, array $statuses): float
    {
        return (float) IntakeRequest::query()
            ->where('organization_id', $organization->id)
            ->where('branch_id', $branch->id)
            ->when($draw, fn ($query) => $query->where('draw_id', $draw->id), fn ($query) => $query->whereNull('draw_id'))
            ->where('detected_number', $number)
            ->whereIn('status', $statuses)
            ->sum('detected_amount');
    }

    public function currentActiveAmount(Organization $organization, Branch $branch, ?Draw $draw, string $number): float
    {
        return $this->currentRequestAmount($organization, $branch, $draw, $number, [
            IntakeRequest::STATUS_CONFIRMED,
            IntakeRequest::STATUS_PENDING,
            IntakeRequest::STATUS_NEEDS_REVIEW,
        ]);
    }

    public function limitFor(Organization $organization, Branch $branch, Draw $draw, string $number): ?NumberLimit
    {
        return NumberLimit::query()
            ->where('organization_id', $organization->id)
            ->where('branch_id', $branch->id)
            ->where('draw_id', $draw->id)
            ->where('number', $number)
            ->first();
    }

    public function warningForAmount(
        Organization $organization,
        Branch $branch,
        ?Draw $draw,
        string $number,
        float $amount,
    ): ?string {
        if ($draw === null) {
            return null;
        }

        $limit = $this->limitFor($organization, $branch, $draw, $number);

        if ($limit === null) {
            return null;
        }

        $activeAmount = $this->currentActiveAmount($organization, $branch, $draw, $number);
        $projectedAmount = $activeAmount + $amount;

        if ($projectedAmount <= (float) $limit->max_amount) {
            return null;
        }

        return sprintf(
            'Limit warning: current active amount for %s on %s %s would exceed max ₡%s.',
            $number,
            $branch->name,
            $draw->name,
            $this->formatAmount($limit->max_amount),
        );
    }

    public function statusFor(?NumberLimit $limit, float $activeAmount): string
    {
        if ($limit === null) {
            return 'available';
        }

        $maxAmount = (float) $limit->max_amount;

        if ($maxAmount <= 0) {
            return $activeAmount > 0 ? 'over_limit' : 'full';
        }

        $usagePercentage = round(($activeAmount / $maxAmount) * 100, 6);

        if ($usagePercentage > 100) {
            return 'over_limit';
        }

        if ($usagePercentage === 100.0) {
            return 'full';
        }

        if ($usagePercentage >= 80) {
            return 'warning';
        }

        return 'available';
    }

    /**
     * @return array{available_amount: float|null, percentage_used: float|null, status: string}
     */
    public function limitStateFor(?NumberLimit $limit, float $activeAmount): array
    {
        if ($limit === null) {
            return [
                'available_amount' => null,
                'percentage_used' => null,
                'status' => 'available',
            ];
        }

        $maxAmount = (float) $limit->max_amount;

        return [
            'available_amount' => $maxAmount - $activeAmount,
            'percentage_used' => $maxAmount > 0
                ? round(($activeAmount / $maxAmount) * 100, 1)
                : null,
            'status' => $this->statusFor($limit, $activeAmount),
        ];
    }

    private function formatAmount(mixed $amount): string
    {
        if (is_numeric($amount) && (float) $amount === (float) (int) $amount) {
            return (string) (int) $amount;
        }

        return rtrim(rtrim(number_format((float) $amount, 2, '.', ''), '0'), '.');
    }
}
