<x-app-layout>
    <div class="space-y-6">
        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div class="max-w-3xl">
                <div class="brand-badge bg-brand-primary/10 text-brand-primary">Catálogo de productos</div>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight text-brand-navy">Catálogo de productos</h1>
                <p class="mt-1 max-w-2xl text-sm text-slate-600">Administra el catálogo por sucursal y referencia operativa.</p>
            </div>

            @if ($canManageLimits)
                <a href="{{ route('limits.create', request()->only(['branch_id', 'draw_id'])) }}" class="brand-btn-primary">
                    Crear registro
                </a>
            @endif
        </div>

        @if (session('status'))
            <div class="rounded-2xl border border-brand-success/20 bg-green-50 px-4 py-3 text-sm text-green-800 shadow-sm">
                {{ session('status') }}
            </div>
        @endif

        <div class="brand-card p-5">
            <form method="GET" action="{{ route('limits.index') }}" class="grid gap-4 md:grid-cols-3 xl:grid-cols-4">
                <div>
                    <label for="branch_id" class="block text-sm font-medium text-slate-700">Sucursal</label>
                    <select id="branch_id" name="branch_id" class="brand-input mt-1 block w-full rounded-xl">
                        <option value="">Todas las sucursales</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((string) ($filters['branch_id'] ?? '') === (string) $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="draw_id" class="block text-sm font-medium text-slate-700">Referencia</label>
                    <select id="draw_id" name="draw_id" class="brand-input mt-1 block w-full rounded-xl">
                        <option value="">Todas las referencias</option>
                        @foreach ($draws as $draw)
                            <option value="{{ $draw->id }}" @selected((string) ($filters['draw_id'] ?? '') === (string) $draw->id)>{{ $draw->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="number" class="block text-sm font-medium text-slate-700">Código</label>
                    <input id="number" name="number" value="{{ $filters['number'] ?? '' }}" type="text" maxlength="2" class="brand-input mt-1 block w-full rounded-xl" placeholder="00">
                </div>

                <div class="flex items-end gap-2 md:col-span-3 xl:col-span-1">
                    <button type="submit" class="brand-btn-primary">Aplicar</button>
                    <a href="{{ route('limits.index') }}" class="brand-btn-secondary">Restablecer</a>
                </div>
            </form>
        </div>

        @if (! $canManageLimits)
            <div class="rounded-2xl border border-slate-200/80 bg-white p-5 text-sm text-slate-600 shadow-sm">
                Acceso de solo lectura. Este catálogo puede verse, pero no editarse desde esta cuenta.
            </div>
        @endif

        <div class="overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Sucursal</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Referencia</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Código</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Max amount</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Updated</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse ($limits as $limit)
                        <tr>
                            <td class="px-4 py-3 text-sm text-slate-900">{{ $limit->branch?->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-sm text-slate-700">{{ $limit->draw?->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-sm font-semibold text-brand-navy">{{ $limit->number }}</td>
                            <td class="px-4 py-3 text-sm text-slate-700">&#8353;{{ number_format((float) $limit->max_amount, 2, '.', ',') }}</td>
                            <td class="px-4 py-3 text-sm text-slate-700">{{ $limit->updated_at?->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-3 text-sm">
                                @if ($canManageLimits)
                                    <div class="flex flex-wrap gap-2">
                                        <a href="{{ route('limits.edit', $limit) }}" class="brand-btn-secondary px-3 py-1.5 text-xs">Editar</a>
                                        <form method="POST" action="{{ route('limits.delete', $limit) }}" onsubmit="return confirm('¿Eliminar este registro?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="brand-btn-danger px-3 py-1.5 text-xs">Eliminar</button>
                                        </form>
                                    </div>
                                @else
                                    <span class="text-slate-400">Solo lectura</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-sm text-slate-500">No se encontraron registros para los filtros seleccionados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $limits->links() }}
    </div>
</x-app-layout>
