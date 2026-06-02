<x-app-layout>
    <div class="space-y-6">
        @php
            $statusLabels = [
                \App\Models\Order::STATUS_PENDING_REVIEW => 'En revisión',
                \App\Models\Order::STATUS_CONFIRMED => 'Confirmado',
                \App\Models\Order::STATUS_PREPARING => 'En preparación',
                \App\Models\Order::STATUS_READY_FOR_DISPATCH => 'Listo para despacho',
                \App\Models\Order::STATUS_DISPATCHED => 'Despachado',
                \App\Models\Order::STATUS_CANCELLED => 'Cancelado',
                \App\Models\Order::STATUS_REJECTED => 'Rechazado',
            ];

            $statusBadgeClasses = [
                \App\Models\Order::STATUS_PENDING_REVIEW => 'bg-amber-100 text-amber-800 ring-1 ring-amber-200',
                \App\Models\Order::STATUS_CONFIRMED => 'bg-blue-100 text-blue-800 ring-1 ring-blue-200',
                \App\Models\Order::STATUS_PREPARING => 'bg-violet-100 text-violet-800 ring-1 ring-violet-200',
                \App\Models\Order::STATUS_READY_FOR_DISPATCH => 'bg-green-100 text-green-800 ring-1 ring-green-200',
                \App\Models\Order::STATUS_DISPATCHED => 'bg-emerald-100 text-emerald-800 ring-1 ring-emerald-200',
                \App\Models\Order::STATUS_CANCELLED => 'bg-red-100 text-red-800 ring-1 ring-red-200',
                \App\Models\Order::STATUS_REJECTED => 'bg-slate-100 text-slate-700 ring-1 ring-slate-200',
            ];

            $statusFilters = [
                null => 'Todos',
                \App\Models\Order::STATUS_PENDING_REVIEW => 'En revisión',
                \App\Models\Order::STATUS_CONFIRMED => 'Confirmados',
                \App\Models\Order::STATUS_PREPARING => 'En preparación',
                \App\Models\Order::STATUS_READY_FOR_DISPATCH => 'Listos para despacho',
                \App\Models\Order::STATUS_DISPATCHED => 'Despachados',
                \App\Models\Order::STATUS_CANCELLED => 'Cancelados',
            ];

            $currentStatus = $filters['status'] ?? null;
        @endphp

        <div class="overflow-hidden rounded-[28px] border border-slate-200/70 bg-gradient-to-br from-white via-white to-slate-50 shadow-[0_18px_50px_-28px_rgba(15,23,42,0.45)]">
            <div class="flex flex-col gap-6 p-6 sm:p-8 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-3xl">
                    <div class="inline-flex items-center rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-brand-primary ring-1 ring-inset ring-blue-100">
                        Benditio Operations
                    </div>
                    <h1 class="mt-4 text-3xl font-semibold tracking-tight text-brand-navy sm:text-4xl">Pedidos</h1>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600 sm:text-base">
                        Gestiona pedidos recibidos por Telegram y canales conectados.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <a href="{{ route('order-reviews.index') }}" class="inline-flex items-center justify-center rounded-2xl bg-brand-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700">
                        Bandeja de revisión
                    </a>
                    <a href="{{ route('dashboard') }}" class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50">
                        Ir al panel
                    </a>
                </div>
            </div>
        </div>

        <div class="rounded-[24px] border border-slate-200/70 bg-white p-4 shadow-sm sm:p-5">
            <div class="flex flex-wrap gap-2">
                @foreach ($statusFilters as $statusValue => $label)
                    @php
                        $isActive = (string) $currentStatus === (string) $statusValue || ($statusValue === null && empty($currentStatus));
                        $pillClass = $isActive
                            ? 'border-brand-primary bg-blue-50 text-brand-primary ring-1 ring-blue-100'
                            : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:bg-slate-50';
                        $query = $statusValue ? ['status' => $statusValue] : [];
                    @endphp
                    <a href="{{ route('orders.index', $query) }}" class="inline-flex items-center rounded-full border px-4 py-2 text-sm font-medium transition {{ $pillClass }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        </div>

        <div class="space-y-4">
            @forelse ($orders as $order)
                @php
                    $isPending = $order->status === \App\Models\Order::STATUS_PENDING_REVIEW;
                    $recognizedCount = (int) ($order->recognized_order_items_count ?? 0);
                    $hasRecognizedProducts = $recognizedCount > 0;
                    $parserConfidence = $order->parser_confidence !== null ? number_format((float) $order->parser_confidence, 2) : '—';
                    $rawPreview = \Illuminate\Support\Str::limit($order->raw_message_text ?? 'Sin mensaje original', 180);
                @endphp

                <article class="rounded-[28px] border border-slate-200/80 bg-white p-5 shadow-[0_18px_50px_-32px_rgba(15,23,42,0.35)] transition hover:-translate-y-0.5 hover:shadow-[0_24px_60px_-34px_rgba(15,23,42,0.42)] {{ $isPending ? 'ring-1 ring-amber-100' : '' }}">
                    <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0 flex-1 space-y-4">
                            <div class="flex flex-wrap items-center gap-3">
                                <div class="rounded-2xl bg-slate-50 px-3 py-2">
                                    <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Pedido</div>
                                    <div class="text-lg font-semibold text-brand-navy">#{{ $order->id }}</div>
                                </div>

                                <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $statusBadgeClasses[$order->status] ?? 'bg-slate-100 text-slate-700 ring-1 ring-slate-200' }}">
                                    {{ $statusLabels[$order->status] ?? str_replace('_', ' ', $order->status) }}
                                </span>
                                @if ($order->status === \App\Models\Order::STATUS_PENDING_REVIEW)
                                    <span class="text-xs font-medium text-slate-500">Pendiente de revisión</span>
                                @endif

                                @if ($isPending)
                                    <span class="inline-flex items-center rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800 ring-1 ring-amber-100">
                                        En revisión prioritaria
                                    </span>
                                @endif
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                <div class="rounded-2xl bg-slate-50 p-4">
                                    <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Cliente</div>
                                    <div class="mt-1 text-sm font-semibold text-slate-900">{{ $order->customer?->name ?? 'Sin cliente' }}</div>
                                    <div class="mt-1 text-sm text-slate-600">{{ $order->customer?->phone ?? 'Sin teléfono' }}</div>
                                </div>
                                <div class="rounded-2xl bg-slate-50 p-4">
                                    <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Canal</div>
                                    <div class="mt-1 text-sm font-semibold text-slate-900">{{ $order->source_channel ?? '—' }}</div>
                                </div>
                                <div class="rounded-2xl bg-slate-50 p-4">
                                    <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Confianza del parser</div>
                                    <div class="mt-1 text-sm font-semibold text-slate-900">{{ $parserConfidence }}</div>
                                </div>
                                <div class="rounded-2xl bg-slate-50 p-4">
                                    <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Creado</div>
                                    <div class="mt-1 text-sm font-semibold text-slate-900">{{ $order->created_at?->format('d/m/Y H:i') ?? '—' }}</div>
                                </div>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="rounded-2xl border border-slate-200/80 bg-white p-4">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="text-sm font-semibold text-brand-navy">Reconocimiento de productos</div>
                                        @if ($hasRecognizedProducts)
                                            <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-100">
                                                {{ $recognizedCount }} detectado(s)
                                            </span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700 ring-1 ring-amber-100">
                                                Pendiente de clasificar
                                            </span>
                                        @endif
                                    </div>
                                    <p class="mt-2 text-sm leading-6 text-slate-600">
                                        @if ($hasRecognizedProducts)
                                            Hay coincidencias automáticas con el catálogo.
                                        @else
                                            Aún no hay productos reconocidos automáticamente.
                                        @endif
                                    </p>
                                </div>

                                <div class="rounded-2xl border border-slate-200/80 bg-white p-4">
                                    <div class="text-sm font-semibold text-brand-navy">Mensaje original</div>
                                    <p class="mt-2 text-sm leading-6 text-slate-600">{{ $rawPreview }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="flex shrink-0 flex-col gap-3 lg:w-52">
                            <a href="{{ route('orders.show', $order) }}" class="inline-flex items-center justify-center rounded-2xl bg-brand-primary px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700">
                                Ver pedido
                            </a>
                            <div class="rounded-2xl border border-slate-200/80 bg-slate-50 px-4 py-3 text-xs leading-5 text-slate-500">
                                Pedido listo para seguimiento operativo y revisión rápida.
                            </div>
                        </div>
                    </div>
                </article>
            @empty
                <div class="rounded-[28px] border border-dashed border-slate-300 bg-white px-6 py-14 text-center shadow-sm">
                    <div class="mx-auto max-w-md">
                        <h2 class="text-lg font-semibold text-brand-navy">No hay pedidos para mostrar</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-600">Cuando lleguen nuevos pedidos por Telegram o por los canales conectados, aparecerán aquí con su estado y nivel de reconocimiento.</p>
                    </div>
                </div>
            @endforelse
        </div>

        <div class="rounded-[24px] border border-slate-200/70 bg-white p-4 shadow-sm sm:p-5">
            {{ $orders->links() }}
        </div>
    </div>
</x-app-layout>
