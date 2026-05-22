<?php

namespace App\Policies;

use App\Models\IntakeRequest;
use App\Models\User;

class IntakeRequestPolicy
{
    public function view(User $user, IntakeRequest $intakeRequest): bool
    {
        return $this->isSameOrganizationAndAllowedBranch($user, $intakeRequest);
    }

    public function update(User $user, IntakeRequest $intakeRequest): bool
    {
        return $this->isManageable($user, $intakeRequest) && $this->isEditable($intakeRequest);
    }

    public function confirm(User $user, IntakeRequest $intakeRequest): bool
    {
        return $this->isManageable($user, $intakeRequest) && $this->isReviewable($intakeRequest);
    }

    public function reject(User $user, IntakeRequest $intakeRequest): bool
    {
        return $this->isManageable($user, $intakeRequest) && $this->isReviewable($intakeRequest);
    }

    public function viewAny(User $user): bool
    {
        return (bool) $user->organization_id;
    }

    private function isSameOrganizationAndAllowedBranch(User $user, IntakeRequest $intakeRequest): bool
    {
        if ($user->organization_id !== $intakeRequest->organization_id) {
            return false;
        }

        if ($user->canViewAllBranches()) {
            return true;
        }

        if ($user->isViewer()) {
            return true;
        }

        if ($user->isSeller()) {
            return $user->branch_id !== null && $user->branch_id === $intakeRequest->branch_id;
        }

        return false;
    }

    private function isManageable(User $user, IntakeRequest $intakeRequest): bool
    {
        return $this->isSameOrganizationAndAllowedBranch($user, $intakeRequest) && ! $user->isViewer();
    }

    private function isEditable(IntakeRequest $intakeRequest): bool
    {
        return in_array($intakeRequest->status, [
            IntakeRequest::STATUS_PENDING,
            IntakeRequest::STATUS_NEEDS_REVIEW,
        ], true);
    }

    private function isReviewable(IntakeRequest $intakeRequest): bool
    {
        return in_array($intakeRequest->status, [
            IntakeRequest::STATUS_PENDING,
            IntakeRequest::STATUS_NEEDS_REVIEW,
        ], true);
    }
}
