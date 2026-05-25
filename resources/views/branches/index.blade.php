<x-app-layout>
    <div class="space-y-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <div class="brand-badge bg-brand-primary/10 text-brand-primary">Sucursales</div>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight text-brand-navy">Sucursales</h1>
                <p class="mt-1 text-sm text-slate-600">Sucursales visibles para tu ámbito actual.</p>
            </div>
            <a href="{{ route('closures.index') }}" class="brand-btn-secondary">
                Cierres diarios
            </a>
        </div>

        <div class="overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Nombre</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Canal</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Identificador</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Estado</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Acción</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse ($branches as $branch)
                        <tr>
                            <td class="px-4 py-3 text-sm font-medium text-brand-navy">{{ $branch->name }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $branch->channel_type }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $branch->channel_identifier ?? '-' }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">
                                <span class="brand-badge bg-slate-100 text-slate-700">{{ $branch->status }}</span>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                @can('create', [\App\Models\BranchDailyClosure::class, $branch])
                                    <a href="{{ route('closures.index', ['branch_id' => $branch->id, 'closure_date' => today()->toDateString()]) }}" class="brand-btn-secondary px-3 py-1.5 text-xs">
                                        Cerrar día
                                    </a>
                                @else
                                    <span class="text-sm text-slate-400">Solo lectura</span>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-500">No hay sucursales disponibles para esta cuenta.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
