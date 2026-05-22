<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Draw;
use App\Models\NumberLimit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class NumberLimitController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        Gate::authorize('viewAny', NumberLimit::class);

        $organizationId = $user?->organization_id;
        $visibleBranchIds = $user?->visibleBranchIds() ?? [];

        $branches = Branch::query()
            ->whereIn('id', $visibleBranchIds)
            ->orderBy('name')
            ->get();

        $draws = Draw::query()
            ->when($organizationId, fn ($query) => $query->where('organization_id', $organizationId), fn ($query) => $query->whereRaw('1 = 0'))
            ->orderBy('draw_time')
            ->get();

        $validated = $request->validate([
            'branch_id' => ['nullable', 'integer', Rule::in($visibleBranchIds ?: [-1])],
            'draw_id' => ['nullable', 'integer', Rule::in($draws->pluck('id')->all() ?: [-1])],
            'number' => ['nullable', 'regex:/^(0[0-9]|[1-9][0-9])$/'],
        ]);

        $limits = NumberLimit::query()
            ->with(['branch', 'draw'])
            ->when($organizationId, fn ($query) => $query->where('organization_id', $organizationId))
            ->when(
                ! empty($visibleBranchIds),
                fn ($query) => $query->whereIn('branch_id', $visibleBranchIds)
            )
            ->when(
                isset($validated['branch_id']),
                fn ($query) => $query->where('branch_id', $validated['branch_id'])
            )
            ->when(
                isset($validated['draw_id']),
                fn ($query) => $query->where('draw_id', $validated['draw_id'])
            )
            ->when(
                isset($validated['number']),
                fn ($query) => $query->where('number', str_pad($validated['number'], 2, '0', STR_PAD_LEFT))
            )
            ->orderBy('branch_id')
            ->orderBy('draw_id')
            ->orderBy('number')
            ->paginate(25)
            ->withQueryString();

        return view('limits.index', [
            'branches' => $branches,
            'draws' => $draws,
            'limits' => $limits,
            'filters' => [
                'branch_id' => $validated['branch_id'] ?? null,
                'draw_id' => $validated['draw_id'] ?? null,
                'number' => $validated['number'] ?? null,
            ],
            'canManageLimits' => $user?->canViewAllBranches() ?? false,
        ]);
    }

    public function create(Request $request): View
    {
        $user = $request->user();
        Gate::authorize('create', NumberLimit::class);

        $branches = $this->visibleBranches($user);
        $draws = $this->organizationDraws($user?->organization_id);
        $numbers = collect(range(0, 99))->map(fn (int $number) => str_pad((string) $number, 2, '0', STR_PAD_LEFT));

        $selectedBranchId = (int) ($request->integer('branch_id') ?: ($branches->first()?->id ?? 0));
        $selectedDrawId = (int) ($request->integer('draw_id') ?: ($draws->first()?->id ?? 0));

        return view('limits.create', [
            'branches' => $branches,
            'draws' => $draws,
            'numbers' => $numbers,
            'selectedBranchId' => $selectedBranchId,
            'selectedDrawId' => $selectedDrawId,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        Gate::authorize('create', NumberLimit::class);

        $mode = $request->string('mode')->toString();
        $organizationId = $user?->organization_id;
        $branches = $this->visibleBranches($user);
        $draws = $this->organizationDraws($organizationId);
        $branchIds = $branches->pluck('id')->all();
        $drawIds = $draws->pluck('id')->all();

        if ($mode === 'bulk') {
            $validated = $request->validate([
                'branch_id' => ['required', 'integer', Rule::in($branchIds ?: [-1])],
                'draw_id' => ['required', 'integer', Rule::in($drawIds ?: [-1])],
                'max_amount' => ['required', 'numeric', 'gt:0'],
                'apply_to' => ['required', Rule::in(['all', 'missing'])],
            ]);

            $branch = $branches->firstWhere('id', $validated['branch_id']);
            $draw = $draws->firstWhere('id', $validated['draw_id']);
            $applied = 0;

            foreach (range(0, 99) as $number) {
                $numberKey = str_pad((string) $number, 2, '0', STR_PAD_LEFT);
                $existing = NumberLimit::query()
                    ->where('organization_id', $organizationId)
                    ->where('branch_id', $branch->id)
                    ->where('draw_id', $draw->id)
                    ->where('number', $numberKey)
                    ->first();

                if ($validated['apply_to'] === 'missing' && $existing !== null) {
                    continue;
                }

                NumberLimit::updateOrCreate(
                    [
                        'organization_id' => $organizationId,
                        'branch_id' => $branch->id,
                        'draw_id' => $draw->id,
                        'number' => $numberKey,
                    ],
                    [
                        'max_amount' => $validated['max_amount'],
                    ]
                );

                $applied++;
            }

            return redirect()
                ->route('limits.index', [
                    'branch_id' => $branch->id,
                    'draw_id' => $draw->id,
                ])
                ->with('status', $validated['apply_to'] === 'missing'
                    ? sprintf('Applied limit to %d numbers without existing limits.', $applied)
                    : 'Applied limit to all numbers 00-99.');
        }

        $validated = $request->validate([
            'branch_id' => ['required', 'integer', Rule::in($branchIds ?: [-1])],
            'draw_id' => ['required', 'integer', Rule::in($drawIds ?: [-1])],
            'number' => [
                'required',
                'regex:/^(0[0-9]|[1-9][0-9])$/',
                Rule::unique('number_limits', 'number')
                    ->where(fn ($query) => $query
                        ->where('organization_id', $organizationId)
                        ->where('branch_id', $request->integer('branch_id'))
                        ->where('draw_id', $request->integer('draw_id'))),
            ],
            'max_amount' => ['required', 'numeric', 'gt:0'],
        ]);

        $branch = $branches->firstWhere('id', $validated['branch_id']);
        $draw = $draws->firstWhere('id', $validated['draw_id']);
        $number = str_pad($validated['number'], 2, '0', STR_PAD_LEFT);

        NumberLimit::create([
            'organization_id' => $organizationId,
            'branch_id' => $branch->id,
            'draw_id' => $draw->id,
            'number' => $number,
            'max_amount' => $validated['max_amount'],
        ]);

        return redirect()
            ->route('limits.index', [
                'branch_id' => $branch->id,
                'draw_id' => $draw->id,
                'number' => $number,
            ])
            ->with('status', sprintf('Limit created for %s.', $number));
    }

    public function edit(NumberLimit $limit, Request $request): View
    {
        $user = $request->user();
        Gate::authorize('update', $limit);

        $branches = $this->visibleBranches($user);
        $draws = $this->organizationDraws($user?->organization_id);
        $numbers = collect(range(0, 99))->map(fn (int $number) => str_pad((string) $number, 2, '0', STR_PAD_LEFT));

        return view('limits.edit', [
            'limit' => $limit,
            'branches' => $branches,
            'draws' => $draws,
            'numbers' => $numbers,
        ]);
    }

    public function update(Request $request, NumberLimit $limit): RedirectResponse
    {
        Gate::authorize('update', $limit);

        $branches = $this->visibleBranches($request->user());
        $draws = $this->organizationDraws($request->user()?->organization_id);
        $branchIds = $branches->pluck('id')->all();
        $drawIds = $draws->pluck('id')->all();

        $validated = $request->validate([
            'branch_id' => ['required', 'integer', Rule::in($branchIds ?: [-1])],
            'draw_id' => ['required', 'integer', Rule::in($drawIds ?: [-1])],
            'number' => [
                'required',
                'regex:/^(0[0-9]|[1-9][0-9])$/',
                Rule::unique('number_limits', 'number')
                    ->where(fn ($query) => $query
                        ->where('organization_id', $limit->organization_id)
                        ->where('branch_id', $request->integer('branch_id'))
                        ->where('draw_id', $request->integer('draw_id')))
                    ->ignore($limit->id),
            ],
            'max_amount' => ['required', 'numeric', 'gt:0'],
        ]);

        $branch = $branches->firstWhere('id', $validated['branch_id']);
        $draw = $draws->firstWhere('id', $validated['draw_id']);

        $limit->update([
            'branch_id' => $branch->id,
            'draw_id' => $draw->id,
            'number' => str_pad($validated['number'], 2, '0', STR_PAD_LEFT),
            'max_amount' => $validated['max_amount'],
        ]);

        return redirect()
            ->route('limits.index', [
                'branch_id' => $limit->branch_id,
                'draw_id' => $limit->draw_id,
                'number' => $limit->number,
            ])
            ->with('status', sprintf('Limit updated for %s.', $limit->number));
    }

    public function destroy(NumberLimit $limit): RedirectResponse
    {
        Gate::authorize('delete', $limit);

        $branchId = $limit->branch_id;
        $drawId = $limit->draw_id;
        $number = $limit->number;

        $limit->delete();

        return redirect()
            ->route('limits.index', [
                'branch_id' => $branchId,
                'draw_id' => $drawId,
                'number' => $number,
            ])
            ->with('status', sprintf('Limit deleted for %s.', $number));
    }

    private function visibleBranches(?\App\Models\User $user)
    {
        return Branch::query()
            ->when($user?->organization_id, fn ($query) => $query->where('organization_id', $user->organization_id), fn ($query) => $query->whereRaw('1 = 0'))
            ->when($user?->isSeller(), fn ($query) => $query->whereKey($user?->branch_id))
            ->orderBy('name')
            ->get();
    }

    private function organizationDraws(?int $organizationId)
    {
        return Draw::query()
            ->when($organizationId, fn ($query) => $query->where('organization_id', $organizationId), fn ($query) => $query->whereRaw('1 = 0'))
            ->orderBy('draw_time')
            ->get();
    }
}
