<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Draw;
use App\Models\IntakeRequest;
use App\Models\IntakeRequestEvent;
use App\Services\NumberLimitService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Validation\Rule;

class IntakeRequestController extends Controller
{
    public function index(HttpRequest $request): View
    {
        $this->authorize('viewAny', IntakeRequest::class);

        $user = auth()->user();
        $branchIds = $user?->visibleBranchIds() ?? [];
        $query = IntakeRequest::query()
            ->with(['branch', 'draw', 'customer', 'incomingMessage'])
            ->orderByDesc('created_at');

        if ($user?->canViewAllBranchesForRead()) {
            if ($user?->organization_id) {
                $query->where('organization_id', $user->organization_id);
            } else {
                $query->whereRaw('1 = 0');
            }
        } else {
            $query->whereIn('branch_id', $branchIds);
        }

        $filters = $request->validate([
            'status' => ['nullable', Rule::in([
                IntakeRequest::STATUS_PENDING,
                IntakeRequest::STATUS_NEEDS_REVIEW,
                IntakeRequest::STATUS_CONFIRMED,
                IntakeRequest::STATUS_REJECTED,
            ])],
            'branch_id' => ['nullable', 'integer', Rule::in($branchIds ?: [-1])],
            'draw_id' => [
                'nullable',
                'integer',
                Rule::in(
                    Draw::query()
                        ->when($user?->organization_id, fn ($drawQuery) => $drawQuery->where('organization_id', $user->organization_id), fn ($drawQuery) => $drawQuery->whereRaw('1 = 0'))
                        ->pluck('id')
                        ->all() ?: [-1]
                ),
            ],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'customer_phone' => ['nullable', 'string', 'max:255'],
            'detected_number' => ['nullable', 'string', 'max:2'],
        ]);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }

        if (! empty($filters['draw_id'])) {
            $query->where('draw_id', $filters['draw_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (! empty($filters['customer_phone'])) {
            $query->whereHas('customer', function ($customerQuery) use ($filters): void {
                $customerQuery->where('phone', 'like', '%' . $filters['customer_phone'] . '%');
            });
        }

        if (! empty($filters['detected_number'])) {
            $query->where('detected_number', str_pad($filters['detected_number'], 2, '0', STR_PAD_LEFT));
        }

        return view('requests.index', [
            'requests' => $query->paginate(25)->withQueryString(),
            'branches' => Branch::query()->whereIn('id', $branchIds)->orderBy('name')->get(),
            'draws' => Draw::query()
                ->when($user?->organization_id, fn ($drawQuery) => $drawQuery->where('organization_id', $user->organization_id), fn ($drawQuery) => $drawQuery->whereRaw('1 = 0'))
                ->where('status', Draw::STATUS_ACTIVE)
                ->orderBy('draw_time')
                ->get(),
            'filters' => $filters,
            'staleThresholdHours' => 24,
        ]);
    }

    public function show(IntakeRequest $intakeRequest): View
    {
        $this->authorize('view', $intakeRequest);

        return view('requests.show', [
            'request' => $intakeRequest->load(['branch', 'draw', 'customer', 'incomingMessage.response', 'events.user']),
        ]);
    }

    public function edit(IntakeRequest $intakeRequest): View
    {
        $this->authorize('update', $intakeRequest);

        $user = auth()->user();

        return view('requests.edit', [
            'request' => $intakeRequest->load(['branch', 'draw', 'customer', 'incomingMessage.response']),
            'draws' => $user?->organization_id
                ? Draw::query()
                    ->where('organization_id', $user->organization_id)
                    ->where('status', Draw::STATUS_ACTIVE)
                    ->orderBy('draw_time')
                    ->get()
                : collect(),
        ]);
    }

    public function update(HttpRequest $request, IntakeRequest $intakeRequest): RedirectResponse
    {
        $this->authorize('update', $intakeRequest);

        $originalValues = [
            'detected_number' => $intakeRequest->detected_number,
            'detected_amount' => $intakeRequest->detected_amount,
            'draw_id' => $intakeRequest->draw_id,
            'notes' => $intakeRequest->notes,
        ];

        $validated = $request->validate([
            'detected_number' => ['nullable', 'regex:/^(0[0-9]|[1-9][0-9])$/'],
            'detected_amount' => ['nullable', 'numeric', 'gt:0'],
            'draw_id' => [
                'nullable',
                'integer',
                Rule::exists('draws', 'id')->where(function ($query): void {
                    $query->where('organization_id', auth()->user()?->organization_id)
                        ->where('status', Draw::STATUS_ACTIVE);
                }),
            ],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $intakeRequest->update($validated);

        $intakeRequest->events()->create([
            'user_id' => auth()->id(),
            'event_type' => IntakeRequestEvent::EVENT_EDITED,
            'old_values' => $originalValues,
            'new_values' => $validated,
            'notes' => 'Manual edit from request detail page.',
            'created_at' => now(),
        ]);

        return redirect()
            ->route('intake-requests.index')
            ->with('status', 'Request updated successfully.');
    }

    public function confirm(IntakeRequest $intakeRequest): RedirectResponse
    {
        $this->authorize('confirm', $intakeRequest);

        $previousStatus = $intakeRequest->status;
        $limitWarning = $this->limitWarningForRequest($intakeRequest);

        if ($intakeRequest->draw_id === null) {
            $intakeRequest->update([
                'status' => IntakeRequest::STATUS_NEEDS_REVIEW,
                'confirmed_by' => null,
                'confirmed_at' => null,
                'rejected_by' => null,
                'rejected_at' => null,
                'notes' => 'Draw schedule is required. Manual review required.',
            ]);

            $intakeRequest->events()->create([
                'user_id' => auth()->id(),
                'event_type' => IntakeRequestEvent::EVENT_STATUS_CHANGED,
                'old_values' => ['status' => $previousStatus],
                'new_values' => ['status' => IntakeRequest::STATUS_NEEDS_REVIEW],
                'notes' => 'Draw schedule is required. Manual review required.',
                'created_at' => now(),
            ]);

            return redirect()
                ->route('intake-requests.index')
                ->with('status', 'Draw schedule is required before confirmation.');
        }

        if ($limitWarning !== null) {
            $intakeRequest->update([
                'status' => IntakeRequest::STATUS_NEEDS_REVIEW,
                'confirmed_by' => null,
                'confirmed_at' => null,
                'rejected_by' => null,
                'rejected_at' => null,
                'notes' => trim($limitWarning),
            ]);

            $intakeRequest->events()->create([
                'user_id' => auth()->id(),
                'event_type' => IntakeRequestEvent::EVENT_STATUS_CHANGED,
                'old_values' => ['status' => $previousStatus],
                'new_values' => ['status' => IntakeRequest::STATUS_NEEDS_REVIEW],
                'notes' => $limitWarning,
                'created_at' => now(),
            ]);

            return redirect()
                ->route('intake-requests.index')
                ->with('status', $limitWarning);
        }

        $intakeRequest->update([
            'status' => IntakeRequest::STATUS_CONFIRMED,
            'confirmed_by' => auth()->id(),
            'confirmed_at' => now(),
            'rejected_by' => null,
            'rejected_at' => null,
        ]);

        $intakeRequest->events()->create([
            'user_id' => auth()->id(),
            'event_type' => IntakeRequestEvent::EVENT_CONFIRMED,
            'old_values' => ['status' => $previousStatus],
            'new_values' => ['status' => IntakeRequest::STATUS_CONFIRMED],
            'notes' => 'Request confirmed.',
            'created_at' => now(),
        ]);

        return redirect()
            ->route('intake-requests.index')
            ->with('status', 'Request confirmed.');
    }

    public function reject(HttpRequest $request, IntakeRequest $intakeRequest): RedirectResponse
    {
        $this->authorize('reject', $intakeRequest);

        $previousStatus = $intakeRequest->status;
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:5000'],
        ]);

        $intakeRequest->update([
            'status' => IntakeRequest::STATUS_REJECTED,
            'rejected_by' => auth()->id(),
            'rejected_at' => now(),
            'notes' => $validated['rejection_reason'],
            'confirmed_by' => null,
            'confirmed_at' => null,
        ]);

        $intakeRequest->events()->create([
            'user_id' => auth()->id(),
            'event_type' => IntakeRequestEvent::EVENT_REJECTED,
            'old_values' => ['status' => $previousStatus],
            'new_values' => ['status' => IntakeRequest::STATUS_REJECTED],
            'notes' => $validated['rejection_reason'],
            'created_at' => now(),
        ]);

        return redirect()
            ->route('intake-requests.index')
            ->with('status', 'Request rejected.');
    }

    private function limitWarningForRequest(IntakeRequest $intakeRequest): ?string
    {
        if ($intakeRequest->draw_id === null || $intakeRequest->detected_number === null || $intakeRequest->detected_amount === null) {
            return null;
        }

        $draw = Draw::query()->whereKey($intakeRequest->draw_id)->first();

        if (! $draw) {
            return null;
        }

        $limit = app(NumberLimitService::class)->limitFor(
            $intakeRequest->organization,
            $intakeRequest->branch,
            $draw,
            $intakeRequest->detected_number,
        );

        if ($limit === null) {
            return null;
        }

        $activeAmount = app(NumberLimitService::class)->currentActiveAmount(
            $intakeRequest->organization,
            $intakeRequest->branch,
            $draw,
            $intakeRequest->detected_number,
        );

        if ($activeAmount <= (float) $limit->max_amount) {
            return null;
        }

        return sprintf(
            'Limit warning: current active amount for %s on %s %s would exceed max ¢%s.',
            $intakeRequest->detected_number,
            $intakeRequest->branch?->name ?? 'branch',
            $draw->name,
            rtrim(rtrim(number_format((float) $limit->max_amount, 2, '.', ''), '0'), '.'),
        );
    }
}

