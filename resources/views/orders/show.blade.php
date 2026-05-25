<x-app-layout>
    <div class="space-y-6">
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

            $statusBadgeClasses = [
                \App\Models\Order::STATUS_PENDING_REVIEW => 'bg-amber-100 text-amber-800',
                \App\Models\Order::STATUS_CONFIRMED => 'bg-blue-100 text-blue-800',
                \App\Models\Order::STATUS_PREPARING => 'bg-violet-100 text-violet-800',
                \App\Models\Order::STATUS_READY_FOR_DISPATCH => 'bg-sky-100 text-sky-800',
                \App\Models\Order::STATUS_DISPATCHED => 'bg-emerald-100 text-emerald-800',
                \App\Models\Order::STATUS_CANCELLED => 'bg-slate-100 text-slate-700',
                \App\Models\Order::STATUS_REJECTED => 'bg-red-100 text-red-800',
            ];
        @endphp

        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <div class="text-sm text-slate-500">Order #{{ $order->id }}</div>
                <h1 class="mt-1 text-3xl font-semibold tracking-tight text-brand-navy">Order detail</h1>
                <p class="mt-2 text-sm text-slate-600">Review the raw message, parsed payload, items, and status history.</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('orders.index') }}" class="brand-btn-secondary">Back to orders</a>
                <a href="{{ route('orders.edit', $order) }}" class="brand-btn-primary">Edit order</a>
            </div>
        </div>

        @if (session('status'))
            <div class="rounded-2xl border border-brand-success/20 bg-green-50 px-4 py-3 text-sm text-green-800 shadow-sm">
                {{ session('status') }}
            </div>
        @endif

        <div class="grid gap-6 xl:grid-cols-3">
            <div class="xl:col-span-2 space-y-6">
                <div class="brand-card p-6">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <div class="text-sm text-slate-500">Status</div>
                            <div class="mt-2 inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusBadgeClasses[$order->status] ?? 'bg-slate-100 text-slate-700' }}">{{ $statusLabels[$order->status] ?? str_replace('_', ' ', $order->status) }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-slate-500">Customer</div>
                            <div class="mt-2 text-base font-semibold text-slate-900">{{ $order->customer?->name ?? '-' }}</div>
                            <div class="text-sm text-slate-600">{{ $order->customer?->phone ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-slate-500">Branch</div>
                            <div class="mt-2 text-base font-semibold text-slate-900">{{ $order->branch?->name ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-slate-500">Source channel</div>
                            <div class="mt-2 text-base font-semibold text-slate-900">{{ $order->source_channel }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-slate-500">Parser confidence</div>
                            <div class="mt-2 text-base font-semibold text-slate-900">{{ $order->parser_confidence !== null ? number_format((float) $order->parser_confidence, 2) : '-' }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-slate-500">Created</div>
                            <div class="mt-2 text-base font-semibold text-slate-900">{{ $order->created_at?->format('Y-m-d H:i') }}</div>
                        </div>
                        <div class="sm:col-span-2">
                            <div class="text-sm text-slate-500">Original raw message</div>
                            <div class="mt-2 whitespace-pre-wrap rounded-2xl border border-slate-200/80 bg-slate-50 p-4 text-sm text-slate-900">{{ $order->raw_message_text ?? '-' }}</div>
                        </div>
                        <div class="sm:col-span-2">
                            <div class="text-sm text-slate-500">Notes</div>
                            <div class="mt-2 whitespace-pre-wrap rounded-2xl border border-slate-200/80 bg-slate-50 p-4 text-sm text-slate-900">{{ $order->notes ?? '-' }}</div>
                        </div>
                    </div>
                </div>

                <div class="brand-card p-6">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-base font-semibold text-brand-navy">Parsed payload</h2>
                        <span class="text-xs text-slate-500">Readable JSON</span>
                    </div>
                    <pre class="mt-4 overflow-auto rounded-2xl border border-slate-200/80 bg-slate-950 p-4 text-xs leading-6 text-slate-100">{{ json_encode($order->parsed_payload_json ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                </div>

                <div class="brand-card p-6">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-base font-semibold text-brand-navy">Order items</h2>
                        <span class="text-xs text-slate-500">{{ $order->orderItems->count() }} item(s)</span>
                    </div>
                    <div class="mt-4 overflow-hidden rounded-2xl border border-slate-200/80">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-slate-500">
                                <tr>
                                    <th class="px-3 py-2 text-left font-medium">Texto detectado</th>
                                    <th class="px-3 py-2 text-left font-medium">Cantidad</th>
                                    <th class="px-3 py-2 text-left font-medium">Unidad</th>
                                    <th class="px-3 py-2 text-left font-medium">Producto reconocido</th>
                                    <th class="px-3 py-2 text-left font-medium">Coincidencia</th>
                                    <th class="px-3 py-2 text-left font-medium">Confianza</th>
                                    <th class="px-3 py-2 text-left font-medium">Notas</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 bg-white">
                                @forelse ($order->orderItems as $item)
                                    <tr>
                                        <td class="px-3 py-2 text-slate-900">{{ $item->raw_text ?? '-' }}</td>
                                        <td class="px-3 py-2 text-slate-600">{{ $item->quantity }}</td>
                                        <td class="px-3 py-2 text-slate-600">{{ $item->unit ?? '-' }}</td>
                                        <td class="px-3 py-2 text-slate-600">
                                            @if ($item->product !== null)
                                                <span class="brand-badge bg-emerald-100 text-emerald-800">{{ $item->product->name }}</span>
                                            @else
                                                <span class="brand-badge bg-slate-100 text-slate-700">Sin producto asociado</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-slate-600">{{ $item->matched_text ?? '-' }}</td>
                                        <td class="px-3 py-2 text-slate-600">{{ $item->confidence_score !== null ? number_format((float) $item->confidence_score, 2) : '-' }}</td>
                                        <td class="px-3 py-2 text-slate-600">{{ $item->notes ?? '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-3 py-4 text-center text-slate-500">No items found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="brand-card p-6">
                    <h2 class="text-base font-semibold text-brand-navy">Actions</h2>
                    <div class="mt-4 flex flex-col gap-3">
                        @if ($order->status === \App\Models\Order::STATUS_PENDING_REVIEW)
                            <form method="POST" action="{{ route('orders.confirm', $order) }}">
                                @csrf
                                <button type="submit" class="brand-btn-primary w-full">Confirmar pedido</button>
                            </form>
                            <form method="POST" action="{{ route('orders.reject', $order) }}">
                                @csrf
                                <button type="submit" class="brand-btn-danger w-full">Rechazar pedido</button>
                            </form>
                            <form method="POST" action="{{ route('orders.cancel', $order) }}">
                                @csrf
                                <button type="submit" class="brand-btn-secondary w-full">Cancelar pedido</button>
                            </form>
                        @elseif ($order->status === \App\Models\Order::STATUS_CONFIRMED)
                            <form method="POST" action="{{ route('orders.prepare', $order) }}">
                                @csrf
                                <button type="submit" class="brand-btn-primary w-full">Iniciar preparación</button>
                            </form>
                            <form method="POST" action="{{ route('orders.cancel', $order) }}">
                                @csrf
                                <button type="submit" class="brand-btn-secondary w-full">Cancelar pedido</button>
                            </form>
                        @elseif ($order->status === \App\Models\Order::STATUS_PREPARING)
                            <form method="POST" action="{{ route('orders.ready-for-dispatch', $order) }}">
                                @csrf
                                <button type="submit" class="brand-btn-primary w-full">Marcar listo para despacho</button>
                            </form>
                            <form method="POST" action="{{ route('orders.cancel', $order) }}">
                                @csrf
                                <button type="submit" class="brand-btn-secondary w-full">Cancelar pedido</button>
                            </form>
                        @elseif ($order->status === \App\Models\Order::STATUS_READY_FOR_DISPATCH)
                            <form method="POST" action="{{ route('orders.dispatch', $order) }}">
                                @csrf
                                <button type="submit" class="brand-btn-primary w-full">Marcar despachado</button>
                            </form>
                            <form method="POST" action="{{ route('orders.cancel', $order) }}">
                                @csrf
                                <button type="submit" class="brand-btn-secondary w-full">Cancelar pedido</button>
                            </form>
                        @else
                            <div class="rounded-2xl border border-slate-200/80 bg-slate-50 p-4 text-sm text-slate-600">
                                No status actions are available for this order.
                            </div>
                        @endif
                    </div>
                </div>

                <div class="brand-card p-6">
                    <h2 class="text-base font-semibold text-brand-navy">Timeline</h2>
                    <div class="mt-4 space-y-3">
                        @forelse ($order->orderStatusHistories as $history)
                            <div class="rounded-2xl border border-slate-200/80 bg-slate-50 p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="text-sm font-medium text-slate-900">{{ $statusLabels[$history->to_status] ?? str_replace('_', ' ', $history->to_status) }}</div>
                                    <div class="text-xs text-slate-500">{{ $history->created_at?->format('Y-m-d H:i') }}</div>
                                </div>
                                <div class="mt-1 text-xs text-slate-500">From {{ $history->from_status ? ($statusLabels[$history->from_status] ?? str_replace('_', ' ', $history->from_status)) : '-' }} · {{ $history->changedByUser?->name ?? 'System' }}</div>
                                @if ($history->reason)
                                    <div class="mt-2 text-sm text-slate-700">{{ $history->reason }}</div>
                                @endif
                            </div>
                        @empty
                            <div class="text-sm text-slate-500">No status history yet.</div>
                        @endforelse
                    </div>
                </div>

                <div class="brand-card p-6">
                    <h2 class="text-base font-semibold text-brand-navy">Manual reviews</h2>
                    <div class="mt-4 space-y-3">
                        @forelse ($order->manualReviews as $review)
                            <div class="rounded-2xl border border-slate-200/80 bg-slate-50 p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="text-sm font-medium text-slate-900">{{ $review->decision }}</div>
                                    <div class="text-xs text-slate-500">{{ $review->reviewed_at?->format('Y-m-d H:i') }}</div>
                                </div>
                                <div class="mt-1 text-xs text-slate-500">{{ $review->reviewedByUser?->name ?? 'System' }}</div>
                            </div>
                        @empty
                            <div class="text-sm text-slate-500">No manual reviews recorded.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
