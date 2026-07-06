<x-app-layout>
    @php
        $ordersCount = count($ordersData);
        $unreadCount = collect($ordersData)->where('unread', true)->count();
        $duplicateCount = collect($ordersData)->where('duplicate', true)->count();
        $vipCount = collect($ordersData)->where('vip', true)->count();

        $statusChips = [
            'all' => 'Todos',
            'nuevos' => 'Nuevos',
            'en_revision' => 'En revision',
            'preparando' => 'Preparando',
            'listos' => 'Listos',
            'despachados' => 'Despachados',
        ];

        $channelChips = [
            '' => 'Todo canal',
            'whatsapp' => 'WhatsApp',
            'telegram' => 'Telegram',
        ];

        $priorityChips = [
            '' => 'Toda prioridad',
            'urgent' => 'Urgente',
            'duplicate' => 'Duplicado',
            'vip' => 'VIP',
        ];

        $statusToneFor = static function (string $status): string {
            return match ($status) {
                \App\Models\Order::STATUS_PENDING_REVIEW => 'bg-amber-50 text-amber-800 ring-1 ring-amber-100',
                \App\Models\Order::STATUS_CONFIRMED => 'bg-blue-50 text-blue-800 ring-1 ring-blue-100',
                \App\Models\Order::STATUS_PREPARING => 'bg-violet-50 text-violet-800 ring-1 ring-violet-100',
                \App\Models\Order::STATUS_READY_FOR_DISPATCH => 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-100',
                \App\Models\Order::STATUS_DISPATCHED => 'bg-slate-100 text-slate-700 ring-1 ring-slate-200',
                \App\Models\Order::STATUS_CANCELLED => 'bg-red-50 text-red-800 ring-1 ring-red-100',
                \App\Models\Order::STATUS_REJECTED => 'bg-slate-100 text-slate-700 ring-1 ring-slate-200',
                default => 'bg-slate-100 text-slate-700 ring-1 ring-slate-200',
            };
        };

        $channelToneFor = static function (string $channel): string {
            return match (strtolower($channel)) {
                'whatsapp' => 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-100',
                'telegram' => 'bg-blue-50 text-blue-800 ring-1 ring-blue-100',
                default => 'bg-slate-100 text-slate-700 ring-1 ring-slate-200',
            };
        };

        $safeQuery = request()->except(['page', 'order']);
    @endphp

    <div
        x-data="operationsCenter({
            orders: @js($ordersData),
            activeId: @js($selectedOrderId),
        })"
        class="space-y-6"
    >
        <div class="overflow-hidden rounded-[28px] border border-slate-200/70 bg-[linear-gradient(135deg,rgba(20,110,219,0.08),rgba(22,163,74,0.05)_36%,rgba(255,255,255,1)_78%)] shadow-[0_18px_50px_-28px_rgba(15,23,42,0.45)]">
            <div class="flex flex-col gap-6 p-6 sm:p-8 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-3xl">
                    <div class="inline-flex items-center rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-brand-primary ring-1 ring-inset ring-blue-100">
                        Benditio Operations Center
                    </div>
                    <h1 class="mt-4 text-3xl font-semibold tracking-tight text-brand-navy sm:text-4xl">
                        Smart Inbox
                    </h1>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600 sm:text-base">
                        Operate WhatsApp and Telegram orders from one place with fast review, quick status actions, and customer context on the side.
                    </p>
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-2xl border border-slate-200/80 bg-white px-4 py-3 shadow-sm">
                        <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Pedidos</div>
                        <div class="mt-1 text-2xl font-semibold text-brand-navy">{{ $ordersCount }}</div>
                    </div>
                    <div class="rounded-2xl border border-amber-200/80 bg-white px-4 py-3 shadow-sm">
                        <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Sin leer</div>
                        <div class="mt-1 text-2xl font-semibold text-amber-700">{{ $unreadCount }}</div>
                    </div>
                    <div class="rounded-2xl border border-emerald-200/80 bg-white px-4 py-3 shadow-sm">
                        <div class="text-xs font-medium uppercase tracking-wide text-slate-500">VIP</div>
                        <div class="mt-1 text-2xl font-semibold text-emerald-700">{{ $vipCount }}</div>
                    </div>
                </div>
            </div>
        </div>

        <form method="GET" action="{{ route('operations.index') }}" class="rounded-[24px] border border-slate-200/70 bg-white p-4 shadow-sm sm:p-5">
            <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_auto]">
                <label class="block">
                    <span class="sr-only">Buscar pedidos</span>
                    <input
                        type="search"
                        name="search"
                        value="{{ $filters['search'] ?? '' }}"
                        placeholder="Buscar cliente, telefono, mensaje o ID"
                        class="brand-input w-full rounded-2xl px-4 py-3 text-sm"
                    >
                </label>

                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="brand-btn-primary">Buscar</button>
                    <a href="{{ route('operations.index') }}" class="brand-btn-secondary">Limpiar</a>
                </div>
            </div>

            @foreach ([
                'status' => $statusChips,
                'channel' => $channelChips,
                'priority' => $priorityChips,
            ] as $filterName => $options)
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach ($options as $value => $label)
                        @php
                            $isActive = (string) ($filters[$filterName] ?? '') === (string) $value;
                            $query = array_merge($safeQuery, [$filterName => $value ?: null]);
                            unset($query['page']);
                        @endphp
                        <a
                            href="{{ route('operations.index', array_filter($query, static fn ($item) => $item !== null && $item !== '')) }}"
                            class="inline-flex items-center rounded-full border px-4 py-2 text-sm font-medium transition {{ $isActive ? 'border-brand-primary bg-blue-50 text-brand-primary ring-1 ring-blue-100' : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:bg-slate-50' }}"
                        >
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
            @endforeach
        </form>

        <div class="grid gap-6 lg:grid-cols-[340px_minmax(0,1fr)_360px]">
            <aside class="space-y-4 lg:sticky lg:top-6 lg:self-start">
                <div class="rounded-[28px] border border-slate-200/80 bg-white p-4 shadow-[0_18px_50px_-34px_rgba(15,23,42,0.35)]">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Inbox</div>
                            <div class="mt-1 text-lg font-semibold text-brand-navy">{{ $ordersCount }} pedidos</div>
                        </div>
                        <div class="rounded-full bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-500 ring-1 ring-slate-200">
                            Foco operativo
                        </div>
                    </div>

                    <div class="mt-4 space-y-3">
                        @forelse ($ordersData as $order)
                            <button
                                type="button"
                                @click="select({{ $order['id'] }})"
                                class="block w-full rounded-[24px] border border-slate-200/80 bg-white p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                                :class="activeId === {{ $order['id'] }} ? 'border-brand-primary ring-2 ring-blue-100' : ''"
                                data-action="select"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <div class="text-sm font-semibold text-brand-navy">{{ $order['customer_name'] }}</div>
                                            @if ($order['unread'])
                                                <span class="h-2.5 w-2.5 rounded-full bg-amber-500" title="Sin leer"></span>
                                            @endif
                                            @if ($order['duplicate'])
                                                <span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-800 ring-1 ring-amber-100">Duplicado</span>
                                            @endif
                                            @if ($order['vip'])
                                                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-800 ring-1 ring-emerald-100">VIP</span>
                                            @endif
                                        </div>

                                        <div class="mt-1 text-sm text-slate-600">{{ $order['preview'] }}</div>
                                    </div>

                                    <div class="text-right text-xs text-slate-500">
                                        <div>{{ $order['elapsed_label'] }}</div>
                                        <div class="mt-1 inline-flex items-center rounded-full px-2.5 py-1 font-semibold {{ $channelToneFor($order['channel_key']) }}">
                                            {{ $order['channel'] }}
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-3 flex flex-wrap items-center gap-2">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusToneFor($order['status']) }}">
                                        {{ $order['status_label'] }}
                                    </span>
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700 ring-1 ring-slate-200">
                                        {{ $order['items_count'] }} item(s)
                                    </span>
                                    <span class="inline-flex items-center rounded-full bg-white px-2.5 py-1 text-xs font-semibold text-slate-500 ring-1 ring-slate-200">
                                        #{{ $order['id'] }}
                                    </span>
                                </div>
                            </button>
                        @empty
                            <div class="rounded-[24px] border border-dashed border-slate-300 bg-slate-50 px-6 py-14 text-center">
                                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-3xl bg-white text-3xl shadow-sm ring-1 ring-slate-200">
                                    <svg viewBox="0 0 24 24" fill="none" class="h-8 w-8 text-brand-primary" aria-hidden="true">
                                        <path d="M4 7.5h16v11H4v-11Zm0 0 8 6 8-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </div>
                                <h2 class="mt-5 text-lg font-semibold text-brand-navy">No hay pedidos pendientes</h2>
                                <p class="mt-2 text-sm leading-6 text-slate-600">Cuando lleguen nuevos pedidos, apareceran aqui para gestion rapida.</p>
                            </div>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-[24px] border border-slate-200/70 bg-white p-4 shadow-sm">
                    {{ $orders->links() }}
                </div>
            </aside>

            <section class="space-y-6">
                <template x-if="activeOrder">
                    <div class="space-y-6">
                        <div class="rounded-[28px] border border-slate-200/80 bg-white p-5 shadow-[0_18px_50px_-34px_rgba(15,23,42,0.38)] sm:p-6">
                            <div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
                                <div class="min-w-0 flex-1 space-y-5">
                                    <div class="flex flex-wrap items-center gap-3">
                                        <div class="rounded-2xl bg-slate-50 px-3 py-2 ring-1 ring-inset ring-slate-100">
                                            <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Pedido</div>
                                            <div class="text-lg font-semibold text-brand-navy" x-text="'#' + activeOrder.id"></div>
                                        </div>

                                        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold" :class="activeOrder.status_tone" x-text="activeOrder.status_label"></span>
                                        <span class="inline-flex items-center rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-brand-primary ring-1 ring-blue-100" x-text="activeOrder.channel"></span>
                                        <span class="text-sm text-slate-500" x-text="activeOrder.elapsed_label"></span>
                                    </div>

                                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                                        <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                            <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Cliente</div>
                                            <div class="mt-1 text-sm font-semibold text-brand-navy" x-text="activeOrder.customer_name"></div>
                                            <div class="mt-1 text-sm text-slate-600" x-text="activeOrder.customer_phone"></div>
                                        </div>
                                        <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                            <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Sucursal</div>
                                            <div class="mt-1 text-sm font-semibold text-brand-navy" x-text="activeOrder.branch_name"></div>
                                        </div>
                                        <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                            <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Recibido</div>
                                            <div class="mt-1 text-sm font-semibold text-brand-navy" x-text="activeOrder.created_at_label"></div>
                                        </div>
                                        <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                            <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Confianza</div>
                                            <div class="mt-1 flex items-center gap-2">
                                                <span class="inline-flex items-center rounded-full bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 ring-1 ring-slate-200" x-text="activeOrder.parser_confidence !== null ? Number(activeOrder.parser_confidence).toFixed(2) : 'Sin dato'"></span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="grid gap-4 xl:grid-cols-[1.25fr_0.95fr]">
                                        <div class="rounded-2xl border border-slate-200/80 border-l-4 border-l-brand-primary bg-white p-4">
                                            <div class="text-sm font-semibold text-brand-navy">Mensaje original</div>
                                            <p class="mt-3 text-sm leading-6 text-slate-700" x-text="activeOrder.preview"></p>
                                        </div>

                                        <div class="rounded-2xl border border-slate-200/80 border-l-4 border-l-amber-500 bg-white p-4">
                                            <div class="flex items-center justify-between gap-3">
                                                <h2 class="text-sm font-semibold text-brand-navy">Recognized items</h2>
                                                <span class="text-xs font-medium uppercase tracking-wide text-slate-500" x-text="activeOrder.items_count + ' item(s)'"></span>
                                            </div>

                                            <div class="mt-3 space-y-3">
                                                <template x-for="item in activeOrder.items" :key="item.id">
                                                    <div class="rounded-2xl border border-emerald-100 bg-emerald-50 p-3">
                                                        <div class="flex flex-wrap items-center gap-2">
                                                            <span class="inline-flex items-center rounded-full bg-white px-2.5 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-100">Item</span>
                                                            <span class="text-sm font-semibold text-emerald-900" x-text="item.product_name ?? item.raw_text ?? 'Sin texto'"></span>
                                                        </div>
                                                        <div class="mt-2 text-xs leading-5 text-emerald-800">
                                                            <span x-text="item.quantity"></span>
                                                            <span x-text="item.unit ? ' ' + item.unit : ''"></span>
                                                        </div>
                                                    </div>
                                                </template>
                                                <template x-if="activeOrder.items.length === 0">
                                                    <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-sm text-slate-600">
                                                        No recognized items yet.
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex shrink-0 flex-col gap-3 xl:w-72">
                                    <div class="rounded-2xl border border-slate-200/80 bg-slate-50 px-4 py-3 text-xs leading-5 text-slate-500">
                                        Fast actions keep the operator in flow. Keyboard shortcuts can hook into the same data-action values later.
                                    </div>

                                    <form method="POST" :action="activeOrder.actions.confirm">
                                        @csrf
                                        <button type="submit" data-action="confirm" class="brand-btn-primary w-full py-3">
                                            Confirm
                                        </button>
                                    </form>

                                    <form method="POST" :action="activeOrder.actions.prepare">
                                        @csrf
                                        <button type="submit" data-action="prepare" class="brand-btn-secondary w-full py-3">
                                            Prepare
                                        </button>
                                    </form>

                                    <form method="POST" :action="activeOrder.actions.ready">
                                        @csrf
                                        <button type="submit" data-action="ready" class="brand-btn-secondary w-full py-3">
                                            Ready
                                        </button>
                                    </form>

                                    <form method="POST" :action="activeOrder.actions.dispatch">
                                        @csrf
                                        <button type="submit" data-action="dispatch" class="brand-btn-secondary w-full py-3">
                                            Dispatch
                                        </button>
                                    </form>

                                    <form method="POST" :action="activeOrder.actions.reject">
                                        @csrf
                                        <button type="submit" data-action="reject" class="brand-btn-danger w-full py-3">
                                            Reject
                                        </button>
                                    </form>

                                    <form method="POST" :action="activeOrder.actions.cancel">
                                        @csrf
                                        <button type="submit" data-action="cancel" class="brand-btn-secondary w-full py-3">
                                            Cancel
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <form method="POST" :action="activeOrder.actions.update" class="rounded-[28px] border border-slate-200/80 bg-white p-5 shadow-[0_18px_50px_-34px_rgba(15,23,42,0.38)] sm:p-6">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="items_json" :value="serializedItems()">

                            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Edit items</div>
                                    <h2 class="mt-1 text-lg font-semibold text-brand-navy">Adjust quantities and lines without leaving the inbox</h2>
                                </div>

                                <button type="button" @click="addItem()" data-action="add-item" class="brand-btn-secondary">
                                    Add item
                                </button>
                            </div>

                            <div class="mt-4 overflow-hidden rounded-[24px] border border-slate-200/80">
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                                        <thead class="bg-slate-50">
                                            <tr>
                                                <th class="px-4 py-3 text-left font-semibold text-slate-600">Qty</th>
                                                <th class="px-4 py-3 text-left font-semibold text-slate-600">Unit</th>
                                                <th class="px-4 py-3 text-left font-semibold text-slate-600">Item</th>
                                                <th class="px-4 py-3 text-left font-semibold text-slate-600">Notes</th>
                                                <th class="px-4 py-3 text-right font-semibold text-slate-600">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 bg-white">
                                            <template x-for="(item, index) in draftItems" :key="item.id ?? 'new-' + index">
                                                <tr class="align-top">
                                                    <td class="px-4 py-3">
                                                        <input x-model="item.quantity" type="number" min="0.01" step="0.01" class="brand-input w-24 rounded-xl px-3 py-2">
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <input x-model="item.unit" type="text" class="brand-input w-28 rounded-xl px-3 py-2" placeholder="unit">
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <input x-model="item.raw_text" type="text" class="brand-input w-full rounded-xl px-3 py-2" placeholder="Item description">
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <input x-model="item.notes" type="text" class="brand-input w-full rounded-xl px-3 py-2" placeholder="Optional note">
                                                    </td>
                                                    <td class="px-4 py-3 text-right">
                                                        <button type="button" @click="removeItem(index)" data-action="remove-item" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 transition hover:border-slate-300 hover:bg-slate-50">
                                                            Remove
                                                        </button>
                                                    </td>
                                                </tr>
                                            </template>
                                            <template x-if="draftItems.length === 0">
                                                <tr>
                                                    <td colspan="5" class="px-4 py-10 text-center text-sm text-slate-500">
                                                        No items on this order yet.
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div class="text-sm text-slate-500">
                                    {{ data_get($selectedOrder, 'customer_context.segment', 'Inactive') }} customer profile is read only.
                                </div>

                                <button type="submit" data-action="save" class="brand-btn-primary px-6 py-3">
                                    Save changes
                                </button>
                            </div>
                        </form>
                    </div>
                </template>

                <template x-if="!activeOrder">
                    <div class="rounded-[28px] border border-dashed border-slate-300 bg-white px-6 py-16 text-center shadow-sm">
                        <div class="mx-auto max-w-md">
                            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-3xl bg-blue-50 text-3xl text-brand-primary">
                                <svg viewBox="0 0 24 24" fill="none" class="h-8 w-8" aria-hidden="true">
                                    <path d="M4 7.5h16v11H4v-11Zm0 0 8 6 8-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </div>
                            <h2 class="mt-5 text-lg font-semibold text-brand-navy">No hay pedidos pendientes</h2>
                            <p class="mt-2 text-sm leading-6 text-slate-600">Cuando haya pedidos disponibles, selecciona uno en la inbox para abrir el workspace operativo.</p>
                        </div>
                    </div>
                </template>
            </section>

            <aside class="space-y-4 lg:sticky lg:top-6 lg:self-start">
                <template x-if="activeOrder">
                    <div class="space-y-4">
                        <div class="rounded-[28px] border border-slate-200/80 bg-white p-5 shadow-[0_18px_50px_-34px_rgba(15,23,42,0.38)]">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Customer context</div>
                                    <h2 class="mt-1 text-xl font-semibold text-brand-navy" x-text="activeOrder.customer_context.name"></h2>
                                </div>

                                <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold bg-slate-100 text-slate-700 ring-1 ring-slate-200" x-text="activeOrder.customer_context.segment"></span>
                            </div>

                            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                    <div class="text-xs uppercase tracking-wide text-slate-500">Phone</div>
                                    <div class="mt-1 text-sm font-semibold text-brand-navy" x-text="activeOrder.customer_context.phone"></div>
                                </div>
                                <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                    <div class="text-xs uppercase tracking-wide text-slate-500">Total orders</div>
                                    <div class="mt-1 text-sm font-semibold text-brand-navy" x-text="activeOrder.customer_context.total_orders"></div>
                                </div>
                                <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                    <div class="text-xs uppercase tracking-wide text-slate-500">Favorite channel</div>
                                    <div class="mt-1 text-sm font-semibold text-brand-navy" x-text="activeOrder.customer_context.favorite_channel.name"></div>
                                </div>
                                <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                    <div class="text-xs uppercase tracking-wide text-slate-500">Open notifications</div>
                                    <div class="mt-1 text-sm font-semibold text-brand-navy" x-text="activeOrder.customer_context.open_notifications"></div>
                                </div>
                            </div>

                            <div class="mt-4 rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                <div class="text-xs uppercase tracking-wide text-slate-500">Favorite products</div>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <template x-for="product in activeOrder.customer_context.favorite_products" :key="product">
                                        <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-700 ring-1 ring-slate-200" x-text="product"></span>
                                    </template>
                                    <span class="text-sm text-slate-500" x-show="activeOrder.customer_context.favorite_products.length === 0">No data yet</span>
                                </div>
                            </div>

                            <div class="mt-4 rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                <div class="text-xs uppercase tracking-wide text-slate-500">Last order</div>
                                <div class="mt-1 text-sm font-semibold text-brand-navy" x-text="activeOrder.customer_context.last_order ? activeOrder.customer_context.last_order.label : 'No orders yet'"></div>
                                <div class="mt-1 text-xs text-slate-500" x-text="activeOrder.customer_context.last_order ? activeOrder.customer_context.last_order.elapsed : ''"></div>
                            </div>
                        </div>

                        <div class="rounded-[28px] border border-slate-200/80 bg-white p-5 shadow-[0_18px_50px_-34px_rgba(15,23,42,0.38)]">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Current alerts</div>
                                    <h2 class="mt-1 text-lg font-semibold text-brand-navy">Open issues</h2>
                                </div>
                            </div>

                            <div class="mt-4 flex flex-wrap gap-2">
                                <template x-for="alert in activeOrder.customer_context.current_alerts" :key="alert">
                                    <span class="inline-flex items-center rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800 ring-1 ring-amber-100" x-text="alert"></span>
                                </template>
                                <span class="text-sm text-slate-500" x-show="activeOrder.customer_context.current_alerts.length === 0">No open alerts</span>
                            </div>

                            <div class="mt-4 rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                <div class="text-xs uppercase tracking-wide text-slate-500">Possible duplicate</div>
                                <div class="mt-1 text-sm font-semibold text-brand-navy" x-text="activeOrder.duplicate ? 'Yes' : 'No'"></div>
                            </div>
                        </div>

                        <div class="rounded-[28px] border border-slate-200/80 bg-white p-5 shadow-[0_18px_50px_-34px_rgba(15,23,42,0.38)]">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Recent activity</div>
                                    <h2 class="mt-1 text-lg font-semibold text-brand-navy">Timeline</h2>
                                </div>
                            </div>

                            <div class="mt-4 space-y-3">
                                <template x-for="activity in activeOrder.customer_context.recent_activity" :key="activity.label + activity.elapsed">
                                    <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <div class="text-sm font-semibold text-brand-navy" x-text="activity.label"></div>
                                                <div class="mt-1 text-xs text-slate-500" x-text="activity.status + ' - ' + activity.channel"></div>
                                            </div>
                                            <div class="text-xs text-slate-500" x-text="activity.elapsed"></div>
                                        </div>
                                    </div>
                                </template>
                                <span class="text-sm text-slate-500" x-show="activeOrder.customer_context.recent_activity.length === 0">No recent activity</span>
                            </div>
                        </div>
                    </div>
                </template>
            </aside>
        </div>
    </div>
</x-app-layout>
