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
        @endphp

        <div class="overflow-hidden rounded-[28px] border border-slate-200/70 bg-gradient-to-br from-white via-white to-slate-50 shadow-[0_18px_50px_-28px_rgba(15,23,42,0.45)]">
            <div class="flex flex-col gap-5 p-6 sm:p-8 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-3xl">
                    <div class="text-sm font-medium uppercase tracking-wide text-slate-500">Pedido #{{ $order->id }}</div>
                    <h1 class="mt-2 text-3xl font-semibold tracking-tight text-brand-navy sm:text-4xl">Editar pedido #{{ $order->id }}</h1>
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
                    <a href="{{ route('orders.show', $order) }}" class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50">
                        Volver al detalle
                    </a>
                    <a href="{{ route('orders.index') }}" class="inline-flex items-center justify-center rounded-2xl bg-brand-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700">
                        Pedidos
                    </a>
                </div>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-3">
            <div class="xl:col-span-2">
                <form method="POST" action="{{ route('orders.update', $order) }}" class="space-y-6">
                    @csrf
                    @method('PATCH')

                    <section class="rounded-[24px] border border-slate-200/80 bg-white p-6 shadow-sm">
                        <div class="flex items-center justify-between gap-3">
                            <h2 class="text-base font-semibold text-brand-navy">Notas del pedido</h2>
                            <span class="text-xs font-medium uppercase tracking-wide text-slate-500">Campo editable</span>
                        </div>
                        <textarea name="notes" rows="4" class="brand-input mt-4 block w-full rounded-2xl border-slate-200 bg-slate-50 text-slate-900 focus:border-brand-primary focus:ring-brand-primary">{{ old('notes', $order->notes) }}</textarea>
                        @error('notes')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </section>

                    <div class="space-y-4">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                            <div>
                                <h2 class="text-base font-semibold text-brand-navy">Items</h2>
                                <p class="mt-1 text-sm text-slate-600">Edita cantidad, unidad, texto detectado y notas sin cambiar el vínculo con el catálogo.</p>
                            </div>
                            <span class="text-xs font-medium uppercase tracking-wide text-slate-500">Matching de catálogo se mantiene opcional</span>
                        </div>

                        @forelse ($order->orderItems as $item)
                            <section class="rounded-[24px] border border-slate-200/80 bg-white p-6 shadow-sm">
                                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="inline-flex items-center rounded-full bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700 ring-1 ring-slate-200">
                                                Item #{{ $item->id }}
                                            </span>
                                            <span class="text-sm font-semibold text-slate-900">Orden {{ $item->sort_order }}</span>
                                        </div>
                                        <div class="mt-3 rounded-2xl border border-slate-200/80 bg-slate-50 p-4">
                                            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                                <div>
                                                    <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Texto detectado</div>
                                                    <div class="mt-1 text-sm text-slate-800">{{ $item->raw_text ?? '—' }}</div>
                                                </div>
                                                <div>
                                                    <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Coincidencia</div>
                                                    <div class="mt-1 text-sm text-slate-800">{{ $item->matched_text ?? '—' }}</div>
                                                </div>
                                                <div>
                                                    <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Confianza</div>
                                                    <div class="mt-1 text-sm text-slate-800">{{ $item->confidence_score !== null ? number_format((float) $item->confidence_score, 2) : '—' }}</div>
                                                </div>
                                                <div>
                                                    <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Producto reconocido</div>
                                                    <div class="mt-1 text-sm text-slate-800">{{ $item->product?->name ?? 'Sin producto asociado' }}</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap gap-2">
                                        @if ($item->product !== null)
                                            <span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-100">
                                                Producto del catálogo: {{ $item->product->name }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700 ring-1 ring-slate-200">
                                                Sin producto asociado
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                <div class="mt-5 grid gap-4 md:grid-cols-2">
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700" for="item-{{ $item->id }}-quantity">Cantidad</label>
                                        <input id="item-{{ $item->id }}-quantity" name="items[{{ $item->id }}][quantity]" type="number" step="0.01" min="0" value="{{ old('items.' . $item->id . '.quantity', $item->quantity) }}" class="brand-input mt-1 block w-full rounded-2xl border-slate-200 bg-slate-50 focus:border-brand-primary focus:ring-brand-primary">
                                        @error('items.' . $item->id . '.quantity')
                                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700" for="item-{{ $item->id }}-unit">Unidad</label>
                                        <input id="item-{{ $item->id }}-unit" name="items[{{ $item->id }}][unit]" type="text" value="{{ old('items.' . $item->id . '.unit', $item->unit) }}" class="brand-input mt-1 block w-full rounded-2xl border-slate-200 bg-slate-50 focus:border-brand-primary focus:ring-brand-primary" placeholder="piece, box, bag">
                                        @error('items.' . $item->id . '.unit')
                                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-medium text-slate-700" for="item-{{ $item->id }}-raw">Texto detectado</label>
                                        <input id="item-{{ $item->id }}-raw" name="items[{{ $item->id }}][raw_text]" type="text" value="{{ old('items.' . $item->id . '.raw_text', $item->raw_text) }}" class="brand-input mt-1 block w-full rounded-2xl border-slate-200 bg-slate-50 focus:border-brand-primary focus:ring-brand-primary">
                                        @error('items.' . $item->id . '.raw_text')
                                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-medium text-slate-700" for="item-{{ $item->id }}-notes">Notas</label>
                                        <textarea id="item-{{ $item->id }}-notes" name="items[{{ $item->id }}][notes]" rows="3" class="brand-input mt-1 block w-full rounded-2xl border-slate-200 bg-slate-50 text-slate-900 focus:border-brand-primary focus:ring-brand-primary">{{ old('items.' . $item->id . '.notes', $item->notes) }}</textarea>
                                        @error('items.' . $item->id . '.notes')
                                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                            </section>
                        @empty
                            <div class="rounded-[24px] border border-dashed border-slate-300 bg-white p-6 text-sm text-slate-500 shadow-sm">
                                No hay items disponibles en este pedido.
                            </div>
                        @endforelse
                    </div>

                    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-end">
                        <a href="{{ route('orders.show', $order) }}" class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                            Cancelar
                        </a>
                        <button type="submit" class="inline-flex items-center justify-center rounded-2xl bg-brand-primary px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700">
                            Guardar cambios
                        </button>
                    </div>
                </form>
            </div>

            <div class="space-y-6">
                <section class="rounded-[24px] border border-slate-200/80 bg-white p-6 shadow-sm">
                    <h2 class="text-base font-semibold text-brand-navy">Resumen</h2>
                    <dl class="mt-4 space-y-3 text-sm text-slate-700">
                        <div class="flex items-center justify-between gap-4 rounded-2xl bg-slate-50 px-4 py-3">
                            <dt class="text-slate-500">Estado</dt>
                            <dd class="font-medium text-slate-900">{{ $statusLabels[$order->status] ?? str_replace('_', ' ', $order->status) }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4 rounded-2xl bg-slate-50 px-4 py-3">
                            <dt class="text-slate-500">Sucursal</dt>
                            <dd class="font-medium text-slate-900">{{ $order->branch?->name ?? '—' }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4 rounded-2xl bg-slate-50 px-4 py-3">
                            <dt class="text-slate-500">Cliente</dt>
                            <dd class="font-medium text-slate-900">{{ $order->customer?->name ?? '—' }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4 rounded-2xl bg-slate-50 px-4 py-3">
                            <dt class="text-slate-500">Confianza</dt>
                            <dd class="font-medium text-slate-900">{{ $order->parser_confidence !== null ? number_format((float) $order->parser_confidence, 2) : '—' }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="rounded-[24px] border border-slate-200/80 bg-white p-6 shadow-sm">
                    <h2 class="text-base font-semibold text-brand-navy">Información del catálogo</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">Si un item ya está vinculado a un producto, aquí tienes el contexto sin necesidad de cambiar la selección manualmente.</p>
                    <div class="mt-4 space-y-3">
                        @forelse ($order->orderItems as $item)
                            <div class="rounded-2xl border border-slate-200/80 bg-slate-50 p-4">
                                <div class="text-sm font-semibold text-slate-900">
                                    {{ $item->product?->name ?? 'Sin producto asociado' }}
                                </div>
                                <div class="mt-2 text-sm leading-6 text-slate-600">
                                    <div><span class="font-medium text-slate-500">Texto:</span> {{ $item->raw_text ?? '—' }}</div>
                                    <div><span class="font-medium text-slate-500">Coincidencia:</span> {{ $item->matched_text ?? '—' }}</div>
                                    <div><span class="font-medium text-slate-500">Unidad:</span> {{ $item->unit ?? '—' }}</div>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-sm text-slate-500">
                                No hay información de catálogo para mostrar.
                            </div>
                        @endforelse
                    </div>
                </section>

                <section class="rounded-[24px] border border-slate-200/80 bg-white p-6 shadow-sm">
                    <h2 class="text-base font-semibold text-brand-navy">Mensaje original</h2>
                    <div class="mt-3 whitespace-pre-wrap rounded-2xl border border-slate-200/80 bg-slate-50 p-4 text-sm leading-6 text-slate-800">
                        {{ $order->raw_message_text ?? '—' }}
                    </div>
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
