<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\BranchDailyClosure;
use App\Models\User;

class BranchDailyClosurePolicy
{
    public function viewAny(User $user): bool
    {
        return (bool) $user->organization_id;
    }

    public function view(User $user, BranchDailyClosure $closure): bool
    {
        if ($user->organization_id !== $closure->organization_id) {
            return false;
        }

        if ($user->canViewAllBranchesForRead()) {
            return true;
        }

        return $user->branch_id !== null && $user->branch_id === $closure->branch_id;
    }

    public function create(User $user, Branch $branch): bool
    {
        if ($user->organization_id !== $branch->organization_id) {
            return false;
        }

        if ($user->canViewAllBranches()) {
            return true;
        }

        return $user->isSeller() && $user->branch_id !== null && $user->branch_id === $branch->id;
    }
}
