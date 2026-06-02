<x-app-layout>
    @php
        $aliases = collect($product->productAliases ?? []);
        $previewAliases = $aliases->pluck('alias')->take(3);
        $previewValues = $previewAliases->isNotEmpty()
            ? $previewAliases
            : collect(['tomate', 'tomates', 'kilos de tomate']);
    @endphp

    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 shadow-sm">
                {{ session('status') }}
            </div>
        @endif

        <section class="overflow-hidden rounded-[2rem] border border-slate-200/80 bg-gradient-to-br from-white via-slate-50 to-blue-50/50 shadow-sm">
            <div class="grid gap-6 px-6 py-7 lg:grid-cols-[1.15fr_0.85fr] lg:px-8">
                <div class="space-y-4">
                    <div class="inline-flex rounded-full bg-brand-primary/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-brand-primary">
                        Editar producto
                    </div>
                    <div class="max-w-3xl space-y-3">
                        <h1 class="text-3xl font-semibold tracking-tight text-brand-navy sm:text-4xl">Editar producto</h1>
                        <p class="max-w-2xl text-sm leading-6 text-slate-600 sm:text-base">
                            Actualiza la ficha del producto, revisa sus alias y confirma como lo reconocera Benditio.
                        </p>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <article class="rounded-2xl border border-slate-200/80 bg-white/90 p-4 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Producto</p>
                        <p class="mt-2 text-lg font-semibold text-brand-navy">{{ $product->name }}</p>
                        <p class="mt-1 text-sm text-slate-500">{{ $product->sku ?? 'Sin SKU' }}</p>
                    </article>
                    <article class="rounded-2xl border border-slate-200/80 bg-white/90 p-4 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Alias</p>
                        <p class="mt-2 text-3xl font-semibold text-brand-navy">{{ $product->product_aliases_count }}</p>
                        <p class="mt-1 text-sm text-slate-500">Pistas de reconocimiento activas</p>
                    </article>
                    <article class="rounded-2xl border border-slate-200/80 bg-white/90 p-4 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Unidad</p>
                        <p class="mt-2 text-lg font-semibold text-brand-navy">{{ $product->unit_label ?? 'Sin unidad' }}</p>
                        <p class="mt-1 text-sm text-slate-500">{{ $product->branch?->name ?? 'Todas las sucursales' }}</p>
                    </article>
                    <article class="rounded-2xl border border-slate-200/80 bg-white/90 p-4 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Estado</p>
                        <span class="mt-2 inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $product->is_active ? 'bg-emerald-50 text-green-700' : 'bg-slate-100 text-slate-600' }}">
                            {{ $product->is_active ? 'Activo' : 'Inactivo' }}
                        </span>
                    </article>
                </div>
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
            <div class="rounded-[2rem] border border-slate-200/80 bg-white p-6 shadow-sm">
                <form method="POST" action="{{ route('products.update', $product) }}" class="space-y-6">
                    @csrf
                    @method('PATCH')
                    @include('products._form', ['product' => $product, 'branches' => $branches])

                    <div class="flex flex-col-reverse gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end">
                        <a href="{{ route('products.index') }}" class="brand-btn-secondary justify-center">
                            Volver al catalogo
                        </a>
                        <button type="submit" class="brand-btn-primary justify-center">
                            Guardar cambios
                        </button>
                    </div>
                </form>
            </div>

            <aside class="space-y-6">
                <div class="rounded-[2rem] border border-slate-200/80 bg-white p-6 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Alias</p>
                    <h2 class="mt-2 text-xl font-semibold text-brand-navy">Gestiona el reconocimiento</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">
                        Anade o elimina alias para que Benditio entienda distintas formas de mencionar este producto en los mensajes.
                    </p>

                    <form method="POST" action="{{ route('product-aliases.store', $product) }}" class="mt-5 space-y-4">
                        @csrf

                        <div>
                            <label for="alias" class="block text-sm font-medium text-slate-700">Alias</label>
                            <input id="alias" name="alias" type="text" value="{{ old('alias') }}" class="brand-input mt-1 block w-full rounded-xl" maxlength="255" required>
                            @error('alias')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="match_weight" class="block text-sm font-medium text-slate-700">Peso de coincidencia</label>
                            <input id="match_weight" name="match_weight" type="number" min="1" max="1000" step="1" value="{{ old('match_weight', 100) }}" class="brand-input mt-1 block w-full rounded-xl">
                            @error('match_weight')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700">
                            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true)) class="rounded border-slate-300 text-brand-primary focus:ring-brand-primary">
                            Activo
                        </label>

                        <div class="flex justify-end">
                            <button type="submit" class="brand-btn-primary">
                                Agregar alias
                            </button>
                        </div>
                    </form>
                </div>

                <div class="rounded-[2rem] border border-slate-200/80 bg-white p-6 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Seccion de alias</p>
                            <h2 class="mt-2 text-xl font-semibold text-brand-navy">Alias configurados</h2>
                            <p class="mt-2 text-sm text-slate-600">{{ $product->product_aliases_count }} alias configurados.</p>
                        </div>
                    </div>

                    <div class="mt-5 space-y-3">
                        @forelse ($product->productAliases as $alias)
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-brand-navy">{{ $alias->alias }}</p>
                                        <p class="mt-1 text-xs text-slate-500">Peso: {{ $alias->match_weight }}</p>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $alias->is_active ? 'bg-emerald-50 text-green-700' : 'bg-slate-100 text-slate-600' }}">
                                            {{ $alias->is_active ? 'Activo' : 'Inactivo' }}
                                        </span>
                                        <form method="POST" action="{{ route('product-aliases.destroy', $alias) }}" onsubmit="return confirm('Eliminar este alias?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs font-medium text-rose-600 hover:text-rose-700">
                                                Eliminar
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">
                                Aun no hay alias configurados.
                            </div>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-[2rem] border border-slate-200/80 bg-white p-6 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Vista previa de reconocimiento</p>
                    <h2 class="mt-2 text-xl font-semibold text-brand-navy">El bot reconocera:</h2>
                    <div class="mt-5 flex flex-wrap gap-2">
                        @foreach ($previewValues as $previewValue)
                            <span class="rounded-full bg-brand-primary/8 px-3 py-1 text-xs font-medium text-brand-navy">
                                {{ $previewValue }}
                            </span>
                        @endforeach
                    </div>
                    <p class="mt-4 text-sm leading-6 text-slate-600">
                        Los alias se normalizan para ayudar a Benditio a identificar el producto aunque el mensaje llegue con variaciones.
                    </p>
                </div>
            </aside>
        </div>
    </div>
</x-app-layout>
