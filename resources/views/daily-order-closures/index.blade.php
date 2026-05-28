<x-app-layout>
    <div class="space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <div class="brand-badge bg-brand-primary/10 text-brand-primary">Cierres diarios</div>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight text-brand-navy">Cierres diarios</h1>
                <p class="mt-1 text-sm text-slate-600">Cierres por sucursal y fecha del negocio.</p>
            </div>
            <a href="{{ route('daily-order-closures.create') }}" class="brand-btn-primary">
                Nuevo cierre
            </a>
        </div>

        @if ($branches->isEmpty())
            <div class="brand-card p-5 text-sm text-slate-600">
                No hay sucursales visibles para esta cuenta.
            </div>
        @endif

        <div class="overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Fecha</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Sucursal</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Pedidos</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Items</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Despachados</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Pendientes</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Cancelados</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse ($closures as $closure)
                        <tr>
                            <td class="px-4 py-3 text-sm text-slate-900">{{ $closure->closure_date?->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $closure->branch?->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-sm text-slate-900">{{ $closure->total_orders }}</td>
                            <td class="px-4 py-3 text-sm text-slate-900">{{ number_format((float) $closure->total_items, 2, '.', ',') }}</td>
                            <td class="px-4 py-3 text-sm text-slate-900">{{ $closure->dispatched_count }}</td>
                            <td class="px-4 py-3 text-sm text-slate-900">{{ $closure->pending_review_count }}</td>
                            <td class="px-4 py-3 text-sm text-slate-900">{{ $closure->cancelled_count }}</td>
                            <td class="px-4 py-3 text-sm">
                                <div class="flex flex-wrap gap-2">
                                    <a href="{{ route('daily-order-closures.show', $closure) }}" class="brand-btn-secondary px-3 py-1.5 text-xs">Ver</a>
                                    <a href="{{ route('daily-order-closures.export', $closure) }}" class="brand-btn-secondary px-3 py-1.5 text-xs">Exportar CSV</a>
                                </div>
                            </td>
                        </tr>
                        @if ($closure->notes)
                            <tr class="bg-slate-50/50">
                                <td colspan="8" class="px-4 py-3 text-sm text-slate-600">{{ $closure->notes }}</td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-sm text-slate-500">No hay cierres registrados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>
            {{ $closures->links() }}
        </div>
    </div>
</x-app-layout>
