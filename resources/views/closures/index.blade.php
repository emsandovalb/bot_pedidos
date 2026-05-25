<x-app-layout>
    <div class="space-y-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <div class="brand-badge bg-brand-primary/10 text-brand-primary">Cierres diarios</div>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight text-brand-navy">Cierres diarios</h1>
                <p class="mt-1 text-sm text-slate-600">Cierres diarios por sucursal y filtros operativos.</p>
            </div>
            @if (session('status'))
                <div class="rounded-2xl border border-brand-success/20 bg-green-50 px-4 py-3 text-sm text-green-800 shadow-sm">
                    {{ session('status') }}
                </div>
            @endif
        </div>

        @if (! auth()->user()->isViewer())
            <div class="brand-card p-5">
                <form method="POST" action="{{ route('closures.store') }}" class="grid gap-4 lg:grid-cols-4">
                    @csrf
                    <div>
                        <label for="branch_id" class="block text-sm font-medium text-slate-700">Sucursal</label>
                        <select id="branch_id" name="branch_id" class="brand-input mt-1 block w-full rounded-xl">
                            <option value="">Selecciona una sucursal</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" @selected(request('branch_id') == $branch->id)>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                        @error('branch_id')<p class="mt-2 text-sm text-brand-danger">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="closure_date" class="block text-sm font-medium text-slate-700">Fecha de cierre</label>
                        <input id="closure_date" name="closure_date" type="date" value="{{ request('closure_date', today()->toDateString()) }}" class="brand-input mt-1 block w-full rounded-xl">
                        @error('closure_date')<p class="mt-2 text-sm text-brand-danger">{{ $message }}</p>@enderror
                    </div>
                    <div class="lg:col-span-2">
                        <label for="notes" class="block text-sm font-medium text-slate-700">Notas</label>
                        <input id="notes" name="notes" type="text" value="{{ old('notes') }}" class="brand-input mt-1 block w-full rounded-xl" placeholder="Notas operativas opcionales">
                        @error('notes')<p class="mt-2 text-sm text-brand-danger">{{ $message }}</p>@enderror
                    </div>
                    <div class="lg:col-span-4 flex justify-end">
                        <button type="submit" class="brand-btn-primary">
                            Cerrar día
                        </button>
                    </div>
                </form>
            </div>
        @else
            <div class="rounded-2xl border border-slate-200/80 bg-white p-5 text-sm text-slate-600 shadow-sm">
                Acceso de solo lectura. Las acciones de cierre están deshabilitadas.
            </div>
        @endif

        <div class="brand-card p-5">
            <form method="GET" action="{{ route('closures.index') }}" class="grid gap-4 lg:grid-cols-4">
                <div>
                    <label for="filter_branch_id" class="block text-sm font-medium text-slate-700">Sucursal</label>
                    <select id="filter_branch_id" name="branch_id" class="brand-input mt-1 block w-full rounded-xl">
                        <option value="">Todas las sucursales</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected(request('branch_id') == $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="filter_closure_date" class="block text-sm font-medium text-slate-700">Fecha</label>
                    <input id="filter_closure_date" name="closure_date" type="date" value="{{ request('closure_date') }}" class="brand-input mt-1 block w-full rounded-xl">
                </div>
                <div>
                    <label for="filter_closed_by" class="block text-sm font-medium text-slate-700">Usuario</label>
                    <select id="filter_closed_by" name="closed_by" class="brand-input mt-1 block w-full rounded-xl">
                        <option value="">Todos los usuarios</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected(request('closed_by') == $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="brand-btn-primary">Aplicar</button>
                    <a href="{{ route('closures.index') }}" class="brand-btn-secondary">Restablecer</a>
                </div>
            </form>
        </div>

        <div class="overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Fecha</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Sucursal</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Cerrado por</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Pedidos</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Confirmados</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Rechazados</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Pendientes</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Monto</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse ($closures as $closure)
                        <tr>
                            <td class="px-4 py-3 text-sm text-slate-900">{{ $closure->closure_date?->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $closure->branch?->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $closure->closedByUser?->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-sm text-slate-900">{{ $closure->total_requests }}</td>
                            <td class="px-4 py-3 text-sm text-slate-900">{{ $closure->total_confirmed }}</td>
                            <td class="px-4 py-3 text-sm text-slate-900">{{ $closure->total_rejected }}</td>
                            <td class="px-4 py-3 text-sm text-slate-900">{{ $closure->total_pending }}</td>
                            <td class="px-4 py-3 text-sm text-slate-900">&#8353;{{ number_format((float) $closure->total_amount_confirmed, 2, '.', ',') }}</td>
                            <td class="px-4 py-3 text-sm">
                                <div class="flex flex-wrap items-center gap-2">
                                    @can('view', $closure)
                                        <a href="{{ route('closures.show', $closure) }}" class="brand-btn-secondary px-3 py-1.5 text-xs">Ver</a>
                                        <a href="{{ route('closures.export', $closure) }}" class="brand-btn-secondary px-3 py-1.5 text-xs">Exportar CSV</a>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                        @if ($closure->notes)
                            <tr class="bg-slate-50">
                                <td colspan="9" class="px-4 py-3 text-sm text-slate-600">{{ $closure->notes }}</td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-8 text-center text-sm text-slate-500">No se encontraron cierres con los filtros seleccionados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $closures->links() }}
    </div>
</x-app-layout>
