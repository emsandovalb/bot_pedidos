<x-app-layout>
    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <div class="brand-badge bg-brand-primary/10 text-brand-primary">Catalogo de productos</div>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight text-brand-navy">Catalogo de productos</h1>
                <p class="mt-2 text-sm text-slate-600">Administra el catalogo de productos y sus alias de coincidencia.</p>
            </div>
            @if ($canManageProducts)
                <a href="{{ route('products.create') }}" class="brand-btn-primary">Nuevo producto</a>
            @endif
        </div>

        <div class="overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">SKU</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Unit label</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Branch</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Aliases</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse ($products as $product)
                        <tr>
                            <td class="px-4 py-3 text-sm font-medium text-brand-navy">{{ $product->name }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $product->sku ?? '-' }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $product->unit_label ?? '-' }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $product->branch?->name ?? 'All branches' }}</td>
                            <td class="px-4 py-3 text-sm">
                                <span class="brand-badge {{ $product->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' }}">
                                    {{ $product->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $product->product_aliases_count }}</td>
                            <td class="px-4 py-3 text-sm">
                                <div class="flex flex-wrap items-center gap-2">
                                    <a href="{{ route('products.edit', $product) }}" class="brand-btn-secondary px-3 py-1.5 text-xs">Edit</a>
                                    @if ($canManageProducts)
                                        <form method="POST" action="{{ route('products.toggle', $product) }}">
                                            @csrf
                                            <button type="submit" class="brand-btn-secondary px-3 py-1.5 text-xs">
                                                {{ $product->is_active ? 'Deactivate' : 'Activate' }}
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-sm text-slate-500">No products found for this account.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
