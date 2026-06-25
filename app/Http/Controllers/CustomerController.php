<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerIdentity;
use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CustomerController extends Controller
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
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $organizationId = auth()->user()?->organization_id;
        $search = trim((string) ($filters['search'] ?? ''));

        $customers = Customer::query()
            ->select('customers.*')
            ->selectRaw('COALESCE((SELECT MAX(created_at) FROM orders WHERE orders.customer_id = customers.id), customers.updated_at, customers.created_at) AS latest_order_at')
            ->selectRaw('COALESCE((SELECT MAX(last_seen_at) FROM customer_identities WHERE customer_identities.customer_id = customers.id), customers.updated_at, customers.created_at) AS latest_identity_last_seen_at')
            ->selectRaw('COALESCE((SELECT MAX(created_at) FROM orders WHERE orders.customer_id = customers.id), (SELECT MAX(last_seen_at) FROM customer_identities WHERE customer_identities.customer_id = customers.id), customers.updated_at, customers.created_at) AS latest_activity_at')
            ->withCount(['orders', 'customerIdentities'])
            ->with([
                'customerIdentities' => fn ($query) => $query->orderByDesc('last_seen_at')->orderByDesc('id'),
            ])
            ->when($organizationId !== null, fn (Builder $query) => $query->where('organization_id', $organizationId))
            ->when($organizationId === null, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';

                $query->where(function (Builder $searchQuery) use ($like): void {
                    $searchQuery
                        ->where('customers.name', 'like', $like)
                        ->orWhere('customers.phone', 'like', $like)
                        ->orWhere('customers.external_id', 'like', $like)
                        ->orWhereExists(function ($identityQuery) use ($like): void {
                            $identityQuery
                                ->selectRaw('1')
                                ->from('customer_identities')
                                ->whereColumn('customer_identities.customer_id', 'customers.id')
                                ->where(function (Builder $identityFilters) use ($like): void {
                                    $identityFilters
                                        ->where('provider_username', 'like', $like)
                                        ->orWhere('phone', 'like', $like)
                                        ->orWhere('normalized_phone', 'like', $like)
                                        ->orWhere('external_user_id', 'like', $like);
                                });
                        });
                });
            })
            ->orderByDesc('latest_activity_at')
            ->orderByDesc('customers.id')
            ->paginate(24)
            ->withQueryString();

        $today = today();
        $activeTodayCount = Customer::query()
            ->when($organizationId !== null, fn (Builder $query) => $query->where('organization_id', $organizationId))
            ->when($organizationId === null, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->where(function (Builder $query) use ($today): void {
                $query->whereExists(function ($orderQuery) use ($today): void {
                    $orderQuery
                        ->selectRaw('1')
                        ->from('orders')
                        ->whereColumn('orders.customer_id', 'customers.id')
                        ->whereDate('orders.created_at', $today);
                })->orWhereExists(function ($identityQuery) use ($today): void {
                    $identityQuery
                        ->selectRaw('1')
                        ->from('customer_identities')
                        ->whereColumn('customer_identities.customer_id', 'customers.id')
                        ->whereDate('customer_identities.last_seen_at', $today);
                });
            })
            ->count();

        $multiChannelCustomersCount = Customer::query()
            ->when($organizationId !== null, fn (Builder $query) => $query->where('organization_id', $organizationId))
            ->when($organizationId === null, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->whereExists(function ($identityQuery): void {
                $identityQuery
                    ->selectRaw('1')
                    ->from('customer_identities')
                    ->whereColumn('customer_identities.customer_id', 'customers.id')
                    ->groupBy('customer_identities.customer_id')
                    ->havingRaw('COUNT(DISTINCT provider) > 1');
            })
            ->count();

        return view('customers.index', [
            'customers' => $customers,
            'filters' => $filters,
            'registeredCustomersCount' => (int) Customer::query()
                ->when($organizationId !== null, fn (Builder $query) => $query->where('organization_id', $organizationId))
                ->when($organizationId === null, fn (Builder $query) => $query->whereRaw('1 = 0'))
                ->count(),
            'multiChannelCustomersCount' => $multiChannelCustomersCount,
            'activeTodayCount' => $activeTodayCount,
            'registeredIdentitiesCount' => (int) CustomerIdentity::query()
                ->when($organizationId !== null, fn (Builder $query) => $query->where('organization_id', $organizationId))
                ->when($organizationId === null, fn (Builder $query) => $query->whereRaw('1 = 0'))
                ->count(),
        ]);
    }

    public function show(Customer $customer): View
    {
        $this->ensureVisible($customer);

        $customer->loadCount(['orders', 'customerIdentities']);
        $customer->load([
            'customerIdentities' => fn ($query) => $query->orderByDesc('last_seen_at')->orderByDesc('id'),
        ]);

        $recentOrders = Order::query()
            ->where('customer_id', $customer->id)
            ->with(['possibleDuplicateOf'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        $duplicateOrders = Order::query()
            ->where('customer_id', $customer->id)
            ->whereNotNull('possible_duplicate_of_order_id')
            ->with(['possibleDuplicateOf'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $orderStatusCounts = Order::query()
            ->where('customer_id', $customer->id)
            ->select('status')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $latestOrderAt = $customer->orders()->max('created_at');
        $latestIdentityLastSeenAt = $customer->customerIdentities()->max('last_seen_at');

        return view('customers.show', [
            'customer' => $customer,
            'customerIdentities' => $customer->customerIdentities,
            'recentOrders' => $recentOrders,
            'duplicateOrders' => $duplicateOrders,
            'orderStatusCounts' => $this->fillStatusCounts($orderStatusCounts),
            'latestActivityAt' => $this->latestActivityAt($latestOrderAt, $latestIdentityLastSeenAt),
            'possibleDuplicateOrdersCount' => $duplicateOrders->count(),
            'hasAmbiguousIdentities' => $customer->customerIdentities->contains(function ($identity): bool {
                $metadata = $identity->metadata_json ?? [];

                return is_array($metadata)
                    && (
                        ($metadata['ambiguous'] ?? false)
                        || str_starts_with((string) ($metadata['match_type'] ?? ''), 'ambiguous')
                    );
            }),
        ]);
    }

    private function ensureVisible(Customer $customer): void
    {
        $user = auth()->user();

        if ($user === null) {
            abort(403);
        }

        abort_unless($user->organization_id !== null && $customer->organization_id === $user->organization_id, 404);
    }

    /**
     * @param  Collection<string, int|string>  $counts
     * @return array<string, int>
     */
    private function fillStatusCounts(Collection $counts): array
    {
        $filled = [];

        foreach (self::ORDER_STATUSES as $status) {
            $filled[$status] = (int) $counts->get($status, 0);
        }

        return $filled;
    }

    private function latestActivityAt(mixed $latestOrderAt, mixed $latestIdentityLastSeenAt): ?Carbon
    {
        $orderAt = $latestOrderAt ? Carbon::parse($latestOrderAt) : null;
        $identityAt = $latestIdentityLastSeenAt ? Carbon::parse($latestIdentityLastSeenAt) : null;

        if ($orderAt === null) {
            return $identityAt;
        }

        if ($identityAt === null) {
            return $orderAt;
        }

        return $orderAt->greaterThan($identityAt) ? $orderAt : $identityAt;
    }
}
