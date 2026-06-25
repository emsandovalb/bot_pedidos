<x-app-layout>
    <div class="space-y-8">
        @php
            $statusLabels = [
                \App\Models\Order::STATUS_PENDING_REVIEW => 'Pendiente de revisión',
                \App\Models\Order::STATUS_CONFIRMED => 'Confirmado',
                \App\Models\Order::STATUS_PREPARING => 'En preparación',
                \App\Models\Order::STATUS_READY_FOR_DISPATCH => 'Listo para despacho',
                \App\Models\Order::STATUS_DISPATCHED => 'Despachado',
                \App\Models\Order::STATUS_CANCELLED => 'Cancelado',
                \App\Models\Order::STATUS_REJECTED => 'Rechazado',
            ];

            $statusClasses = [
                \App\Models\Order::STATUS_PENDING_REVIEW => 'bg-amber-50 text-amber-800 ring-1 ring-amber-100',
                \App\Models\Order::STATUS_CONFIRMED => 'bg-blue-50 text-blue-800 ring-1 ring-blue-100',
                \App\Models\Order::STATUS_PREPARING => 'bg-violet-50 text-violet-800 ring-1 ring-violet-100',
                \App\Models\Order::STATUS_READY_FOR_DISPATCH => 'bg-sky-50 text-sky-800 ring-1 ring-sky-100',
                \App\Models\Order::STATUS_DISPATCHED => 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-100',
                \App\Models\Order::STATUS_CANCELLED => 'bg-slate-100 text-slate-700 ring-1 ring-slate-200',
                \App\Models\Order::STATUS_REJECTED => 'bg-rose-50 text-rose-800 ring-1 ring-rose-100',
            ];

            $displayName = $customer->name ?: $customer->phone ?: $customer->external_id ?: 'Cliente sin nombre';
        @endphp

        <section class="overflow-hidden rounded-[28px] border border-slate-200/70 bg-[linear-gradient(135deg,rgba(20,110,219,0.08),rgba(22,163,74,0.06)_38%,rgba(255,255,255,1)_82%)] shadow-[0_18px_50px_-28px_rgba(15,23,42,0.4)]">
            <div class="flex flex-col gap-6 p-6 sm:p-8 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-3xl">
                    <div class="inline-flex items-center rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-brand-primary ring-1 ring-inset ring-blue-100">
                        Benditio Customer Identity
                    </div>
                    <h1 class="mt-4 text-3xl font-semibold tracking-tight text-brand-navy sm:text-4xl">Cliente</h1>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600 sm:text-base">
                        Vista unificada de pedidos, canales e identidades.
                    </p>
                </div>

                <a href="{{ route('customers.index') }}" class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:-translate-y-0.5 hover:border-slate-300 hover:bg-slate-50">
                    Volver a clientes
                </a>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            <div class="rounded-[24px] border border-slate-200/70 bg-white p-5 shadow-sm">
                <div class="text-sm font-medium text-slate-500">Cliente</div>
                <div class="mt-2 text-xl font-semibold text-brand-navy">{{ $displayName }}</div>
                <div class="mt-1 text-sm text-slate-600">{{ $customer->phone ?? 'Sin teléfono' }}</div>
            </div>

            <div class="rounded-[24px] border border-slate-200/70 bg-white p-5 shadow-sm">
                <div class="text-sm font-medium text-slate-500">Total de pedidos</div>
                <div class="mt-2 text-3xl font-semibold tracking-tight text-brand-navy">{{ $customer->orders_count }}</div>
            </div>

            <div class="rounded-[24px] border border-slate-200/70 bg-white p-5 shadow-sm">
                <div class="text-sm font-medium text-slate-500">Identidades</div>
                <div class="mt-2 text-3xl font-semibold tracking-tight text-brand-navy">{{ $customer->customer_identities_count }}</div>
            </div>

            <div class="rounded-[24px] border border-slate-200/70 bg-white p-5 shadow-sm">
                <div class="text-sm font-medium text-slate-500">Última actividad</div>
                <div class="mt-2 text-lg font-semibold text-brand-navy">{{ $latestActivityAt?->format('d/m/Y H:i') ?? 'Sin actividad' }}</div>
            </div>

            <div class="rounded-[24px] border border-slate-200/70 bg-white p-5 shadow-sm">
                <div class="text-sm font-medium text-slate-500">Posibles duplicados</div>
                <div class="mt-2 text-3xl font-semibold tracking-tight text-brand-navy">{{ $possibleDuplicateOrdersCount }}</div>
            </div>
        </section>

        <section class="rounded-[28px] border border-slate-200/70 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-brand-navy">Customer Insights</h2>
                    <p class="mt-1 text-sm text-slate-500">Señales operativas calculadas en solo lectura sobre el historial del cliente.</p>
                </div>
            </div>

            @php
                $segmentClasses = [
                    'NEW' => 'bg-sky-50 text-sky-800 ring-1 ring-sky-100',
                    'FREQUENT' => 'bg-blue-50 text-blue-800 ring-1 ring-blue-100',
                    'VIP' => 'bg-amber-50 text-amber-800 ring-1 ring-amber-100',
                    'INACTIVE' => 'bg-slate-100 text-slate-700 ring-1 ring-slate-200',
                ];
            @endphp

            <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <article class="rounded-2xl border border-slate-200/80 bg-slate-50/80 p-4">
                    <div class="text-sm font-medium text-slate-500">Segment</div>
                    <div class="mt-3">
                        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $segmentClasses[$customerInsights->segment] ?? 'bg-slate-100 text-slate-700 ring-1 ring-slate-200' }}">
                            {{ $customerInsights->segment }}
                        </span>
                    </div>
                    <div class="mt-3 text-sm text-slate-600">Total orders: {{ $customerInsights->total_orders }}</div>
                </article>

                <article class="rounded-2xl border border-slate-200/80 bg-slate-50/80 p-4">
                    <div class="text-sm font-medium text-slate-500">Favorite channel</div>
                    <div class="mt-3 text-2xl font-semibold text-brand-navy">{{ $customerInsights->favorite_channel['name'] ?? 'Unknown' }}</div>
                    <div class="mt-2 text-sm text-slate-600">{{ number_format((float) ($customerInsights->favorite_channel['percentage'] ?? 0), 0) }}% of orders</div>
                </article>

                <article class="rounded-2xl border border-slate-200/80 bg-slate-50/80 p-4">
                    <div class="text-sm font-medium text-slate-500">Favorite hour</div>
                    <div class="mt-3 text-2xl font-semibold text-brand-navy">{{ $customerInsights->favorite_hour ?? 'Sin datos' }}</div>
                    <div class="mt-2 text-sm text-slate-600">Most common order hour</div>
                </article>

                <article class="rounded-2xl border border-slate-200/80 bg-slate-50/80 p-4">
                    <div class="text-sm font-medium text-slate-500">Average frequency</div>
                    <div class="mt-3 text-2xl font-semibold text-brand-navy">
                        {{ $customerInsights->average_days !== null ? number_format((float) $customerInsights->average_days, 1) . ' days' : 'Sin datos' }}
                    </div>
                    <div class="mt-2 text-sm text-slate-600">Average days between purchases</div>
                </article>

                <article class="rounded-2xl border border-slate-200/80 bg-slate-50/80 p-4">
                    <div class="text-sm font-medium text-slate-500">Inactive days</div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @forelse ($customerInsights->inactive_days as $day)
                            <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700 ring-1 ring-slate-200">
                                {{ $day }}
                            </span>
                        @empty
                            <span class="text-sm text-slate-600">Sin datos</span>
                        @endforelse
                    </div>
                </article>

                <article class="rounded-2xl border border-slate-200/80 bg-slate-50/80 p-4">
                    <div class="text-sm font-medium text-slate-500">Favorite products</div>
                    <div class="mt-3 space-y-3">
                        @forelse ($customerInsights->favorite_products as $favoriteProduct)
                            <div class="flex items-center justify-between gap-3 rounded-xl bg-white px-3 py-2 ring-1 ring-slate-200">
                                <span class="min-w-0 truncate text-sm font-medium text-brand-navy">{{ $favoriteProduct['product'] }}</span>
                                <span class="inline-flex shrink-0 items-center rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-brand-primary ring-1 ring-blue-100">
                                    {{ $favoriteProduct['times_ordered'] }}
                                </span>
                            </div>
                        @empty
                            <div class="rounded-xl border border-dashed border-slate-200 bg-white px-3 py-4 text-sm text-slate-600">
                                No product history yet.
                            </div>
                        @endforelse
                    </div>
                </article>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-2">
            <div class="rounded-[28px] border border-slate-200/70 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-brand-navy">Identidades del cliente</h2>
                        <p class="mt-1 text-sm text-slate-500">Trazabilidad por canal e identificador.</p>
                    </div>
                </div>

                <div class="mt-5 space-y-4">
                    @forelse ($customerIdentities as $identity)
                        <article class="rounded-2xl border border-slate-200/80 bg-slate-50/80 p-4">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex items-center rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-brand-primary ring-1 ring-blue-100">
                                    {{ \Illuminate\Support\Str::headline((string) $identity->provider) }}
                                </span>
                                @if ($identity->is_primary)
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-100">
                                        Principal
                                    </span>
                                @endif
                                <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 ring-1 ring-slate-200">
                                    Confianza {{ number_format((float) $identity->confidence_score, 0) }}%
                                </span>
                            </div>

                            <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                <div>
                                    <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Phone</div>
                                    <div class="mt-1 text-sm font-semibold text-brand-navy">{{ $identity->phone ?? '—' }}</div>
                                </div>
                                <div>
                                    <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Username</div>
                                    <div class="mt-1 text-sm font-semibold text-brand-navy">{{ $identity->provider_username ?? '—' }}</div>
                                </div>
                                <div>
                                    <div class="text-xs font-medium uppercase tracking-wide text-slate-500">External user ID</div>
                                    <div class="mt-1 text-sm font-semibold text-brand-navy">{{ $identity->external_user_id ?? '—' }}</div>
                                </div>
                                <div>
                                    <div class="text-xs font-medium uppercase tracking-wide text-slate-500">External chat ID</div>
                                    <div class="mt-1 text-sm font-semibold text-brand-navy">{{ $identity->external_chat_id ?? '—' }}</div>
                                </div>
                                <div>
                                    <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Primer visto</div>
                                    <div class="mt-1 text-sm font-semibold text-brand-navy">{{ $identity->first_seen_at?->format('d/m/Y H:i') ?? '—' }}</div>
                                </div>
                                <div>
                                    <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Último visto</div>
                                    <div class="mt-1 text-sm font-semibold text-brand-navy">{{ $identity->last_seen_at?->format('d/m/Y H:i') ?? '—' }}</div>
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-sm text-slate-600">
                            Este cliente todavía no tiene identidades registradas.
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="rounded-[28px] border border-slate-200/70 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-brand-navy">Estados de pedidos</h2>
                        <p class="mt-1 text-sm text-slate-500">Conteo por estado dentro del cliente.</p>
                    </div>
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($statusLabels as $status => $label)
                        <div class="rounded-2xl border border-slate-200/80 bg-slate-50/80 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <div class="text-sm font-medium text-slate-500">{{ $label }}</div>
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClasses[$status] }}">
                                    {{ $orderStatusCounts[$status] ?? 0 }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-6 rounded-2xl border border-slate-200/80 bg-slate-50/80 p-4">
                    <h3 class="text-sm font-semibold uppercase tracking-[0.16em] text-slate-500">Alertas</h3>
                    <div class="mt-3 space-y-3 text-sm text-slate-700">
                        @if ($customerIdentities->count() > 1)
                            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-amber-900">
                                Este cliente tiene múltiples identidades registradas.
                            </div>
                        @endif

                        @if ($possibleDuplicateOrdersCount > 0)
                            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-amber-900">
                                Existen pedidos marcados como posibles duplicados.
                            </div>
                        @endif

                        @if ($hasAmbiguousIdentities)
                            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-amber-900">
                                Hay metadatos de identidad que sugieren una coincidencia ambigua.
                            </div>
                        @endif

                        @if ($customerIdentities->count() === 1 && $possibleDuplicateOrdersCount === 0 && ! $hasAmbiguousIdentities)
                            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-slate-600">
                                No hay alertas relevantes para este cliente.
                            </div>
                        @endif
                    </div>

                    @if ($duplicateOrders->isNotEmpty())
                        <div class="mt-5 space-y-3">
                            @foreach ($duplicateOrders as $duplicateOrder)
                                <article class="rounded-2xl border border-amber-200 bg-white p-4">
                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                        <div>
                                            <div class="text-sm font-semibold text-brand-navy">Pedido #{{ $duplicateOrder->id }}</div>
                                            <div class="mt-1 text-xs text-slate-500">
                                                Marcado como posible duplicado de #{{ $duplicateOrder->possibleDuplicateOf?->id ?? '—' }}
                                            </div>
                                        </div>
                                        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $statusClasses[$duplicateOrder->status] ?? 'bg-slate-100 text-slate-700 ring-1 ring-slate-200' }}">
                                            {{ $statusLabels[$duplicateOrder->status] ?? $duplicateOrder->status }}
                                        </span>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </section>

        <section class="rounded-[28px] border border-slate-200/70 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-brand-navy">Pedidos recientes</h2>
                    <p class="mt-1 text-sm text-slate-500">Últimos pedidos relacionados con este cliente.</p>
                </div>
            </div>

            <div class="mt-5 space-y-3">
                @forelse ($recentOrders as $order)
                    <article class="rounded-2xl border border-slate-200/80 bg-slate-50/80 p-4 transition hover:-translate-y-0.5 hover:bg-white">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0 flex-1 space-y-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-sm font-semibold text-brand-navy">#{{ $order->id }}</span>
                                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $statusClasses[$order->status] ?? 'bg-slate-100 text-slate-700 ring-1 ring-slate-200' }}">
                                        {{ $statusLabels[$order->status] ?? $order->status }}
                                    </span>
                                    <span class="inline-flex items-center rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-brand-primary ring-1 ring-blue-100">
                                        {{ \Illuminate\Support\Str::headline((string) $order->source_channel) }}
                                    </span>
                                    @if ($order->possibleDuplicateOf)
                                        <span class="inline-flex items-center rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800 ring-1 ring-amber-100">
                                            Posible duplicado
                                        </span>
                                    @endif
                                </div>
                                <div class="text-sm text-slate-500">{{ $order->created_at?->format('d/m/Y H:i') ?? '—' }}</div>
                                <p class="text-sm leading-6 text-slate-700">{{ \Illuminate\Support\Str::limit($order->raw_message_text ?? 'Sin mensaje original', 180) }}</p>
                            </div>

                            <div class="flex shrink-0 flex-col gap-3 lg:w-40">
                                <a href="{{ route('orders.show', $order) }}" class="inline-flex items-center justify-center rounded-2xl bg-brand-primary px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-blue-700">
                                    Ver pedido
                                </a>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-sm text-slate-600">
                        No hay pedidos recientes para mostrar.
                    </div>
                @endforelse
            </div>
        </section>
    </div>
</x-app-layout>
