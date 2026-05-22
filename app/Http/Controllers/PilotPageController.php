<?php

namespace App\Http\Controllers;

use App\Models\BranchDailyClosure;
use App\Models\IncomingMessage;
use App\Models\IntakeRequest;
use Illuminate\Contracts\View\View;

class PilotPageController extends Controller
{
    public function checklist(): View
    {
        $user = auth()->user();
        $organizationId = $user?->organization_id;
        $branchIds = $user?->visibleBranchIds() ?? [];

        $closureCount = BranchDailyClosure::query()
            ->when($user?->canViewAllBranchesForRead(), fn ($query) => $query->where('organization_id', $organizationId), fn ($query) => $query->whereIn('branch_id', $branchIds))
            ->count();

        $requestCount = IntakeRequest::query()
            ->when($user?->canViewAllBranchesForRead(), fn ($query) => $query->where('organization_id', $organizationId), fn ($query) => $query->whereIn('branch_id', $branchIds))
            ->count();

        $needsReviewCount = IntakeRequest::query()
            ->when($user?->canViewAllBranchesForRead(), fn ($query) => $query->where('organization_id', $organizationId), fn ($query) => $query->whereIn('branch_id', $branchIds))
            ->where('status', IntakeRequest::STATUS_NEEDS_REVIEW)
            ->count();

        $rejectedCount = IntakeRequest::query()
            ->when($user?->canViewAllBranchesForRead(), fn ($query) => $query->where('organization_id', $organizationId), fn ($query) => $query->whereIn('branch_id', $branchIds))
            ->where('status', IntakeRequest::STATUS_REJECTED)
            ->count();

        $messageCount = IncomingMessage::query()
            ->when($user?->canViewAllBranchesForRead(), fn ($query) => $query->where('organization_id', $organizationId), fn ($query) => $query->whereIn('branch_id', $branchIds))
            ->count();

        return view('pilot.checklist', [
            'items' => [
                ['label' => 'Test simulator', 'status' => $messageCount > 0 ? 'done' : 'pending'],
                ['label' => 'Review incoming messages', 'status' => $messageCount > 0 ? 'done' : 'pending'],
                ['label' => 'Review requests', 'status' => $requestCount > 0 ? 'done' : 'pending'],
                ['label' => 'Confirm pending request', 'status' => IntakeRequest::query()
                    ->when($user?->canViewAllBranchesForRead(), fn ($query) => $query->where('organization_id', $organizationId), fn ($query) => $query->whereIn('branch_id', $branchIds))
                    ->where('status', IntakeRequest::STATUS_CONFIRMED)
                    ->exists() ? 'done' : 'pending'],
                ['label' => 'Edit needs_review request', 'status' => $needsReviewCount > 0 ? 'done' : 'pending'],
                ['label' => 'Reject invalid request', 'status' => $rejectedCount > 0 ? 'done' : 'pending'],
                ['label' => 'Close day by branch', 'status' => $user?->isViewer() ? 'not applicable' : ($closureCount > 0 ? 'done' : 'pending')],
                ['label' => 'View closure', 'status' => $closureCount > 0 ? 'done' : 'pending'],
                ['label' => 'Export CSV', 'status' => $closureCount > 0 ? 'done' : 'pending'],
                ['label' => 'Print closure', 'status' => $closureCount > 0 ? 'done' : 'pending'],
            ],
        ]);
    }

    public function script(): View
    {
        return view('pilot.script');
    }

    public function guide(): View
    {
        return view('pilot.guide');
    }
}
