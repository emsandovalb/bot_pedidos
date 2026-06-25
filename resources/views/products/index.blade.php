<x-app-layout>
    @php
        $productItems = method_exists($products, 'getCollection') ? $products->getCollection() : collect($products ?? []);

        $activeCount = $productItems->where('is_active', true)->count();
        $inactiveCount = $productItems->where('is_active', false)->count();
        $aliasCount = $productItems->sum(function ($product) {
            $aliasCollection = collect($product->productAliases ?? []);

            return (int) ($product->product_aliases_count ?? $aliasCollection->count());
        });
        $latestUpdatedAt = $productItems
            ->pluck('updated_at')
            ->filter()
            ->sortDesc()
            ->first();
    @endphp

    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 shadow-sm">
                {{ session('status') }}
            </div>
        @endif

        <section class="overflow-hidden rounded-[2rem] border border-slate-200/80 bg-[linear-gradient(135deg,rgba(20,110,219,0.08),rgba(22,163,74,0.06)_40%,rgba(255,255,255,1)_82%)] shadow-sm">
            <div class="grid gap-6 px-6 py-7 lg:grid-cols-[1.2fr_0.8fr] lg:px-8">
                <div class="space-y-4">
                    <div class="inline-flex rounded-full bg-brand-primary/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-brand-primary ring-1 ring-inset ring-blue-100">
                        Catálogo de productos
                    </div>
                    <div class="max-w-3xl space-y-3">
                        <h1 class="text-3xl font-semibold tracking-tight text-brand-navy sm:text-4xl">Catálogo de productos</h1>
                        <p class="max-w-2xl text-sm leading-6 text-slate-600 sm:text-base">
                            Administra los productos que Benditio puede reconocer automáticamente.
                        </p>
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row">
                        @if ($canManageProducts)
                            <a href="{{ route('products.create') }}" class="brand-btn-primary justify-center">
                                Nuevo producto
                            </a>
                        @endif
                        <a href="{{ route('products.import') }}" class="brand-btn-secondary justify-center">
                            Importar productos
                        </a>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <article class="rounded-2xl border border-slate-200/80 bg-white/90 p-4 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Productos activos</p>
                        <p class="mt-2 text-3xl font-semibold text-brand-navy">{{ $productItems->isNotEmpty() ? $activeCount : '-' }}</p>
                        <p class="mt-1 text-sm text-slate-500">{{ $productItems->isNotEmpty() ? 'Visibles y listos para reconocimiento' : 'Sin datos' }}</p>
                    </article>
                    <article class="rounded-2xl border border-slate-200/80 bg-white/90 p-4 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Productos inactivos</p>
                        <p class="mt-2 text-3xl font-semibold text-brand-navy">{{ $productItems->isNotEmpty() ? $inactiveCount : '-' }}</p>
                        <p class="mt-1 text-sm text-slate-500">{{ $productItems->isNotEmpty() ? 'Pausados temporalmente' : 'Sin datos' }}</p>
                    </article>
                    <article class="rounded-2xl border border-slate-200/80 bg-white/90 p-4 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total alias</p>
                        <p class="mt-2 text-3xl font-semibold text-brand-navy">{{ $productItems->isNotEmpty() ? $aliasCount : '-' }}</p>
                        <p class="mt-1 text-sm text-slate-500">{{ $productItems->isNotEmpty() ? 'Pistas de reconocimiento activas' : 'Sin datos' }}</p>
                    </article>
                    <article class="rounded-2xl border border-slate-200/80 bg-white/90 p-4 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Última actualización</p>
                        <p class="mt-2 text-lg font-semibold text-brand-navy">
                            {{ $latestUpdatedAt ? $latestUpdatedAt->format('d/m/Y') : '-' }}
                        </p>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ $latestUpdatedAt ? $latestUpdatedAt->diffForHumans() : 'Sin datos' }}
                        </p>
                    </article>
                </div>
            </div>
        </section>

        @if ($productItems->isEmpty())
            <section class="rounded-[2rem] border border-dashed border-blue-200 bg-blue-50/70 px-6 py-16 text-center shadow-sm">
                <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-brand-primary/10 text-4xl text-brand-primary ring-1 ring-inset ring-blue-100">
                    📦
                </div>
                <h2 class="mt-6 text-2xl font-semibold text-brand-navy">Aún no hay productos.</h2>
                <p class="mx-auto mt-3 max-w-xl text-sm leading-6 text-slate-600">
                    Comienza creando un producto o importando tu catálogo.
                </p>
                <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                    @if ($canManageProducts)
                        <a href="{{ route('products.create') }}" class="brand-btn-primary justify-center">
                            Crear producto
                        </a>
                    @endif
                    <a href="{{ route('products.import') }}" class="brand-btn-secondary justify-center">
                        Importar catálogo
                    </a>
                </div>
            </section>
        @else
            <section class="space-y-4">
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-brand-navy">Productos</h2>
                        <p class="mt-1 text-sm text-slate-600">Vista rápida para revisar estado, SKU y alias.</p>
                    </div>
                    <p class="hidden text-sm text-slate-500 md:block">{{ $productItems->count() }} productos visibles</p>
                </div>

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($products as $product)
                        @php
                            $aliases = collect($product->productAliases ?? []);
                            $aliasPreview = $aliases->take(3);
                        @endphp

                        <article class="flex h-full flex-col rounded-[1.75rem] border border-slate-200/80 border-l-4 border-l-brand-primary bg-white p-5 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-lg">
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <h3 class="truncate text-lg font-semibold text-brand-navy">{{ $product->name }}</h3>
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">
                                            SKU: {{ $product->sku ?? 'Sin SKU' }}
                                        </span>
                                        <span class="inline-flex rounded-full bg-blue-50 px-2.5 py-1 text-xs font-medium text-brand-primary ring-1 ring-inset ring-blue-100">
                                            Unidad: {{ $product->unit_label ?? 'Sin unidad' }}
                                        </span>
                                    </div>
                                </div>
                                <span class="inline-flex shrink-0 rounded-full px-3 py-1 text-xs font-semibold {{ $product->is_active ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100' : 'bg-slate-100 text-slate-600 ring-1 ring-slate-200' }}">
                                    {{ $product->is_active ? 'Activo' : 'Inactivo' }}
                                </span>
                            </div>

                            <div class="mt-5 grid grid-cols-2 gap-3">
                                <div class="rounded-2xl border border-slate-100 bg-slate-50 px-3 py-3">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Alias</p>
                                    <p class="mt-1 text-xl font-semibold text-brand-navy">{{ (int) ($product->product_aliases_count ?? $aliases->count()) }}</p>
                                </div>
                                <div class="rounded-2xl border border-slate-100 bg-slate-50 px-3 py-3">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Sucursal</p>
                                    <p class="mt-1 truncate text-sm font-semibold text-brand-navy">{{ $product->branch?->name ?? 'Todas las sucursales' }}</p>
                                </div>
                            </div>

                            <div class="mt-5 space-y-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Alias</p>
                                <div class="flex flex-wrap gap-2">
                                    @forelse ($aliasPreview as $alias)
                                        <span class="inline-flex rounded-full bg-brand-primary/8 px-3 py-1 text-xs font-medium text-brand-navy ring-1 ring-inset ring-blue-100">
                                            {{ $alias->alias }}
                                        </span>
                                    @empty
                                        <span class="text-sm text-slate-500">Sin alias configurados.</span>
                                    @endforelse
                                </div>
                                @if ($aliases->count() > 3)
                                    <p class="text-xs text-slate-500">+{{ $aliases->count() - 3 }} alias más</p>
                                @endif
                            </div>

                            <div class="mt-6 flex flex-col gap-3 border-t border-slate-100 pt-4 sm:flex-row">
                                <a href="{{ route('products.edit', $product) }}" class="brand-btn-secondary justify-center px-4 py-2 text-sm">
                                    Editar
                                </a>
                                @if ($canManageProducts)
                                    <form method="POST" action="{{ route('products.toggle', $product) }}" class="sm:flex-1">
                                        @csrf
                                        <button type="submit" class="brand-btn-secondary w-full justify-center px-4 py-2 text-sm">
                                            {{ $product->is_active ? 'Desactivar' : 'Activar' }}
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>

                @if (method_exists($products, 'links'))
                    <div class="pt-4">
                        {{ $products->links() }}
                    </div>
                @endif
            </section>
        @endif
    </div>
</x-app-layout>
