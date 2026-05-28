<x-app-layout>
    <div class="space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <div class="brand-badge bg-brand-primary/10 text-brand-primary">Cierres diarios</div>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight text-brand-navy">Detalle del cierre</h1>
                <p class="mt-1 text-sm text-slate-600">{{ $closure->branch?->name ?? '-' }} · {{ $closure->closure_date?->format('Y-m-d') }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('daily-order-closures.export', $closure) }}" class="brand-btn-primary">Exportar CSV</a>
                <a href="{{ route('daily-order-closures.index') }}" class="brand-btn-secondary">Volver</a>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="brand-card p-5">
                <div class="text-sm text-slate-500">Pedidos totales</div>
                <div class="mt-2 text-3xl font-semibold text-brand-navy">{{ $closure->total_orders }}</div>
            </div>
            <div class="brand-card p-5">
                <div class="text-sm text-slate-500">Items totales</div>
                <div class="mt-2 text-3xl font-semibold text-brand-navy">{{ number_format((float) $closure->total_items, 2, '.', ',') }}</div>
            </div>
            <div class="brand-card p-5">
                <div class="text-sm text-slate-500">Despachados</div>
                <div class="mt-2 text-3xl font-semibold text-brand-navy">{{ $closure->dispatched_count }}</div>
            </div>
            <div class="brand-card p-5">
                <div class="text-sm text-slate-500">Pendientes de revision</div>
                <div class="mt-2 text-3xl font-semibold text-brand-navy">{{ $closure->pending_review_count }}</div>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div class="brand-card p-5">
                <div class="text-sm text-slate-500">Confirmados</div>
                <div class="mt-2 text-2xl font-semibold text-brand-navy">{{ $closure->confirmed_count }}</div>
            </div>
            <div class="brand-card p-5">
                <div class="text-sm text-slate-500">En preparacion</div>
                <div class="mt-2 text-2xl font-semibold text-brand-navy">{{ $closure->preparing_count }}</div>
            </div>
            <div class="brand-card p-5">
                <div class="text-sm text-slate-500">Listos para despacho</div>
                <div class="mt-2 text-2xl font-semibold text-brand-navy">{{ $closure->ready_for_dispatch_count }}</div>
            </div>
            <div class="brand-card p-5">
                <div class="text-sm text-slate-500">Cancelados</div>
                <div class="mt-2 text-2xl font-semibold text-brand-navy">{{ $closure->cancelled_count }}</div>
            </div>
            <div class="brand-card p-5">
                <div class="text-sm text-slate-500">Rechazados</div>
                <div class="mt-2 text-2xl font-semibold text-brand-navy">{{ $closure->rejected_count }}</div>
            </div>
            <div class="brand-card p-5">
                <div class="text-sm text-slate-500">Cerrado por</div>
                <div class="mt-2 text-2xl font-semibold text-brand-navy">{{ $closure->closedByUser?->name ?? '-' }}</div>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <div class="brand-card p-5">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-base font-semibold text-brand-navy">Resumen de items</h2>
                    <span class="text-xs text-slate-500">Agrupado por producto o texto crudo</span>
                </div>
                <div class="mt-4 overflow-hidden rounded-2xl border border-slate-200/80">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-slate-500">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium">Producto / texto</th>
                                <th class="px-3 py-2 text-left font-medium">Unidad</th>
                                <th class="px-3 py-2 text-left font-medium">Cantidad</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white">
                            @forelse ($itemSummaries as $item)
                                <tr>
                                    <td class="px-3 py-2">
                                        <div class="font-medium text-slate-900">{{ $item['label'] }}</div>
                                        @if ($item['product_name'] && $item['raw_text'] !== $item['product_name'])
                                            <div class="text-xs text-slate-500">{{ $item['raw_text'] }}</div>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-slate-600">{{ $item['unit'] ?? '-' }}</td>
                                    <td class="px-3 py-2 text-slate-900">{{ number_format((float) $item['quantity'], 2, '.', ',') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-3 py-4 text-center text-slate-500">No hay items para este cierre.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="brand-card p-5">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-base font-semibold text-brand-navy">Detalles</h2>
                    <span class="text-xs text-slate-500">Metadatos del cierre</span>
                </div>
                <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm text-slate-500">Sucursal</dt>
                        <dd class="mt-1 font-medium text-slate-900">{{ $closure->branch?->name ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-slate-500">Fecha</dt>
                        <dd class="mt-1 font-medium text-slate-900">{{ $closure->closure_date?->format('Y-m-d') }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-slate-500">Cerrado por</dt>
                        <dd class="mt-1 font-medium text-slate-900">{{ $closure->closedByUser?->name ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-slate-500">Cerrado a las</dt>
                        <dd class="mt-1 font-medium text-slate-900">{{ $closure->closed_at?->format('Y-m-d H:i:s') ?? '-' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-sm text-slate-500">Notas</dt>
                        <dd class="mt-1 whitespace-pre-wrap text-sm text-slate-900">{{ $closure->notes ?? '-' }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        <div class="brand-card p-5">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-base font-semibold text-brand-navy">Pedidos vinculados</h2>
                <span class="text-xs text-slate-500">{{ $orders->count() }} pedidos</span>
            </div>
            <div class="mt-4 overflow-hidden rounded-2xl border border-slate-200/80">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-slate-500">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium">Pedido</th>
                            <th class="px-3 py-2 text-left font-medium">Estado</th>
                            <th class="px-3 py-2 text-left font-medium">Cliente</th>
                            <th class="px-3 py-2 text-left font-medium">Mensaje</th>
                            <th class="px-3 py-2 text-left font-medium">Notas</th>
                            <th class="px-3 py-2 text-left font-medium">Creado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        @forelse ($orders as $order)
                            <tr>
                                <td class="px-3 py-2 font-medium text-slate-900">{{ $order->id }}</td>
                                <td class="px-3 py-2 text-slate-600">{{ $order->status }}</td>
                                <td class="px-3 py-2 text-slate-600">{{ $order->customer?->name ?? '-' }}</td>
                                <td class="px-3 py-2 text-slate-600">{{ $order->raw_message_text }}</td>
                                <td class="px-3 py-2 text-slate-600">{{ $order->notes ?? '-' }}</td>
                                <td class="px-3 py-2 text-slate-600">{{ $order->created_at?->format('Y-m-d H:i:s') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-3 py-4 text-center text-slate-500">No hay pedidos para este cierre.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
