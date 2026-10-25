<?php

namespace App\Services\Operations;

use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;

/**
 * Pedidos que el usuario actual puede ver: toda la organización o solo sus sucursales.
 */
class OperationsOrderScope
{
    public function scopedQuery(): Builder
    {
        $user = auth()->user();

        return Order::query()->where(function (Builder $query) use ($user): void {
            if ($user?->canViewAllBranchesForRead()) {
                if ($user?->organization_id) {
                    $query->where('organization_id', $user->organization_id);

                    return;
                }

                $query->whereRaw('1 = 0');

                return;
            }

            $branchIds = $user?->visibleBranchIds() ?? [];

            if ($branchIds === []) {
                $query->whereRaw('1 = 0');

                return;
            }

            $query->whereIn('branch_id', $branchIds);
        });
    }
}
