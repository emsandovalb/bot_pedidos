<x-app-layout>
    @php
        $statusBadgeClasses = [
            \App\Models\Order::STATUS_PENDING_REVIEW => 'bg-amber-50 text-amber-800 ring-1 ring-amber-100',
            \App\Models\Order::STATUS_CONFIRMED => 'bg-blue-50 text-blue-800 ring-1 ring-blue-100',
            \App\Models\Order::STATUS_PREPARING => 'bg-violet-50 text-violet-800 ring-1 ring-violet-100',
            \App\Models\Order::STATUS_READY_FOR_DISPATCH => 'bg-sky-50 text-sky-800 ring-1 ring-sky-100',
            \App\Models\Order::STATUS_DISPATCHED => 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-100',
            \App\Models\Order::STATUS_CANCELLED => 'bg-slate-100 text-slate-700 ring-1 ring-slate-200',
            \App\Models\Order::STATUS_REJECTED => 'bg-rose-50 text-rose-800 ring-1 ring-rose-100',
        ];

        $statusLabels = $statusLabels ?? [];
        $formatQuantity = function (float|int|string $value): string {
            $formatted = number_format((float) $value, 2, '.', '');

            return rtrim(rtrim($formatted, '0'), '.');
        };
    @endphp

    <div class="space-y-10">
        <section class="rounded-[2rem] border border-slate-200/70 bg-[linear-gradient(135deg,rgba(20,110,219,0.08),rgba(22,163,74,0.06)_40%,rgba(255,255,255,1)_82%)] p-6 shadow-sm sm:p-8">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-3xl">
                    <div class="inline-flex items-center rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-brand-primary ring-1 ring-inset ring-blue-100">
                        Benditio analytics v1
                    </div>
                    <h1 class="mt-4 text-3xl font-semibold tracking-tight text-brand-navy sm:text-4xl">
                        Lectura rápida de ventas, clasificación y seguimiento
                    </h1>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600 sm:text-base">
                        Vista operativa para entender el ritmo de pedidos, la calidad de la clasificación automática y la actividad reciente sin salir del panel.
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-3 lg:min-w-[460px]">
                    <div class="rounded-2xl border border-slate-200/70 bg-white p-4 shadow-sm">
                        <div class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Hoy</div>
                        <div class="mt-2 text-2xl font-semibold tracking-tight text-brand-navy">{{ $ordersTodayCount }}</div>
                        <div class="mt-1 text-xs text-slate-500">Pedidos creados</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200/70 bg-white p-4 shadow-sm">
                        <div class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">7 días</div>
                        <div class="mt-2 text-2xl font-semibold tracking-tight text-brand-navy">{{ $ordersLast7DaysCount }}</div>
                        <div class="mt-1 text-xs text-slate-500">Pedidos acumulados</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200/70 bg-white p-4 shadow-sm">
                        <div class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Mes</div>
                        <div class="mt-2 text-2xl font-semibold tracking-tight text-brand-navy">{{ $ordersThisMonthCount }}</div>
                        <div class="mt-1 text-xs text-slate-500">Pedidos del período</div>
                    </div>
                </div>
            </div>
        </section>

        <section class="grid gap-6 md:grid-cols-2 xl:grid-cols-5">
            <article class="rounded-2xl border border-[#146EDB]/15 border-l-4 border-l-brand-primary bg-white p-5 shadow-sm">
                <div class="text-sm font-medium text-slate-500">Pedidos hoy</div>
                <div class="mt-3 text-3xl font-semibold tracking-tight text-brand-navy">{{ $ordersTodayCount }}</div>
                <div class="mt-1 text-sm text-slate-600">Pedidos creados durante el día</div>
            </article>

            <article class="rounded-2xl border border-[#146EDB]/15 border-l-4 border-l-brand-primary bg-white p-5 shadow-sm">
                <div class="text-sm font-medium text-slate-500">Pedidos últimos 7 días</div>
                <div class="mt-3 text-3xl font-semibold tracking-tight text-brand-navy">{{ $ordersLast7DaysCount }}</div>
                <div class="mt-1 text-sm text-slate-600">Volumen semanal visible</div>
            </article>

            <article class="rounded-2xl border border-[#146EDB]/15 border-l-4 border-l-brand-primary bg-white p-5 shadow-sm">
                <div class="text-sm font-medium text-slate-500">Pedidos este mes</div>
                <div class="mt-3 text-3xl font-semibold tracking-tight text-brand-navy">{{ $ordersThisMonthCount }}</div>
                <div class="mt-1 text-sm text-slate-600">Acumulado mensual</div>
            </article>

            <article class="rounded-2xl border border-emerald-200/70 border-l-4 border-l-emerald-500 bg-white p-5 shadow-sm">
                <div class="text-sm font-medium text-slate-500">Despachados este mes</div>
                <div class="mt-3 text-3xl font-semibold tracking-tight text-brand-navy">{{ $dispatchedThisMonthCount }}</div>
                <div class="mt-1 text-sm text-slate-600">Pedidos ya entregados</div>
            </article>

            <article class="rounded-2xl border border-amber-200/70 border-l-4 border-l-amber-500 bg-white p-5 shadow-sm">
                <div class="text-sm font-medium text-slate-500">Pendientes de revisión</div>
                <div class="mt-3 text-3xl font-semibold tracking-tight text-brand-navy">{{ $pendingReviewCount }}</div>
                <div class="mt-1 text-sm text-slate-600">Esperando validación manual</div>
            </article>
        </section>

        <section class="grid gap-8 xl:grid-cols-[1.35fr_0.9fr]">
            <div class="rounded-[2rem] border border-slate-200/70 border-l-4 border-l-brand-primary bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-brand-navy">Pedidos por estado</h2>
                        <p class="mt-1 text-sm text-slate-500">Distribución del flujo operativo actual.</p>
                    </div>
                </div>

                <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach ($statusLabels as $status => $label)
                        <a href="{{ route('orders.index', ['status' => $status]) }}" class="rounded-2xl border border-slate-200/70 bg-slate-50/70 p-4 transition hover:-translate-y-0.5 hover:border-brand-primary/25 hover:bg-white hover:shadow-sm">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="text-sm font-medium text-slate-500">{{ $label }}</div>
                                    <div class="mt-2 text-2xl font-semibold tracking-tight text-brand-navy">{{ $statusCounts[$status] ?? 0 }}</div>
                                </div>
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusBadgeClasses[$status] ?? 'bg-slate-100 text-slate-700 ring-slate-200/70' }}">
                                    {{ $statusCounts[$status] ?? 0 }}
                                </span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="rounded-[2rem] border border-slate-200/70 border-l-4 border-l-emerald-500 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-brand-navy">Clasificación automática</h2>
                        <p class="mt-1 text-sm text-slate-500">Qué tan bien se están asociando los ítems al catálogo.</p>
                    </div>
                </div>

                <div class="mt-6 space-y-4">
                    <div class="rounded-2xl bg-slate-50 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-sm font-medium text-slate-500">Ítems totales</div>
                                <div class="mt-1 text-2xl font-semibold tracking-tight text-brand-navy">{{ $totalOrderItems }}</div>
                            </div>
                            <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-blue-50 text-brand-primary ring-1 ring-inset ring-blue-100">#</div>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="rounded-2xl border border-emerald-200/70 bg-emerald-50/60 p-4">
                            <div class="text-sm font-medium text-slate-500">Con producto</div>
                            <div class="mt-1 text-2xl font-semibold tracking-tight text-brand-navy">{{ $classifiedOrderItems }}</div>
                        </div>
                        <div class="rounded-2xl border border-amber-200/70 bg-amber-50/70 p-4">
                            <div class="text-sm font-medium text-slate-500">Sin producto</div>
                            <div class="mt-1 text-2xl font-semibold tracking-tight text-brand-navy">{{ $unclassifiedOrderItems }}</div>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200/70 bg-white p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div class="text-sm font-medium text-slate-500">Porcentaje clasificado</div>
                            <div class="text-2xl font-semibold tracking-tight text-brand-navy">{{ $classificationPercentage }}%</div>
                        </div>
                        <div class="mt-3 h-3 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full bg-gradient-to-r from-[#146EDB] to-emerald-500" style="width: {{ $classificationPercentage }}%"></div>
                        </div>
                        <p class="mt-3 text-sm text-slate-500">
                            {{ $classificationPercentage }}% de los ítems visibles ya quedaron asociados a un producto.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <section class="grid gap-8 xl:grid-cols-2">
            <div class="rounded-[2rem] border border-slate-200/70 border-l-4 border-l-brand-primary bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-brand-navy">Productos más solicitados</h2>
                        <p class="mt-1 text-sm text-slate-500">Agrupados por producto asociado o texto crudo cuando aún no hay clasificación.</p>
                    </div>
                </div>

                <div class="mt-5 space-y-3">
                    @forelse ($topRequestedProducts as $product)
                        <article class="rounded-2xl border border-slate-200/70 bg-slate-50/70 p-4 transition hover:bg-white">
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <div class="truncate text-sm font-semibold text-brand-navy">{{ $product->label }}</div>
                                    <div class="mt-1 text-xs text-slate-500">{{ $product->line_count }} líneas de pedido</div>
                                </div>
                                <div class="flex shrink-0 flex-col items-end gap-1">
                                    <span class="rounded-full bg-brand-primary/10 px-3 py-1 text-xs font-semibold text-brand-primary ring-1 ring-inset ring-blue-100">
                                        {{ $formatQuantity($product->total_quantity) }} unidades
                                    </span>
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="rounded-2xl border border-dashed border-blue-200 bg-blue-50/70 px-4 py-6 text-sm text-slate-600">
                            Todavía no hay productos suficientes para construir un ranking.
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="rounded-[2rem] border border-slate-200/70 border-l-4 border-l-emerald-500 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-brand-navy">Clientes frecuentes</h2>
                        <p class="mt-1 text-sm text-slate-500">Quién repite más y cuándo fue su última compra.</p>
                    </div>
                </div>

                <div class="mt-5 space-y-3">
                    @forelse ($frequentCustomers as $customer)
                        <article class="rounded-2xl border border-slate-200/70 bg-slate-50/70 p-4 transition hover:bg-white">
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <div class="truncate text-sm font-semibold text-brand-navy">{{ $customer->label }}</div>
                                    <div class="mt-1 text-xs text-slate-500">
                                        {{ $customer->phone ?? $customer->external_id ?? 'Sin contacto visible' }}
                                        @if ($customer->last_order_at)
                                            · Último pedido {{ $customer->last_order_at->format('d M Y H:i') }}
                                        @endif
                                    </div>
                                </div>
                                <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">
                                    {{ $customer->total_orders }} pedidos
                                </span>
                            </div>
                        </article>
                    @empty
                        <div class="rounded-2xl border border-dashed border-blue-200 bg-blue-50/70 px-4 py-6 text-sm text-slate-600">
                            Aún no hay pedidos suficientes para identificar clientes frecuentes.
                        </div>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="rounded-[2rem] border border-slate-200/70 border-l-4 border-l-brand-primary bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-brand-navy">Actividad reciente</h2>
                    <p class="mt-1 text-sm text-slate-500">Últimos 10 pedidos visibles en la operación.</p>
                </div>
                <a href="{{ route('orders.index') }}" class="text-sm font-medium text-[#146EDB] transition hover:text-[#146EDB]/80">
                    Ver todos
                </a>
            </div>

            <div class="mt-5 space-y-3">
                @forelse ($recentOrders as $order)
                    <article class="rounded-2xl border border-slate-200/70 bg-slate-50/70 p-4 transition hover:-translate-y-0.5 hover:border-brand-primary/25 hover:bg-white">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-sm font-semibold text-brand-navy">#{{ $order->id }}</span>
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusBadgeClasses[$order->status] ?? 'bg-slate-100 text-slate-700 ring-slate-200/70' }}">
                                        {{ $statusLabels[$order->status] ?? str_replace('_', ' ', $order->status) }}
                                    </span>
                                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">
                                        {{ $order->source_channel ?? 'telegram' }}
                                    </span>
                                </div>

                                <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-slate-600">
                                    <span>{{ $order->customer?->name ?? $order->customer?->phone ?? 'Cliente sin nombre' }}</span>
                                    <span class="text-slate-300">•</span>
                                    <span>{{ $order->customer?->phone ?? $order->customer?->external_id ?? 'Sin contacto' }}</span>
                                    <span class="text-slate-300">•</span>
                                    <span>{{ $order->created_at?->format('d M Y H:i') }}</span>
                                </div>
                            </div>

                            <a href="{{ route('orders.show', $order) }}" class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-brand-primary/25 hover:text-brand-primary">
                                Abrir pedido
                            </a>
                        </div>
                    </article>
                @empty
                    <div class="rounded-2xl border border-dashed border-blue-200 bg-blue-50/70 px-4 py-6 text-sm text-slate-600">
                        No hay pedidos recientes para mostrar.
                    </div>
                @endforelse
            </div>
        </section>
    </div>
</x-app-layout>
