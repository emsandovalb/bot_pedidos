<x-app-layout>
    <div class="space-y-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-slate-900">Detalle del pedido</h1>
                <p class="mt-1 text-sm text-slate-600">Trazabilidad completa para este pedido.</p>
            </div>
            <a href="{{ route('intake-requests.index') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">
                Volver a órdenes
            </a>
        </div>

        <div class="grid gap-6 xl:grid-cols-3">
            <div class="xl:col-span-2 space-y-4">
                <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <div class="text-sm text-slate-500">Estado</div>
                            <div class="mt-1 inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $request->status === \App\Models\IntakeRequest::STATUS_CONFIRMED ? 'bg-emerald-100 text-emerald-800' : ($request->status === \App\Models\IntakeRequest::STATUS_REJECTED ? 'bg-red-100 text-red-800' : ($request->status === \App\Models\IntakeRequest::STATUS_NEEDS_REVIEW ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700')) }}">
                                {{ str_replace('_', ' ', $request->status) }}
                            </div>
                        </div>
                        <div>
                            <div class="text-sm text-slate-500">Sucursal</div>
                            <div class="mt-1 text-base font-semibold text-slate-900">{{ $request->branch?->name ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-slate-500">Referencia</div>
                            <div class="mt-1 text-base font-semibold text-slate-900">{{ $request->draw?->name ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-slate-500">Cliente</div>
                            <div class="mt-1 text-base font-semibold text-slate-900">{{ $request->customer?->name ?? '-' }}</div>
                            <div class="text-sm text-slate-600">{{ $request->customer?->phone ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-slate-500">Mensaje original</div>
                            <div class="mt-1 whitespace-pre-wrap text-sm text-slate-900">{{ $request->incomingMessage?->raw_text ?? $request->raw_text }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-slate-500">ID del mensaje entrante</div>
                            <div class="mt-1 text-base font-semibold text-slate-900">{{ $request->incomingMessage?->id ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-slate-500">Detalle detectado</div>
                            <div class="mt-1 text-base font-semibold text-slate-900">{{ $request->detected_number ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-slate-500">Cantidad detectada</div>
                            <div class="mt-1 text-base font-semibold text-slate-900">{{ $request->detected_amount ?? '-' }}</div>
                        </div>
                        <div class="sm:col-span-2">
                            <div class="text-sm text-slate-500">Notas</div>
                            <div class="mt-1 text-sm text-slate-900">{{ $request->notes ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-slate-500">Creado</div>
                            <div class="mt-1 text-sm text-slate-900">{{ $request->created_at?->format('Y-m-d H:i') }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-slate-500">Actualizado</div>
                            <div class="mt-1 text-sm text-slate-900">{{ $request->updated_at?->format('Y-m-d H:i') }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-slate-500">Confirmado a las</div>
                            <div class="mt-1 text-sm text-slate-900">{{ $request->confirmed_at?->format('Y-m-d H:i') ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-slate-500">Rechazado a las</div>
                            <div class="mt-1 text-sm text-slate-900">{{ $request->rejected_at?->format('Y-m-d H:i') ?? '-' }}</div>
                        </div>
                        <div class="sm:col-span-2">
                            <div class="text-sm text-slate-500">Respuesta generada</div>
                            <div class="mt-1 whitespace-pre-wrap text-sm text-slate-900">{{ $request->incomingMessage?->response?->response_text ?? '-' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-4">
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Línea de tiempo</h2>
                    <div class="mt-4 space-y-4">
                        @forelse ($request->events as $event)
                            <div class="rounded-md border border-slate-200 bg-slate-50 p-4">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="text-sm font-semibold text-slate-900">{{ str_replace('_', ' ', $event->event_type) }}</div>
                                    <div class="text-xs text-slate-500">{{ $event->created_at?->format('Y-m-d H:i') }}</div>
                                </div>
                                <div class="mt-1 text-xs text-slate-600">{{ $event->user?->name ?? 'System' }}</div>
                                @if ($event->notes)
                                    <div class="mt-2 text-sm text-slate-700">{{ $event->notes }}</div>
                                @endif
                            </div>
                        @empty
                            <div class="text-sm text-slate-500">Aún no hay eventos de auditoría registrados.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
