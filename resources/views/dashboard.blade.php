<x-app-layout>
    <div class="space-y-8">
        @php
            $orderStatusLabels = [
                \App\Models\Order::STATUS_PENDING_REVIEW => 'Pendiente de revisión',
                \App\Models\Order::STATUS_CONFIRMED => 'Confirmado',
                \App\Models\Order::STATUS_PREPARING => 'En preparación',
                \App\Models\Order::STATUS_READY_FOR_DISPATCH => 'Listo para despacho',
                \App\Models\Order::STATUS_DISPATCHED => 'Despachado',
                \App\Models\Order::STATUS_CANCELLED => 'Cancelado',
                \App\Models\Order::STATUS_REJECTED => 'Rechazado',
            ];

            $orderStatusClasses = [
                \App\Models\Order::STATUS_PENDING_REVIEW => 'bg-amber-100 text-amber-800 ring-amber-200/70',
                \App\Models\Order::STATUS_CONFIRMED => 'bg-blue-100 text-blue-800 ring-blue-200/70',
                \App\Models\Order::STATUS_PREPARING => 'bg-violet-100 text-violet-800 ring-violet-200/70',
                \App\Models\Order::STATUS_READY_FOR_DISPATCH => 'bg-sky-100 text-sky-800 ring-sky-200/70',
                \App\Models\Order::STATUS_DISPATCHED => 'bg-emerald-100 text-emerald-800 ring-emerald-200/70',
                \App\Models\Order::STATUS_CANCELLED => 'bg-slate-100 text-slate-700 ring-slate-200/70',
                \App\Models\Order::STATUS_REJECTED => 'bg-rose-100 text-rose-800 ring-rose-200/70',
            ];
        @endphp

        <section class="rounded-3xl border border-slate-200/70 bg-gradient-to-br from-white via-white to-emerald-50/60 p-6 shadow-sm">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-3xl">
                    <div class="inline-flex items-center rounded-full bg-brand-primary/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-brand-primary">
                        Sistema operativo
                    </div>
                    <h1 class="mt-4 text-3xl font-semibold tracking-tight text-brand-navy sm:text-4xl">Benditio</h1>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600 sm:text-base">
                        Convierte mensajes en ventas.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <a href="{{ route('order-reviews.index') }}" class="inline-flex items-center justify-center rounded-xl bg-brand-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-primary/90">
                        Revisar pedidos
                    </a>
                    <a href="{{ route('daily-order-closures.index') }}" class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-brand-primary/30 hover:text-brand-primary">
                        Cierres diarios
                    </a>
                </div>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            <a href="{{ route('orders.index', ['status' => \App\Models\Order::STATUS_PENDING_REVIEW]) }}" class="group rounded-xl border border-slate-200/70 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="text-sm font-medium text-slate-500">Pedidos hoy</div>
                        <div class="mt-2 text-3xl font-semibold tracking-tight text-brand-navy">{{ $ordersTodayCount }}</div>
                        <div class="mt-1 text-xs text-slate-500">Pedidos creados durante el día</div>
                    </div>
                    <div class="rounded-2xl bg-brand-primary/10 px-3 py-2 text-brand-primary">#</div>
                </div>
            </a>

            <a href="{{ route('orders.index', ['status' => \App\Models\Order::STATUS_PENDING_REVIEW]) }}" class="group rounded-xl border border-amber-200/70 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="text-sm font-medium text-slate-500">Pendientes de revisión</div>
                        <div class="mt-2 text-3xl font-semibold tracking-tight text-brand-navy">{{ $orderPendingReviewCount }}</div>
                        <div class="mt-1 text-xs text-slate-500">Pedidos listos para validar</div>
                    </div>
                    <div class="rounded-2xl bg-amber-50 px-3 py-2 text-amber-700">?</div>
                </div>
            </a>

            <a href="{{ route('orders.index', ['status' => \App\Models\Order::STATUS_CONFIRMED]) }}" class="group rounded-xl border border-blue-200/70 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="text-sm font-medium text-slate-500">Confirmados</div>
                        <div class="mt-2 text-3xl font-semibold tracking-tight text-brand-navy">{{ $orderConfirmedCount }}</div>
                        <div class="mt-1 text-xs text-slate-500">Pedidos confirmados en el flujo</div>
                    </div>
                    <div class="rounded-2xl bg-blue-50 px-3 py-2 text-brand-info">✓</div>
                </div>
            </a>

            <a href="{{ route('orders.index', ['status' => \App\Models\Order::STATUS_PREPARING]) }}" class="group rounded-xl border border-violet-200/70 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="text-sm font-medium text-slate-500">En preparación</div>
                        <div class="mt-2 text-3xl font-semibold tracking-tight text-brand-navy">{{ $orderPreparingCount }}</div>
                        <div class="mt-1 text-xs text-slate-500">Pedidos que avanzan a cocina</div>
                    </div>
                    <div class="rounded-2xl bg-violet-50 px-3 py-2 text-violet-700">~</div>
                </div>
            </a>

            <a href="{{ route('orders.index', ['status' => \App\Models\Order::STATUS_DISPATCHED]) }}" class="group rounded-xl border border-emerald-200/70 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="text-sm font-medium text-slate-500">Despachados</div>
                        <div class="mt-2 text-3xl font-semibold tracking-tight text-brand-navy">{{ $orderDispatchedCount }}</div>
                        <div class="mt-1 text-xs text-slate-500">Salidas registradas hoy</div>
                    </div>
                    <div class="rounded-2xl bg-emerald-50 px-3 py-2 text-emerald-700">→</div>
                </div>
            </a>
        </section>

        <section class="grid gap-6 xl:grid-cols-2">
            <div class="rounded-3xl border border-slate-200/70 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-brand-navy">Pedidos recientes</h2>
                        <p class="mt-1 text-sm text-slate-500">Últimos pedidos detectados en el sistema.</p>
                    </div>
                    <a href="{{ route('orders.index') }}" class="text-sm font-medium text-brand-primary transition hover:text-brand-primary/80">
                        Ver todos
                    </a>
                </div>

                <div class="mt-5 space-y-3">
                    @forelse ($recentOrders as $order)
                        @php
                            $statusLabel = $orderStatusLabels[$order->status] ?? str_replace('_', ' ', $order->status);
                            $statusClass = $orderStatusClasses[$order->status] ?? 'bg-slate-100 text-slate-700 ring-slate-200/70';
                        @endphp
                        <article class="rounded-xl border border-slate-200/80 bg-slate-50/80 p-4 transition hover:border-brand-primary/20 hover:bg-white">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0 space-y-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="text-sm font-semibold text-brand-navy">#{{ $order->id }}</span>
                                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 {{ $statusClass }}">
                                            {{ $statusLabel }}
                                        </span>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="truncate text-sm font-medium text-slate-900">{{ $order->customer?->name ?? 'Cliente sin nombre' }}</div>
                                        <div class="mt-0.5 text-xs text-slate-500">{{ $order->customer?->phone ?? '-' }} · {{ $order->branch?->name ?? 'Sucursal no asignada' }}</div>
                                    </div>
                                    <p class="text-sm leading-6 text-slate-600">
                                        {{ \Illuminate\Support\Str::limit($order->raw_message_text ?? 'Sin mensaje disponible', 130) }}
                                    </p>
                                </div>

                                <div class="flex shrink-0 items-center gap-2">
                                    <a href="{{ route('orders.show', $order) }}" class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-sm transition hover:border-brand-primary/30 hover:text-brand-primary">
                                        Abrir
                                    </a>
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-6 text-sm text-slate-500">
                            No hay pedidos recientes para mostrar.
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="rounded-3xl border border-slate-200/70 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-brand-navy">Productos activos y cierres</h2>
                        <p class="mt-1 text-sm text-slate-500">Estado del catálogo y actividad de cierres.</p>
                    </div>
                    <a href="{{ route('products.index') }}" class="text-sm font-medium text-brand-primary transition hover:text-brand-primary/80">
                        Ir al catálogo
                    </a>
                </div>

                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-xl border border-brand-primary/10 bg-brand-primary/5 p-4">
                        <div class="text-sm font-medium text-slate-500">Productos activos</div>
                        <div class="mt-2 text-3xl font-semibold tracking-tight text-brand-navy">{{ $activeProductCount }}</div>
                        <div class="mt-1 text-xs text-slate-500">Catálogo disponible para venta</div>
                    </div>
                    <div class="rounded-xl border border-slate-200/70 bg-slate-50 p-4">
                        <div class="text-sm font-medium text-slate-500">Total de productos</div>
                        <div class="mt-2 text-3xl font-semibold tracking-tight text-brand-navy">{{ $totalProductCount }}</div>
                        <div class="mt-1 text-xs text-slate-500">Incluye activos e inactivos</div>
                    </div>
                    <div class="rounded-xl border border-emerald-200/70 bg-emerald-50/60 p-4">
                        <div class="text-sm font-medium text-slate-500">Cierres recientes</div>
                        <div class="mt-2 text-3xl font-semibold tracking-tight text-brand-navy">{{ $recentClosures->count() }}</div>
                        <div class="mt-1 text-xs text-slate-500">Últimos cierres cargados</div>
                    </div>
                    <div class="rounded-xl border border-brand-gold/20 bg-amber-50 p-4">
                        <div class="text-sm font-medium text-slate-500">Total de cierres</div>
                        <div class="mt-2 text-3xl font-semibold tracking-tight text-brand-navy">{{ $totalClosuresCount }}</div>
                        <div class="mt-1 text-xs text-slate-500">Cierres acumulados del ámbito</div>
                    </div>
                </div>

                <div class="mt-6">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold uppercase tracking-[0.16em] text-slate-500">Cierres recientes</h3>
                        <span class="text-xs text-slate-500">Últimos registros</span>
                    </div>

                    <div class="mt-4 space-y-3">
                        @forelse ($recentClosures as $closure)
                            <article class="rounded-xl border border-slate-200/80 bg-slate-50/80 px-4 py-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="text-sm font-medium text-slate-900">{{ $closure->branch?->name ?? 'Sucursal no asignada' }}</div>
                                        <div class="mt-1 text-xs text-slate-500">
                                            {{ $closure->closure_date?->format('Y-m-d') }}
                                            · Cerrado por {{ $closure->closedByUser?->name ?? '-' }}
                                        </div>
                                    </div>
                                    <span class="rounded-full bg-brand-primary/10 px-3 py-1 text-xs font-semibold text-brand-primary">
                                        {{ $closure->total_orders }} pedidos
                                    </span>
                                </div>
                            </article>
                        @empty
                            <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-6 text-sm text-slate-500">
                                No hay cierres recientes disponibles.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-3">
            <a href="{{ route('order-reviews.index') }}" class="rounded-xl border border-amber-200/70 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                <div class="text-sm font-medium text-slate-500">Revisión pendiente</div>
                <div class="mt-2 text-3xl font-semibold tracking-tight text-brand-navy">{{ $totalNeedsReviewRequests }}</div>
                <div class="mt-1 text-sm text-slate-600">Pedidos listos para validar manualmente.</div>
            </a>

            <a href="{{ route('products.index') }}" class="rounded-xl border border-brand-primary/10 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                <div class="text-sm font-medium text-slate-500">Catálogo</div>
                <div class="mt-2 text-3xl font-semibold tracking-tight text-brand-navy">{{ $activeProductCount }}</div>
                <div class="mt-1 text-sm text-slate-600">Productos activos para la operación diaria.</div>
            </a>

            <a href="{{ route('daily-order-closures.index') }}" class="rounded-xl border border-emerald-200/70 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                <div class="text-sm font-medium text-slate-500">Cierres diarios</div>
                <div class="mt-2 text-3xl font-semibold tracking-tight text-brand-navy">{{ $dailyOrderClosuresTodayCount }}</div>
                <div class="mt-1 text-sm text-slate-600">Cierres hechos hoy dentro del ámbito visible.</div>
            </a>
        </section>
    </div>
</x-app-layout>
