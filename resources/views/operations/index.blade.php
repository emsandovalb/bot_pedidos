<x-app-layout>
    @php
        $inboxOrders = $feedData['inbox'] ?? [];
        $liveCounts = $feedData['counts'] ?? [
            'pending_review' => 0,
            'confirmed' => 0,
            'preparing' => 0,
            'ready_for_dispatch' => 0,
            'dispatched' => 0,
        ];
        $selectedOrderDetails = collect($ordersData)->keyBy('id')->all();
    @endphp

    <div
        x-data="operationsBoard({
            orders: @js($inboxOrders),
            selectedOrder: @js($selectedOrder),
            selectedOrderId: @js($selectedOrderId),
            counts: @js($liveCounts),
            serverTime: @js($feedData['server_time'] ?? null),
            feedUrl: @js(route('operations.feed')),
            snapshotUrlBase: @js(url('/operations/orders')),
            ordersBaseUrl: @js(url('/orders')),
            pollIntervalMs: @js(config('operations.live_queue_poll_interval_ms', 8000)),
            orderDetails: @js($selectedOrderDetails),
            filters: @js($filters),
        })"
        x-init="init()"
        x-on:operations-select-order="select($event.detail.orderId, $event.detail.order)"
        x-on:beforeunload="destroy()"
        x-on:keydown.escape.window="closeDrawer()"
        class="space-y-6"
    >
        <div class="sr-only" aria-hidden="true">
            Benditio Operations Center Bandeja inteligente Nuevos Preparando Listos Despachados Promedio Contexto del cliente Slide-over Actividad reciente
            @foreach ($ordersData as $order)
                {{ $order['customer_name'] }} {{ $order['preview'] }}
            @endforeach
        </div>

        <div class="fixed right-4 top-4 z-50 w-[320px] max-w-[calc(100vw-2rem)]" x-cloak>
            <div
                x-show="liveToast.visible"
                x-transition
                class="rounded-2xl border border-emerald-200 bg-white p-4 shadow-[0_16px_32px_-18px_rgba(15,23,42,0.35)]"
            >
                <div class="flex items-start gap-3">
                    <div class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700">N</div>
                    <div class="min-w-0 flex-1">
                        <div class="text-sm font-semibold text-brand-navy" x-text="liveToast.title"></div>
                        <div class="mt-0.5 text-sm text-slate-600" x-text="liveToast.customer"></div>
                        <div class="mt-0.5 text-xs text-slate-500" x-text="liveToast.elapsed"></div>
                    </div>
                </div>
            </div>
        </div>

        <section class="overflow-hidden rounded-[30px] border border-slate-200/70 bg-[linear-gradient(135deg,rgba(20,110,219,0.08),rgba(22,163,74,0.05)_36%,rgba(255,255,255,1)_78%)] shadow-[0_18px_50px_-28px_rgba(15,23,42,0.45)]">
            <div class="flex flex-col gap-6 p-6 sm:p-8 lg:flex-row lg:items-start lg:justify-between">
                <div class="max-w-3xl">
                    <div class="inline-flex items-center rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-brand-primary ring-1 ring-inset ring-blue-100">
                        Benditio Operations Center
                    </div>
                    <h1 class="mt-4 text-3xl font-semibold tracking-tight text-brand-navy sm:text-4xl">
                        Bandeja inteligente
                    </h1>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600 sm:text-base">
                        Production floor view for incoming orders. The board shows what just arrived, what is being prepared, what is ready, and what has already been dispatched.
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

            <div class="border-t border-slate-200/70 bg-white/65 px-6 py-5 sm:px-8">
                <div class="grid gap-3 lg:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)_auto]">
                    <label class="block">
                        <span class="sr-only">Busqueda global</span>
                        <input
                            type="search"
                            x-model="filters.search"
                            @input.debounce.250ms="applyFilter('search', $event.target.value)"
                            placeholder="Buscar cliente, telefono, mensaje, sucursal o ID"
                            class="brand-input w-full rounded-2xl px-4 py-3 text-sm"
                        >
                    </label>

                    <label class="block">
                        <span class="sr-only">Filtro cliente</span>
                        <input
                            type="search"
                            x-model="filters.customer"
                            @input.debounce.250ms="applyFilter('customer', $event.target.value)"
                            placeholder="Filtrar por cliente"
                            class="brand-input w-full rounded-2xl px-4 py-3 text-sm"
                        >
                    </label>

                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <button type="button" @click="toggleFilter('vip')" class="brand-btn-secondary" :class="filters.vip ? 'border-emerald-300 bg-emerald-50 text-emerald-800' : ''">VIP</button>
                        <button type="button" @click="toggleFilter('duplicates')" class="brand-btn-secondary" :class="filters.duplicates ? 'border-amber-300 bg-amber-50 text-amber-800' : ''">Duplicados</button>
                        <select
                            class="brand-input rounded-2xl px-4 py-3 text-sm"
                            x-model="filters.channel"
                            @change="applyFilter('channel', $event.target.value)"
                        >
                            <option value="all">Todo canal</option>
                            <option value="whatsapp">WhatsApp</option>
                            <option value="telegram">Telegram</option>
                        </select>
                        <select
                            class="brand-input rounded-2xl px-4 py-3 text-sm"
                            x-model="filters.priority"
                            @change="applyFilter('priority', $event.target.value)"
                        >
                            <option value="all">Toda prioridad</option>
                            <option value="urgent">Urgente</option>
                            <option value="normal">Normal</option>
                        </select>
                        <button type="button" @click="clearFilters()" class="brand-btn-secondary">Limpiar</button>
                    </div>
                </div>
            </div>
        </section>

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <template x-for="summary in boardTotals" :key="summary.key">
                <div class="rounded-[24px] border px-4 py-4 shadow-sm" :class="summary.tone">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500" x-text="summary.label"></div>
                            <div class="mt-1 text-2xl font-semibold text-brand-navy" x-text="summary.count"></div>
                        </div>
                        <div class="rounded-full bg-white/80 px-3 py-1 text-xs font-semibold text-slate-500 ring-1 ring-inset ring-slate-200">
                            Promedio: <span x-text="summary.average_wait_label"></span>
                        </div>
                    </div>
                </div>
            </template>
        </section>

        <section class="md:hidden space-y-3">
            <template x-for="order in visibleOrders" :key="order.id">
                <div x-data="operationsCard(order)">
                    <button
                        type="button"
                        @click="$dispatch('operations-select-order', { orderId: order.id, order })"
                        class="block w-full rounded-[24px] border border-slate-200/80 bg-white p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                        :class="[
                            activeId === order.id ? 'border-brand-primary ring-2 ring-blue-100' : '',
                            flashOrderIds.includes(order.id) ? 'border-emerald-200 bg-emerald-50/80' : '',
                            cardAccentClass(order),
                        ]"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <div class="text-sm font-semibold text-brand-navy" x-text="order.customer_name"></div>
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="badgeForChannel(order).tone">
                                        <span x-text="badgeForChannel(order).glyph"></span>
                                    </span>
                                </div>
                                <div class="mt-1 text-xs text-slate-500" x-text="order.preview"></div>
                            </div>
                            <div class="text-right text-xs text-slate-500">
                                <div x-text="order.elapsed_label"></div>
                                <div class="mt-1 inline-flex items-center rounded-full px-2.5 py-1 font-semibold" :class="order.channel_key === 'telegram' ? 'bg-sky-50 text-sky-800 ring-1 ring-sky-100' : 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-100'" x-text="order.channel"></div>
                            </div>
                        </div>

                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold" :class="order.status_tone" x-text="order.status_label"></span>
                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700 ring-1 ring-slate-200" x-text="order.items_count + ' articulo(s)'"></span>
                            <span class="inline-flex items-center rounded-full bg-white px-2.5 py-1 text-xs font-semibold text-slate-500 ring-1 ring-slate-200" x-text="'#' + order.id"></span>
                        </div>
                    </button>
                </div>
            </template>

            <template x-if="visibleOrders.length === 0">
                <div class="rounded-[24px] border border-dashed border-slate-300 bg-white px-6 py-14 text-center shadow-sm">
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-3xl bg-blue-50 text-3xl text-brand-primary">
                        <svg viewBox="0 0 24 24" fill="none" class="h-8 w-8" aria-hidden="true">
                            <path d="M4 7.5h16v11H4v-11Zm0 0 8 6 8-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </div>
                    <h2 class="mt-5 text-lg font-semibold text-brand-navy">No hay pedidos visibles</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">Ajusta los filtros para volver a ver la cola activa.</p>
                </div>
            </template>
        </section>

        <section class="hidden md:block">
            <div class="overflow-x-auto rounded-[30px] border border-slate-200/80 bg-white/90 shadow-[0_18px_50px_-34px_rgba(15,23,42,0.35)]">
                <div class="grid gap-4 p-4 md:grid-cols-2 xl:grid-cols-4 min-w-[760px] lg:min-w-[1120px]">
                    <template x-for="column in boardColumns" :key="column.key">
                        <div x-data="operationsColumn(column)" class="min-w-0">
                            <div class="flex h-full flex-col rounded-[26px] border border-slate-200/80 bg-slate-50/80">
                                <div class="flex items-start justify-between gap-3 border-b border-slate-200/70 px-4 py-4" :class="column.tone">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="inline-flex h-2.5 w-2.5 rounded-full" :class="column.dot"></span>
                                            <h2 class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-600" x-text="column.label"></h2>
                                        </div>
                                        <div class="mt-1 text-xl font-semibold text-brand-navy">
                                            <span x-text="column.count"></span>
                                            <span class="text-sm font-medium text-slate-500">pedidos</span>
                                        </div>
                                    </div>
                                    <div class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-500 ring-1 ring-inset ring-slate-200">
                                        Promedio: <span x-text="column.average_wait_label"></span>
                                    </div>
                                </div>

                                <div class="flex-1 space-y-3 p-4">
                                    <template x-for="order in column.orders" :key="order.id">
                                        <div x-data="operationsCard(order)">
                                            <button
                                                type="button"
                                                @click="$dispatch('operations-select-order', { orderId: order.id, order })"
                                                class="block w-full rounded-[22px] border border-slate-200/80 bg-white p-4 text-left shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-md"
                                                :class="[
                                                    activeId === order.id ? 'border-brand-primary ring-2 ring-blue-100' : '',
                                                    flashOrderIds.includes(order.id) ? 'border-emerald-200 bg-emerald-50/80' : '',
                                                    cardAccentClass(order),
                                                ]"
                                            >
                                                <div class="flex items-start justify-between gap-3">
                                                    <div class="min-w-0 flex-1">
                                                        <div class="flex flex-wrap items-center gap-2">
                                                            <div class="text-sm font-semibold text-brand-navy" x-text="order.customer_name"></div>
                                                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="badgeForChannel(order).tone">
                                                                <span x-text="badgeForChannel(order).glyph"></span>
                                                            </span>
                                                            <template x-if="order.vip">
                                                                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-800 ring-1 ring-emerald-100">VIP</span>
                                                            </template>
                                                            <template x-if="order.duplicate">
                                                                <span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-800 ring-1 ring-amber-100">Duplicado</span>
                                                            </template>
                                                        </div>

                                                        <div class="mt-1 text-xs leading-5 text-slate-500" x-text="order.preview"></div>
                                                    </div>

                                                    <div class="text-right text-xs text-slate-500">
                                                        <div class="font-semibold text-slate-700" x-text="order.elapsed_label"></div>
                                                        <div class="mt-1 text-[11px]" x-text="'#' + order.id"></div>
                                                    </div>
                                                </div>

                                                <div class="mt-3 flex flex-wrap items-center gap-2">
                                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold" :class="order.status_tone" x-text="order.status_label"></span>
                                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700 ring-1 ring-slate-200" x-text="order.items_count + ' articulo(s)'"></span>
                                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold" :class="badgeForPriority(order).tone" x-text="badgeForPriority(order).label"></span>
                                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold" :class="badgeForConfidence(order).tone" x-text="'Parser ' + badgeForConfidence(order).label"></span>
                                                </div>
                                            </button>
                                        </div>
                                    </template>

                                    <template x-if="column.orders.length === 0">
                                        <div class="flex min-h-[180px] flex-col items-center justify-center rounded-[22px] border border-dashed border-slate-300 bg-white px-6 py-10 text-center">
                                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-50 text-slate-400 ring-1 ring-slate-200">
                                                <svg viewBox="0 0 24 24" fill="none" class="h-6 w-6" aria-hidden="true">
                                                    <path d="M4 7.5h16v11H4v-11Zm0 0 8 6 8-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                                </svg>
                                            </div>
                                            <h3 class="mt-4 text-sm font-semibold text-brand-navy" x-text="$root.columnEmptyLabel(column.key)"></h3>
                                            <p class="mt-1 text-xs leading-5 text-slate-500">The column stays quiet until the next production wave arrives.</p>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </section>

        <div
            x-show="drawerOpen"
            x-transition.opacity
            class="fixed inset-0 z-40"
            x-cloak
        >
            <div class="absolute inset-0 bg-slate-950/45 backdrop-blur-[2px]" @click="closeDrawer()"></div>

            <aside
                x-transition:enter="transform transition ease-out duration-200"
                x-transition:enter-start="translate-x-full"
                x-transition:enter-end="translate-x-0"
                x-transition:leave="transform transition ease-in duration-150"
                x-transition:leave-start="translate-x-0"
                x-transition:leave-end="translate-x-full"
                class="absolute right-0 top-0 h-full w-[min(100vw-1rem,560px)] overflow-y-auto border-l border-slate-200 bg-white shadow-[0_24px_60px_-24px_rgba(15,23,42,0.32)]"
            >
                <div class="sticky top-0 z-10 border-b border-slate-200 bg-white/95 px-5 py-4 backdrop-blur">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Slide-over</div>
                            <h2 class="mt-1 text-xl font-semibold text-brand-navy" x-text="activeOrder ? activeOrder.customer_name : 'Sin pedido seleccionado'"></h2>
                            <p class="mt-1 text-sm text-slate-500" x-text="activeOrder ? activeOrder.preview : ''"></p>
                        </div>

                        <button type="button" @click="closeDrawer()" class="brand-btn-secondary">Cerrar</button>
                    </div>
                </div>

                <div class="space-y-5 p-5">
                    <div class="rounded-2xl border border-sky-100 bg-sky-50 px-4 py-3 text-sm text-sky-800" x-show="drawerLoading" x-cloak>
                        Cargando detalle del pedido...
                    </div>

                    <div class="rounded-2xl border border-rose-100 bg-rose-50 px-4 py-3 text-sm text-rose-800" x-show="drawerError" x-text="drawerError" x-cloak></div>

                    <template x-if="activeOrder">
                        <div class="space-y-5">
                            <div class="rounded-[26px] border border-slate-200/80 bg-white p-5 shadow-sm">
                                <div class="flex flex-wrap items-center gap-3">
                                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold" :class="activeOrder.status_tone" x-text="activeOrder.status_label"></span>
                                    <span class="inline-flex items-center rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-brand-primary ring-1 ring-blue-100" x-text="activeOrder.channel"></span>
                                    <span class="text-sm text-slate-500" x-text="activeOrder.elapsed_label"></span>
                                    <span class="text-sm font-semibold text-slate-700" x-text="'#' + activeOrder.id"></span>
                                </div>

                                <div class="mt-4 grid gap-3 sm:grid-cols-2">
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
                                        <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Parser</div>
                                        <div class="mt-1 text-sm font-semibold text-brand-navy" x-text="activeOrder.parser_confidence !== null ? Number(activeOrder.parser_confidence).toFixed(2) : 'Sin dato'"></div>
                                    </div>
                                </div>
                            </div>

                            <div class="grid gap-4">
                                <div class="rounded-[26px] border border-slate-200/80 bg-white p-5 shadow-sm">
                                    <div class="text-sm font-semibold text-brand-navy">Items</div>
                                    <div class="mt-3 space-y-2">
                                        <template x-for="item in activeOrder.items" :key="item.id ?? item.raw_text">
                                            <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4 text-sm text-slate-700">
                                                <div class="flex items-start justify-between gap-3">
                                                    <div class="min-w-0">
                                                        <div class="font-semibold text-brand-navy" x-text="item.name ?? item.product_name ?? item.raw_text ?? 'Sin descripcion'"></div>
                                                        <div class="mt-1 text-xs text-slate-500" x-text="(item.quantity ?? 1) + ' ' + (item.unit ?? '') + (item.notes ? ' - ' + item.notes : '')"></div>
                                                    </div>
                                                    <span class="rounded-full bg-white px-2 py-1 text-[11px] font-semibold text-slate-500 ring-1 ring-slate-200" x-text="'#' + (item.id ?? 'nuevo')"></span>
                                                </div>
                                            </div>
                                        </template>
                                        <div class="rounded-2xl border border-dashed border-slate-200 px-4 py-8 text-center text-sm text-slate-500" x-show="!drawerLoading && activeOrder.items.length === 0">
                                            Este pedido aun no tiene articulos.
                                        </div>
                                    </div>
                                </div>

                                <div class="rounded-[26px] border border-slate-200/80 bg-white p-5 shadow-sm">
                                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Acciones</div>
                                    <div class="mt-3 space-y-3">
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
                                            <div class="space-y-2">
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
                                        </template>
                                    </div>
                                </div>

                                <div class="rounded-[26px] border border-slate-200/80 bg-white p-5 shadow-sm">
                                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Contexto del cliente</div>
                                    <h3 class="mt-1 text-lg font-semibold text-brand-navy" x-text="activeOrder.customer_context.name"></h3>

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

                                <div class="rounded-[26px] border border-slate-200/80 bg-white p-5 shadow-sm">
                                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Alertas y actividad</div>
                                    <div class="mt-4 flex flex-wrap gap-2">
                                        <template x-for="alert in activeOrder.customer_context.current_alerts" :key="alert">
                                            <span class="inline-flex items-center rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800 ring-1 ring-amber-100" x-text="alert"></span>
                                        </template>
                                        <span class="text-sm text-slate-500" x-show="activeOrder.customer_context.current_alerts.length === 0">No hay alertas abiertas</span>
                                    </div>

                                    <div class="mt-5 space-y-3">
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
                        </div>
                    </template>
                </div>
            </aside>
        </div>
    </div>
</x-app-layout>
