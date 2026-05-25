<x-app-layout>
    <div class="space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <div class="brand-badge bg-brand-primary/10 text-brand-primary">Pending review</div>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight text-brand-navy">Pending review queue</h1>
                <p class="mt-2 text-sm text-slate-600">Focused queue for orders created by the new ingestion flow.</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('orders.index', ['status' => \App\Models\Order::STATUS_PENDING_REVIEW]) }}" class="brand-btn-primary">Open orders index</a>
                <a href="{{ route('dashboard') }}" class="brand-btn-secondary">Dashboard</a>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="brand-card p-5">
                <div class="text-sm font-medium text-slate-500">Pending review</div>
                <div class="mt-2 text-3xl font-semibold text-brand-navy">{{ $pendingReviewCount }}</div>
            </div>
            <div class="brand-card p-5">
                <div class="text-sm font-medium text-slate-500">Confirmed</div>
                <div class="mt-2 text-3xl font-semibold text-brand-navy">{{ $confirmedCount }}</div>
            </div>
            <div class="brand-card p-5">
                <div class="text-sm font-medium text-slate-500">Dispatched</div>
                <div class="mt-2 text-3xl font-semibold text-brand-navy">{{ $dispatchedCount }}</div>
            </div>
        </div>

        <div class="overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Order</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Customer</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Branch</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Created</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Clasificación</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Resumen de items</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Message</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse ($orders as $order)
                        <tr class="bg-amber-50/40">
                            <td class="px-4 py-3 text-sm font-medium text-slate-900">#{{ $order->id }}</td>
                            <td class="px-4 py-3 text-sm text-slate-900">
                                <div class="font-medium">{{ $order->customer?->name ?? '-' }}</div>
                                <div class="text-xs text-slate-500">{{ $order->customer?->phone ?? '-' }}</div>
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $order->branch?->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $order->created_at?->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-3 text-sm">
                                @if (($order->recognized_order_items_count ?? 0) > 0)
                                    <span class="brand-badge bg-emerald-100 text-emerald-800">Clasificado</span>
                                @else
                                    <span class="brand-badge bg-amber-100 text-amber-800">Pendiente de clasificar</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <div class="space-y-2">
                                    @forelse ($order->orderItems as $item)
                                        <div class="rounded-2xl border border-slate-200/80 bg-slate-50 px-3 py-2">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="text-xs font-semibold text-slate-900">
                                                    {{ $item->quantity }}{{ $item->unit ? ' '.$item->unit : '' }}
                                                </span>
                                                @if ($item->product !== null)
                                                    <span class="brand-badge bg-emerald-100 text-emerald-800">Producto reconocido: {{ $item->product->name }}</span>
                                                @else
                                                    <span class="brand-badge bg-slate-100 text-slate-700">Sin producto asociado</span>
                                                @endif
                                            </div>
                                            <div class="mt-1 text-xs text-slate-600">
                                                <span class="font-medium text-slate-500">Texto detectado:</span> {{ $item->raw_text ?? '-' }}
                                            </div>
                                            <div class="mt-1 text-xs text-slate-600">
                                                <span class="font-medium text-slate-500">Coincidencia:</span> {{ $item->matched_text ?? '-' }}
                                            </div>
                                            <div class="mt-1 text-xs text-slate-600">
                                                <span class="font-medium text-slate-500">Confianza:</span> {{ $item->confidence_score !== null ? number_format((float) $item->confidence_score, 2) : '-' }}
                                            </div>
                                        </div>
                                    @empty
                                        <div class="text-xs text-slate-500">Sin items.</div>
                                    @endforelse
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ \Illuminate\Support\Str::limit($order->raw_message_text ?? '-', 70) }}</td>
                            <td class="px-4 py-3 text-sm">
                                <div class="flex flex-wrap items-center gap-2">
                                    <a href="{{ route('orders.show', $order) }}" class="brand-btn-secondary px-3 py-1.5 text-xs">View</a>
                                    <a href="{{ route('orders.edit', $order) }}" class="brand-btn-secondary px-3 py-1.5 text-xs">Edit</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-10 text-center text-sm text-slate-500">No pending review orders found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $orders->links() }}
    </div>
</x-app-layout>
