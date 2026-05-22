<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Draw;
use App\Models\IntakeRequest;
use App\Models\IntakeRequestEvent;
use App\Services\NumberLimitService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NumberBoardController extends Controller
{
    public function __construct(
        private readonly NumberLimitService $numberLimitService,
    ) {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $visibleBranchIds = $user?->visibleBranchIds() ?? [];
        $organizationId = $user?->organization_id;

        $branches = Branch::query()
            ->whereIn('id', $visibleBranchIds)
            ->orderBy('name')
            ->get();

        $draws = Draw::query()
            ->when($organizationId, fn ($query) => $query->where('organization_id', $organizationId), fn ($query) => $query->whereRaw('1 = 0'))
            ->where('status', Draw::STATUS_ACTIVE)
            ->orderBy('draw_time')
            ->get();

        $validated = $request->validate([
            'branch_id' => ['nullable', 'integer', Rule::in($visibleBranchIds ?: [-1])],
            'draw_id' => ['nullable', 'integer', Rule::in($draws->pluck('id')->all() ?: [-1])],
        ]);

        $selectedBranch = $branches->firstWhere('id', (int) ($validated['branch_id'] ?? 0))
            ?? $branches->first();

        $selectedDraw = $draws->firstWhere('id', (int) ($validated['draw_id'] ?? 0))
            ?? $draws->first();

        $numbers = $this->buildNumberRows(
            organizationId: $organizationId,
            branch: $selectedBranch,
            draw: $selectedDraw,
        );

        return view('numbers.index', [
            'branches' => $branches,
            'draws' => $draws,
            'selectedBranch' => $selectedBranch,
            'selectedDraw' => $selectedDraw,
            'numbers' => $numbers['rows'],
            'summary' => $numbers['summary'],
            'canCreateManualRequests' => ! $user?->isViewer(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_if($user?->isViewer(), 403);

        $visibleBranchIds = $user?->visibleBranchIds() ?? [];
        $organizationId = $user?->organization_id;

        $validated = $request->validate([
            'branch_id' => ['required', 'integer', Rule::in($visibleBranchIds ?: [-1])],
            'draw_id' => ['required', 'integer'],
            'number' => ['required', 'regex:/^(0[0-9]|[1-9][0-9])$/'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'regex:/^\+?[0-9]{8,15}$/'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $branch = Branch::query()
            ->whereKey($validated['branch_id'])
            ->whereIn('id', $visibleBranchIds)
            ->firstOrFail();

        $draw = Draw::query()
            ->whereKey($validated['draw_id'])
            ->where('organization_id', $organizationId)
            ->where('status', Draw::STATUS_ACTIVE)
            ->firstOrFail();

        $customer = null;

        if (! empty($validated['customer_phone'])) {
            $customer = Customer::query()
                ->where('organization_id', $organizationId)
                ->where('phone', $validated['customer_phone'])
                ->first();

            if (! $customer) {
                $customer = Customer::create([
                    'organization_id' => $organizationId,
                    'branch_id' => $branch->id,
                    'name' => $validated['customer_name'] ?? null,
                    'phone' => $validated['customer_phone'],
                    'external_id' => null,
                ]);
            } elseif (! empty($validated['customer_name']) && empty($customer->name)) {
                $customer->forceFill([
                    'name' => $validated['customer_name'],
                ])->save();
            }
        } elseif (! empty($validated['customer_name'])) {
            $customer = Customer::create([
                'organization_id' => $organizationId,
                'branch_id' => $branch->id,
                'name' => $validated['customer_name'],
                'phone' => null,
                'external_id' => null,
            ]);
        }

        $warning = $this->numberLimitService->warningForAmount(
            $user->organization,
            $branch,
            $draw,
            $validated['number'],
            (float) $validated['amount'],
        );

        $status = $warning === null
            ? IntakeRequest::STATUS_PENDING
            : IntakeRequest::STATUS_NEEDS_REVIEW;

        $notes = trim(implode(' ', array_filter([
            $validated['notes'] ?? null,
            $warning,
        ])));

        $requestModel = IntakeRequest::create([
            'organization_id' => $organizationId,
            'branch_id' => $branch->id,
            'draw_id' => $draw->id,
            'customer_id' => $customer?->id,
            'incoming_message_id' => null,
            'detected_number' => $validated['number'],
            'detected_amount' => $validated['amount'],
            'raw_text' => sprintf('Manual request from number board: %s on %s.', $validated['number'], $draw->name),
            'status' => $status,
            'confirmed_by' => null,
            'confirmed_at' => null,
            'rejected_by' => null,
            'rejected_at' => null,
            'notes' => $notes !== '' ? $notes : null,
        ]);

        $requestModel->events()->create([
            'user_id' => $user->id,
            'event_type' => IntakeRequestEvent::EVENT_CREATED,
            'old_values' => null,
            'new_values' => [
                'status' => $requestModel->status,
                'draw_id' => $requestModel->draw_id,
                'detected_number' => $requestModel->detected_number,
                'detected_amount' => $requestModel->detected_amount,
            ],
            'notes' => 'Created manually from number board.',
            'created_at' => now(),
        ]);

        return redirect()
            ->route('numbers.index', [
                'branch_id' => $branch->id,
                'draw_id' => $draw->id,
            ])
            ->with('status', $warning ?? 'Manual request created.');
    }

    /**
     * @return array{rows: array<int, array<string, mixed>>, summary: array<string, mixed>}
     */
    private function buildNumberRows(?int $organizationId, ?Branch $branch, ?Draw $draw): array
    {
        if ($organizationId === null || $branch === null || $draw === null) {
            return [
                'rows' => collect(range(0, 99))->map(fn (int $number) => [
                    'number' => str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                    'confirmed_amount' => 0,
                    'pending_amount' => 0,
                    'needs_review_amount' => 0,
                    'rejected_amount' => 0,
                    'active_amount' => 0,
                    'max_amount' => null,
                    'available_amount' => null,
                    'percentage_used' => null,
                    'status' => 'available',
                ])->all(),
                'summary' => [
                    'confirmed_amount' => 0,
                    'pending_amount' => 0,
                    'needs_review_amount' => 0,
                    'active_amount' => 0,
                    'near_limit_count' => 0,
                    'over_limit_count' => 0,
                ],
            ];
        }

        $limitRows = $branch->numberLimits()
            ->where('draw_id', $draw->id)
            ->get()
            ->keyBy('number');

        $requestRows = IntakeRequest::query()
            ->where('organization_id', $organizationId)
            ->where('branch_id', $branch->id)
            ->where('draw_id', $draw->id)
            ->whereIn('status', [
                IntakeRequest::STATUS_CONFIRMED,
                IntakeRequest::STATUS_PENDING,
                IntakeRequest::STATUS_NEEDS_REVIEW,
                IntakeRequest::STATUS_REJECTED,
            ])
            ->get()
            ->groupBy('detected_number');

        $rows = [];
        $summary = [
            'confirmed_amount' => 0,
            'pending_amount' => 0,
            'needs_review_amount' => 0,
            'active_amount' => 0,
            'near_limit_count' => 0,
            'over_limit_count' => 0,
        ];

        foreach (range(0, 99) as $number) {
            $numberKey = str_pad((string) $number, 2, '0', STR_PAD_LEFT);
            $numberRequests = $requestRows->get($numberKey, collect());

            $confirmedAmount = (float) $numberRequests->where('status', IntakeRequest::STATUS_CONFIRMED)->sum('detected_amount');
            $pendingAmount = (float) $numberRequests->where('status', IntakeRequest::STATUS_PENDING)->sum('detected_amount');
            $needsReviewAmount = (float) $numberRequests->where('status', IntakeRequest::STATUS_NEEDS_REVIEW)->sum('detected_amount');
            $rejectedAmount = (float) $numberRequests->where('status', IntakeRequest::STATUS_REJECTED)->sum('detected_amount');
            $activeAmount = $confirmedAmount + $pendingAmount + $needsReviewAmount;
            $limit = $limitRows->get($numberKey);
            $limitState = $this->numberLimitService->limitStateFor($limit, $activeAmount);

            $rows[] = [
                'number' => $numberKey,
                'confirmed_amount' => $confirmedAmount,
                'pending_amount' => $pendingAmount,
                'needs_review_amount' => $needsReviewAmount,
                'rejected_amount' => $rejectedAmount,
                'active_amount' => $activeAmount,
                'max_amount' => $limit?->max_amount,
                'available_amount' => $limitState['available_amount'],
                'percentage_used' => $limitState['percentage_used'],
                'status' => $limitState['status'],
            ];

            $summary['confirmed_amount'] += $confirmedAmount;
            $summary['pending_amount'] += $pendingAmount;
            $summary['needs_review_amount'] += $needsReviewAmount;
            $summary['active_amount'] += $activeAmount;

            if (in_array($limitState['status'], ['warning', 'full'], true)) {
                $summary['near_limit_count']++;
            }

            if ($limitState['status'] === 'over_limit') {
                $summary['over_limit_count']++;
            }
        }

        return [
            'rows' => $rows,
            'summary' => $summary,
        ];
    }
}
