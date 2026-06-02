@php
    $selectedBranchId = old('branch_id', $product->branch_id);
    $isActive = old('is_active', $product->exists ? $product->is_active : true);
@endphp

<div class="space-y-6">
    <section class="rounded-[1.75rem] border border-slate-200/80 bg-slate-50/80 p-5 shadow-sm">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Informacion basica</p>
                <h2 class="mt-2 text-xl font-semibold text-brand-navy">Datos del producto</h2>
                <p class="mt-2 text-sm text-slate-600">Manten la ficha clara para facilitar la gestion en Benditio.</p>
            </div>
        </div>

        <div class="mt-5 grid gap-4 md:grid-cols-2">
            <div class="md:col-span-2">
                <label for="name" class="block text-sm font-medium text-slate-700">Nombre</label>
                <input id="name" name="name" type="text" value="{{ old('name', $product->name) }}" class="brand-input mt-1 block w-full rounded-xl" maxlength="255" required>
                @error('name')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="sku" class="block text-sm font-medium text-slate-700">SKU</label>
                <input id="sku" name="sku" type="text" value="{{ old('sku', $product->sku) }}" class="brand-input mt-1 block w-full rounded-xl" maxlength="255" placeholder="SKU-001">
                @error('sku')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="unit_label" class="block text-sm font-medium text-slate-700">Unidad</label>
                <input id="unit_label" name="unit_label" type="text" value="{{ old('unit_label', $product->unit_label) }}" class="brand-input mt-1 block w-full rounded-xl" maxlength="255" placeholder="bolsa">
                @error('unit_label')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="branch_id" class="block text-sm font-medium text-slate-700">Sucursal</label>
                <select id="branch_id" name="branch_id" class="brand-input mt-1 block w-full rounded-xl">
                    <option value="">Todas las sucursales</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((string) $selectedBranchId === (string) $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
                @error('branch_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="sort_order" class="block text-sm font-medium text-slate-700">Orden</label>
                <input id="sort_order" name="sort_order" type="number" min="0" step="1" value="{{ old('sort_order', $product->sort_order ?? 0) }}" class="brand-input mt-1 block w-full rounded-xl">
                @error('sort_order')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>
    </section>

    <section class="rounded-[1.75rem] border border-slate-200/80 bg-white p-5 shadow-sm">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Reconocimiento</p>
                <h2 class="mt-2 text-xl font-semibold text-brand-navy">Como lo entendera Benditio</h2>
                <p class="mt-2 text-sm text-slate-600">
                    Los alias se agregan desde la pantalla de edicion, donde puedes revisar como se reconocera el producto en los mensajes.
                </p>
            </div>
        </div>

        <div class="mt-5 rounded-2xl bg-slate-50 p-4">
            <p class="text-sm font-medium text-slate-700">Ejemplo de alias</p>
            <div class="mt-3 flex flex-wrap gap-2">
                <span class="rounded-full bg-white px-3 py-1 text-xs font-medium text-brand-navy shadow-sm">tomate</span>
                <span class="rounded-full bg-white px-3 py-1 text-xs font-medium text-brand-navy shadow-sm">tomates</span>
                <span class="rounded-full bg-white px-3 py-1 text-xs font-medium text-brand-navy shadow-sm">kilos de tomate</span>
            </div>
        </div>
    </section>

    <section class="rounded-[1.75rem] border border-slate-200/80 bg-slate-50/80 p-5 shadow-sm">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Estado</p>
                <h2 class="mt-2 text-xl font-semibold text-brand-navy">Disponibilidad</h2>
                <p class="mt-2 text-sm text-slate-600">Activa el producto para que pueda ser reconocido por Benditio.</p>
            </div>
        </div>

        <div class="mt-5 flex flex-wrap items-center gap-3">
            <label class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 shadow-sm">
                <input type="checkbox" name="is_active" value="1" @checked((bool) $isActive) class="rounded border-slate-300 text-brand-primary focus:ring-brand-primary">
                Activo
            </label>
            @error('is_active')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    </section>
</div>
