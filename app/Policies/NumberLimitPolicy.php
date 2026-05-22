<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\NumberLimit;
use App\Models\User;

class NumberLimitPolicy
{
    public function viewAny(User $user): bool
    {
        return (bool) $user->organization_id;
    }

    public function view(User $user, NumberLimit $limit): bool
    {
        if ($user->organization_id !== $limit->organization_id) {
            return false;
        }

        if ($user->canViewAllBranchesForRead()) {
            return true;
        }

        return $user->branch_id !== null && $user->branch_id === $limit->branch_id;
    }

    public function create(User $user, ?Branch $branch = null): bool
    {
        if (! $user->canViewAllBranches()) {
            return false;
        }

        if ($branch !== null && $user->organization_id !== $branch->organization_id) {
            return false;
        }

        return (bool) $user->organization_id;
    }

    public function update(User $user, NumberLimit $limit): bool
    {
        return $user->canViewAllBranches() && $user->organization_id === $limit->organization_id;
    }

    public function delete(User $user, NumberLimit $limit): bool
    {
        return $this->update($user, $limit);
    }
}
