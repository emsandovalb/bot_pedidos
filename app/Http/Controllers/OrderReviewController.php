<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

class OrderReviewController extends Controller
{
    public function index(): View
    {
        $query = $this->scopedQuery()
            ->with([
                'branch',
                'customer',
                'possibleDuplicateOf',
                'orderItems' => fn ($query) => $query->orderBy('sort_order'),
                'orderItems.product',
            ])
            ->withCount([
                'orderItems as recognized_order_items_count' => fn ($query) => $query->whereNotNull('product_id'),
            ])
            ->where('status', Order::STATUS_PENDING_REVIEW)
            ->orderByDesc('created_at');

        return view('order-reviews.index', [
            'orders' => $query->paginate(25),
            'pendingReviewCount' => $this->scopedQuery()->where('status', Order::STATUS_PENDING_REVIEW)->count(),
            'confirmedCount' => $this->scopedQuery()->where('status', Order::STATUS_CONFIRMED)->count(),
            'dispatchedCount' => $this->scopedQuery()->where('status', Order::STATUS_DISPATCHED)->count(),
        ]);
    }

    private function scopedQuery(): Builder
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
