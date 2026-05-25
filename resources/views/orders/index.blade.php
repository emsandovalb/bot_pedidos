<x-app-layout>
    <div class="space-y-6">
        @php
            $statusLabels = [
                \App\Models\Order::STATUS_PENDING_REVIEW => 'Pendiente de revisión',
                \App\Models\Order::STATUS_CONFIRMED => 'Confirmado',
                \App\Models\Order::STATUS_PREPARING => 'En preparación',
                \App\Models\Order::STATUS_READY_FOR_DISPATCH => 'Listo para despacho',
                \App\Models\Order::STATUS_DISPATCHED => 'Despachado',
                \App\Models\Order::STATUS_CANCELLED => 'Cancelado',
                \App\Models\Order::STATUS_REJECTED => 'Rechazado',
            ];

            $statusBadgeClasses = [
                \App\Models\Order::STATUS_PENDING_REVIEW => 'bg-amber-100 text-amber-800',
                \App\Models\Order::STATUS_CONFIRMED => 'bg-blue-100 text-blue-800',
                \App\Models\Order::STATUS_PREPARING => 'bg-violet-100 text-violet-800',
                \App\Models\Order::STATUS_READY_FOR_DISPATCH => 'bg-sky-100 text-sky-800',
                \App\Models\Order::STATUS_DISPATCHED => 'bg-emerald-100 text-emerald-800',
                \App\Models\Order::STATUS_CANCELLED => 'bg-slate-100 text-slate-700',
                \App\Models\Order::STATUS_REJECTED => 'bg-red-100 text-red-800',
            ];
        @endphp

        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <div class="brand-badge bg-brand-primary/10 text-brand-primary">Orders</div>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight text-brand-navy">Orders</h1>
                <p class="mt-2 text-sm text-slate-600">Latest orders with status filters and direct access to the review flow.</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('order-reviews.index') }}" class="brand-btn-primary">Pending review queue</a>
                <a href="{{ route('dashboard') }}" class="brand-btn-secondary">Dashboard</a>
            </div>
        </div>

        <div class="brand-card p-5">
            <form method="GET" action="{{ route('orders.index') }}" class="grid gap-4 lg:grid-cols-3 xl:grid-cols-4">
                <div>
                    <label for="status" class="block text-sm font-medium text-slate-700">Status</label>
                    <select id="status" name="status" class="brand-input mt-1 block w-full rounded-xl">
                        <option value="">All statuses</option>
                        @foreach ($statusOptions as $status)
                            <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $statusLabels[$status] ?? str_replace('_', ' ', $status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end gap-2 xl:col-span-3">
                    <button type="submit" class="brand-btn-primary">Filter</button>
                    <a href="{{ route('orders.index') }}" class="brand-btn-secondary">Reset</a>
                </div>
            </form>
        </div>

        <div class="overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Order</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Customer</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Branch</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Source</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Clasificación</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Confidence</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Created</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Raw message</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse ($orders as $order)
                        @php
                            $isPending = $order->status === \App\Models\Order::STATUS_PENDING_REVIEW;
                            $statusClass = match ($order->status) {
                                \App\Models\Order::STATUS_CONFIRMED => 'bg-green-100 text-green-800',
                                \App\Models\Order::STATUS_PREPARING => 'bg-blue-100 text-blue-800',
                                \App\Models\Order::STATUS_READY_FOR_DISPATCH => 'bg-sky-100 text-sky-800',
                                \App\Models\Order::STATUS_DISPATCHED => 'bg-emerald-100 text-emerald-800',
                                \App\Models\Order::STATUS_CANCELLED => 'bg-slate-100 text-slate-700',
                                \App\Models\Order::STATUS_REJECTED => 'bg-red-100 text-red-800',
                                default => 'bg-amber-100 text-amber-800',
                            };
                        @endphp
                        <tr class="{{ $isPending ? 'bg-amber-50/40' : '' }}">
                            <td class="px-4 py-3 text-sm font-medium text-slate-900">#{{ $order->id }}</td>
                            <td class="px-4 py-3 text-sm text-slate-900">
                                <div class="font-medium">{{ $order->customer?->name ?? '-' }}</div>
                                <div class="text-xs text-slate-500">{{ $order->customer?->phone ?? '-' }}</div>
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $order->branch?->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $order->source_channel }}</td>
                            <td class="px-4 py-3 text-sm">
                                <span class="brand-badge {{ $statusBadgeClasses[$order->status] ?? $statusClass }}">{{ $statusLabels[$order->status] ?? str_replace('_', ' ', $order->status) }}</span>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                @if (($order->recognized_order_items_count ?? 0) > 0)
                                    <span class="brand-badge bg-emerald-100 text-emerald-800">Con productos reconocidos</span>
                                @else
                                    <span class="brand-badge bg-amber-100 text-amber-800">Pendiente de clasificar</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $order->parser_confidence !== null ? number_format((float) $order->parser_confidence, 2) : '-' }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $order->created_at?->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ \Illuminate\Support\Str::limit($order->raw_message_text ?? '-', 70) }}</td>
                            <td class="px-4 py-3 text-sm">
                                <a href="{{ route('orders.show', $order) }}" class="brand-btn-secondary px-3 py-1.5 text-xs">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-4 py-10 text-center text-sm text-slate-500">No orders found for this account.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $orders->links() }}
    </div>
</x-app-layout>
