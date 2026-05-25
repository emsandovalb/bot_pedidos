<x-app-layout>
    <div class="space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <div class="brand-badge bg-brand-primary/10 text-brand-primary">Revisión de pedidos</div>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight text-brand-navy">Órdenes pendientes</h1>
                <p class="mt-1 text-sm text-slate-600">Bandeja de pedidos para el ámbito actual.</p>
            </div>

            @if (session('status'))
                <div class="rounded-2xl border border-brand-success/20 bg-green-50 px-4 py-3 text-sm text-green-800 shadow-sm">
                    {{ session('status') }}
                </div>
            @endif
        </div>

        <div class="brand-card p-5">
            <form method="GET" action="{{ route('intake-requests.index') }}" class="grid gap-4 lg:grid-cols-3 xl:grid-cols-6">
                <div>
                    <label for="status" class="block text-sm font-medium text-slate-700">Estado</label>
                    <select id="status" name="status" class="brand-input mt-1 block w-full rounded-xl">
                        <option value="">All</option>
                        @foreach ([\App\Models\IntakeRequest::STATUS_PENDING, \App\Models\IntakeRequest::STATUS_NEEDS_REVIEW, \App\Models\IntakeRequest::STATUS_CONFIRMED, \App\Models\IntakeRequest::STATUS_REJECTED] as $status)
                            <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ str_replace('_', ' ', $status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="branch_id" class="block text-sm font-medium text-slate-700">Sucursal</label>
                    <select id="branch_id" name="branch_id" class="brand-input mt-1 block w-full rounded-xl">
                        <option value="">All</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((string) ($filters['branch_id'] ?? '') === (string) $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="draw_id" class="block text-sm font-medium text-slate-700">Referencia</label>
                    <select id="draw_id" name="draw_id" class="brand-input mt-1 block w-full rounded-xl">
                        <option value="">All</option>
                        @foreach ($draws as $draw)
                            <option value="{{ $draw->id }}" @selected((string) ($filters['draw_id'] ?? '') === (string) $draw->id)>{{ $draw->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="date_from" class="block text-sm font-medium text-slate-700">Fecha desde</label>
                    <input id="date_from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}" class="brand-input mt-1 block w-full rounded-xl">
                </div>
                <div>
                    <label for="date_to" class="block text-sm font-medium text-slate-700">Fecha hasta</label>
                    <input id="date_to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}" class="brand-input mt-1 block w-full rounded-xl">
                </div>
                <div>
                    <label for="customer_phone" class="block text-sm font-medium text-slate-700">Teléfono del cliente</label>
                    <input id="customer_phone" name="customer_phone" type="text" value="{{ $filters['customer_phone'] ?? '' }}" class="brand-input mt-1 block w-full rounded-xl" placeholder="+50255510001">
                </div>
                <div>
                    <label for="detected_number" class="block text-sm font-medium text-slate-700">Producto o código detectado</label>
                    <input id="detected_number" name="detected_number" type="text" value="{{ $filters['detected_number'] ?? '' }}" class="brand-input mt-1 block w-full rounded-xl" placeholder="28">
                </div>
                <div class="flex items-end gap-2 xl:col-span-6">
                    <button type="submit" class="brand-btn-primary">Apply</button>
                    <a href="{{ route('intake-requests.index') }}" class="brand-btn-secondary">Reset</a>
                </div>
            </form>
        </div>

        <div class="overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Creado</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Sucursal</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Detalle detectado</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Referencia</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Monto</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Estado</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Indicadores</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Notas</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse ($requests as $request)
                        @php
                            $isPending = $request->status === \App\Models\IntakeRequest::STATUS_PENDING;
                            $isNeedsReview = $request->status === \App\Models\IntakeRequest::STATUS_NEEDS_REVIEW;
                            $isStale = $isPending && $request->created_at && $request->created_at->lt(now()->subHours($staleThresholdHours));
                            $isPreviousDay = $isPending && $request->created_at && $request->created_at->isBefore(today());
                        @endphp
                        <tr class="{{ $isNeedsReview ? 'bg-amber-50/40' : ($isStale ? 'bg-red-50/40' : '') }}">
                            <td class="px-4 py-3 text-sm text-slate-600">
                                <div>{{ $request->created_at?->format('Y-m-d H:i') }}</div>
                                @if ($isPreviousDay)
                                    <div class="mt-1 text-xs font-semibold text-brand-danger">Día anterior</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm font-medium text-slate-900">{{ $request->branch?->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-sm text-slate-900">{{ $request->detected_number ?? '-' }}</td>
                            <td class="px-4 py-3 text-sm">
                                @if ($request->draw)
                                    <span class="inline-flex rounded-full bg-sky-100 px-2.5 py-1 text-xs font-semibold text-sky-800 brand-badge">
                                        {{ $request->draw->name }}
                                    </span>
                                @else
                                    <span class="text-slate-400">-</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $request->detected_amount ?? '-' }}</td>
                            <td class="px-4 py-3 text-sm">
                                <span class="brand-badge {{ $request->status === \App\Models\IntakeRequest::STATUS_CONFIRMED ? 'bg-green-100 text-green-800' : ($request->status === \App\Models\IntakeRequest::STATUS_REJECTED ? 'bg-red-100 text-red-800' : ($request->status === \App\Models\IntakeRequest::STATUS_NEEDS_REVIEW ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700')) }}">
                                    {{ str_replace('_', ' ', $request->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <div class="flex flex-wrap gap-2">
                                    @if ($isNeedsReview)
                                        <span class="brand-badge bg-amber-100 text-amber-800">Revisión</span>
                                    @endif
                                    @if ($isStale)
                                        <span class="brand-badge bg-red-100 text-red-800">Pendiente &gt; {{ $staleThresholdHours }}h</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $request->notes ?? '-' }}</td>
                            <td class="px-4 py-3 text-sm">
                                <div class="flex flex-wrap items-center gap-2">
                                    <a href="{{ route('intake-requests.show', $request) }}" class="brand-btn-secondary px-3 py-1.5 text-xs">Ver</a>
                                    <a href="{{ route('intake-requests.edit', $request) }}" class="brand-btn-secondary px-3 py-1.5 text-xs">Ver / editar</a>

                                    @can('confirm', $request)
                                        <form method="POST" action="{{ route('intake-requests.confirm', $request) }}">
                                            @csrf
                                            <button type="submit" class="brand-btn-primary px-3 py-1.5 text-xs">Confirmar</button>
                                        </form>
                                        <details class="group">
                                            <summary class="cursor-pointer list-none rounded-xl border border-brand-danger/20 px-3 py-1.5 text-xs font-medium text-brand-danger transition hover:bg-red-50">Rechazar</summary>
                                            <form method="POST" action="{{ route('intake-requests.reject', $request) }}" class="mt-2 space-y-2 rounded-2xl border border-brand-danger/20 bg-red-50 p-3">
                                                @csrf
                                                <textarea name="rejection_reason" rows="2" class="brand-input block w-full rounded-xl" placeholder="Motivo del rechazo"></textarea>
                                                <button type="submit" class="brand-btn-danger px-3 py-1.5 text-xs">Enviar rechazo</button>
                                            </form>
                                        </details>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-10 text-center text-sm text-slate-500">No hay pedidos disponibles para esta cuenta.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $requests->links() }}
    </div>
</x-app-layout>
