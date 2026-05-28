<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\DailyOrderClosure;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DailyOrderClosureController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $branches = $this->visibleBranches($user);

        $closures = DailyOrderClosure::query()
            ->with(['branch', 'closedByUser'])
            ->tap(fn (Builder $query) => $this->scopeVisibleClosures($query, $user))
            ->orderByDesc('closure_date')
            ->orderByDesc('closed_at')
            ->paginate(25)
            ->withQueryString();

        return view('daily-order-closures.index', [
            'closures' => $closures,
            'branches' => $branches,
        ]);
    }

    public function create(Request $request): View
    {
        $user = $request->user();
        $branches = $this->visibleBranches($user);

        return view('daily-order-closures.create', [
            'branches' => $branches,
            'selectedBranchId' => (string) $request->string('branch_id'),
            'selectedClosureDate' => $request->string('closure_date')->toString() ?: today()->toDateString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $branchIds = $this->visibleBranchIds($user);

        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'in:' . implode(',', $branchIds ?: [-1])],
            'closure_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $branch = Branch::query()->findOrFail($validated['branch_id']);

        abort_unless($user !== null && $branch->organization_id === $user->organization_id, 404);
        abort_unless(in_array($branch->id, $branchIds, true), 404);

        $closureDate = Carbon::parse($validated['closure_date'])->toDateString();

        $alreadyClosed = DailyOrderClosure::query()
            ->where('branch_id', $branch->id)
            ->whereDate('closure_date', $closureDate)
            ->exists();

        if ($alreadyClosed) {
            throw ValidationException::withMessages([
                'closure_date' => 'This branch already has a closure for the selected date.',
            ]);
        }

        $orders = Order::query()
            ->where('organization_id', $branch->organization_id)
            ->where('branch_id', $branch->id)
            ->whereDate('created_at', $closureDate)
            ->get(['id', 'status']);

        $orderIds = $orders->pluck('id');
        $statusCounts = $orders->countBy('status');

        $closure = DailyOrderClosure::create([
            'organization_id' => $branch->organization_id,
            'branch_id' => $branch->id,
            'closure_date' => $closureDate,
            'pending_review_count' => (int) $statusCounts->get(Order::STATUS_PENDING_REVIEW, 0),
            'confirmed_count' => (int) $statusCounts->get(Order::STATUS_CONFIRMED, 0),
            'preparing_count' => (int) $statusCounts->get(Order::STATUS_PREPARING, 0),
            'ready_for_dispatch_count' => (int) $statusCounts->get(Order::STATUS_READY_FOR_DISPATCH, 0),
            'dispatched_count' => (int) $statusCounts->get(Order::STATUS_DISPATCHED, 0),
            'cancelled_count' => (int) $statusCounts->get(Order::STATUS_CANCELLED, 0),
            'rejected_count' => (int) $statusCounts->get(Order::STATUS_REJECTED, 0),
            'total_orders' => (int) $orders->count(),
            'total_items' => (float) OrderItem::query()
                ->whereIn('order_id', $orderIds)
                ->sum('quantity'),
            'notes' => $validated['notes'] ?? null,
            'closed_by' => $user?->id,
            'closed_at' => now(),
        ]);

        return redirect()
            ->route('daily-order-closures.show', $closure)
            ->with('status', sprintf(
                'Daily closure created for %s on %s.',
                $branch->name,
                $closureDate,
            ));
    }

    public function show(DailyOrderClosure $dailyOrderClosure): View
    {
        $this->ensureVisible($dailyOrderClosure);

        $orders = $this->ordersForClosure($dailyOrderClosure);

        return view('daily-order-closures.show', [
            'closure' => $dailyOrderClosure->load(['branch', 'closedByUser']),
            'orders' => $orders,
            'itemSummaries' => $this->itemSummaries($orders),
        ]);
    }

    public function export(DailyOrderClosure $dailyOrderClosure): StreamedResponse
    {
        $this->ensureVisible($dailyOrderClosure);

        $orders = $this->ordersForClosure($dailyOrderClosure);
        $fileName = sprintf(
            'daily-order-closure-%s-%s.csv',
            $dailyOrderClosure->branch_id,
            $dailyOrderClosure->closure_date?->format('Y-m-d'),
        );

        return response()->streamDownload(function () use ($dailyOrderClosure, $orders): void {
            $output = fopen('php://output', 'w');

            fputcsv($output, [
                'closure_date',
                'branch',
                'order_id',
                'order_status',
                'customer',
                'raw_message_text',
                'item_quantity',
                'item_unit',
                'product_name',
                'item_raw_text',
                'order_notes',
                'created_at',
            ]);

            foreach ($orders as $order) {
                $rows = $order->orderItems->isEmpty()
                    ? [[
                        'quantity' => null,
                        'unit' => null,
                        'product_name' => null,
                        'raw_text' => null,
                    ]]
                    : $order->orderItems->map(static function (OrderItem $item): array {
                        return [
                            'quantity' => $item->quantity,
                            'unit' => $item->unit ?? $item->product?->unit_label,
                            'product_name' => $item->product?->name,
                            'raw_text' => $item->raw_text,
                        ];
                    })->all();

                foreach ($rows as $row) {
                    fputcsv($output, [
                        $dailyOrderClosure->closure_date?->format('Y-m-d'),
                        $dailyOrderClosure->branch?->name,
                        $order->id,
                        $order->status,
                        $order->customer?->name,
                        $order->raw_message_text,
                        $row['quantity'],
                        $row['unit'],
                        $row['product_name'],
                        $row['raw_text'],
                        $order->notes,
                        $order->created_at?->format('Y-m-d H:i:s'),
                    ]);
                }
            }

            fclose($output);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function ensureVisible(DailyOrderClosure $dailyOrderClosure): void
    {
        $user = auth()->user();

        abort_unless($user !== null, 403);

        if ($user->canViewAllBranchesForRead()) {
            abort_unless($user->organization_id !== null && $dailyOrderClosure->organization_id === $user->organization_id, 404);

            return;
        }

        abort_unless(in_array($dailyOrderClosure->branch_id, $user->visibleBranchIds(), true), 404);
    }

    /**
     * @return Collection<int, Branch>
     */
    private function visibleBranches(?User $user): Collection
    {
        $branchIds = $this->visibleBranchIds($user);

        if ($branchIds === []) {
            return collect();
        }

        return Branch::query()
            ->whereIn('id', $branchIds)
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array<int, int>
     */
    private function visibleBranchIds(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        return $user->visibleBranchIds();
    }

    private function scopeVisibleClosures(Builder $query, ?User $user): Builder
    {
        $branchIds = $this->visibleBranchIds($user);

        if ($user?->canViewAllBranchesForRead()) {
            if ($user?->organization_id) {
                return $query->where('organization_id', $user->organization_id);
            }

            return $query->whereRaw('1 = 0');
        }

        if ($branchIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('branch_id', $branchIds);
    }

    /**
     * @return Collection<int, Order>
     */
    private function ordersForClosure(DailyOrderClosure $dailyOrderClosure): Collection
    {
        return Order::query()
            ->with([
                'customer',
                'orderItems' => fn (HasMany $query) => $query->with('product')->orderBy('sort_order')->orderBy('id'),
            ])
            ->where('organization_id', $dailyOrderClosure->organization_id)
            ->where('branch_id', $dailyOrderClosure->branch_id)
            ->whereDate('created_at', $dailyOrderClosure->closure_date)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, array{label: string, product_name: ?string, raw_text: ?string, unit: ?string, quantity: float}>
     */
    private function itemSummaries(Collection $orders): Collection
    {
        return $orders
            ->flatMap(static function (Order $order): Collection {
                return $order->orderItems->map(static function (OrderItem $item): array {
                    $unit = $item->unit ?? $item->product?->unit_label;
                    $rawText = $item->raw_text ?? $item->matched_text;

                    return [
                        'group_key' => $item->product_id !== null
                            ? 'product:' . $item->product_id . ':' . ($unit ?? '')
                            : 'raw:' . md5((string) $rawText . '|' . (string) $unit),
                        'label' => $item->product?->name ?? $rawText ?? '-',
                        'product_name' => $item->product?->name,
                        'raw_text' => $rawText,
                        'unit' => $unit,
                        'quantity' => (float) $item->quantity,
                    ];
                });
            })
            ->groupBy('group_key')
            ->map(static function (Collection $items): array {
                $first = $items->first();

                return [
                    'label' => $first['label'],
                    'product_name' => $first['product_name'],
                    'raw_text' => $first['raw_text'],
                    'unit' => $items->pluck('unit')->filter()->first(),
                    'quantity' => (float) $items->sum('quantity'),
                ];
            })
            ->sortBy('label')
            ->values();
    }
}
