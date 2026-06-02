<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\DailyOrderClosure;
use App\Models\IntakeRequest;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();
        $organizationId = $user?->organization_id;
        $branchIds = $user?->visibleBranchIds() ?? [];

        $requestScope = function (Builder $query) use ($user, $organizationId, $branchIds): Builder {
            if ($user?->canViewAllBranchesForRead()) {
                return $organizationId ? $query->where('organization_id', $organizationId) : $query->whereRaw('1 = 0');
            }

            return $branchIds ? $query->whereIn('branch_id', $branchIds) : $query->whereRaw('1 = 0');
        };

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

        $branchesQuery = Branch::query()
            ->whereIn('id', $branchIds)
            ->withCount([
                'requests as requests_today_count' => fn (Builder $query) => $query->whereDate('created_at', today()),
                'requests as pending_requests_count' => fn (Builder $query) => $query->where('status', IntakeRequest::STATUS_PENDING),
                'requests as needs_review_requests_count' => fn (Builder $query) => $query->where('status', IntakeRequest::STATUS_NEEDS_REVIEW),
                'requests as confirmed_requests_count' => fn (Builder $query) => $query->where('status', IntakeRequest::STATUS_CONFIRMED),
            ])
            ->orderBy('name');

        return view('dashboard', [
            'totalPendingRequests' => IntakeRequest::query()
                ->tap($requestScope)
                ->where('status', IntakeRequest::STATUS_PENDING)
                ->count(),
            'totalNeedsReviewRequests' => IntakeRequest::query()
                ->tap($requestScope)
                ->where('status', IntakeRequest::STATUS_NEEDS_REVIEW)
                ->count(),
            'totalConfirmedRequestsToday' => IntakeRequest::query()
                ->tap($requestScope)
                ->where('status', IntakeRequest::STATUS_CONFIRMED)
                ->whereDate('confirmed_at', today())
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
            'statusCounts' => [
                'pending' => IntakeRequest::query()->tap($requestScope)->where('status', IntakeRequest::STATUS_PENDING)->count(),
                'needs_review' => IntakeRequest::query()->tap($requestScope)->where('status', IntakeRequest::STATUS_NEEDS_REVIEW)->count(),
                'confirmed' => IntakeRequest::query()->tap($requestScope)->where('status', IntakeRequest::STATUS_CONFIRMED)->count(),
                'rejected' => IntakeRequest::query()->tap($requestScope)->where('status', IntakeRequest::STATUS_REJECTED)->count(),
            ],
            'branchTotals' => $branchesQuery->get(),
            'topBranchesToday' => Branch::query()
                ->whereIn('id', $branchIds)
                ->withCount(['requests as requests_today_count' => fn (Builder $query) => $query->whereDate('created_at', today())])
                ->orderByDesc('requests_today_count')
                ->orderBy('name')
                ->limit(3)
                ->get(),
            'needsReviewRequests' => IntakeRequest::query()
                ->tap($requestScope)
                ->with(['branch', 'customer'])
                ->where('status', IntakeRequest::STATUS_NEEDS_REVIEW)
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
            'stalePendingRequestCount' => IntakeRequest::query()
                ->tap($requestScope)
                ->where('status', IntakeRequest::STATUS_PENDING)
                ->where('created_at', '<=', now()->subHours(24))
                ->count(),
            'previousDayPendingCount' => IntakeRequest::query()
                ->tap($requestScope)
                ->where('status', IntakeRequest::STATUS_PENDING)
                ->whereDate('created_at', '<', today())
                ->count(),
        ]);
    }
}
