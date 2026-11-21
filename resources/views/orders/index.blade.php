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
                \App\Models\Order::STATUS_PENDING_REVIEW => 'bg-amber-50 text-amber-800',
                \App\Models\Order::STATUS_CONFIRMED => 'bg-blue-50 text-blue-800',
                \App\Models\Order::STATUS_PREPARING => 'bg-violet-50 text-violet-800',
                \App\Models\Order::STATUS_READY_FOR_DISPATCH => 'bg-green-50 text-green-800',
                \App\Models\Order::STATUS_DISPATCHED => 'bg-emerald-50 text-emerald-800',
                \App\Models\Order::STATUS_CANCELLED => 'bg-red-50 text-red-800',
                \App\Models\Order::STATUS_REJECTED => 'bg-slate-100 text-slate-700',
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
            $currentChannel = $filters['channel'] ?? null;
            $currentSearch = trim((string) ($filters['q'] ?? ''));
            $channelLabels = ['whatsapp' => 'WhatsApp', 'telegram' => 'Telegram', 'instagram' => 'Instagram'];
        @endphp

        <x-page-header title="Pedidos" subtitle="Pedidos recibidos por WhatsApp, Telegram y los canales conectados.">
            <x-slot:actions>
                <a href="{{ route('dashboard') }}" class="brand-btn-secondary">Ir al panel</a>
                <a href="{{ route('order-reviews.index') }}" class="brand-btn-primary">Bandeja de revisión</a>
            </x-slot:actions>
        </x-page-header>

        <nav class="filter-tabs" aria-label="Filtrar pedidos por estado">
            @foreach ($statusFilters as $statusValue => $label)
                @php
                    $isActive = (string) $currentStatus === (string) $statusValue || ($statusValue === null && empty($currentStatus));
                    // A status tab keeps the channel and the search already chosen.
                    $query = array_filter([
                        'status' => $statusValue,
                        'channel' => $currentChannel,
                        'q' => $currentSearch,
                    ], static fn ($value) => $value !== null && $value !== '');
                    $count = $statusValue ? ($statusCounts[$statusValue] ?? 0) : array_sum($statusCounts);
                @endphp
                <a href="{{ route('orders.index', $query) }}" @class(['filter-tab', 'is-active' => $isActive]) @if ($isActive) aria-current="page" @endif>
                    {{ $label }} <span class="count">{{ $count }}</span>
                </a>
            @endforeach
        </nav>

        <form method="GET" action="{{ route('orders.index') }}" class="flex flex-col gap-3 sm:flex-row sm:items-end" role="search">
            @if ($currentStatus)
                <input type="hidden" name="status" value="{{ $currentStatus }}">
            @endif
            <label class="flex min-w-0 flex-1 flex-col gap-1.5 text-sm font-bold text-brand-navy">
                Buscar
                <input type="search" name="q" value="{{ $currentSearch }}" maxlength="100"
                    placeholder="Nombre o teléfono del cliente, o número de pedido"
                    class="brand-input h-11 w-full rounded-xl text-sm">
            </label>
            <label class="flex flex-col gap-1.5 text-sm font-bold text-brand-navy sm:w-48">
                Canal
                <select name="channel" class="brand-input h-11 rounded-xl text-sm">
                    <option value="">Todos los canales</option>
                    @foreach ($channelOptions as $channel)
                        <option value="{{ $channel }}" @selected($currentChannel === $channel)>{{ $channelLabels[$channel] ?? ucfirst($channel) }}</option>
                    @endforeach
                </select>
            </label>
            <div class="flex gap-2">
                <button type="submit" class="brand-btn-primary h-11">Buscar</button>
                @if ($currentChannel || $currentSearch)
                    <a href="{{ route('orders.index', array_filter(['status' => $currentStatus])) }}" class="brand-btn-secondary h-11">Limpiar</a>
                @endif
            </div>
        </form>

        <p class="-mt-2 text-sm text-slate-500" data-orders-total>
            {{ $orders->total() }} {{ $orders->total() === 1 ? 'pedido' : 'pedidos' }}
        </p>

        <div class="space-y-3">
            @forelse ($orders as $order)
                @php
                    $isPending = $order->status === \App\Models\Order::STATUS_PENDING_REVIEW;
                    $recognizedCount = (int) ($order->recognized_order_items_count ?? 0);
                    $hasRecognizedProducts = $recognizedCount > 0;
                    $parserConfidence = $order->parser_confidence !== null ? number_format((float) $order->parser_confidence * 100) . ' %' : '—';
                    $rawPreview = \Illuminate\Support\Str::limit($order->raw_message_text ?? 'Sin mensaje original', 180);
                @endphp

                <article class="grid gap-4 rounded-[20px] border border-slate-200 bg-white p-4 shadow-sm transition hover:border-slate-300 hover:shadow-card sm:p-5 lg:grid-cols-[minmax(0,1fr)_200px] lg:gap-x-6">
                    <div class="min-w-0 space-y-3.5">
                        <div class="flex flex-wrap items-center gap-2">
                            <a href="{{ route('orders.show', $order) }}" class="mr-1 font-display text-xl font-extrabold tracking-tight text-brand-navy hover:text-brand-primary">#{{ $order->id }}</a>
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-bold {{ $statusBadgeClasses[$order->status] ?? 'bg-slate-100 text-slate-700' }}">
                                {{ $statusLabels[$order->status] ?? str_replace('_', ' ', $order->status) }}
                            </span>
                            @if ($order->possibleDuplicateOf)
                                <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-bold text-amber-800">Posible duplicado</span>
                            @endif
                            @if ($isPending)
                                <span class="inline-flex items-center rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-bold text-red-700" title="Pendiente de revisión">En revisión prioritaria</span>
                            @endif
                            <span class="ml-auto text-xs font-medium text-slate-500">{{ $order->created_at?->format('d/m/Y · H:i') ?? '—' }}</span>
                        </div>

                        <dl class="grid grid-cols-2 overflow-hidden rounded-2xl border border-slate-200 sm:grid-cols-4">
                            <div class="border-b border-slate-200 px-3.5 py-2.5 sm:border-b-0">
                                <dt class="text-[0.68rem] font-extrabold uppercase tracking-[0.08em] text-slate-500">Cliente</dt>
                                <dd class="truncate text-sm font-bold text-brand-navy">{{ $order->customer?->name ?? 'Sin cliente' }}</dd>
                                <dd class="truncate text-xs text-slate-500">{{ $order->customer?->phone ?? 'Sin teléfono' }}</dd>
                            </div>
                            <div class="border-b border-l border-slate-200 px-3.5 py-2.5 sm:border-b-0">
                                <dt class="text-[0.68rem] font-extrabold uppercase tracking-[0.08em] text-slate-500">Canal</dt>
                                <dd class="truncate text-sm font-bold capitalize text-brand-navy">{{ $order->source_channel ?? '—' }}</dd>
                            </div>
                            <div class="px-3.5 py-2.5 sm:border-l sm:border-slate-200">
                                <dt class="text-[0.68rem] font-extrabold uppercase tracking-[0.08em] text-slate-500">Sucursal</dt>
                                <dd class="truncate text-sm font-bold text-brand-navy">{{ $order->branch?->name ?? '—' }}</dd>
                            </div>
                            <div class="border-l border-slate-200 px-3.5 py-2.5">
                                <dt class="text-[0.68rem] font-extrabold uppercase tracking-[0.08em] text-slate-500">Confianza</dt>
                                <dd class="text-sm font-bold text-brand-navy">{{ $parserConfidence }}</dd>
                            </div>
                        </dl>

                        @if ($order->possibleDuplicateOf)
                            <p class="rounded-xl bg-amber-50 px-3.5 py-2 text-sm text-amber-900">Similar al pedido #{{ $order->possibleDuplicateOf->id }}</p>
                        @endif

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="rounded-2xl bg-slate-50 px-3.5 py-3">
                                <h3 class="text-xs font-bold text-slate-500">Mensaje original</h3>
                                <p class="mt-1 text-sm font-semibold leading-6 text-brand-navy">“{{ $rawPreview }}”</p>
                            </div>
                            <div class="rounded-2xl bg-slate-50 px-3.5 py-3">
                                <div class="flex items-center justify-between gap-2">
                                    <h3 class="text-xs font-bold text-slate-500">Productos reconocidos</h3>
                                    @if ($hasRecognizedProducts)
                                        <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[0.7rem] font-bold text-emerald-700">{{ $recognizedCount }} detectado(s)</span>
                                    @else
                                        <span class="rounded-full bg-amber-50 px-2 py-0.5 text-[0.7rem] font-bold text-amber-700">Pendiente de clasificar</span>
                                    @endif
                                </div>
                                <p class="mt-1 text-sm leading-6 text-slate-600">
                                    @if ($hasRecognizedProducts)
                                        Hay coincidencias automáticas con el catálogo.
                                    @else
                                        Aún no hay productos reconocidos automáticamente.
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col gap-2">
                        <a href="{{ route('orders.show', $order) }}" class="brand-btn-primary py-3">Ver pedido</a>
                        @if ($isPending)
                            <a href="{{ route('order-reviews.index') }}" class="brand-btn-secondary">Ir a revisión</a>
                        @endif
                    </div>
                </article>
            @empty
                <div class="rounded-[20px] border border-dashed border-slate-300 bg-white px-6 py-14 text-center">
                    <div class="mx-auto max-w-md">
                        <h2 class="text-lg font-bold text-brand-navy">No hay pedidos para mostrar</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-600">Cuando lleguen nuevos pedidos por Telegram o por los canales conectados, aparecerán aquí con su estado y nivel de reconocimiento.</p>
                    </div>
                </div>
            @endforelse
        </div>

        <div class="rounded-[20px] border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            {{ $orders->links() }}
        </div>
    </div>
</x-app-layout>
