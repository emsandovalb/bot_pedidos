<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\BranchDailyClosure;
use App\Models\IntakeRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BranchDailyClosureController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', BranchDailyClosure::class);

        $user = $request->user();
        $branchIds = $user?->visibleBranchIds() ?? [];
        $users = User::query()
            ->where('organization_id', $user?->organization_id)
            ->orderBy('name')
            ->get();

        $query = BranchDailyClosure::query()
            ->with(['branch', 'closedByUser'])
            ->orderByDesc('closure_date')
            ->orderByDesc('closed_at');

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
            'branch_id' => ['nullable', 'integer', Rule::in($branchIds ?: [-1])],
            'closure_date' => ['nullable', 'date'],
            'closed_by' => ['nullable', 'integer', Rule::in($users->pluck('id')->all() ?: [-1])],
        ]);

        if (! empty($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }

        if (! empty($filters['closure_date'])) {
            $query->whereDate('closure_date', Carbon::parse($filters['closure_date'])->toDateString());
        }

        if (! empty($filters['closed_by'])) {
            $query->where('closed_by', $filters['closed_by']);
        }

        $branches = Branch::query()
            ->whereIn('id', $branchIds)
            ->orderBy('name')
            ->get();

        return view('closures.index', [
            'closures' => $query->paginate(25)->withQueryString(),
            'branches' => $branches,
            'users' => $users,
            'activeFilters' => [
                'branch_id' => $request->string('branch_id')->toString(),
                'closure_date' => $request->string('closure_date')->toString(),
                'closed_by' => $request->string('closed_by')->toString(),
            ],
        ]);
    }

    public function show(BranchDailyClosure $closure): View
    {
        $this->authorize('view', $closure);

        return view('closures.show', [
            'closure' => $closure->load(['organization', 'branch', 'closedByUser']),
            'requests' => $this->requestsForClosure($closure),
        ]);
    }

    public function export(BranchDailyClosure $closure): StreamedResponse
    {
        $this->authorize('view', $closure);

        $requests = $this->requestsForClosure($closure);
        $fileName = sprintf('closure-%s-%s.csv', $closure->branch_id, $closure->closure_date->format('Y-m-d'));

        return response()->streamDownload(function () use ($closure, $requests): void {
            $output = fopen('php://output', 'w');

            fputcsv($output, [
                'closure_id',
                'organization_name',
                'branch_name',
                'closure_date',
                'closed_by',
                'closed_at',
                'total_requests',
                'total_confirmed',
                'total_rejected',
                'total_pending',
                'total_amount_confirmed',
                'closure_notes',
                'request_id',
                'customer_name',
                'customer_phone',
                'detected_number',
                'detected_amount',
                'status',
                'confirmed_at',
                'rejected_at',
                'request_raw_text',
            ]);

            foreach ($requests as $request) {
                fputcsv($output, [
                    $closure->id,
                    $closure->organization?->name,
                    $closure->branch?->name,
                    $closure->closure_date?->format('Y-m-d'),
                    $closure->closedByUser?->name,
                    $closure->closed_at?->format('Y-m-d H:i:s'),
                    $closure->total_requests,
                    $closure->total_confirmed,
                    $closure->total_rejected,
                    $closure->total_pending,
                    $closure->total_amount_confirmed,
                    $closure->notes,
                    $request->id,
                    $request->customer?->name,
                    $request->customer?->phone,
                    $request->detected_number,
                    $request->detected_amount,
                    $request->status,
                    $request->confirmed_at?->format('Y-m-d H:i:s'),
                    $request->rejected_at?->format('Y-m-d H:i:s'),
                    $request->raw_text,
                ]);
            }

            fclose($output);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')],
            'closure_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $branch = Branch::query()->findOrFail($validated['branch_id']);
        $this->authorize('create', [BranchDailyClosure::class, $branch]);

        $closureDate = Carbon::parse($validated['closure_date'])->toDateString();

        $alreadyClosed = BranchDailyClosure::query()
            ->where('branch_id', $branch->id)
            ->whereDate('closure_date', $closureDate)
            ->exists();

        if ($alreadyClosed) {
            throw ValidationException::withMessages([
                'closure_date' => 'This branch already has a closure for the selected date.',
            ]);
        }

        $requestScope = IntakeRequest::query()
            ->where('organization_id', $branch->organization_id)
            ->where('branch_id', $branch->id)
            ->whereDate('created_at', $closureDate);

        $closure = BranchDailyClosure::create([
            'organization_id' => $branch->organization_id,
            'branch_id' => $branch->id,
            'closed_by' => $request->user()->id,
            'closure_date' => $closureDate,
            'total_requests' => (int) (clone $requestScope)->count(),
            'total_confirmed' => (int) (clone $requestScope)->where('status', IntakeRequest::STATUS_CONFIRMED)->count(),
            'total_rejected' => (int) (clone $requestScope)->where('status', IntakeRequest::STATUS_REJECTED)->count(),
            'total_pending' => (int) (clone $requestScope)->where('status', IntakeRequest::STATUS_PENDING)->count(),
            'total_amount_confirmed' => (float) (clone $requestScope)
                ->where('status', IntakeRequest::STATUS_CONFIRMED)
                ->sum('detected_amount'),
            'notes' => $validated['notes'] ?? null,
            'closed_at' => now(),
        ]);

        return redirect()
            ->route('closures.index')
            ->with('status', sprintf(
                'Day closed for %s on %s. Requests: %d, confirmed: %d, rejected: %d, pending: %d.',
                $branch->name,
                $closureDate,
                $closure->total_requests,
                $closure->total_confirmed,
                $closure->total_rejected,
                $closure->total_pending,
            ));
    }

    private function requestsForClosure(BranchDailyClosure $closure)
    {
        return IntakeRequest::query()
            ->with('customer')
            ->where('organization_id', $closure->organization_id)
            ->where('branch_id', $closure->branch_id)
            ->whereDate('created_at', $closure->closure_date)
            ->orderBy('created_at')
            ->get();
    }
}
