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
                \App\Models\Order::STATUS_PENDING_REVIEW => 'bg-amber-50 text-amber-800 ring-1 ring-amber-100',
                \App\Models\Order::STATUS_CONFIRMED => 'bg-blue-50 text-blue-800 ring-1 ring-blue-100',
                \App\Models\Order::STATUS_PREPARING => 'bg-violet-50 text-violet-800 ring-1 ring-violet-100',
                \App\Models\Order::STATUS_READY_FOR_DISPATCH => 'bg-green-50 text-green-800 ring-1 ring-green-100',
                \App\Models\Order::STATUS_DISPATCHED => 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-100',
                \App\Models\Order::STATUS_CANCELLED => 'bg-red-50 text-red-800 ring-1 ring-red-100',
                \App\Models\Order::STATUS_REJECTED => 'bg-slate-100 text-slate-700 ring-1 ring-slate-200',
            ];

            $timelineEntries = $order->orderStatusHistories;
            $recognizedItems = $order->orderItems->filter(fn ($item) => $item->product !== null);
            $detectedItems = $order->orderItems;
        @endphp

        <div class="overflow-hidden rounded-[28px] border border-slate-200/70 bg-[linear-gradient(135deg,rgba(20,110,219,0.08),rgba(22,163,74,0.05)_40%,rgba(255,255,255,1)_80%)] shadow-[0_18px_50px_-28px_rgba(15,23,42,0.45)]">
            <div class="flex flex-col gap-5 p-6 sm:p-8 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-3xl">
                    <div class="text-sm font-medium uppercase tracking-wide text-slate-500">Pedido #{{ $order->id }}</div>
                    <h1 class="mt-2 text-3xl font-semibold tracking-tight text-brand-navy sm:text-4xl">Pedido #{{ $order->id }}</h1>
                    <div class="mt-4 flex flex-wrap items-center gap-3">
                        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $statusBadgeClasses[$order->status] ?? 'bg-slate-100 text-slate-700 ring-1 ring-slate-200' }}">
                            {{ $statusLabels[$order->status] ?? str_replace('_', ' ', $order->status) }}
                        </span>
                        <span class="inline-flex items-center rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-brand-primary ring-1 ring-blue-100">
                            {{ $order->source_channel ?? 'Canal no definido' }}
                        </span>
                        <span class="text-sm text-slate-600">
                            Creado el {{ $order->created_at?->format('d/m/Y H:i') ?? '—' }}
                        </span>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <a href="{{ route('orders.index') }}" class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:-translate-y-0.5 hover:border-slate-300 hover:bg-slate-50">
                        Volver a pedidos
                    </a>
                    <a href="{{ route('orders.edit', $order) }}" class="inline-flex items-center justify-center rounded-2xl bg-brand-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-blue-700">
                        Editar pedido
                    </a>
                </div>
            </div>
        </div>

        @if (session('status'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 shadow-sm">
                {{ session('status') }}
            </div>
        @endif

        @if ($order->possibleDuplicateOf)
            <div class="rounded-[24px] border border-amber-200 bg-amber-50 p-5 shadow-sm">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <div class="text-sm font-semibold uppercase tracking-wide text-amber-800">Posible pedido duplicado</div>
                        <p class="mt-2 text-sm leading-6 text-amber-900">
                            Este pedido se parece al pedido #{{ $order->possibleDuplicateOf->id }} recibido recientemente.
                        </p>
                        <p class="mt-2 text-sm leading-6 text-amber-800">
                            Score: {{ $order->duplicate_score !== null ? number_format((float) $order->duplicate_score, 0) : 'â€”' }}
                        </p>
                        @if ($order->duplicate_reason)
                            <p class="mt-1 text-sm leading-6 text-amber-800">
                                Motivo: {{ $order->duplicate_reason }}
                            </p>
                        @endif
                    </div>

                    <a href="{{ route('orders.show', $order->possibleDuplicateOf) }}" class="inline-flex items-center justify-center rounded-2xl bg-amber-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-amber-700">
                        Ver pedido original #{{ $order->possibleDuplicateOf->id }}
                    </a>
                </div>
            </div>
        @endif

        <div class="grid gap-6 xl:grid-cols-3">
            <div class="space-y-6 xl:col-span-2">
                <section class="rounded-[24px] border border-slate-200/80 border-l-4 border-l-brand-primary bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-base font-semibold text-brand-navy">Mensaje original</h2>
                        <span class="text-xs font-medium uppercase tracking-wide text-slate-500">Texto crudo recibido</span>
                    </div>
                    <div class="mt-4 whitespace-pre-wrap rounded-2xl border border-slate-200/80 bg-slate-50 p-4 text-sm leading-6 text-slate-800">
                        {{ $order->raw_message_text ?? 'Sin mensaje original' }}
                    </div>
                </section>

                <section class="rounded-[24px] border border-slate-200/80 border-l-4 border-l-slate-300 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-base font-semibold text-brand-navy">Cliente y sucursal</h2>
                        <span class="text-xs font-medium uppercase tracking-wide text-slate-500">Contexto operativo</span>
                    </div>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                            <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Cliente</div>
                            <div class="mt-1 text-sm font-semibold text-slate-900">{{ $order->customer?->name ?? 'Sin cliente' }}</div>
                            <div class="mt-1 text-sm text-slate-600">{{ $order->customer?->phone ?? 'Sin teléfono' }}</div>
                        </div>
                        <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                            <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Sucursal</div>
                            <div class="mt-1 text-sm font-semibold text-slate-900">{{ $order->branch?->name ?? 'Sin sucursal' }}</div>
                        </div>
                        <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                            <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Canal de origen</div>
                            <div class="mt-1 text-sm font-semibold text-slate-900">{{ $order->source_channel ?? '—' }}</div>
                        </div>
                        <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                            <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Confianza del parser</div>
                            <div class="mt-1 text-sm font-semibold text-slate-900">{{ $order->parser_confidence !== null ? number_format((float) $order->parser_confidence, 2) : '—' }}</div>
                        </div>
                    </div>
                </section>

                <section class="rounded-[24px] border border-slate-200/80 border-l-4 border-l-emerald-500 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-base font-semibold text-brand-navy">Items detectados</h2>
                        <span class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $detectedItems->count() }} item(s)</span>
                    </div>

                    <div class="mt-4 space-y-3">
                        @forelse ($detectedItems as $item)
                            <div class="rounded-2xl border border-slate-200/80 bg-slate-50 p-4 transition hover:bg-white">
                                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="inline-flex items-center rounded-full bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 ring-1 ring-slate-200">
                                                Item #{{ $item->id }}
                                            </span>
                                            <span class="text-sm font-medium text-slate-900">
                                                {{ $item->raw_text ?? 'Sin texto detectado' }}
                                            </span>
                                        </div>
                                        <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                            <div class="rounded-xl border border-slate-100 bg-white p-3">
                                                <div class="text-xs text-slate-500">Cantidad</div>
                                                <div class="mt-1 text-sm font-semibold text-slate-900">{{ $item->quantity }}</div>
                                            </div>
                                            <div class="rounded-xl border border-slate-100 bg-white p-3">
                                                <div class="text-xs text-slate-500">Unidad</div>
                                                <div class="mt-1 text-sm font-semibold text-slate-900">{{ $item->unit ?? '—' }}</div>
                                            </div>
                                            <div class="rounded-xl border border-slate-100 bg-white p-3">
                                                <div class="text-xs text-slate-500">Coincidencia</div>
                                                <div class="mt-1 text-sm font-semibold text-slate-900">{{ $item->matched_text ?? '—' }}</div>
                                            </div>
                                            <div class="rounded-xl border border-slate-100 bg-white p-3">
                                                <div class="text-xs text-slate-500">Confianza</div>
                                                <div class="mt-1 text-sm font-semibold text-slate-900">{{ $item->confidence_score !== null ? number_format((float) $item->confidence_score, 2) : '—' }}</div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap gap-2">
                                        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $item->product !== null ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100' : 'bg-slate-100 text-slate-700 ring-1 ring-slate-200' }}">
                                            {{ $item->product !== null ? 'Producto reconocido' : 'Sin producto asociado' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-2xl border border-dashed border-blue-200 bg-blue-50/70 px-4 py-6 text-sm text-slate-600">
                                No se detectaron items.
                            </div>
                        @endforelse
                    </div>
                </section>

                <section class="rounded-[24px] border border-slate-200/80 border-l-4 border-l-emerald-500 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-base font-semibold text-brand-navy">Productos reconocidos</h2>
                        <span class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $recognizedItems->count() }} coincidencia(s)</span>
                    </div>

                    <div class="mt-4 space-y-3">
                        @forelse ($recognizedItems as $item)
                            <div class="rounded-2xl border border-emerald-100 bg-emerald-50 p-4">
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <div class="text-sm font-semibold text-emerald-900">{{ $item->product->name }}</div>
                                        <div class="mt-1 text-sm text-emerald-800">
                                            Detectado desde: {{ $item->raw_text ?? 'sin texto' }}
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <span class="inline-flex items-center rounded-full bg-white px-3 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-200">
                                            {{ $item->confidence_score !== null ? number_format((float) $item->confidence_score, 2) : '—' }}
                                        </span>
                                        <span class="inline-flex items-center rounded-full bg-white px-3 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-200">
                                            {{ $item->unit ?? '—' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-2xl border border-dashed border-emerald-200 bg-emerald-50/70 px-4 py-6 text-sm text-slate-600">
                                No hay productos reconocidos automáticamente.
                            </div>
                        @endforelse
                    </div>
                </section>

                <section class="rounded-[24px] border border-slate-200/80 border-l-4 border-l-slate-300 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-base font-semibold text-brand-navy">Notas</h2>
                        <span class="text-xs font-medium uppercase tracking-wide text-slate-500">Observaciones internas</span>
                    </div>
                    <div class="mt-4 whitespace-pre-wrap rounded-2xl border border-slate-200/80 bg-slate-50 p-4 text-sm leading-6 text-slate-800">
                        {{ $order->notes ?? 'Sin notas registradas' }}
                    </div>
                </section>
            </div>

            <div class="space-y-6">
                <section class="rounded-[24px] border border-slate-200/80 border-l-4 border-l-brand-primary bg-white p-6 shadow-sm">
                    <h2 class="text-base font-semibold text-brand-navy">Acciones disponibles</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">
                        Ejecuta el siguiente paso operativo sin salir de la ficha del pedido.
                    </p>
                    <div class="mt-4 flex flex-col gap-3">
                        @if ($order->status === \App\Models\Order::STATUS_PENDING_REVIEW)
                            <form method="POST" action="{{ route('orders.confirm', $order) }}">
                                @csrf
                                <button type="submit" class="inline-flex w-full items-center justify-center rounded-2xl bg-brand-primary px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-blue-700">
                                    Confirmar pedido
                                </button>
                            </form>
                            <form method="POST" action="{{ route('orders.reject', $order) }}">
                                @csrf
                                <button type="submit" class="inline-flex w-full items-center justify-center rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700 transition hover:bg-red-100">
                                    Rechazar pedido
                                </button>
                            </form>
                            <form method="POST" action="{{ route('orders.cancel', $order) }}">
                                @csrf
                                <button type="submit" class="inline-flex w-full items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                                    Cancelar pedido
                                </button>
                            </form>
                        @elseif ($order->status === \App\Models\Order::STATUS_CONFIRMED)
                            <form method="POST" action="{{ route('orders.prepare', $order) }}">
                                @csrf
                                <button type="submit" class="inline-flex w-full items-center justify-center rounded-2xl bg-brand-primary px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-blue-700">
                                    Iniciar preparación
                                </button>
                            </form>
                            <form method="POST" action="{{ route('orders.cancel', $order) }}">
                                @csrf
                                <button type="submit" class="inline-flex w-full items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                                    Cancelar pedido
                                </button>
                            </form>
                        @elseif ($order->status === \App\Models\Order::STATUS_PREPARING)
                            <form method="POST" action="{{ route('orders.ready-for-dispatch', $order) }}">
                                @csrf
                                <button type="submit" class="inline-flex w-full items-center justify-center rounded-2xl bg-brand-primary px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-blue-700">
                                    Marcar listo para despacho
                                </button>
                            </form>
                            <form method="POST" action="{{ route('orders.cancel', $order) }}">
                                @csrf
                                <button type="submit" class="inline-flex w-full items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                                    Cancelar pedido
                                </button>
                            </form>
                        @elseif ($order->status === \App\Models\Order::STATUS_READY_FOR_DISPATCH)
                            <form method="POST" action="{{ route('orders.dispatch', $order) }}">
                                @csrf
                                <button type="submit" class="inline-flex w-full items-center justify-center rounded-2xl bg-brand-primary px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-blue-700">
                                    Marcar despachado
                                </button>
                            </form>
                            <form method="POST" action="{{ route('orders.cancel', $order) }}">
                                @csrf
                                <button type="submit" class="inline-flex w-full items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                                    Cancelar pedido
                                </button>
                            </form>
                        @else
                            <div class="rounded-2xl border border-slate-200/80 bg-slate-50 p-4 text-sm leading-6 text-slate-600">
                                No hay acciones operativas disponibles para este pedido en su estado actual.
                            </div>
                        @endif
                    </div>
                </section>

                <section class="rounded-[24px] border border-slate-200/80 border-l-4 border-l-slate-300 bg-white p-6 shadow-sm">
                    <h2 class="text-base font-semibold text-brand-navy">Línea de tiempo</h2>
                    <div class="mt-4 space-y-3">
                        @forelse ($timelineEntries as $history)
                            <div class="rounded-2xl border border-slate-200/80 bg-slate-50 p-4 transition hover:bg-white">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="text-sm font-semibold text-slate-900">
                                        {{ $statusLabels[$history->to_status] ?? str_replace('_', ' ', $history->to_status) }}
                                    </div>
                                    <div class="text-xs text-slate-500">{{ $history->created_at?->format('d/m/Y H:i') ?? '—' }}</div>
                                </div>
                                <div class="mt-1 text-xs text-slate-500">
                                    Desde {{ $history->from_status ? ($statusLabels[$history->from_status] ?? str_replace('_', ' ', $history->from_status)) : '—' }}
                                    · {{ $history->changedByUser?->name ?? 'System' }}
                                </div>
                                @if ($history->reason)
                                    <div class="mt-2 text-sm leading-6 text-slate-700">{{ $history->reason }}</div>
                                @endif
                            </div>
                        @empty
                            <div class="rounded-2xl border border-dashed border-emerald-200 bg-emerald-50/70 px-4 py-6 text-sm text-slate-600">
                                No hay historial de estado todavía.
                            </div>
                        @endforelse
                    </div>
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
