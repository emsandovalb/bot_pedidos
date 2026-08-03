<x-app-layout>
    @php
        $operatorName = auth()->user()->name ?? 'Carlos';
        $selectedOrderDetails = collect($ordersData)->keyBy('id')->all();
        $headerDateLabel = \Illuminate\Support\Carbon::parse($feedData['server_time'] ?? now())->locale('en')->isoFormat('dddd MMMM D');
        $selectedWorkflowLabel = match ($selectedOrder['status'] ?? null) {
            'pending_review' => 'Confirm',
            'confirmed' => 'Prepare',
            'preparing' => 'Ready',
            'ready_for_dispatch' => 'Dispatch',
            'dispatched' => 'Complete',
            default => null,
        };
    @endphp

    <div
        x-data="operationsBoard({
            orders: @js($ordersData),
            selectedOrder: @js($selectedOrder),
            selectedOrderId: @js($selectedOrderId),
            counts: @js($feedData['counts'] ?? []),
            serverTime: @js($feedData['server_time'] ?? null),
            feedUrl: @js(route('operations.feed')),
            snapshotUrlBase: @js(url('/operations/orders')),
            ordersBaseUrl: @js(url('/orders')),
            pollIntervalMs: @js(config('operations.live_queue_poll_interval_ms', 8000)),
            orderDetails: @js($selectedOrderDetails),
            queueData: @js($queueData ?? ['sections' => [], 'metrics' => []]),
            filters: @js($filters),
            operatorName: @js($operatorName),
        })"
        x-init="init()"
        x-on:operations-select-order="select($event.detail.orderId, $event.detail.order)"
        x-on:beforeunload="destroy()"
        x-on:keydown.escape.window="closeDrawer()"
        class="relative overflow-hidden"
    >
        <div class="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(circle_at_top_left,rgba(20,110,219,0.12),transparent_28%),radial-gradient(circle_at_top_right,rgba(22,163,74,0.10),transparent_26%),linear-gradient(180deg,#f8fbff_0%,#ffffff_42%,#f8fafc_100%)]"></div>
        <div class="pointer-events-none absolute left-[-8rem] top-24 -z-10 h-80 w-80 rounded-full bg-sky-200/30 blur-3xl"></div>
        <div class="pointer-events-none absolute right-[-6rem] top-44 -z-10 h-72 w-72 rounded-full bg-emerald-200/30 blur-3xl"></div>

        <div class="sr-only" aria-hidden="true">
            Benditio Operations Center Next Action First
            {{ $selectedWorkflowLabel }}
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

        <section class="mx-auto max-w-[1680px] px-4 py-4 sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-[32px] border border-slate-200/70 bg-white/85 shadow-[0_24px_60px_-40px_rgba(15,23,42,0.38)] backdrop-blur">
                <div class="flex flex-col gap-6 px-5 py-6 sm:px-8 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-3xl">
                        <div class="inline-flex items-center rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-brand-primary ring-1 ring-inset ring-blue-100">
                            Benditio Operations Center
                        </div>
                        <div class="mt-4 flex flex-wrap items-baseline gap-x-4 gap-y-1">
                            <h1 class="text-3xl font-semibold tracking-tight text-brand-navy sm:text-4xl">
                                Today
                            </h1>
                            <span class="text-base font-medium text-slate-500 sm:text-lg" x-text="headerDateLabel"></span>
                        </div>
                        <div class="mt-2 text-lg font-medium text-slate-600">
                            <span x-text="greetingLabel"></span>
                            <span class="text-brand-navy" x-text="operatorName"></span>
                        </div>
                        <span class="sr-only">{{ $headerDateLabel }}</span>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <div class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-2 text-xs font-semibold shadow-sm">
                            <span class="inline-flex h-2.5 w-2.5 rounded-full" :class="{
                                'bg-emerald-500': liveConnection.state === 'live',
                                'bg-amber-400': liveConnection.state === 'reconnecting',
                                'bg-rose-500': liveConnection.state === 'offline',
                            }"></span>
                            <span x-text="liveConnection.label"></span>
                        </div>
                        <button type="button" @click="toggleLiveSound()" class="brand-btn-secondary">
                            <span x-text="isLiveMuted() ? 'Audio off' : 'Audio on'"></span>
                        </button>
                    </div>
                </div>

                <div class="grid gap-3 border-t border-slate-200/70 px-5 py-5 sm:px-8 lg:grid-cols-5">
                    <div class="rounded-[24px] border border-rose-200 bg-rose-50/70 p-4 shadow-sm">
                        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-rose-700">Urgent orders</div>
                        <div class="mt-2 text-3xl font-semibold text-brand-navy" x-text="todaySummary.urgent"></div>
                    </div>
                    <div class="rounded-[24px] border border-blue-200 bg-blue-50/70 p-4 shadow-sm">
                        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-700">Orders today</div>
                        <div class="mt-2 text-3xl font-semibold text-brand-navy" x-text="todaySummary.today"></div>
                    </div>
                    <div class="rounded-[24px] border border-emerald-200 bg-emerald-50/70 p-4 shadow-sm">
                        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">Deliveries</div>
                        <div class="mt-2 text-3xl font-semibold text-brand-navy" x-text="todaySummary.deliveries"></div>
                    </div>
                    <div class="rounded-[24px] border border-slate-200 bg-slate-50 p-4 shadow-sm">
                        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Pickups</div>
                        <div class="mt-2 text-3xl font-semibold text-brand-navy" x-text="todaySummary.pickups"></div>
                    </div>
                    <div class="rounded-[24px] border border-orange-200 bg-orange-50/70 p-4 shadow-sm">
                        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-orange-700">Overdue</div>
                        <div class="mt-2 text-3xl font-semibold text-brand-navy" x-text="todaySummary.overdue"></div>
                    </div>
                </div>
            </div>
        </section>

        <section class="mx-auto max-w-[1680px] px-4 sm:px-6 lg:px-8">
            <details class="group rounded-[28px] border border-slate-200/80 bg-white/90 shadow-[0_18px_50px_-34px_rgba(15,23,42,0.35)]">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 sm:px-6">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Secondary toolbar</div>
                        <div class="mt-1 text-sm text-slate-600">Hidden by default. Open only when you need to filter.</div>
                    </div>
                    <span class="text-sm font-semibold text-brand-primary group-open:text-slate-500">Filters</span>
                </summary>

                <div class="border-t border-slate-200/70 px-5 py-5 sm:px-6">
                    <div class="grid gap-3 lg:grid-cols-[minmax(0,1.3fr)_repeat(4,minmax(0,1fr))]">
                        <input
                            type="search"
                            x-model="filters.search"
                            @input.debounce.250ms="applyFilter('search', $event.target.value)"
                            placeholder="Search customer, phone, message, branch, or ID"
                            class="brand-input w-full rounded-2xl px-4 py-3 text-sm"
                        >

                        <select class="brand-input rounded-2xl px-4 py-3 text-sm" x-model="filters.channel" @change="applyFilter('channel', $event.target.value)">
                            <option value="all">Any channel</option>
                            <option value="whatsapp">WhatsApp</option>
                            <option value="telegram">Telegram</option>
                            <option value="instagram">Instagram</option>
                        </select>

                        <select class="brand-input rounded-2xl px-4 py-3 text-sm" x-model="filters.time" @change="applyFilter('time', $event.target.value)">
                            <option value="all">Any time</option>
                            <option value="today">Today</option>
                            <option value="tomorrow">Tomorrow</option>
                            <option value="no_commitment">No commitment</option>
                        </select>

                        <select class="brand-input rounded-2xl px-4 py-3 text-sm" x-model="filters.delivery" @change="applyFilter('delivery', $event.target.value)">
                            <option value="all">Any delivery</option>
                            <option value="pickup">Pickup</option>
                            <option value="delivery">Delivery</option>
                            <option value="express">Express</option>
                        </select>

                        <select class="brand-input rounded-2xl px-4 py-3 text-sm" x-model="filters.payment" @change="applyFilter('payment', $event.target.value)">
                            <option value="all">Any payment</option>
                            <option value="sinpe">SINPE</option>
                            <option value="cash">Cash</option>
                            <option value="transfer">Transfer</option>
                        </select>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <button type="button" @click="toggleFilter('vip')" class="brand-btn-secondary" :class="filters.vip ? 'border-emerald-300 bg-emerald-50 text-emerald-800' : ''">VIP</button>
                        <button type="button" @click="toggleFilter('duplicates')" class="brand-btn-secondary" :class="filters.duplicates ? 'border-amber-300 bg-amber-50 text-amber-800' : ''">Duplicates</button>
                        <button type="button" @click="toggleFilter('urgent')" class="brand-btn-secondary" :class="filters.urgent ? 'border-orange-300 bg-orange-50 text-orange-800' : ''">Urgent</button>
                        <button type="button" @click="clearFilters()" class="brand-btn-secondary">Clear</button>
                    </div>
                </div>
            </details>
        </section>

        <section class="mx-auto max-w-[1680px] px-4 py-5 sm:px-6 lg:px-8">
            <div class="rounded-[32px] border border-slate-200/80 bg-white/90 p-5 shadow-[0_18px_50px_-34px_rgba(15,23,42,0.35)]">
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">DO NOW</div>
                        <h2 class="mt-2 text-2xl font-semibold text-brand-navy">Maximum 5 cards. System ordered.</h2>
                    </div>
                    <div class="text-sm text-slate-500" x-text="doNowCards.length ? `${doNowCards.length} active card(s)` : 'No urgent work'"></div>
                </div>

                <template x-if="doNowCards.length === 0 && upcomingGroups.length === 0">
                    <div class="mt-6 rounded-[28px] border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center">
                        <div class="text-2xl font-semibold text-brand-navy">No urgent work.</div>
                        <p class="mt-2 text-sm text-slate-600">Great job! You are on schedule.</p>
                    </div>
                </template>

                <div class="mt-6 grid gap-4 xl:grid-cols-2 2xl:grid-cols-3" x-show="doNowCards.length > 0" x-cloak>
                    <template x-for="order in doNowCards" :key="order.id">
                        <article
                            class="group flex h-full flex-col rounded-[28px] border border-slate-200/80 bg-white p-5 shadow-[0_16px_36px_-28px_rgba(15,23,42,0.32)] transition hover:-translate-y-0.5 hover:shadow-[0_20px_44px_-30px_rgba(15,23,42,0.35)]"
                            :class="flashOrderIds.includes(order.id) ? 'ring-2 ring-emerald-200' : ''"
                            @click="openDrawer(order)"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="truncate text-2xl font-semibold tracking-tight text-brand-navy" x-text="order.customer_name"></h3>
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold" :class="channelPill(order).tone">
                                            <span x-text="channelPill(order).glyph"></span>
                                        </span>
                                    </div>
                                    <p class="mt-2 text-sm leading-6 text-slate-600" x-text="agendaSummary(order)"></p>
                                </div>

                                <div class="text-right">
                                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Commitment</div>
                                    <div class="mt-1 text-xl font-semibold text-brand-navy" x-text="order.commitment_date ? `${order.commitment_date} ${String(order.commitment_time ?? '').slice(0, 5)}` : 'No commitment'"></div>
                                </div>
                            </div>

                            <div class="mt-4 flex flex-wrap items-center gap-2 text-sm text-slate-600">
                                <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 font-semibold text-slate-700" x-text="agendaDeliveryLabel(order)"></span>
                                <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 font-semibold text-slate-700" x-text="agendaPaymentLabel(order)"></span>
                                <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 font-semibold text-slate-700" x-text="agendaTimeWindowLabel(order)"></span>
                            </div>

                            <div class="mt-4 flex flex-wrap gap-2">
                                <template x-for="label in agendaSmartLabels(order)" :key="label">
                                    <span class="rounded-full bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-500 ring-1 ring-slate-200" x-text="label"></span>
                                </template>
                            </div>

                            <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
                                <div class="text-sm text-slate-500">
                                    <span class="font-semibold text-brand-navy" x-text="order.branch_name"></span>
                                    <span class="mx-2">·</span>
                                    <span x-text="order.elapsed_label"></span>
                                </div>

                                <button
                                    type="button"
                                    class="brand-btn-primary px-5 py-3 text-base shadow-none"
                                    @click.stop="submitWorkflowAction(workflowAction(order))"
                                    x-text="workflowActionLabel(order)"
                                ></button>
                            </div>
                        </article>
                    </template>
                </div>
            </div>
        </section>

        <section class="mx-auto max-w-[1680px] px-4 py-2 sm:px-6 lg:px-8">
            <div class="rounded-[32px] border border-slate-200/80 bg-white/90 p-5 shadow-[0_18px_50px_-34px_rgba(15,23,42,0.35)]">
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">NEXT</div>
                        <h2 class="mt-2 text-2xl font-semibold text-brand-navy">Upcoming work</h2>
                    </div>
                    <div class="text-sm text-slate-500" x-text="upcomingGroups.length ? `${upcomingGroups.length} group(s)` : 'No upcoming work'"></div>
                </div>

                <div class="mt-6 grid gap-4">
                    <template x-for="group in upcomingGroups" :key="group.key">
                        <div class="rounded-[28px] border border-slate-200/80 bg-slate-50/80 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <div class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500" x-text="group.label"></div>
                                <div class="text-xs text-slate-500" x-text="`${group.cards.length} order(s)`"></div>
                            </div>

                            <div class="mt-4 grid gap-3 xl:grid-cols-2">
                                <template x-for="order in group.cards" :key="order.id">
                                    <button
                                        type="button"
                                        class="group rounded-[24px] border border-slate-200 bg-white p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                                        @click="openDrawer(order)"
                                    >
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <div class="text-lg font-semibold text-brand-navy" x-text="order.customer_name"></div>
                                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="channelPill(order).tone">
                                                        <span x-text="channelPill(order).glyph"></span>
                                                    </span>
                                                </div>
                                                <div class="mt-1 text-sm text-slate-600" x-text="agendaSummary(order)"></div>
                                            </div>

                                            <div class="text-right text-xs text-slate-500">
                                                <div class="font-semibold text-slate-700" x-text="order.commitment_date ? `${order.commitment_date} ${String(order.commitment_time ?? '').slice(0, 5)}` : 'No commitment'"></div>
                                                <div class="mt-1" x-text="order.elapsed_label"></div>
                                            </div>
                                        </div>

                                        <div class="mt-3 flex flex-wrap gap-2">
                                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="agendaRiskTone(order)" x-text="agendaRiskLabel(order)"></span>
                                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="agendaPriorityTone(order)" x-text="agendaPriorityLabel(order)"></span>
                                        </div>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </section>

        <section class="mx-auto max-w-[1680px] px-4 py-2 sm:px-6 lg:px-8">
            <details class="rounded-[32px] border border-slate-200/80 bg-white/90 p-5 shadow-[0_18px_50px_-34px_rgba(15,23,42,0.35)]">
                <summary class="cursor-pointer list-none">
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">COMPLETED</div>
                            <h2 class="mt-2 text-2xl font-semibold text-brand-navy">Completed today</h2>
                        </div>
                        <div class="text-sm text-slate-500" x-text="`${completedCards.length} order(s)`"></div>
                    </div>
                </summary>

                <div class="mt-6">
                    <template x-if="completedCards.length === 0">
                        <div class="rounded-[24px] border border-dashed border-slate-300 bg-slate-50 px-6 py-8 text-center text-sm text-slate-500">
                            No completed orders yet.
                        </div>
                    </template>

                    <div class="grid gap-3 xl:grid-cols-2" x-show="completedCards.length > 0" x-cloak>
                        <template x-for="order in completedCards" :key="order.id">
                            <button
                                type="button"
                                class="rounded-[24px] border border-slate-200 bg-white p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                                @click="openDrawer(order)"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="text-lg font-semibold text-brand-navy" x-text="order.customer_name"></div>
                                        <div class="mt-1 text-sm text-slate-600" x-text="agendaSummary(order)"></div>
                                    </div>
                                    <div class="text-right text-xs text-slate-500">
                                        <div class="font-semibold text-slate-700" x-text="order.commitment_date ? `${order.commitment_date} ${String(order.commitment_time ?? '').slice(0, 5)}` : 'No commitment'"></div>
                                        <div class="mt-1" x-text="order.elapsed_label"></div>
                                    </div>
                                </div>
                            </button>
                        </template>
                    </div>
                </div>
            </details>
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
                class="absolute right-0 top-0 h-full w-[min(100vw-1rem,580px)] overflow-y-auto border-l border-slate-200 bg-white shadow-[0_24px_60px_-24px_rgba(15,23,42,0.32)]"
            >
                <div class="sticky top-0 z-10 border-b border-slate-200 bg-white/95 px-5 py-4 backdrop-blur">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Detail drawer</div>
                            <h2 class="mt-1 text-xl font-semibold text-brand-navy" x-text="activeOrder ? activeOrder.customer_name : 'No order selected'"></h2>
                            <p class="mt-1 text-sm text-slate-500" x-text="activeOrder ? activeOrder.preview : ''"></p>
                        </div>

                        <button type="button" @click="closeDrawer()" class="brand-btn-secondary">Close</button>
                    </div>
                </div>

                <div class="space-y-5 p-5">
                    <div class="rounded-2xl border border-sky-100 bg-sky-50 px-4 py-3 text-sm text-sky-800" x-show="drawerLoading" x-cloak>
                        Loading order details...
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
                                        <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Customer</div>
                                        <div class="mt-1 text-sm font-semibold text-brand-navy" x-text="activeOrder.customer_name"></div>
                                        <div class="mt-1 text-sm text-slate-600" x-text="activeOrder.customer_phone"></div>
                                    </div>
                                    <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                        <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Branch</div>
                                        <div class="mt-1 text-sm font-semibold text-brand-navy" x-text="activeOrder.branch_name"></div>
                                    </div>
                                    <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                        <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Received</div>
                                        <div class="mt-1 text-sm font-semibold text-brand-navy" x-text="activeOrder.created_at_label"></div>
                                    </div>
                                    <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                        <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Commitment</div>
                                        <div class="mt-1 text-sm font-semibold text-brand-navy" x-text="formatCommitment(activeOrder)"></div>
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-[26px] border border-slate-200/80 bg-white p-5 shadow-sm">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Primary action</div>
                                        <h3 class="mt-1 text-lg font-semibold text-brand-navy" x-text="workflowActionLabel(activeOrder)"></h3>
                                    </div>
                                    <button
                                        type="button"
                                        class="brand-btn-primary px-5 py-3"
                                        :disabled="submittingActionKey === workflowAction(activeOrder).key"
                                        @click="submitWorkflowAction(workflowAction(activeOrder))"
                                        x-text="submittingActionKey === workflowAction(activeOrder).key ? 'Processing...' : workflowActionLabel(activeOrder)"
                                    ></button>
                                </div>
                            </div>

                            <div class="rounded-[26px] border border-slate-200/80 bg-white p-5 shadow-sm">
                                <div class="text-sm font-semibold text-brand-navy">Items</div>
                                <div class="mt-3 space-y-2">
                                    <template x-for="item in activeOrder.items" :key="item.id ?? item.raw_text">
                                        <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4 text-sm text-slate-700">
                                            <div class="flex items-start justify-between gap-3">
                                                <div class="min-w-0">
                                                    <div class="font-semibold text-brand-navy" x-text="item.name ?? item.product_name ?? item.raw_text ?? 'No description'"></div>
                                                    <div class="mt-1 text-xs text-slate-500" x-text="(item.quantity ?? 1) + ' ' + (item.unit ?? '') + (item.notes ? ' - ' + item.notes : '')"></div>
                                                </div>
                                                <span class="rounded-full bg-white px-2 py-1 text-[11px] font-semibold text-slate-500 ring-1 ring-slate-200" x-text="'#' + (item.id ?? 'new')"></span>
                                            </div>
                                        </div>
                                    </template>
                                    <div class="rounded-2xl border border-dashed border-slate-200 px-4 py-8 text-center text-sm text-slate-500" x-show="!drawerLoading && activeOrder.items.length === 0">
                                        This order has no items yet.
                                    </div>
                                </div>
                            </div>

                            <div class="grid gap-4">
                                <div class="rounded-[26px] border border-slate-200/80 bg-white p-5 shadow-sm">
                                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Parser details</div>
                                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                        <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                            <div class="text-xs uppercase tracking-wide text-slate-500">Parser confidence</div>
                                            <div class="mt-1 text-sm font-semibold text-brand-navy" x-text="activeOrder.parser_details?.parser_confidence !== null && activeOrder.parser_details?.parser_confidence !== undefined ? Number(activeOrder.parser_details.parser_confidence).toFixed(2) : 'No data'"></div>
                                        </div>
                                        <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                            <div class="text-xs uppercase tracking-wide text-slate-500">Decision version</div>
                                            <div class="mt-1 text-sm font-semibold text-brand-navy" x-text="activeOrder.parser_details?.decision_version ?? 'No data'"></div>
                                        </div>
                                        <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                            <div class="text-xs uppercase tracking-wide text-slate-500">Planner confidence</div>
                                            <div class="mt-1 text-sm font-semibold text-brand-navy" x-text="activeOrder.parser_details?.planner_confidence ?? 'No data'"></div>
                                        </div>
                                        <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                            <div class="text-xs uppercase tracking-wide text-slate-500">Planner notes</div>
                                            <div class="mt-1 text-sm font-semibold text-brand-navy" x-text="activeOrder.parser_details?.planner_notes ?? 'No data'"></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="rounded-[26px] border border-slate-200/80 bg-white p-5 shadow-sm">
                                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Fulfillment plan</div>
                                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                        <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                            <div class="text-xs uppercase tracking-wide text-slate-500">Requested date</div>
                                            <div class="mt-1 text-sm font-semibold text-brand-navy" x-text="activeOrder.fulfillment_plan?.requested_date ?? 'No data'"></div>
                                        </div>
                                        <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                            <div class="text-xs uppercase tracking-wide text-slate-500">Time window</div>
                                            <div class="mt-1 text-sm font-semibold text-brand-navy" x-text="activeOrder.fulfillment_plan?.requested_time_window ?? 'No data'"></div>
                                        </div>
                                        <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                            <div class="text-xs uppercase tracking-wide text-slate-500">Delivery</div>
                                            <div class="mt-1 text-sm font-semibold text-brand-navy" x-text="activeOrder.fulfillment_plan?.delivery_method ?? 'No data'"></div>
                                        </div>
                                        <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                            <div class="text-xs uppercase tracking-wide text-slate-500">Payment</div>
                                            <div class="mt-1 text-sm font-semibold text-brand-navy" x-text="activeOrder.fulfillment_plan?.payment_method ?? 'No data'"></div>
                                        </div>
                                        <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                            <div class="text-xs uppercase tracking-wide text-slate-500">Pickup branch</div>
                                            <div class="mt-1 text-sm font-semibold text-brand-navy" x-text="activeOrder.fulfillment_plan?.pickup_branch ?? 'No data'"></div>
                                        </div>
                                        <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                            <div class="text-xs uppercase tracking-wide text-slate-500">Delivery address</div>
                                            <div class="mt-1 text-sm font-semibold text-brand-navy" x-text="activeOrder.fulfillment_plan?.delivery_address ?? 'No data'"></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="rounded-[26px] border border-slate-200/80 bg-white p-5 shadow-sm">
                                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Priority and risk</div>
                                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                        <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                            <div class="text-xs uppercase tracking-wide text-slate-500">Priority score</div>
                                            <div class="mt-1 text-sm font-semibold text-brand-navy" x-text="activeOrder.priority_score ?? 'No data'"></div>
                                        </div>
                                        <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                            <div class="text-xs uppercase tracking-wide text-slate-500">Priority reason</div>
                                            <div class="mt-1 text-sm font-semibold text-brand-navy" x-text="activeOrder.priority_reason ?? 'No data'"></div>
                                        </div>
                                        <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                            <div class="text-xs uppercase tracking-wide text-slate-500">Risk level</div>
                                            <div class="mt-1 text-sm font-semibold text-brand-navy" x-text="activeOrder.risk_level ?? 'No data'"></div>
                                        </div>
                                        <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                            <div class="text-xs uppercase tracking-wide text-slate-500">Risk reason</div>
                                            <div class="mt-1 text-sm font-semibold text-brand-navy" x-text="activeOrder.risk_reason ?? 'No data'"></div>
                                        </div>
                                    </div>

                                    <div class="mt-4 rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                        <div class="text-xs uppercase tracking-wide text-slate-500">Duplicate analysis</div>
                                        <div class="mt-1 text-sm font-semibold text-brand-navy" x-text="activeOrder.duplicate_analysis?.duplicate_reason ?? 'No duplicate found'"></div>
                                        <div class="mt-1 text-xs text-slate-500" x-text="activeOrder.duplicate_analysis?.duplicate_checked_at ?? ''"></div>
                                    </div>
                                </div>

                                <div class="rounded-[26px] border border-slate-200/80 bg-white p-5 shadow-sm">
                                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Timeline</div>
                                    <div class="mt-4 space-y-3">
                                        <template x-for="event in activeOrder.timeline" :key="(event.to_status ?? '') + (event.created_at ?? '')">
                                            <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                                <div class="flex items-start justify-between gap-3">
                                                    <div>
                                                        <div class="text-sm font-semibold text-brand-navy" x-text="event.to_status"></div>
                                                        <div class="mt-1 text-xs text-slate-500" x-text="event.reason ?? event.changed_via ?? 'Status change'"></div>
                                                    </div>
                                                    <div class="text-xs text-slate-500" x-text="event.created_at ?? ''"></div>
                                                </div>
                                            </div>
                                        </template>
                                        <span class="text-sm text-slate-500" x-show="activeOrder.timeline.length === 0">No timeline available.</span>
                                    </div>
                                </div>

                                <div class="rounded-[26px] border border-slate-200/80 bg-white p-5 shadow-sm">
                                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Notification history</div>
                                    <div class="mt-4 space-y-3">
                                        <template x-for="item in activeOrder.notification_history" :key="(item.event ?? '') + (item.evaluated_at ?? '')">
                                            <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                                <div class="flex items-start justify-between gap-3">
                                                    <div>
                                                        <div class="text-sm font-semibold text-brand-navy" x-text="item.event ?? 'Notification'"></div>
                                                        <div class="mt-1 text-xs text-slate-500" x-text="(item.channel ?? '') + ' · ' + (item.status ?? '')"></div>
                                                    </div>
                                                    <div class="text-xs text-slate-500" x-text="item.evaluated_at ?? item.sent_at ?? ''"></div>
                                                </div>
                                                <p class="mt-2 text-xs leading-5 text-slate-500" x-text="item.reason ?? item.message_body ?? ''"></p>
                                            </div>
                                        </template>
                                        <span class="text-sm text-slate-500" x-show="activeOrder.notification_history.length === 0">No notification history.</span>
                                    </div>
                                </div>

                                <div class="rounded-[26px] border border-slate-200/80 bg-white p-5 shadow-sm">
                                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Customer context</div>
                                    <h3 class="mt-1 text-lg font-semibold text-brand-navy" x-text="activeOrder.customer_context.name"></h3>

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
                                        <div class="text-xs uppercase tracking-wide text-slate-500">Recent activity</div>
                                        <div class="mt-2 space-y-2">
                                            <template x-for="activity in activeOrder.customer_context.recent_activity" :key="activity.label + activity.elapsed">
                                                <div class="rounded-xl border border-slate-100 bg-white p-3">
                                                    <div class="flex items-start justify-between gap-3">
                                                        <div>
                                                            <div class="text-sm font-semibold text-brand-navy" x-text="activity.label"></div>
                                                            <div class="mt-1 text-xs text-slate-500" x-text="activity.status + ' · ' + activity.channel"></div>
                                                        </div>
                                                        <div class="text-xs text-slate-500" x-text="activity.elapsed"></div>
                                                    </div>
                                                </div>
                                            </template>
                                            <span class="text-sm text-slate-500" x-show="activeOrder.customer_context.recent_activity.length === 0">No recent activity</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="rounded-[26px] border border-slate-200/80 bg-white p-5 shadow-sm">
                                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Notes</div>
                                    <div class="mt-2 rounded-2xl border border-slate-100 bg-slate-50 p-4 text-sm text-slate-600" x-text="activeOrder.notes ?? 'No notes available.'"></div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </aside>
        </div>
    </div>
</x-app-layout>
