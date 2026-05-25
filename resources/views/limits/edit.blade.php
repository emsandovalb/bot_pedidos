<x-app-layout>
    <div class="space-y-6">
        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-slate-900">Editar registro</h1>
                <p class="mt-1 text-sm text-slate-600">
                    Actualiza la sucursal, referencia, código o valor máximo.
                </p>
            </div>
            <a href="{{ route('limits.index') }}" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">
                Volver al catálogo
            </a>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <form method="POST" action="{{ route('limits.update', $limit) }}" class="space-y-4">
                @csrf
                @method('PUT')

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label for="branch_id" class="block text-sm font-medium text-slate-700">Sucursal</label>
                        <select id="branch_id" name="branch_id" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" @selected(old('branch_id', $limit->branch_id) == $branch->id)>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                        @error('branch_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="draw_id" class="block text-sm font-medium text-slate-700">Referencia</label>
                        <select id="draw_id" name="draw_id" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                            @foreach ($draws as $draw)
                                <option value="{{ $draw->id }}" @selected(old('draw_id', $limit->draw_id) == $draw->id)>{{ $draw->name }}</option>
                            @endforeach
                        </select>
                        @error('draw_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="number" class="block text-sm font-medium text-slate-700">Código</label>
                        <select id="number" name="number" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                            @foreach ($numbers as $number)
                                <option value="{{ $number }}" @selected(old('number', $limit->number) === $number)>{{ $number }}</option>
                            @endforeach
                        </select>
                        @error('number')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="max_amount" class="block text-sm font-medium text-slate-700">Valor máximo</label>
                        <input id="max_amount" name="max_amount" type="number" step="0.01" min="0.01" value="{{ old('max_amount', $limit->max_amount) }}" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                        @error('max_amount')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-slate-200 pt-4">
                    <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                        Actualizar registro
                    </button>
                </div>
            </form>
        </div>

        <div class="rounded-2xl border border-rose-200 bg-rose-50 p-6 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-rose-900">Eliminar registro</h2>
                    <p class="mt-1 text-sm text-rose-800">Esto elimina el registro para esta sucursal, referencia y código.</p>
                </div>
                <form method="POST" action="{{ route('limits.delete', $limit) }}" onsubmit="return confirm('¿Eliminar este registro?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rounded-md border border-rose-300 px-4 py-2 text-sm font-medium text-rose-700 hover:bg-rose-100">
                        Eliminar
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
