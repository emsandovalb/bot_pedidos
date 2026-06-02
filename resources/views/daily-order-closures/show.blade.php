<x-app-layout>
    @php
        $statusCards = [
            ['key' => 'pending_review', 'label' => 'En revisión', 'count' => (int) $closure->pending_review_count, 'class' => 'border-amber-200 bg-amber-50 text-amber-800', 'countClass' => 'text-amber-900'],
            ['key' => 'confirmed', 'label' => 'Confirmados', 'count' => (int) $closure->confirmed_count, 'class' => 'border-[#146EDB]/15 bg-[#EFF6FF] text-[#146EDB]', 'countClass' => 'text-[#0F172A]'],
            ['key' => 'preparing', 'label' => 'En preparación', 'count' => (int) $closure->preparing_count, 'class' => 'border-violet-200 bg-violet-50 text-violet-700', 'countClass' => 'text-violet-800'],
            ['key' => 'ready_for_dispatch', 'label' => 'Listos para despacho', 'count' => (int) $closure->ready_for_dispatch_count, 'class' => 'border-emerald-200 bg-emerald-50 text-emerald-700', 'countClass' => 'text-emerald-800'],
            ['key' => 'dispatched', 'label' => 'Despachados', 'count' => (int) $closure->dispatched_count, 'class' => 'border-green-200 bg-green-50 text-green-700', 'countClass' => 'text-green-800'],
            ['key' => 'cancelled', 'label' => 'Cancelados', 'count' => (int) $closure->cancelled_count, 'class' => 'border-red-200 bg-red-50 text-red-700', 'countClass' => 'text-red-800'],
            ['key' => 'rejected', 'label' => 'Rechazados', 'count' => (int) $closure->rejected_count, 'class' => 'border-slate-200 bg-slate-50 text-slate-700', 'countClass' => 'text-slate-800'],
        ];

        $statusLabels = [
            'pending_review' => 'En revisión',
            'confirmed' => 'Confirmado',
            'preparing' => 'En preparación',
            'ready_for_dispatch' => 'Listo para despacho',
            'dispatched' => 'Despachado',
            'cancelled' => 'Cancelado',
            'rejected' => 'Rechazado',
        ];

        $statusBadgeClasses = [
            'pending_review' => 'border-amber-200 bg-amber-50 text-amber-800',
            'confirmed' => 'border-[#146EDB]/15 bg-[#EFF6FF] text-[#146EDB]',
            'preparing' => 'border-violet-200 bg-violet-50 text-violet-700',
            'ready_for_dispatch' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
            'dispatched' => 'border-green-200 bg-green-50 text-green-700',
            'cancelled' => 'border-red-200 bg-red-50 text-red-700',
            'rejected' => 'border-slate-200 bg-slate-50 text-slate-700',
        ];
    @endphp

    <div class="space-y-6">
        <div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-[linear-gradient(135deg,#F8FAFC_0%,#EEF4FF_52%,#F8FAFC_100%)] shadow-sm">
            <div class="relative px-6 py-8 sm:px-8 lg:px-10">
                <div class="relative flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-2xl">
                        <div class="inline-flex items-center rounded-full border border-[#146EDB]/10 bg-white/80 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-[#146EDB] shadow-sm">
                            Benditio · Cierres diarios
                        </div>
                        <h1 class="mt-4 text-3xl font-semibold tracking-tight text-[#0F172A] sm:text-4xl">Cierre diario</h1>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-[#64748B] sm:text-base">
                            Resumen operativo de la fecha seleccionada.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <a href="{{ route('daily-order-closures.export', $closure) }}" class="inline-flex items-center justify-center rounded-2xl bg-[#0F172A] px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800">
                            Exportar CSV
                        </a>
                        <a href="{{ route('daily-order-closures.index') }}" class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-[#146EDB]/30 hover:text-[#146EDB]">
                            Volver
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-xl font-semibold tracking-tight text-[#0F172A]">Resumen operativo</h2>
                    <p class="mt-1 text-sm text-slate-600">Datos principales del cierre.</p>
                </div>
            </div>

            <dl class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <div class="rounded-3xl bg-slate-50 p-5">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Fecha</dt>
                    <dd class="mt-2 text-lg font-semibold text-[#0F172A]">{{ $closure->closure_date?->format('d/m/Y') ?? '-' }}</dd>
                </div>
                <div class="rounded-3xl bg-slate-50 p-5">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Sucursal</dt>
                    <dd class="mt-2 text-lg font-semibold text-[#0F172A]">{{ $closure->branch?->name ?? '-' }}</dd>
                </div>
                <div class="rounded-3xl bg-slate-50 p-5">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total pedidos</dt>
                    <dd class="mt-2 text-lg font-semibold text-[#0F172A]">{{ number_format((int) $closure->total_orders, 0, '.', ',') }}</dd>
                </div>
                <div class="rounded-3xl bg-slate-50 p-5">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total ítems</dt>
                    <dd class="mt-2 text-lg font-semibold text-[#0F172A]">{{ number_format((float) $closure->total_items, 2, '.', ',') }}</dd>
                </div>
                <div class="rounded-3xl bg-slate-50 p-5">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cerrado por</dt>
                    <dd class="mt-2 text-lg font-semibold text-[#0F172A]">{{ $closure->closedByUser?->name ?? '-' }}</dd>
                </div>
                <div class="rounded-3xl bg-slate-50 p-5">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Fecha de creación</dt>
                    <dd class="mt-2 text-lg font-semibold text-[#0F172A]">{{ $closure->created_at?->format('d/m/Y H:i') ?? '-' }}</dd>
                </div>
            </dl>
        </section>

        <section class="space-y-4">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold tracking-tight text-[#0F172A]">Desglose por estado</h2>
                    <p class="mt-1 text-sm text-slate-600">Distribución operativa del día.</p>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($statusCards as $card)
                    <div class="rounded-3xl border p-5 shadow-sm {{ $card['class'] }}">
                        <div class="text-sm font-medium">{{ $card['label'] }}</div>
                        <div class="mt-3 text-3xl font-semibold tracking-tight {{ $card['countClass'] }}">{{ number_format($card['count'], 0, '.', ',') }}</div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-xl font-semibold tracking-tight text-[#0F172A]">Productos e ítems del cierre</h2>
                    <p class="mt-1 text-sm text-slate-600">Agrupados por producto reconocido o por texto detectado.</p>
                </div>
                <div class="text-sm text-slate-500">{{ $itemSummaries->count() }} grupos</div>
            </div>

            <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @forelse ($itemSummaries as $item)
                    <article class="rounded-3xl border border-slate-200 bg-slate-50 p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    {{ $item['product_name'] ? 'Producto reconocido' : 'Texto detectado' }}
                                </div>
                                <h3 class="mt-2 break-words text-lg font-semibold text-[#0F172A]">{{ $item['product_name'] ?? $item['raw_text'] ?? '-' }}</h3>
                                @if (! $item['product_name'] && $item['raw_text'])
                                    <p class="mt-2 break-words text-sm text-slate-600">{{ $item['raw_text'] }}</p>
                                @endif
                            </div>
                            <div class="rounded-2xl bg-white px-4 py-3 text-right shadow-sm">
                                <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Cantidad</div>
                                <div class="mt-1 text-xl font-semibold text-[#0F172A]">{{ number_format((float) $item['quantity'], 2, '.', ',') }}</div>
                                <div class="text-xs text-slate-500">{{ $item['unit'] ?? 'Sin unidad' }}</div>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="rounded-3xl border border-dashed border-slate-200 bg-slate-50 p-8 text-center text-sm text-slate-500 md:col-span-2 xl:col-span-3">
                        No hay ítems para este cierre.
                    </div>
                @endforelse
            </div>
        </section>

        <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-xl font-semibold tracking-tight text-[#0F172A]">Pedidos incluidos</h2>
                    <p class="mt-1 text-sm text-slate-600">Pedidos vinculados al cierre diario.</p>
                </div>
                <div class="text-sm text-slate-500">{{ $orders->count() }} pedidos</div>
            </div>

            <div class="mt-6 space-y-4">
                @forelse ($orders as $order)
                    @php
                        $orderStatus = $order->status ?? 'pending_review';
                        $orderLabel = $statusLabels[$orderStatus] ?? str_replace('_', ' ', (string) $orderStatus);
                        $orderBadgeClass = $statusBadgeClasses[$orderStatus] ?? 'border-slate-200 bg-slate-50 text-slate-700';
                        $rawPreview = \Illuminate\Support\Str::limit((string) ($order->raw_message_text ?? '-'), 160);
                    @endphp

                    <article class="rounded-3xl border border-slate-200 bg-slate-50 p-5 shadow-sm">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div class="space-y-4">
                                <div class="flex flex-wrap items-center gap-3">
                                    <div class="rounded-2xl bg-white px-4 py-2 text-sm font-semibold text-[#0F172A] shadow-sm">
                                        Pedido #{{ $order->id }}
                                    </div>
                                    <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold {{ $orderBadgeClass }}">
                                        {{ $orderLabel }}
                                    </span>
                                </div>

                                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                                    <div class="rounded-2xl bg-white p-4 shadow-sm">
                                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cliente</div>
                                        <div class="mt-2 text-sm font-medium text-[#0F172A]">{{ $order->customer?->name ?? '-' }}</div>
                                    </div>
                                    <div class="rounded-2xl bg-white p-4 shadow-sm">
                                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Fecha de creación</div>
                                        <div class="mt-2 text-sm font-medium text-[#0F172A]">{{ $order->created_at?->format('d/m/Y H:i') ?? '-' }}</div>
                                    </div>
                                    <div class="rounded-2xl bg-white p-4 shadow-sm md:col-span-2 xl:col-span-1">
                                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Notas</div>
                                        <div class="mt-2 text-sm text-slate-700">{{ $order->notes ?? '-' }}</div>
                                    </div>
                                </div>

                                <div class="rounded-2xl bg-white p-4 shadow-sm">
                                    <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Vista previa del mensaje</div>
                                    <div class="mt-2 text-sm leading-6 text-slate-700">{{ $rawPreview }}</div>
                                </div>
                            </div>

                            <div class="flex shrink-0 flex-wrap gap-3 lg:justify-end">
                                <a href="{{ route('orders.show', $order) }}" class="inline-flex items-center justify-center rounded-2xl bg-[#146EDB] px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700">
                                    Ver pedido
                                </a>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="rounded-3xl border border-dashed border-slate-200 bg-slate-50 p-8 text-center text-sm text-slate-500">
                        No hay pedidos para este cierre.
                    </div>
                @endforelse
            </div>
        </section>
    </div>
</x-app-layout>
