<x-app-layout>
    <div class="space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <div class="brand-badge bg-brand-primary/10 text-brand-primary">Cierres diarios</div>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight text-brand-navy">Nuevo cierre</h1>
                <p class="mt-1 text-sm text-slate-600">Cierra el dia de trabajo y genera el resumen operativo.</p>
            </div>
            <a href="{{ route('daily-order-closures.index') }}" class="brand-btn-secondary">Volver</a>
        </div>

        <div class="brand-card p-6">
            <form method="POST" action="{{ route('daily-order-closures.store') }}" class="grid gap-5 lg:grid-cols-3">
                @csrf

                <div>
                    <label for="branch_id" class="block text-sm font-medium text-slate-700">Sucursal</label>
                    <select id="branch_id" name="branch_id" class="brand-input mt-1 block w-full rounded-xl">
                        <option value="">Seleccione una sucursal</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((string) old('branch_id', $selectedBranchId) === (string) $branch->id)>
                                {{ $branch->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('branch_id')<p class="mt-2 text-sm text-brand-danger">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="closure_date" class="block text-sm font-medium text-slate-700">Fecha de cierre</label>
                    <input id="closure_date" name="closure_date" type="date" value="{{ old('closure_date', $selectedClosureDate) }}" class="brand-input mt-1 block w-full rounded-xl">
                    @error('closure_date')<p class="mt-2 text-sm text-brand-danger">{{ $message }}</p>@enderror
                </div>

                <div class="lg:col-span-3">
                    <label for="notes" class="block text-sm font-medium text-slate-700">Notas</label>
                    <textarea id="notes" name="notes" rows="5" class="brand-input mt-1 block w-full rounded-xl">{{ old('notes') }}</textarea>
                    @error('notes')<p class="mt-2 text-sm text-brand-danger">{{ $message }}</p>@enderror
                </div>

                <div class="lg:col-span-3 flex flex-wrap items-center gap-3">
                    <button type="submit" class="brand-btn-primary">Guardar cierre</button>
                    <a href="{{ route('daily-order-closures.index') }}" class="brand-btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
