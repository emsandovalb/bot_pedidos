<x-app-layout>
    <div class="space-y-8">
        <section class="overflow-hidden rounded-3xl border border-slate-200/80 bg-gradient-to-br from-white via-slate-50 to-emerald-50 shadow-[0_24px_80px_-40px_rgba(15,23,42,0.35)]">
            <div class="px-6 py-8 sm:px-8 lg:px-10">
                <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-3xl">
                        <div class="inline-flex items-center rounded-full border border-brand-primary/10 bg-brand-primary/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.22em] text-brand-primary">
                            Cierres diarios
                        </div>
                        <h1 class="mt-4 text-3xl font-semibold tracking-tight text-brand-navy sm:text-4xl">
                            Cierre diario
                        </h1>
                        <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600 sm:text-base">
                            {{ $closure->branch?->name ?? 'Sucursal no disponible' }} · {{ $closure->closure_date?->format('d/m/Y') ?? '-' }}
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <a href="{{ route('daily-order-closures.export', $closure) }}" class="inline-flex items-center justify-center rounded-xl bg-brand-primary px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-brand-primary/20 transition hover:-translate-y-0.5 hover:bg-brand-primary/90">
                            Exportar CSV
                        </a>
                        <a href="{{ route('daily-order-closures.index') }}" class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-brand-primary/30 hover:text-brand-primary">
                            Volver
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            <div class="rounded-3xl border border-slate-200/80 bg-white p-5 shadow-[0_18px_55px_-35px_rgba(15,23,42,0.45)]">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Fecha</div>
                <div class="mt-2 text-xl font-semibold text-brand-navy">{{ $closure->closure_date?->format('d/m/Y') ?? '-' }}</div>
            </div>
            <div class="rounded-3xl border border-slate-200/80 bg-white p-5 shadow-[0_18px_55px_-35px_rgba(15,23,42,0.45)]">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Sucursal</div>
                <div class="mt-2 text-xl font-semibold text-brand-navy">{{ $closure->branch?->name ?? '-' }}</div>
            </div>
            <div class="rounded-3xl border border-slate-200/80 bg-white p-5 shadow-[0_18px_55px_-35px_rgba(15,23,42,0.45)]">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Total pedidos</div>
                <div class="mt-2 text-xl font-semibold text-brand-navy">{{ $closure->total_orders ?? 0 }}</div>
            </div>
            <div class="rounded-3xl border border-slate-200/80 bg-white p-5 shadow-[0_18px_55px_-35px_rgba(15,23,42,0.45)]">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Total ítems</div>
                <div class="mt-2 text-xl font-semibold text-brand-navy">{{ number_format((float) ($closure->total_items ?? 0), 2, '.', ',') }}</div>
            </div>
            <div class="rounded-3xl border border-slate-200/80 bg-white p-5 shadow-[0_18px_55px_-35px_rgba(15,23,42,0.45)]">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Cerrado por</div>
                <div class="mt-2 text-xl font-semibold text-brand-navy">{{ $closure->closedByUser?->name ?? '-' }}</div>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
            <div class="rounded-3xl border border-slate-200/80 bg-gradient-to-br from-white to-sky-50 p-5 shadow-[0_18px_55px_-35px_rgba(15,23,42,0.45)]">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Pendientes</div>
                <div class="mt-2 text-3xl font-semibold text-brand-navy">{{ $closure->pending_review_count ?? 0 }}</div>
            </div>
            <div class="rounded-3xl border border-slate-200/80 bg-gradient-to-br from-white to-emerald-50 p-5 shadow-[0_18px_55px_-35px_rgba(15,23,42,0.45)]">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Confirmados</div>
                <div class="mt-2 text-3xl font-semibold text-brand-navy">{{ $closure->confirmed_count ?? 0 }}</div>
            </div>
            <div class="rounded-3xl border border-slate-200/80 bg-gradient-to-br from-white to-amber-50 p-5 shadow-[0_18px_55px_-35px_rgba(15,23,42,0.45)]">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">En preparación</div>
                <div class="mt-2 text-3xl font-semibold text-brand-navy">{{ $closure->preparing_count ?? 0 }}</div>
            </div>
            <div class="rounded-3xl border border-slate-200/80 bg-gradient-to-br from-white to-cyan-50 p-5 shadow-[0_18px_55px_-35px_rgba(15,23,42,0.45)]">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Listos para despacho</div>
                <div class="mt-2 text-3xl font-semibold text-brand-navy">{{ $closure->ready_for_dispatch_count ?? 0 }}</div>
            </div>
            <div class="rounded-3xl border border-slate-200/80 bg-gradient-to-br from-white to-rose-50 p-5 shadow-[0_18px_55px_-35px_rgba(15,23,42,0.45)]">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Despachados</div>
                <div class="mt-2 text-3xl font-semibold text-brand-navy">{{ $closure->dispatched_count ?? 0 }}</div>
            </div>
            <div class="rounded-3xl border border-slate-200/80 bg-gradient-to-br from-white to-slate-100 p-5 shadow-[0_18px_55px_-35px_rgba(15,23,42,0.45)]">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Cancelados</div>
                <div class="mt-2 text-3xl font-semibold text-brand-navy">{{ $closure->cancelled_count ?? 0 }}</div>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)]">
            <div class="overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-[0_18px_55px_-35px_rgba(15,23,42,0.45)]">
                <div class="border-b border-slate-200/70 px-6 py-5">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-lg font-semibold text-brand-navy">Resumen de ítems</h2>
                        <span class="text-xs text-slate-500">Agrupado por producto o texto crudo</span>
                    </div>
                </div>

                <div class="p-6">
                    @if ($itemSummaries->isNotEmpty())
                        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            @foreach ($itemSummaries as $item)
                                <article class="rounded-2xl border border-slate-200 bg-slate-50/80 p-4">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <h3 class="font-semibold text-brand-navy">{{ $item['label'] }}</h3>
                                            @if (($item['product_name'] ?? null) && ($item['raw_text'] ?? null) !== ($item['product_name'] ?? null))
                                                <p class="mt-1 text-xs text-slate-500">{{ $item['raw_text'] }}</p>
                                            @endif
                                        </div>
                                        <span class="rounded-full border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-600">
                                            {{ $item['unit'] ?? 'unidad' }}
                                        </span>
                                    </div>

                                    <div class="mt-4 flex items-end justify-between gap-3">
                                        <div>
                                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Cantidad</div>
                                            <div class="mt-1 text-2xl font-semibold text-brand-navy">{{ number_format((float) ($item['quantity'] ?? 0), 2, '.', ',') }}</div>
                                        </div>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @else
                        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50/60 px-5 py-8 text-center text-sm text-slate-500">
                            No hay items para este cierre.
                        </div>
                    @endif
                </div>
            </div>

            <aside class="space-y-6">
                <div class="overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-[0_18px_55px_-35px_rgba(15,23,42,0.45)]">
                    <div class="border-b border-slate-200/70 px-6 py-5">
                        <h2 class="text-lg font-semibold text-brand-navy">Detalle operativo</h2>
                    </div>
                    <dl class="grid gap-4 px-6 py-6 sm:grid-cols-2">
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
                            <dd class="mt-1 whitespace-pre-wrap text-sm leading-6 text-slate-900">{{ $closure->notes ?? 'Sin notas' }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="overflow-hidden rounded-3xl border border-slate-200/80 bg-gradient-to-br from-slate-50 to-white shadow-[0_18px_55px_-35px_rgba(15,23,42,0.45)]">
                    <div class="border-b border-slate-200/70 px-6 py-5">
                        <h2 class="text-lg font-semibold text-brand-navy">Indicadores</h2>
                    </div>
                    <div class="grid gap-3 px-6 py-6 sm:grid-cols-2">
                        <div class="rounded-2xl bg-white p-4 shadow-sm">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Total pedidos</div>
                            <div class="mt-2 text-2xl font-semibold text-brand-navy">{{ $closure->total_orders ?? 0 }}</div>
                        </div>
                        <div class="rounded-2xl bg-white p-4 shadow-sm">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Total ítems</div>
                            <div class="mt-2 text-2xl font-semibold text-brand-navy">{{ number_format((float) ($closure->total_items ?? 0), 2, '.', ',') }}</div>
                        </div>
                    </div>
                </div>
            </aside>
        </section>

        <section class="overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-[0_18px_55px_-35px_rgba(15,23,42,0.45)]">
            <div class="border-b border-slate-200/70 px-6 py-5">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold text-brand-navy">Pedidos vinculados</h2>
                    <span class="text-xs text-slate-500">{{ $orders->count() }} pedidos</span>
                </div>
            </div>

            <div class="p-6">
                @if ($orders->isNotEmpty())
                    <div class="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
                        @foreach ($orders as $order)
                            <article class="rounded-2xl border border-slate-200 bg-slate-50/80 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Pedido</p>
                                        <h3 class="mt-1 text-lg font-semibold text-brand-navy">#{{ $order->id }}</h3>
                                    </div>
                                    <span class="rounded-full border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-600">
                                        {{ $order->status ?? '-' }}
                                    </span>
                                </div>

                                <dl class="mt-4 space-y-3 text-sm">
                                    <div class="flex items-start justify-between gap-4">
                                        <dt class="text-slate-500">Cliente</dt>
                                        <dd class="text-right font-medium text-slate-900">{{ $order->customer?->name ?? '-' }}</dd>
                                    </div>
                                    <div class="flex items-start justify-between gap-4">
                                        <dt class="text-slate-500">Mensaje</dt>
                                        <dd class="max-w-[14rem] text-right text-slate-700">{{ $order->raw_message_text ?? '-' }}</dd>
                                    </div>
                                    <div class="flex items-start justify-between gap-4">
                                        <dt class="text-slate-500">Notas</dt>
                                        <dd class="text-right text-slate-700">{{ $order->notes ?? '-' }}</dd>
                                    </div>
                                    <div class="flex items-start justify-between gap-4">
                                        <dt class="text-slate-500">Creado</dt>
                                        <dd class="text-right text-slate-700">{{ $order->created_at?->format('Y-m-d H:i:s') ?? '-' }}</dd>
                                    </div>
                                </dl>
                            </article>
                        @endforeach
                    </div>
                @else
                    <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50/60 px-5 py-8 text-center text-sm text-slate-500">
                        No hay pedidos para este cierre.
                    </div>
                @endif
            </div>
        </section>
    </div>
</x-app-layout>
