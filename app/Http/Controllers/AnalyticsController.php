<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function index(): View
    {
        $user = request()->user();
        $organizationId = $user?->organization_id;
        $branchIds = $user?->visibleBranchIds() ?? [];

        $orderScope = function ($query) use ($user, $organizationId, $branchIds) {
            if ($user?->canViewAllBranchesForRead()) {
                return $organizationId ? $query->where('organization_id', $organizationId) : $query->whereRaw('1 = 0');
            }

            return $branchIds ? $query->whereIn('branch_id', $branchIds) : $query->whereRaw('1 = 0');
        };

        $orderIdsSubquery = function ($query) use ($orderScope): void {
            $query->from('orders')->select('id');
            $orderScope($query);
        };

        $today = today();
        $ordersQuery = Order::query()->tap($orderScope);
        $statusLabels = $this->orderStatusLabels();

        $classifiedProducts = DB::table('order_items')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->whereIn('order_items.order_id', $orderIdsSubquery)
            ->whereNotNull('order_items.product_id')
            ->selectRaw('order_items.product_id as product_id')
            ->selectRaw("COALESCE(products.name, 'Producto eliminado') as label")
            ->selectRaw('SUM(order_items.quantity) as total_quantity')
            ->selectRaw('COUNT(*) as line_count')
            ->groupBy('order_items.product_id', 'products.name')
            ->get()
            ->map(function ($row) {
                return (object) [
                    'label' => (string) $row->label,
                    'total_quantity' => (float) $row->total_quantity,
                    'line_count' => (int) $row->line_count,
                ];
            });

        $rawRequestedProducts = DB::table('order_items')
            ->whereIn('order_items.order_id', $orderIdsSubquery)
            ->whereNull('order_items.product_id')
            ->selectRaw("COALESCE(NULLIF(order_items.raw_text, ''), 'Sin texto') as label")
            ->selectRaw('SUM(order_items.quantity) as total_quantity')
            ->selectRaw('COUNT(*) as line_count')
            ->groupBy('order_items.raw_text')
            ->get()
            ->map(function ($row) {
                return (object) [
                    'label' => (string) $row->label,
                    'total_quantity' => (float) $row->total_quantity,
                    'line_count' => (int) $row->line_count,
                ];
            });

        $topRequestedProducts = $classifiedProducts
            ->concat($rawRequestedProducts)
            ->sort(function ($left, $right) {
                return [$right->total_quantity, $right->line_count, $left->label] <=> [$left->total_quantity, $left->line_count, $right->label];
            })
            ->take(5)
            ->values();

        $frequentCustomers = DB::table('orders')
            ->join('customers', 'customers.id', '=', 'orders.customer_id')
            ->whereIn('orders.id', $orderIdsSubquery)
            ->selectRaw('orders.customer_id')
            ->selectRaw('COALESCE(customers.name, customers.phone, customers.external_id, "Cliente sin nombre") as label')
            ->selectRaw('customers.name')
            ->selectRaw('customers.phone')
            ->selectRaw('customers.external_id')
            ->selectRaw('COUNT(*) as total_orders')
            ->selectRaw('MAX(orders.created_at) as last_order_at')
            ->groupBy('orders.customer_id', 'customers.name', 'customers.phone', 'customers.external_id')
            ->orderByDesc('total_orders')
            ->orderByDesc('last_order_at')
            ->limit(5)
            ->get()
            ->map(function ($row) {
                return (object) [
                    'customer_id' => (int) $row->customer_id,
                    'label' => (string) $row->label,
                    'name' => $row->name,
                    'phone' => $row->phone,
                    'external_id' => $row->external_id,
                    'total_orders' => (int) $row->total_orders,
                    'last_order_at' => $row->last_order_at ? Carbon::parse($row->last_order_at) : null,
                ];
            });

        $totalOrderItems = DB::table('order_items')
            ->whereIn('order_items.order_id', $orderIdsSubquery)
            ->count();

        $classifiedOrderItems = DB::table('order_items')
            ->whereIn('order_items.order_id', $orderIdsSubquery)
            ->whereNotNull('product_id')
            ->count();

        $recentOrders = Order::query()
            ->tap($orderScope)
            ->with(['customer', 'branch'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $statusCounts = [];
        foreach (array_keys($statusLabels) as $status) {
            $statusCounts[$status] = (clone $ordersQuery)->where('status', $status)->count();
        }

        return view('analytics.index', [
            'ordersTodayCount' => (clone $ordersQuery)
                ->whereDate('created_at', $today)
                ->count(),
            'ordersLast7DaysCount' => (clone $ordersQuery)
                ->whereDate('created_at', '>=', $today->copy()->subDays(6))
                ->count(),
            'ordersThisMonthCount' => (clone $ordersQuery)
                ->whereYear('created_at', $today->year)
                ->whereMonth('created_at', $today->month)
                ->count(),
            'dispatchedThisMonthCount' => (clone $ordersQuery)
                ->where('status', Order::STATUS_DISPATCHED)
                ->whereNotNull('dispatched_at')
                ->whereYear('dispatched_at', $today->year)
                ->whereMonth('dispatched_at', $today->month)
                ->count(),
            'pendingReviewCount' => $statusCounts[Order::STATUS_PENDING_REVIEW] ?? 0,
            'statusCounts' => $statusCounts,
            'statusLabels' => $statusLabels,
            'topRequestedProducts' => $topRequestedProducts,
            'frequentCustomers' => $frequentCustomers,
            'totalOrderItems' => $totalOrderItems,
            'classifiedOrderItems' => $classifiedOrderItems,
            'unclassifiedOrderItems' => max(0, $totalOrderItems - $classifiedOrderItems),
            'classificationPercentage' => $totalOrderItems > 0
                ? (int) round(($classifiedOrderItems / $totalOrderItems) * 100)
                : 0,
            'recentOrders' => $recentOrders,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function orderStatusLabels(): array
    {
        return [
            Order::STATUS_PENDING_REVIEW => 'Pendiente de revisión',
            Order::STATUS_CONFIRMED => 'Confirmado',
            Order::STATUS_PREPARING => 'En preparación',
            Order::STATUS_READY_FOR_DISPATCH => 'Listo para despacho',
            Order::STATUS_DISPATCHED => 'Despachado',
            Order::STATUS_CANCELLED => 'Cancelado',
            Order::STATUS_REJECTED => 'Rechazado',
        ];
    }
}
