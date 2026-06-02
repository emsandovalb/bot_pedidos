<x-app-layout>
    @php
        $closureCollection = $closures->getCollection();
        $hasClosures = $closureCollection->isNotEmpty();
        $latestClosure = $closureCollection->first();
        $totalOrders = (int) $closureCollection->sum('total_orders');
        $totalItems = (float) $closureCollection->sum('total_items');
    @endphp

    <div class="space-y-6">
        <div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-[linear-gradient(135deg,#F8FAFC_0%,#EEF4FF_52%,#E8F1FF_100%)] shadow-sm">
            <div class="relative px-6 py-8 sm:px-8 lg:px-10">
                <div class="absolute inset-y-0 right-0 hidden w-1/3 bg-[radial-gradient(circle_at_top_right,rgba(20,110,219,0.18),transparent_68%)] lg:block"></div>
                <div class="relative flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-2xl">
                        <div class="inline-flex items-center rounded-full border border-[#146EDB]/10 bg-white/80 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-[#146EDB] shadow-sm">
                            Benditio · Cierres diarios
                        </div>
                        <h1 class="mt-4 text-3xl font-semibold tracking-tight text-[#0F172A] sm:text-4xl">Cierres diarios</h1>
                        <p class="mt-2 max-w-xl text-sm leading-6 text-[#64748B] sm:text-base">
                            Revisa, exporta y controla el resumen operativo de cada día.
                        </p>
                    </div>

                    <a href="{{ route('daily-order-closures.create') }}" class="inline-flex items-center justify-center rounded-2xl bg-[#146EDB] px-5 py-3 text-sm font-semibold text-white shadow-[0_10px_30px_rgba(20,110,219,0.22)] transition hover:bg-blue-700">
                        Nuevo cierre
                    </a>
                </div>
            </div>
        </div>

        @if ($branches->isEmpty())
            <div class="rounded-3xl border border-dashed border-slate-200 bg-white p-6 text-sm text-slate-600 shadow-sm">
                No hay sucursales visibles para esta cuenta.
            </div>
        @endif

        @if ($hasClosures)
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="text-sm font-medium text-slate-500">Cierres registrados</div>
                    <div class="mt-3 text-3xl font-semibold tracking-tight text-[#0F172A]">{{ number_format((float) $closures->total(), 0, '.', ',') }}</div>
                    <div class="mt-2 text-xs text-slate-500">Disponibles en el listado actual.</div>
                </div>
                <div class="rounded-3xl border border-blue-100 bg-[#EFF6FF] p-5 shadow-sm">
                    <div class="text-sm font-medium text-blue-700">Pedidos cerrados</div>
                    <div class="mt-3 text-3xl font-semibold tracking-tight text-[#0F172A]">{{ number_format($totalOrders, 0, '.', ',') }}</div>
                    <div class="mt-2 text-xs text-slate-500">Suma de la página actual.</div>
                </div>
                <div class="rounded-3xl border border-emerald-100 bg-[#F0FDF4] p-5 shadow-sm">
                    <div class="text-sm font-medium text-emerald-700">Ítems procesados</div>
                    <div class="mt-3 text-3xl font-semibold tracking-tight text-[#0F172A]">{{ number_format($totalItems, 2, '.', ',') }}</div>
                    <div class="mt-2 text-xs text-slate-500">Total de unidades registradas.</div>
                </div>
                <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="text-sm font-medium text-slate-500">Último cierre</div>
                    @if ($latestClosure)
                        <div class="mt-3 text-2xl font-semibold tracking-tight text-[#0F172A]">{{ $latestClosure->closure_date?->format('d/m/Y') ?? '-' }}</div>
                        <div class="mt-2 text-sm text-slate-600">{{ $latestClosure->branch?->name ?? '-' }}</div>
                    @else
                        <div class="mt-3 text-2xl font-semibold tracking-tight text-[#0F172A]">-</div>
                        <div class="mt-2 text-sm text-slate-500">Sin cierres recientes.</div>
                    @endif
                </div>
            </div>
        @else
            <div class="rounded-[2rem] border border-dashed border-slate-200 bg-white p-8 shadow-sm">
                <div class="mx-auto flex max-w-md flex-col items-center text-center">
                    <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-100 text-3xl">📁</div>
                    <h2 class="mt-5 text-xl font-semibold tracking-tight text-[#0F172A]">Aún no hay cierres diarios.</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">
                        Crea tu primer cierre para revisar el resumen operativo del día.
                    </p>
                    <a href="{{ route('daily-order-closures.create') }}" class="mt-6 inline-flex items-center justify-center rounded-2xl bg-[#146EDB] px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700">
                        Crear cierre diario
                    </a>
                </div>
            </div>
        @endif

        @if ($hasClosures)
            <div class="space-y-4">
                @foreach ($closures as $closure)
                    <article class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md sm:p-6">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div class="space-y-4">
                                <div class="flex flex-wrap items-center gap-3">
                                    <div class="rounded-2xl bg-[#EFF6FF] px-4 py-2 text-sm font-semibold text-[#146EDB]">
                                        {{ $closure->closure_date?->format('d/m/Y') ?? '-' }}
                                    </div>
                                    <div class="text-sm font-medium text-slate-600">{{ $closure->branch?->name ?? '-' }}</div>
                                </div>

                                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                    <div class="rounded-2xl bg-slate-50 p-4">
                                        <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Total pedidos</div>
                                        <div class="mt-2 text-2xl font-semibold text-[#0F172A]">{{ number_format((int) $closure->total_orders, 0, '.', ',') }}</div>
                                    </div>
                                    <div class="rounded-2xl bg-slate-50 p-4">
                                        <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Total ítems</div>
                                        <div class="mt-2 text-2xl font-semibold text-[#0F172A]">{{ number_format((float) $closure->total_items, 2, '.', ',') }}</div>
                                    </div>
                                    <div class="rounded-2xl bg-emerald-50 p-4">
                                        <div class="text-xs font-medium uppercase tracking-wide text-emerald-700">Despachados</div>
                                        <div class="mt-2 text-2xl font-semibold text-emerald-700">{{ number_format((int) $closure->dispatched_count, 0, '.', ',') }}</div>
                                    </div>
                                    <div class="rounded-2xl bg-amber-50 p-4">
                                        <div class="text-xs font-medium uppercase tracking-wide text-amber-700">Pendientes</div>
                                        <div class="mt-2 text-2xl font-semibold text-amber-700">{{ number_format((int) $closure->pending_review_count, 0, '.', ',') }}</div>
                                    </div>
                                </div>

                                <div class="flex flex-wrap gap-3 text-sm">
                                    <div class="rounded-full bg-[#F8FAFC] px-3 py-1.5 text-slate-600">
                                        Cancelados: <span class="font-semibold text-[#0F172A]">{{ number_format((int) $closure->cancelled_count, 0, '.', ',') }}</span>
                                    </div>
                                    @if ($closure->notes)
                                        <div class="rounded-full bg-[#F8FAFC] px-3 py-1.5 text-slate-600">
                                            Notas: <span class="font-medium text-[#0F172A]">{{ \Illuminate\Support\Str::limit($closure->notes, 80) }}</span>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-3 lg:justify-end">
                                <a href="{{ route('daily-order-closures.show', $closure) }}" class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-[#146EDB]/30 hover:text-[#146EDB]">
                                    Ver cierre
                                </a>
                                <a href="{{ route('daily-order-closures.export', $closure) }}" class="inline-flex items-center justify-center rounded-2xl bg-[#0F172A] px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800">
                                    Exportar CSV
                                </a>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif

        <div>
            {{ $closures->links() }}
        </div>
    </div>
</x-app-layout>
