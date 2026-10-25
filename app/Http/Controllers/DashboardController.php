<?php

namespace App\Http\Controllers;

use App\Models\DailyOrderClosure;
use App\Models\Order;
use App\Models\Product;
use App\Models\SetupRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();
        $organizationId = $user?->organization_id;
        $branchIds = $user?->visibleBranchIds() ?? [];

        $closureScope = function (Builder $query) use ($user, $organizationId, $branchIds): Builder {
            if ($user?->canViewAllBranchesForRead()) {
                return $organizationId ? $query->where('organization_id', $organizationId) : $query->whereRaw('1 = 0');
            }

            return $branchIds ? $query->whereIn('branch_id', $branchIds) : $query->whereRaw('1 = 0');
        };

        $orderScope = function (Builder $query) use ($user, $organizationId, $branchIds): Builder {
            if ($user?->canViewAllBranchesForRead()) {
                return $organizationId ? $query->where('organization_id', $organizationId) : $query->whereRaw('1 = 0');
            }

            return $branchIds ? $query->whereIn('branch_id', $branchIds) : $query->whereRaw('1 = 0');
        };

        return view('dashboard', [
            'openSetupRequestsCount' => SetupRequest::query()
                ->when($organizationId, fn (Builder $query) => $query->where('organization_id', $organizationId), fn (Builder $query) => $query->whereRaw('1 = 0'))
                ->where('status', SetupRequest::STATUS_OPEN)
                ->count(),
            'inProgressSetupRequestsCount' => SetupRequest::query()
                ->when($organizationId, fn (Builder $query) => $query->where('organization_id', $organizationId), fn (Builder $query) => $query->whereRaw('1 = 0'))
                ->where('status', SetupRequest::STATUS_IN_PROGRESS)
                ->count(),
            'completedSetupRequestsCount' => SetupRequest::query()
                ->when($organizationId, fn (Builder $query) => $query->where('organization_id', $organizationId), fn (Builder $query) => $query->whereRaw('1 = 0'))
                ->where('status', SetupRequest::STATUS_COMPLETED)
                ->count(),
            'activeProductCount' => Product::query()
                ->when($organizationId, fn ($query) => $query->where('organization_id', $organizationId), fn ($query) => $query->whereRaw('1 = 0'))
                ->where('is_active', true)
                ->count(),
            'totalProductCount' => Product::query()
                ->when($organizationId, fn ($query) => $query->where('organization_id', $organizationId), fn ($query) => $query->whereRaw('1 = 0'))
                ->count(),
            'ordersTodayCount' => Order::query()
                ->tap($orderScope)
                ->whereDate('created_at', today())
                ->count(),
            'orderPendingReviewCount' => Order::query()
                ->tap($orderScope)
                ->where('status', Order::STATUS_PENDING_REVIEW)
                ->count(),
            'orderConfirmedCount' => Order::query()
                ->tap($orderScope)
                ->where('status', Order::STATUS_CONFIRMED)
                ->count(),
            'orderPreparingCount' => Order::query()
                ->tap($orderScope)
                ->where('status', Order::STATUS_PREPARING)
                ->count(),
            'orderReadyForDispatchCount' => Order::query()
                ->tap($orderScope)
                ->where('status', Order::STATUS_READY_FOR_DISPATCH)
                ->count(),
            'orderDispatchedCount' => Order::query()
                ->tap($orderScope)
                ->where('status', Order::STATUS_DISPATCHED)
                ->whereDate('dispatched_at', today())
                ->count(),
            'recentOrders' => Order::query()
                ->tap($orderScope)
                ->with(['customer', 'branch'])
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(),
            'dailyOrderClosuresTodayCount' => DailyOrderClosure::query()
                ->tap($closureScope)
                ->whereDate('closure_date', today())
                ->count(),
            'recentClosures' => DailyOrderClosure::query()
                ->tap($closureScope)
                ->with(['branch', 'closedByUser'])
                ->orderByDesc('closed_at')
                ->limit(5)
                ->get(),
            'totalClosuresCount' => DailyOrderClosure::query()
                ->tap($closureScope)
                ->count(),
        ]);
    }
}
