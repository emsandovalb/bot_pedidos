<x-app-layout>
    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-slate-900">Edit product</h1>
                <p class="mt-1 text-sm text-slate-600">Update catalog fields and manage aliases.</p>
            </div>
            <a href="{{ route('products.index') }}" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">
                Back to catalog
            </a>
        </div>

        <div class="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <form method="POST" action="{{ route('products.update', $product) }}" class="space-y-6">
                    @csrf
                    @method('PATCH')
                    @include('products._form', ['product' => $product, 'branches' => $branches])

                    <div class="flex items-center justify-end gap-3 border-t border-slate-200 pt-4">
                        <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                            Update product
                        </button>
                    </div>
                </form>
            </div>

            <div class="space-y-6">
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-900">Add alias</h2>
                    <p class="mt-1 text-sm text-slate-600">Aliases are normalized automatically.</p>

                    <form method="POST" action="{{ route('product-aliases.store', $product) }}" class="mt-5 space-y-4">
                        @csrf

                        <div>
                            <label for="alias" class="block text-sm font-medium text-slate-700">Alias</label>
                            <input id="alias" name="alias" type="text" value="{{ old('alias') }}" class="brand-input mt-1 block w-full rounded-xl" maxlength="255" required>
                            @error('alias')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="match_weight" class="block text-sm font-medium text-slate-700">Match weight</label>
                            <input id="match_weight" name="match_weight" type="number" min="1" max="1000" step="1" value="{{ old('match_weight', 100) }}" class="brand-input mt-1 block w-full rounded-xl">
                            @error('match_weight')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700">
                            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true)) class="rounded border-slate-300 text-brand-primary focus:ring-brand-primary">
                            Active
                        </label>

                        <div class="flex justify-end">
                            <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                                Add alias
                            </button>
                        </div>
                    </form>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-900">Aliases</h2>
                            <p class="mt-1 text-sm text-slate-600">{{ $product->product_aliases_count }} aliases configured.</p>
                        </div>
                    </div>

                    <div class="mt-5 overflow-hidden rounded-2xl border border-slate-200/80">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-slate-500">
                                <tr>
                                    <th class="px-3 py-2 text-left font-medium">Alias</th>
                                    <th class="px-3 py-2 text-left font-medium">Weight</th>
                                    <th class="px-3 py-2 text-left font-medium">Status</th>
                                    <th class="px-3 py-2 text-left font-medium">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 bg-white">
                                @forelse ($product->productAliases as $alias)
                                    <tr>
                                        <td class="px-3 py-2 text-slate-900">{{ $alias->alias }}</td>
                                        <td class="px-3 py-2 text-slate-600">{{ $alias->match_weight }}</td>
                                        <td class="px-3 py-2 text-slate-600">
                                            <span class="brand-badge {{ $alias->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' }}">
                                                {{ $alias->is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 text-slate-600">
                                            <form method="POST" action="{{ route('product-aliases.destroy', $alias) }}" onsubmit="return confirm('Delete this alias?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-xs font-medium text-rose-600 hover:text-rose-700">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-3 py-6 text-center text-slate-500">No aliases configured yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
