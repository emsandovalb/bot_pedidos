<x-app-layout>
    <div class="space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <div class="text-sm text-slate-500">Order #{{ $order->id }}</div>
                <h1 class="mt-1 text-3xl font-semibold tracking-tight text-brand-navy">Edit order</h1>
                <p class="mt-2 text-sm text-slate-600">Adjust notes and item details before confirming the order.</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('orders.show', $order) }}" class="brand-btn-secondary">Back to detail</a>
                <a href="{{ route('orders.index') }}" class="brand-btn-secondary">Orders list</a>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-3">
            <div class="xl:col-span-2">
                <form method="POST" action="{{ route('orders.update', $order) }}" class="space-y-6">
                    @csrf
                    @method('PATCH')

                    <div class="brand-card p-6">
                        <div class="flex items-center justify-between gap-3">
                            <h2 class="text-base font-semibold text-brand-navy">Order notes</h2>
                            <span class="text-xs text-slate-500">Optional</span>
                        </div>
                        <textarea name="notes" rows="4" class="brand-input mt-4 block w-full rounded-xl">{{ old('notes', $order->notes) }}</textarea>
                        @error('notes')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="space-y-4">
                        <div class="flex items-center justify-between gap-3">
                            <h2 class="text-base font-semibold text-brand-navy">Items</h2>
                            <span class="text-xs text-slate-500">Catalog matching stays nullable in this phase</span>
                        </div>

                        @forelse ($order->orderItems as $item)
                            <div class="brand-card p-6">
                                <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                                    <div class="text-sm font-semibold text-slate-900">Item #{{ $item->id }}</div>
                                    <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500">
                                        <span>Sort {{ $item->sort_order }}</span>
                                        @if ($item->product !== null)
                                            <span class="brand-badge bg-emerald-100 text-emerald-800">Producto reconocido: {{ $item->product->name }}</span>
                                        @else
                                            <span class="brand-badge bg-slate-100 text-slate-700">Sin producto asociado</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="mt-4 grid gap-3 rounded-2xl border border-slate-200/80 bg-slate-50 p-4 text-sm text-slate-700 md:grid-cols-2">
                                    <div><span class="font-medium text-slate-500">Texto detectado:</span> {{ $item->raw_text ?? '-' }}</div>
                                    <div><span class="font-medium text-slate-500">Coincidencia:</span> {{ $item->matched_text ?? '-' }}</div>
                                    <div><span class="font-medium text-slate-500">Confianza:</span> {{ $item->confidence_score !== null ? number_format((float) $item->confidence_score, 2) : '-' }}</div>
                                    <div><span class="font-medium text-slate-500">Producto reconocido:</span> {{ $item->product?->name ?? 'Sin producto asociado' }}</div>
                                </div>
                                <div class="mt-4 grid gap-4 md:grid-cols-2">
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700" for="item-{{ $item->id }}-quantity">Quantity</label>
                                        <input id="item-{{ $item->id }}-quantity" name="items[{{ $item->id }}][quantity]" type="number" step="0.01" min="0" value="{{ old('items.' . $item->id . '.quantity', $item->quantity) }}" class="brand-input mt-1 block w-full rounded-xl">
                                        @error('items.' . $item->id . '.quantity')
                                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700" for="item-{{ $item->id }}-unit">Unit</label>
                                        <input id="item-{{ $item->id }}-unit" name="items[{{ $item->id }}][unit]" type="text" value="{{ old('items.' . $item->id . '.unit', $item->unit) }}" class="brand-input mt-1 block w-full rounded-xl" placeholder="piece, box, bag">
                                        @error('items.' . $item->id . '.unit')
                                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-medium text-slate-700" for="item-{{ $item->id }}-raw">Raw text</label>
                                        <input id="item-{{ $item->id }}-raw" name="items[{{ $item->id }}][raw_text]" type="text" value="{{ old('items.' . $item->id . '.raw_text', $item->raw_text) }}" class="brand-input mt-1 block w-full rounded-xl">
                                        @error('items.' . $item->id . '.raw_text')
                                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-medium text-slate-700" for="item-{{ $item->id }}-notes">Notes</label>
                                        <textarea id="item-{{ $item->id }}-notes" name="items[{{ $item->id }}][notes]" rows="3" class="brand-input mt-1 block w-full rounded-xl">{{ old('items.' . $item->id . '.notes', $item->notes) }}</textarea>
                                        @error('items.' . $item->id . '.notes')
                                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="brand-card p-6 text-sm text-slate-500">No items available on this order.</div>
                        @endforelse
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <a href="{{ route('orders.show', $order) }}" class="brand-btn-secondary">Cancel</a>
                        <button type="submit" class="brand-btn-primary">Save changes</button>
                    </div>
                </form>
            </div>

            <div class="space-y-6">
                <div class="brand-card p-6">
                    <h2 class="text-base font-semibold text-brand-navy">Summary</h2>
                    <dl class="mt-4 space-y-3 text-sm text-slate-700">
                        <div class="flex justify-between gap-4"><dt>Status</dt><dd>{{ str_replace('_', ' ', $order->status) }}</dd></div>
                        <div class="flex justify-between gap-4"><dt>Branch</dt><dd>{{ $order->branch?->name ?? '-' }}</dd></div>
                        <div class="flex justify-between gap-4"><dt>Customer</dt><dd>{{ $order->customer?->name ?? '-' }}</dd></div>
                        <div class="flex justify-between gap-4"><dt>Confidence</dt><dd>{{ $order->parser_confidence !== null ? number_format((float) $order->parser_confidence, 2) : '-' }}</dd></div>
                    </dl>
                </div>

                <div class="brand-card p-6">
                    <h2 class="text-base font-semibold text-brand-navy">Raw message</h2>
                    <div class="mt-3 whitespace-pre-wrap rounded-2xl border border-slate-200/80 bg-slate-50 p-4 text-sm text-slate-900">{{ $order->raw_message_text ?? '-' }}</div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
