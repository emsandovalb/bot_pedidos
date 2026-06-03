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
                            Cierres diarios
                        </h1>
                        <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600 sm:text-base">
                            Revisa, exporta y controla el resumen operativo de cada día.
                        </p>
                    </div>

                    <a href="{{ route('daily-order-closures.create') }}" class="inline-flex items-center justify-center rounded-xl bg-brand-primary px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-brand-primary/20 transition hover:-translate-y-0.5 hover:bg-brand-primary/90">
                        Nuevo cierre
                    </a>
                </div>
            </div>
        </section>

        @if ($branches->isEmpty())
            <div class="rounded-2xl border border-amber-200 bg-amber-50/80 px-5 py-4 text-sm text-amber-900 shadow-sm">
                No hay sucursales visibles para esta cuenta.
            </div>
        @endif

        @if ($closures->isNotEmpty())
            <div class="grid gap-5 xl:grid-cols-2">
                @foreach ($closures as $closure)
                    <article class="group overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-[0_18px_55px_-35px_rgba(15,23,42,0.45)] transition hover:-translate-y-0.5 hover:shadow-[0_26px_70px_-35px_rgba(15,23,42,0.55)]">
                        <div class="border-b border-slate-200/70 bg-gradient-to-r from-slate-50 to-white px-5 py-4 sm:px-6">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Fecha</p>
                                    <h2 class="mt-1 text-xl font-semibold text-brand-navy">
                                        {{ $closure->closure_date?->format('d/m/Y') ?? '-' }}
                                    </h2>
                                    <p class="mt-1 text-sm text-slate-600">
                                        {{ $closure->branch?->name ?? 'Sucursal no disponible' }}
                                    </p>
                                </div>

                                <div class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700">
                                    Cierre operativo
                                </div>
                            </div>
                        </div>

                        <div class="p-5 sm:p-6">
                            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                <div class="rounded-2xl bg-slate-50 p-4">
                                    <div class="text-xs font-medium uppercase tracking-[0.18em] text-slate-500">Total pedidos</div>
                                    <div class="mt-2 text-2xl font-semibold text-brand-navy">{{ $closure->total_orders ?? 0 }}</div>
                                </div>
                                <div class="rounded-2xl bg-slate-50 p-4">
                                    <div class="text-xs font-medium uppercase tracking-[0.18em] text-slate-500">Total ítems</div>
                                    <div class="mt-2 text-2xl font-semibold text-brand-navy">{{ number_format((float) ($closure->total_items ?? 0), 2, '.', ',') }}</div>
                                </div>
                                <div class="rounded-2xl bg-slate-50 p-4">
                                    <div class="text-xs font-medium uppercase tracking-[0.18em] text-slate-500">Despachados</div>
                                    <div class="mt-2 text-2xl font-semibold text-brand-navy">{{ $closure->dispatched_count ?? 0 }}</div>
                                </div>
                                <div class="rounded-2xl bg-slate-50 p-4">
                                    <div class="text-xs font-medium uppercase tracking-[0.18em] text-slate-500">Pendientes</div>
                                    <div class="mt-2 text-2xl font-semibold text-brand-navy">{{ $closure->pending_review_count ?? 0 }}</div>
                                </div>
                                <div class="rounded-2xl bg-slate-50 p-4">
                                    <div class="text-xs font-medium uppercase tracking-[0.18em] text-slate-500">Cancelados</div>
                                    <div class="mt-2 text-2xl font-semibold text-brand-navy">{{ $closure->cancelled_count ?? 0 }}</div>
                                </div>
                            </div>

                            @if ($closure->notes)
                                <div class="mt-5 rounded-2xl border border-slate-200 bg-slate-50/80 p-4 text-sm leading-6 text-slate-600">
                                    {{ $closure->notes }}
                                </div>
                            @endif

                            <div class="mt-5 flex flex-wrap gap-3">
                                <a href="{{ route('daily-order-closures.show', $closure) }}" class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-brand-primary/30 hover:text-brand-primary">
                                    Ver cierre
                                </a>
                                <a href="{{ route('daily-order-closures.export', $closure) }}" class="inline-flex items-center justify-center rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-700 shadow-sm transition hover:border-emerald-300 hover:bg-emerald-100">
                                    Exportar CSV
                                </a>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="rounded-2xl border border-slate-200/80 bg-white px-4 py-3 shadow-sm">
                {{ $closures->links() }}
            </div>
        @else
            <div class="rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center shadow-sm">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-primary/10 text-brand-primary">
                    <svg viewBox="0 0 24 24" class="h-7 w-7 fill-none stroke-current" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M8 3v3M16 3v3M4 8h16M6 5h12a2 2 0 0 1 2 2v11a1 1 0 0 1-1 2H6a1 1 0 0 1-1-2V7a2 2 0 0 1 2-2Z" />
                    </svg>
                </div>
                <h2 class="mt-4 text-lg font-semibold text-brand-navy">No hay cierres registrados</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600">
                    Cuando generes el primer cierre diario, aparecerá aquí con sus métricas operativas y accesos de exportación.
                </p>
                <div class="mt-6">
                    <a href="{{ route('daily-order-closures.create') }}" class="inline-flex items-center justify-center rounded-xl bg-brand-primary px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-brand-primary/20 transition hover:-translate-y-0.5 hover:bg-brand-primary/90">
                        Nuevo cierre
                    </a>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
