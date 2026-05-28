<x-app-layout>
    <div class="space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <div class="brand-badge bg-brand-primary/10 text-brand-primary">Bot de pedidos</div>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight text-brand-navy">Panel</h1>
                <p class="mt-2 text-sm text-slate-600">Resumen operativo de pedidos por Telegram, revision manual y cierres diarios.</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('orders.index', ['status' => \App\Models\Order::STATUS_PENDING_REVIEW]) }}" class="brand-btn-primary">Pedidos pendientes</a>
                <a href="{{ route('order-reviews.index') }}" class="brand-btn-secondary">Revisar pedidos</a>
                <a href="{{ route('incoming-messages.index') }}" class="brand-btn-secondary">Bandeja de mensajes</a>
                <a href="{{ route('daily-order-closures.index') }}" class="brand-btn-secondary">Cierres diarios</a>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            <div class="brand-card border-brand-primary/10 p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="text-sm font-medium text-slate-500">Mensajes recibidos hoy</div>
                        <div class="mt-2 text-3xl font-semibold text-brand-navy">{{ $totalIncomingMessagesToday }}</div>
                    </div>
                    <div class="rounded-2xl bg-brand-primary/10 px-3 py-2 text-brand-primary">#</div>
                </div>
            </div>
            <div class="brand-card border-brand-gold/20 p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="text-sm font-medium text-slate-500">Pedidos pendientes</div>
                        <div class="mt-2 text-3xl font-semibold text-brand-navy">{{ $totalPendingRequests }}</div>
                    </div>
                    <div class="rounded-2xl bg-amber-50 px-3 py-2 text-amber-700">#</div>
                </div>
            </div>
            <div class="brand-card border-brand-info/20 p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="text-sm font-medium text-slate-500">Revision de pedidos</div>
                        <div class="mt-2 text-3xl font-semibold text-brand-navy">{{ $totalNeedsReviewRequests }}</div>
                    </div>
                    <div class="rounded-2xl bg-blue-50 px-3 py-2 text-brand-info">!</div>
                </div>
            </div>
            <div class="brand-card border-brand-success/20 p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="text-sm font-medium text-slate-500">Pedidos confirmados hoy</div>
                        <div class="mt-2 text-3xl font-semibold text-brand-navy">{{ $totalConfirmedRequestsToday }}</div>
                    </div>
                    <div class="rounded-2xl bg-green-50 px-3 py-2 text-brand-success">#</div>
                </div>
            </div>
            <a href="{{ route('daily-order-closures.index') }}" class="brand-card border-brand-gold/20 p-5 transition hover:-translate-y-0.5 hover:shadow-md">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="text-sm font-medium text-slate-500">Cierres diarios hoy</div>
                        <div class="mt-2 text-3xl font-semibold text-brand-navy">{{ $dailyOrderClosuresTodayCount }}</div>
                    </div>
                    <div class="rounded-2xl bg-amber-50 px-3 py-2 text-amber-700">#</div>
                </div>
            </a>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            <a href="{{ route('orders.index', ['status' => \App\Models\Order::STATUS_PENDING_REVIEW]) }}" class="brand-card border-brand-gold/20 p-5 transition hover:-translate-y-0.5 hover:shadow-md">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="text-sm font-medium text-slate-500">Pendientes de revision</div>
                        <div class="mt-2 text-3xl font-semibold text-brand-navy">{{ $orderPendingReviewCount }}</div>
                    </div>
                    <div class="rounded-2xl bg-amber-50 px-3 py-2 text-amber-700">?</div>
                </div>
            </a>
            <a href="{{ route('orders.index', ['status' => \App\Models\Order::STATUS_CONFIRMED]) }}" class="brand-card border-brand-success/20 p-5 transition hover:-translate-y-0.5 hover:shadow-md">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="text-sm font-medium text-slate-500">Confirmados</div>
                        <div class="mt-2 text-3xl font-semibold text-brand-navy">{{ $orderConfirmedCount }}</div>
                    </div>
                    <div class="rounded-2xl bg-green-50 px-3 py-2 text-brand-success">#</div>
                </div>
            </a>
            <a href="{{ route('orders.index', ['status' => \App\Models\Order::STATUS_PREPARING]) }}" class="brand-card border-violet-200 p-5 transition hover:-translate-y-0.5 hover:shadow-md">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="text-sm font-medium text-slate-500">En preparacion</div>
                        <div class="mt-2 text-3xl font-semibold text-brand-navy">{{ $orderPreparingCount }}</div>
                    </div>
                    <div class="rounded-2xl bg-violet-50 px-3 py-2 text-violet-700">~</div>
                </div>
            </a>
            <a href="{{ route('orders.index', ['status' => \App\Models\Order::STATUS_READY_FOR_DISPATCH]) }}" class="brand-card border-brand-info/20 p-5 transition hover:-translate-y-0.5 hover:shadow-md">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="text-sm font-medium text-slate-500">Listos para despacho</div>
                        <div class="mt-2 text-3xl font-semibold text-brand-navy">{{ $orderReadyForDispatchCount }}</div>
                    </div>
                    <div class="rounded-2xl bg-sky-50 px-3 py-2 text-brand-info">-></div>
                </div>
            </a>
            <a href="{{ route('orders.index', ['status' => \App\Models\Order::STATUS_DISPATCHED]) }}" class="brand-card border-emerald-200 p-5 transition hover:-translate-y-0.5 hover:shadow-md">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="text-sm font-medium text-slate-500">Despachados hoy</div>
                        <div class="mt-2 text-3xl font-semibold text-brand-navy">{{ $orderDispatchedCount }}</div>
                    </div>
                    <div class="rounded-2xl bg-emerald-50 px-3 py-2 text-emerald-700">#</div>
                </div>
            </a>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <div class="brand-card p-5">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-base font-semibold text-brand-navy">Pedidos por sucursal</h2>
                    <span class="text-xs text-slate-500">Ambito visible</span>
                </div>
                <div class="mt-4 overflow-hidden rounded-2xl border border-slate-200/80">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-slate-500">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium">Branch</th>
                                <th class="px-3 py-2 text-left font-medium">Hoy</th>
                                <th class="px-3 py-2 text-left font-medium">Pendientes</th>
                                <th class="px-3 py-2 text-left font-medium">Revision</th>
                                <th class="px-3 py-2 text-left font-medium">Confirmadas</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white">
                            @forelse ($branchTotals as $branch)
                                <tr>
                                    <td class="px-3 py-2 font-medium text-slate-900">{{ $branch->name }}</td>
                                    <td class="px-3 py-2 text-slate-600">{{ $branch->requests_today_count }}</td>
                                    <td class="px-3 py-2 text-slate-600">{{ $branch->pending_requests_count }}</td>
                                    <td class="px-3 py-2 text-slate-600">{{ $branch->needs_review_requests_count }}</td>
                                    <td class="px-3 py-2 text-slate-600">{{ $branch->confirmed_requests_count }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-3 py-4 text-center text-slate-500">No branches available.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="brand-card p-5">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-base font-semibold text-brand-navy">Estados de pedidos</h2>
                    <span class="text-xs text-slate-500">Resumen actual</span>
                </div>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-2xl border border-slate-200/80 bg-slate-50 p-4">
                        <div class="text-sm text-slate-500">Pendientes</div>
                        <div class="mt-1 text-2xl font-semibold text-brand-navy">{{ $statusCounts['pending'] }}</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200/80 bg-slate-50 p-4">
                        <div class="text-sm text-slate-500">Revision</div>
                        <div class="mt-1 text-2xl font-semibold text-brand-navy">{{ $statusCounts['needs_review'] }}</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200/80 bg-slate-50 p-4">
                        <div class="text-sm text-slate-500">Confirmadas</div>
                        <div class="mt-1 text-2xl font-semibold text-brand-navy">{{ $statusCounts['confirmed'] }}</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200/80 bg-slate-50 p-4">
                        <div class="text-sm text-slate-500">Rechazadas</div>
                        <div class="mt-1 text-2xl font-semibold text-brand-navy">{{ $statusCounts['rejected'] }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-3">
            <div class="brand-card p-5">
                <h2 class="text-base font-semibold text-brand-navy">Sucursales con mas pedidos hoy</h2>
                <div class="mt-4 space-y-3">
                    @forelse ($topBranchesToday as $branch)
                        <div class="flex items-center justify-between rounded-2xl border border-slate-200/80 bg-slate-50 px-3 py-2.5">
                            <span class="text-sm text-slate-900">{{ $branch->name }}</span>
                            <span class="brand-badge bg-brand-primary/10 text-brand-primary">{{ $branch->requests_today_count }}</span>
                        </div>
                    @empty
                        <div class="text-sm text-slate-500">No activity today.</div>
                    @endforelse
                </div>
            </div>

            <div class="brand-card p-5">
                <h2 class="text-base font-semibold text-brand-navy">Pedidos en revision</h2>
                <div class="mt-4 space-y-3">
                    @forelse ($needsReviewRequests as $request)
                        <div class="rounded-2xl border border-slate-200/80 bg-slate-50 px-3 py-3">
                            <div class="flex items-center justify-between gap-3">
                                <div class="text-sm font-medium text-slate-900">{{ $request->branch?->name ?? '-' }}</div>
                                <div class="text-xs text-slate-500">{{ $request->created_at?->format('Y-m-d H:i') }}</div>
                            </div>
                            <div class="mt-1 text-xs text-slate-600">{{ $request->customer?->phone ?? '-' }} · {{ $request->raw_text }}</div>
                        </div>
                    @empty
                        <div class="text-sm text-slate-500">No hay pedidos en revision.</div>
                    @endforelse
                </div>
            </div>

            <div class="brand-card p-5">
                <h2 class="text-base font-semibold text-brand-navy">Cierres diarios recientes</h2>
                <div class="mt-4 space-y-3">
                    @forelse ($recentClosures as $closure)
                        <div class="rounded-2xl border border-slate-200/80 bg-slate-50 px-3 py-3">
                            <div class="flex items-center justify-between gap-3">
                                <div class="text-sm font-medium text-slate-900">{{ $closure->branch?->name ?? '-' }}</div>
                                <div class="text-xs text-slate-500">{{ $closure->closure_date?->format('Y-m-d') }}</div>
                            </div>
                            <div class="mt-1 text-xs text-slate-600">Cerrado por {{ $closure->closedByUser?->name ?? '-' }} · Pedidos {{ $closure->total_orders }}</div>
                        </div>
                    @empty
                        <div class="text-sm text-slate-500">No closures recorded yet.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <div class="brand-card p-5">
                <h2 class="text-base font-semibold text-brand-navy">Operational indicators</h2>
                <div class="mt-4 grid gap-3 sm:grid-cols-4">
                    <div class="rounded-2xl border border-slate-200/80 bg-slate-50 p-4">
                        <div class="text-sm text-slate-500">Pendientes &gt; 24h</div>
                        <div class="mt-1 text-2xl font-semibold text-brand-navy">{{ $stalePendingRequestCount }}</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200/80 bg-slate-50 p-4">
                        <div class="text-sm text-slate-500">Revision</div>
                        <div class="mt-1 text-2xl font-semibold text-brand-navy">{{ $totalNeedsReviewRequests }}</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200/80 bg-slate-50 p-4">
                        <div class="text-sm text-slate-500">Pendientes del dia anterior</div>
                        <div class="mt-1 text-2xl font-semibold text-brand-navy">{{ $previousDayPendingCount }}</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200/80 bg-slate-50 p-4">
                        <div class="text-sm text-slate-500">Productos activos</div>
                        <div class="mt-1 text-2xl font-semibold text-brand-navy">{{ $activeProductCount }}</div>
                    </div>
                </div>
            </div>

            <div class="brand-card p-5">
                <h2 class="text-base font-semibold text-brand-navy">Guia de estados</h2>
                <p class="mt-2 text-sm text-slate-600">Los pedidos pendientes y en revision permanecen visibles hasta que un usuario autorizado los confirma o rechaza.</p>
            </div>
        </div>
    </div>
</x-app-layout>
