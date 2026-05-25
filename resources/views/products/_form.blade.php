@php
    $selectedBranchId = old('branch_id', $product->branch_id);
    $isActive = old('is_active', $product->exists ? $product->is_active : true);
@endphp

<div class="grid gap-4 md:grid-cols-2">
    <div>
        <label for="name" class="block text-sm font-medium text-slate-700">Name</label>
        <input id="name" name="name" type="text" value="{{ old('name', $product->name) }}" class="brand-input mt-1 block w-full rounded-xl" maxlength="255" required>
        @error('name')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="sku" class="block text-sm font-medium text-slate-700">SKU</label>
        <input id="sku" name="sku" type="text" value="{{ old('sku', $product->sku) }}" class="brand-input mt-1 block w-full rounded-xl" maxlength="255" placeholder="SKU-001">
        @error('sku')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="unit_label" class="block text-sm font-medium text-slate-700">Unit label</label>
        <input id="unit_label" name="unit_label" type="text" value="{{ old('unit_label', $product->unit_label) }}" class="brand-input mt-1 block w-full rounded-xl" maxlength="255" placeholder="bolsa">
        @error('unit_label')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="branch_id" class="block text-sm font-medium text-slate-700">Branch</label>
        <select id="branch_id" name="branch_id" class="brand-input mt-1 block w-full rounded-xl">
            <option value="">All branches</option>
            @foreach ($branches as $branch)
                <option value="{{ $branch->id }}" @selected((string) $selectedBranchId === (string) $branch->id)>{{ $branch->name }}</option>
            @endforeach
        </select>
        @error('branch_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="sort_order" class="block text-sm font-medium text-slate-700">Sort order</label>
        <input id="sort_order" name="sort_order" type="number" min="0" step="1" value="{{ old('sort_order', $product->sort_order ?? 0) }}" class="brand-input mt-1 block w-full rounded-xl">
        @error('sort_order')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div class="flex items-center gap-3 pt-6">
        <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700">
            <input type="checkbox" name="is_active" value="1" @checked((bool) $isActive) class="rounded border-slate-300 text-brand-primary focus:ring-brand-primary">
            Active
        </label>
        @error('is_active')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
</div>
