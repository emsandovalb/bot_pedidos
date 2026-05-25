<?php

namespace App\Http\Controllers;

use App\Models\ManualReview;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    private const ORDER_STATUSES = [
        Order::STATUS_PENDING_REVIEW,
        Order::STATUS_CONFIRMED,
        Order::STATUS_PREPARING,
        Order::STATUS_READY_FOR_DISPATCH,
        Order::STATUS_DISPATCHED,
        Order::STATUS_CANCELLED,
        Order::STATUS_REJECTED,
    ];

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(self::ORDER_STATUSES)],
        ]);

        $query = $this->scopedQuery()
            ->with(['branch', 'customer'])
            ->withCount([
                'orderItems as recognized_order_items_count' => fn ($query) => $query->whereNotNull('product_id'),
            ])
            ->orderByDesc('created_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return view('orders.index', [
            'orders' => $query->paginate(25)->withQueryString(),
            'filters' => $filters,
            'statusOptions' => self::ORDER_STATUSES,
        ]);
    }

    public function show(Order $order): View
    {
        $this->ensureVisible($order);

        return view('orders.show', [
            'order' => $order->load([
                'branch',
                'customer',
                'orderItems' => fn ($query) => $query->orderBy('sort_order'),
                'orderItems.product',
                'orderStatusHistories' => fn ($query) => $query->orderBy('created_at'),
                'orderStatusHistories.changedByUser',
                'manualReviews' => fn ($query) => $query->orderByDesc('reviewed_at'),
                'manualReviews.reviewedByUser',
            ]),
        ]);
    }

    public function edit(Order $order): View
    {
        $this->ensureVisible($order);

        return view('orders.edit', [
            'order' => $order->load([
                'branch',
                'customer',
                'orderItems' => fn ($query) => $query->orderBy('sort_order'),
                'orderItems.product',
            ]),
        ]);
    }

    public function update(Request $request, Order $order): RedirectResponse
    {
        $this->ensureVisible($order);

        $order->load([
            'orderItems' => fn ($query) => $query->orderBy('sort_order'),
        ]);
        $beforeSnapshot = $this->reviewSnapshot($order);

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit' => ['nullable', 'string', 'max:255'],
            'items.*.raw_text' => ['nullable', 'string', 'max:5000'],
            'items.*.notes' => ['nullable', 'string', 'max:5000'],
        ]);

        DB::transaction(function () use ($order, $validated, $beforeSnapshot): void {
            $order->update([
                'notes' => $validated['notes'] ?? null,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);

            foreach ($validated['items'] as $itemId => $itemData) {
                $item = $order->orderItems->firstWhere('id', (int) $itemId);

                if ($item === null) {
                    continue;
                }

                $item->update([
                    'quantity' => $itemData['quantity'],
                    'unit' => $itemData['unit'] ?? null,
                    'raw_text' => $itemData['raw_text'] ?? null,
                    'notes' => $itemData['notes'] ?? null,
                ]);
            }

            $order->refresh()->load('orderItems');
            $afterSnapshot = $this->reviewSnapshot($order);

            if ($beforeSnapshot !== $afterSnapshot) {
                ManualReview::create([
                    'order_id' => $order->id,
                    'reviewed_by_user_id' => auth()->id(),
                    'decision' => 'edited',
                    'reason' => 'Manual edit from order review page.',
                    'before_json' => $beforeSnapshot,
                    'after_json' => $afterSnapshot,
                    'suggested_changes_json' => [],
                    'reviewed_at' => now(),
                ]);
            }
        });

        return redirect()
            ->route('orders.show', $order)
            ->with('status', 'Order updated successfully.');
    }

    public function confirm(Order $order): RedirectResponse
    {
        $this->ensureVisible($order);
        $this->assertTransitionAllowed($order, [Order::STATUS_PENDING_REVIEW], 'confirm');

        $this->transitionOrder(
            order: $order,
            toStatus: Order::STATUS_CONFIRMED,
            updates: [
                'confirmed_by' => auth()->id(),
                'confirmed_at' => now(),
                'rejected_at' => null,
                'cancelled_at' => null,
            ],
            reason: 'Order confirmed from admin UI.',
        );

        return redirect()
            ->route('orders.show', $order)
            ->with('status', 'Order confirmed.');
    }

    public function reject(Order $order): RedirectResponse
    {
        $this->ensureVisible($order);
        $this->assertTransitionAllowed($order, [Order::STATUS_PENDING_REVIEW], 'reject');

        $this->transitionOrder(
            order: $order,
            toStatus: Order::STATUS_REJECTED,
            updates: [
                'rejected_at' => now(),
                'confirmed_by' => null,
                'confirmed_at' => null,
                'cancelled_at' => null,
            ],
            reason: 'Order rejected from admin UI.',
        );

        return redirect()
            ->route('orders.show', $order)
            ->with('status', 'Order rejected.');
    }

    public function cancel(Order $order): RedirectResponse
    {
        $this->ensureVisible($order);
        $this->assertTransitionAllowed(
            $order,
            [
                Order::STATUS_PENDING_REVIEW,
                Order::STATUS_CONFIRMED,
                Order::STATUS_PREPARING,
                Order::STATUS_READY_FOR_DISPATCH,
            ],
            'cancel',
        );

        $this->transitionOrder(
            order: $order,
            toStatus: Order::STATUS_CANCELLED,
            updates: [
                'cancelled_at' => $order->cancelled_at ?? now(),
                'confirmed_by' => $order->status === Order::STATUS_PENDING_REVIEW ? null : $order->confirmed_by,
                'confirmed_at' => $order->status === Order::STATUS_PENDING_REVIEW ? null : $order->confirmed_at,
                'preparing_at' => $order->preparing_at,
                'ready_for_dispatch_at' => $order->ready_for_dispatch_at,
                'rejected_at' => null,
            ],
            reason: 'Order cancelled from admin UI.',
        );

        return redirect()
            ->route('orders.show', $order)
            ->with('status', 'Order cancelled.');
    }

    public function prepare(Order $order): RedirectResponse
    {
        $this->ensureVisible($order);
        $this->assertTransitionAllowed($order, [Order::STATUS_CONFIRMED], 'prepare');

        $this->transitionOrder(
            order: $order,
            toStatus: Order::STATUS_PREPARING,
            updates: [
                'preparing_at' => now(),
                'ready_for_dispatch_at' => null,
                'dispatched_at' => null,
            ],
            reason: 'Order moved to preparing from admin UI.',
        );

        return redirect()
            ->route('orders.show', $order)
            ->with('status', 'Order moved to preparing.');
    }

    public function readyForDispatch(Order $order): RedirectResponse
    {
        $this->ensureVisible($order);
        $this->assertTransitionAllowed($order, [Order::STATUS_PREPARING], 'ready for dispatch');

        $this->transitionOrder(
            order: $order,
            toStatus: Order::STATUS_READY_FOR_DISPATCH,
            updates: [
                'ready_for_dispatch_at' => now(),
                'dispatched_at' => null,
            ],
            reason: 'Order marked ready for dispatch from admin UI.',
        );

        return redirect()
            ->route('orders.show', $order)
            ->with('status', 'Order marked ready for dispatch.');
    }

    public function dispatch(Order $order): RedirectResponse
    {
        $this->ensureVisible($order);
        $this->assertTransitionAllowed($order, [Order::STATUS_READY_FOR_DISPATCH], 'dispatch');

        $this->transitionOrder(
            order: $order,
            toStatus: Order::STATUS_DISPATCHED,
            updates: [
                'dispatched_at' => now(),
            ],
            reason: 'Order dispatched from admin UI.',
        );

        return redirect()
            ->route('orders.show', $order)
            ->with('status', 'Order marked dispatched.');
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

    private function ensureVisible(Order $order): void
    {
        $user = auth()->user();

        if ($user === null) {
            abort(403);
        }

        if ($user->canViewAllBranchesForRead()) {
            abort_unless($user->organization_id !== null && $order->organization_id === $user->organization_id, 404);

            return;
        }

        abort_unless(in_array($order->branch_id, $user->visibleBranchIds(), true), 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function reviewSnapshot(Order $order): array
    {
        return [
            'notes' => $order->notes,
            'items' => $order->orderItems
                ->sortBy('sort_order')
                ->values()
                ->map(static function ($item): array {
                    return [
                        'id' => $item->id,
                        'quantity' => (float) $item->quantity,
                        'unit' => $item->unit,
                        'raw_text' => $item->raw_text,
                        'notes' => $item->notes,
                    ];
                })
                ->all(),
        ];
    }

    /**
     * @param  array<int, string>  $allowedStatuses
     */
    private function assertTransitionAllowed(Order $order, array $allowedStatuses, string $action): void
    {
        abort_unless(in_array($order->status, $allowedStatuses, true), 422, "The order cannot be {$action} from its current status.");
    }

    /**
     * @param  array<string, mixed>  $updates
     */
    private function transitionOrder(Order $order, string $toStatus, array $updates, string $reason): void
    {
        DB::transaction(function () use ($order, $toStatus, $updates, $reason): void {
            $fromStatus = $order->status;

            $order->update(array_merge([
                'status' => $toStatus,
            ], $updates));

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'changed_by_user_id' => auth()->id(),
                'changed_via' => 'admin_ui',
                'reason' => $reason,
                'metadata_json' => [
                    'action' => $toStatus,
                    'previous_status' => $fromStatus,
                ],
                'created_at' => now(),
            ]);
        });
    }
}
