<x-app-layout>
    @php
        $inboxOrders = $feedData['inbox'] ?? [];
        $liveCounts = $feedData['counts'] ?? [
            'pending_review' => 0,
            'confirmed' => 0,
            'preparing' => 0,
            'ready_for_dispatch' => 0,
        ];
        $selectedOrderDetails = collect($ordersData)->keyBy('id')->all();

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

        $safeQuery = request()->except(['page', 'order']);
    @endphp

    <div
        x-data="operationsCenter({
            orders: @js($inboxOrders),
            selectedOrder: @js($selectedOrder),
            selectedOrderId: @js($selectedOrderId),
            counts: @js($liveCounts),
            serverTime: @js($feedData['server_time'] ?? null),
            feedUrl: @js(route('operations.feed')),
            ordersBaseUrl: @js(url('/orders')),
            pollIntervalMs: @js(config('operations.live_queue_poll_interval_ms', 8000)),
            orderDetails: @js($selectedOrderDetails),
        })"
        x-init="init()"
        x-on:beforeunload="destroy()"
        class="space-y-6"
    >
        <div class="sr-only" aria-hidden="true">
            @foreach ($ordersData as $order)
                {{ $order['customer_name'] }} {{ $order['preview'] }}
            @endforeach
        </div>

        <div class="fixed right-4 top-4 z-50 w-[320px] max-w-[calc(100vw-2rem)]" x-cloak>
            <div
                x-show="liveToast.visible"
                x-transition
                class="live-toast-enter rounded-2xl border border-emerald-200 bg-white p-4 shadow-[0_16px_32px_-18px_rgba(15,23,42,0.35)]"
            >
                <div class="flex items-start gap-3">
                    <div class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700">🟢</div>
                    <div class="min-w-0 flex-1">
                        <div class="text-sm font-semibold text-brand-navy" x-text="liveToast.title"></div>
                        <div class="mt-0.5 text-sm text-slate-600" x-text="liveToast.customer"></div>
                        <div class="mt-0.5 text-xs text-slate-500" x-text="liveToast.elapsed"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="overflow-hidden rounded-[28px] border border-slate-200/70 bg-[linear-gradient(135deg,rgba(20,110,219,0.08),rgba(22,163,74,0.05)_36%,rgba(255,255,255,1)_78%)] shadow-[0_18px_50px_-28px_rgba(15,23,42,0.45)]">
            <div class="flex flex-col gap-6 p-6 sm:p-8 lg:flex-row lg:items-start lg:justify-between">
                <div class="max-w-3xl">
                    <div class="inline-flex items-center rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-brand-primary ring-1 ring-inset ring-blue-100">
                        Benditio Operations Center
                    </div>
                    <h1 class="mt-4 text-3xl font-semibold tracking-tight text-brand-navy sm:text-4xl">
                        Bandeja inteligente
                    </h1>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600 sm:text-base">
                        Opera pedidos de WhatsApp y Telegram desde un solo lugar con revision rapida, acciones guiadas y contexto del cliente al costado.
                    </p>
                </div>

                <div class="flex flex-col items-end gap-3">
                    <div class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-2 text-xs font-semibold shadow-sm">
                        <span class="inline-flex h-2.5 w-2.5 rounded-full" :class="{
                            'bg-emerald-500': liveConnection.state === 'live',
                            'bg-amber-400': liveConnection.state === 'reconnecting',
                            'bg-rose-500': liveConnection.state === 'offline',
                        }"></span>
                        <span x-text="liveConnection.label"></span>
                    </div>

                    <button type="button" @click="toggleLiveSound()" class="brand-btn-secondary">
                        <span x-text="isLiveMuted() ? 'Audio silenciado' : 'Audio activo'"></span>
                    </button>
                </div>
            </div>

            <div class="grid gap-3 border-t border-slate-200/70 bg-white/60 px-6 py-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 sm:px-8">
                <div class="rounded-2xl border border-amber-200/80 bg-white px-4 py-3 shadow-sm">
                    <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Pending review</div>
                    <div class="mt-1 text-2xl font-semibold text-amber-700" x-text="counts.pending_review ?? 0"></div>
                </div>
                <div class="rounded-2xl border border-blue-200/80 bg-white px-4 py-3 shadow-sm">
                    <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Confirmed</div>
                    <div class="mt-1 text-2xl font-semibold text-blue-700" x-text="counts.confirmed ?? 0"></div>
                </div>
                <div class="rounded-2xl border border-violet-200/80 bg-white px-4 py-3 shadow-sm">
                    <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Preparing</div>
                    <div class="mt-1 text-2xl font-semibold text-violet-700" x-text="counts.preparing ?? 0"></div>
                </div>
                <div class="rounded-2xl border border-emerald-200/80 bg-white px-4 py-3 shadow-sm">
                    <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Ready</div>
                    <div class="mt-1 text-2xl font-semibold text-emerald-700" x-text="counts.ready_for_dispatch ?? 0"></div>
                </div>
                <div class="rounded-2xl border border-slate-200/80 bg-white px-4 py-3 shadow-sm">
                    <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Dispatch</div>
                    <div class="mt-1 text-2xl font-semibold text-brand-navy" x-text="counts.dispatched ?? 0"></div>
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
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Bandeja</div>
                            <div class="mt-1 text-lg font-semibold text-brand-navy" x-text="orders.length + ' pedidos'"></div>
                        </div>
                        <div class="rounded-full bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-500 ring-1 ring-slate-200">
                            Live queue
                        </div>
                    </div>

                    <div class="mt-4 space-y-3">
                        <template x-for="order in orders" :key="order.id">
                            <button
                                type="button"
                                @click="select(order.id, order)"
                                class="block w-full rounded-[24px] border border-slate-200/80 bg-white p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                                :class="[
                                    activeId === order.id ? 'border-brand-primary ring-2 ring-blue-100' : '',
                                    flashOrderIds.includes(order.id) ? 'live-card-fresh border-emerald-200 bg-emerald-50/80' : '',
                                ]"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <div class="text-sm font-semibold text-brand-navy" x-text="order.customer_name"></div>
                                            <template x-if="order.unread">
                                                <span class="h-2.5 w-2.5 rounded-full bg-amber-500" title="Sin leer"></span>
                                            </template>
                                            <template x-if="order.duplicate">
                                                <span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-800 ring-1 ring-amber-100">Duplicado</span>
                                            </template>
                                            <template x-if="order.vip">
                                                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-800 ring-1 ring-emerald-100">VIP</span>
                                            </template>
                                        </div>

                                        <div class="mt-1 text-sm text-slate-600" x-text="order.preview"></div>
                                    </div>

                                    <div class="text-right text-xs text-slate-500">
                                        <div x-text="order.elapsed_label"></div>
                                        <div class="mt-1 inline-flex items-center rounded-full px-2.5 py-1 font-semibold" :class="order.channel_key === 'telegram' ? 'bg-blue-50 text-blue-800 ring-1 ring-blue-100' : 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-100'" x-text="order.channel"></div>
                                    </div>
                                </div>

                                <div class="mt-3 flex flex-wrap items-center gap-2">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold" :class="order.status_tone" x-text="order.status_label"></span>
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700 ring-1 ring-slate-200" x-text="order.items_count + ' articulo(s)'"></span>
                                    <span class="inline-flex items-center rounded-full bg-white px-2.5 py-1 text-xs font-semibold text-slate-500 ring-1 ring-slate-200" x-text="'#' + order.id"></span>
                                </div>
                            </button>
                        </template>

                        <template x-if="orders.length === 0">
                            <div class="rounded-[24px] border border-dashed border-slate-300 bg-slate-50 px-6 py-14 text-center">
                                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-3xl bg-white text-3xl shadow-sm ring-1 ring-slate-200">
                                    <svg viewBox="0 0 24 24" fill="none" class="h-8 w-8 text-brand-primary" aria-hidden="true">
                                        <path d="M4 7.5h16v11H4v-11Zm0 0 8 6 8-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </div>
                                <h2 class="mt-5 text-lg font-semibold text-brand-navy">No hay pedidos pendientes</h2>
                                <p class="mt-2 text-sm leading-6 text-slate-600">Cuando lleguen nuevos pedidos, apareceran aqui para gestion rapida.</p>
                            </div>
                        </template>
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
                                            <div class="mt-1 text-sm font-semibold text-brand-navy" x-text="activeOrder.parser_confidence !== null ? Number(activeOrder.parser_confidence).toFixed(2) : 'Sin dato'"></div>
                                        </div>
                                    </div>

                                    <div class="grid gap-4 xl:grid-cols-[1.25fr_0.95fr]">
                                        <div class="rounded-2xl border border-slate-200/80 border-l-4 border-l-brand-primary bg-white p-4">
                                            <div class="text-sm font-semibold text-brand-navy">Mensaje original</div>
                                            <p class="mt-3 text-sm leading-6 text-slate-700" x-text="activeOrder.preview"></p>
                                        </div>

                                        <div class="rounded-2xl border border-slate-200/80 border-l-4 border-l-amber-500 bg-white p-4">
                                            <div class="flex items-center justify-between gap-3">
                                                <h2 class="text-sm font-semibold text-brand-navy">Articulos reconocidos</h2>
                                                <span class="text-xs font-medium uppercase tracking-wide text-slate-500" x-text="activeOrder.items_count + ' articulo(s)'"></span>
                                            </div>

                                            <div class="mt-3 space-y-3">
                                                <template x-for="item in activeOrder.items" :key="item.id">
                                                    <div class="rounded-2xl border border-emerald-100 bg-emerald-50 p-3">
                                                        <div class="flex flex-wrap items-center gap-2">
                                                            <span class="inline-flex items-center rounded-full bg-white px-2.5 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-100">Articulo</span>
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
                                                        Aun no hay articulos reconocidos.
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex shrink-0 flex-col gap-3 xl:w-80">
                                    <div class="rounded-2xl border border-slate-200/80 bg-slate-50 px-4 py-3 text-xs leading-5 text-slate-500">
                                        El panel solo muestra la siguiente accion valida para este estado.
                                    </div>

                                    <div x-show="toast.visible" x-transition class="rounded-2xl border px-4 py-3 text-sm shadow-sm"
                                        :class="toast.type === 'error' ? 'border-red-200 bg-red-50 text-red-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800'"
                                    >
                                        <span x-text="toast.message"></span>
                                    </div>

                                    <template x-if="activeOrder.primary_action">
                                        <form method="POST" :action="activeOrder.primary_action.url" @submit.prevent="submitWorkflowAction(activeOrder.primary_action)">
                                            @csrf
                                            <button
                                                type="submit"
                                                class="w-full py-3"
                                                :class="activeOrder.primary_action.style === 'primary' ? 'brand-btn-primary' : (activeOrder.primary_action.style === 'danger' ? 'brand-btn-danger' : 'brand-btn-secondary')"
                                                :disabled="submittingActionKey === activeOrder.primary_action.key"
                                                x-text="submittingActionKey === activeOrder.primary_action.key ? 'Procesando...' : activeOrder.primary_action.label"
                                            ></button>
                                        </form>
                                    </template>

                                    <template x-if="!activeOrder.primary_action && activeOrder.terminal_message">
                                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                            <div class="text-sm font-semibold text-brand-navy" x-text="activeOrder.terminal_message"></div>
                                            <p class="mt-1 text-xs leading-5 text-slate-500">No hay transiciones disponibles para este pedido.</p>
                                        </div>
                                    </template>

                                    <template x-if="activeOrder.secondary_actions.length > 0">
                                        <div class="rounded-2xl border border-slate-200/80 bg-white p-3">
                                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Acciones secundarias</div>
                                            <div class="mt-3 space-y-2">
                                                <template x-for="action in activeOrder.secondary_actions" :key="action.key">
                                                    <template x-if="action.method === 'GET'">
                                                        <a :href="action.url" class="brand-btn-secondary w-full justify-center py-2.5 text-sm" x-text="action.label"></a>
                                                    </template>
                                                    <template x-if="action.method !== 'GET'">
                                                        <form method="POST" :action="action.url" @submit.prevent="submitWorkflowAction(action)">
                                                            @csrf
                                                            <button
                                                                type="submit"
                                                                class="w-full py-2.5 text-sm"
                                                                :class="action.style === 'danger' ? 'brand-btn-danger' : 'brand-btn-secondary'"
                                                                :disabled="submittingActionKey === action.key"
                                                                x-text="submittingActionKey === action.key ? 'Procesando...' : action.label"
                                                            ></button>
                                                        </form>
                                                    </template>
                                                </template>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <form method="POST" :action="activeOrder.update_url" class="rounded-[28px] border border-slate-200/80 bg-white p-5 shadow-[0_18px_50px_-34px_rgba(15,23,42,0.38)] sm:p-6">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="items_json" :value="serializedItems()">

                            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Editar articulos</div>
                                    <h2 class="mt-1 text-lg font-semibold text-brand-navy">Ajusta cantidades y lineas sin salir de la bandeja</h2>
                                </div>

                                <button type="button" @click="addItem()" data-action="add-item" class="brand-btn-secondary">
                                    Agregar articulo
                                </button>
                            </div>

                            <div class="mt-4 overflow-hidden rounded-[24px] border border-slate-200/80">
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                                        <thead class="bg-slate-50">
                                            <tr>
                                                <th class="px-4 py-3 text-left font-semibold text-slate-600">Cantidad</th>
                                                <th class="px-4 py-3 text-left font-semibold text-slate-600">Unidad</th>
                                                <th class="px-4 py-3 text-left font-semibold text-slate-600">Articulo</th>
                                                <th class="px-4 py-3 text-left font-semibold text-slate-600">Notas</th>
                                                <th class="px-4 py-3 text-right font-semibold text-slate-600">Accion</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 bg-white">
                                            <template x-for="(item, index) in draftItems" :key="item.id ?? 'new-' + index">
                                                <tr class="align-top">
                                                    <td class="px-4 py-3">
                                                        <input x-model="item.quantity" type="number" min="0.01" step="0.01" class="brand-input w-24 rounded-xl px-3 py-2">
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <input x-model="item.unit" type="text" class="brand-input w-28 rounded-xl px-3 py-2" placeholder="unidad">
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <input x-model="item.raw_text" type="text" class="brand-input w-full rounded-xl px-3 py-2" placeholder="Descripcion del articulo">
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <input x-model="item.notes" type="text" class="brand-input w-full rounded-xl px-3 py-2" placeholder="Nota opcional">
                                                    </td>
                                                    <td class="px-4 py-3 text-right">
                                                        <button type="button" @click="removeItem(index)" data-action="remove-item" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 transition hover:border-slate-300 hover:bg-slate-50">
                                                            Quitar
                                                        </button>
                                                    </td>
                                                </tr>
                                            </template>
                                            <template x-if="draftItems.length === 0">
                                                <tr>
                                                    <td colspan="5" class="px-4 py-10 text-center text-sm text-slate-500">
                                                        Este pedido aun no tiene articulos.
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div class="text-sm text-slate-500">
                                    {{ data_get($selectedOrder, 'customer_context.segment', 'Inactive') }} perfil del cliente solo lectura.
                                </div>

                                <button type="submit" data-action="save" class="brand-btn-primary px-6 py-3">
                                    Guardar cambios
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
                                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Contexto del cliente</div>
                                    <h2 class="mt-1 text-xl font-semibold text-brand-navy" x-text="activeOrder.customer_context.name"></h2>
                                </div>

                                <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold bg-slate-100 text-slate-700 ring-1 ring-slate-200" x-text="activeOrder.customer_context.segment"></span>
                            </div>

                            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                    <div class="text-xs uppercase tracking-wide text-slate-500">Telefono</div>
                                    <div class="mt-1 text-sm font-semibold text-brand-navy" x-text="activeOrder.customer_context.phone"></div>
                                </div>
                                <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                    <div class="text-xs uppercase tracking-wide text-slate-500">Total de pedidos</div>
                                    <div class="mt-1 text-sm font-semibold text-brand-navy" x-text="activeOrder.customer_context.total_orders"></div>
                                </div>
                                <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                    <div class="text-xs uppercase tracking-wide text-slate-500">Canal favorito</div>
                                    <div class="mt-1 text-sm font-semibold text-brand-navy" x-text="activeOrder.customer_context.favorite_channel.name"></div>
                                </div>
                                <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                    <div class="text-xs uppercase tracking-wide text-slate-500">Notificaciones abiertas</div>
                                    <div class="mt-1 text-sm font-semibold text-brand-navy" x-text="activeOrder.customer_context.open_notifications"></div>
                                </div>
                            </div>

                            <div class="mt-4 rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                <div class="text-xs uppercase tracking-wide text-slate-500">Productos favoritos</div>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <template x-for="product in activeOrder.customer_context.favorite_products" :key="product">
                                        <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-700 ring-1 ring-slate-200" x-text="product"></span>
                                    </template>
                                    <span class="text-sm text-slate-500" x-show="activeOrder.customer_context.favorite_products.length === 0">Aun no hay datos</span>
                                </div>
                            </div>

                            <div class="mt-4 rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                <div class="text-xs uppercase tracking-wide text-slate-500">Ultimo pedido</div>
                                <div class="mt-1 text-sm font-semibold text-brand-navy" x-text="activeOrder.customer_context.last_order ? activeOrder.customer_context.last_order.label : 'Sin pedidos aun'"></div>
                                <div class="mt-1 text-xs text-slate-500" x-text="activeOrder.customer_context.last_order ? activeOrder.customer_context.last_order.elapsed : ''"></div>
                            </div>
                        </div>

                        <div class="rounded-[28px] border border-slate-200/80 bg-white p-5 shadow-[0_18px_50px_-34px_rgba(15,23,42,0.38)]">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Alertas actuales</div>
                                    <h2 class="mt-1 text-lg font-semibold text-brand-navy">Pendientes abiertos</h2>
                                </div>
                            </div>

                            <div class="mt-4 flex flex-wrap gap-2">
                                <template x-for="alert in activeOrder.customer_context.current_alerts" :key="alert">
                                    <span class="inline-flex items-center rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800 ring-1 ring-amber-100" x-text="alert"></span>
                                </template>
                                <span class="text-sm text-slate-500" x-show="activeOrder.customer_context.current_alerts.length === 0">No hay alertas abiertas</span>
                            </div>

                            <div class="mt-4 rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                <div class="text-xs uppercase tracking-wide text-slate-500">Posible duplicado</div>
                                <div class="mt-1 text-sm font-semibold text-brand-navy" x-text="activeOrder.duplicate ? 'Si' : 'No'"></div>
                            </div>
                        </div>

                        <div class="rounded-[28px] border border-slate-200/80 bg-white p-5 shadow-[0_18px_50px_-34px_rgba(15,23,42,0.38)]">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Actividad reciente</div>
                                    <h2 class="mt-1 text-lg font-semibold text-brand-navy">Linea de tiempo</h2>
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
                                <span class="text-sm text-slate-500" x-show="activeOrder.customer_context.recent_activity.length === 0">Sin actividad reciente</span>
                            </div>
                        </div>
                    </div>
                </template>
            </aside>
        </div>
    </div>
</x-app-layout>
