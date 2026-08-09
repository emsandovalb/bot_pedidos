<x-app-layout>
    <div
        x-data="operationsFocus(@js($focusData))"
        x-init="init()"
        x-on:beforeunload="destroy()"
        x-on:keydown.escape.window="workspaceOpen && closeWorkspace()"
        class="relative"
        data-focus-page
    >
        <div class="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(circle_at_top,rgba(20,110,219,0.12),transparent_28%),radial-gradient(circle_at_bottom_right,rgba(22,163,74,0.08),transparent_24%),linear-gradient(180deg,#f8fbff_0%,#ffffff_44%,#f8fafc_100%)]"></div>

        <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
            <section class="rounded-[2rem] border border-slate-200/80 bg-white/90 p-6 shadow-[0_18px_50px_-34px_rgba(15,23,42,0.32)] backdrop-blur">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                    <div class="max-w-3xl">
                        <div class="inline-flex items-center rounded-full bg-brand-primary/10 px-3 py-1 text-xs font-semibold tracking-[0.18em] text-brand-primary ring-1 ring-inset ring-brand-primary/10">
                            <span x-text="visibleName">Mi Jornada</span>
                        </div>
                        <div class="mt-4 flex flex-wrap items-baseline gap-x-3 gap-y-1">
                            <h1 class="text-3xl font-semibold tracking-tight text-brand-navy sm:text-4xl">
                                <span x-text="summary.greeting"></span>, {{ auth()->user()->name }}
                            </h1>
                            <span class="text-lg font-medium text-slate-500" x-text="summary.date"></span>
                        </div>
                        <p class="mt-3 text-lg leading-7 text-slate-600" x-text="mainQuestion">¿Qué debo hacer ahora?</p>
                        <p class="mt-2 text-sm text-slate-500">
                            Benditio organiza el orden. Vos preparás el siguiente pedido.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <div class="rounded-full border border-slate-200 bg-white px-4 py-2 text-sm text-slate-600 shadow-sm">
                            <span x-text="liveConnection.label"></span>
                        </div>
                        <a href="{{ route('operations.index') }}" class="brand-btn-secondary">
                            Vista completa
                        </a>
                    </div>
                </div>

                <div class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <article class="rounded-[1.5rem] border border-blue-200 bg-blue-50/70 p-4 shadow-sm">
                        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-700">Pedidos para hoy</div>
                        <div class="mt-2 text-3xl font-semibold text-brand-navy" x-text="summary.orders_today"></div>
                    </article>
                    <article class="rounded-[1.5rem] border border-rose-200 bg-rose-50/70 p-4 shadow-sm">
                        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-rose-700">Urgentes</div>
                        <div class="mt-2 text-3xl font-semibold text-brand-navy" x-text="summary.urgent_orders"></div>
                    </article>
                    <article class="rounded-[1.5rem] border border-emerald-200 bg-emerald-50/70 p-4 shadow-sm">
                        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">Entregas</div>
                        <div class="mt-2 text-3xl font-semibold text-brand-navy" x-text="summary.deliveries"></div>
                    </article>
                    <article class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4 shadow-sm">
                        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Retiros</div>
                        <div class="mt-2 text-3xl font-semibold text-brand-navy" x-text="summary.pickups"></div>
                    </article>
                </div>
            </section>

            <template x-if="!workspaceOpen && recommendedOrder">
                <section class="rounded-[2rem] border border-slate-200/80 bg-white/90 p-6 shadow-[0_18px_50px_-34px_rgba(15,23,42,0.32)] backdrop-blur" data-focus-recommended>
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Tu siguiente pedido</div>
                            <h2 class="mt-2 text-2xl font-semibold text-brand-navy">Atendé uno, terminá uno.</h2>
                        </div>
                        <div class="text-sm text-slate-500" x-text="recommendedOrder.order_number"></div>
                    </div>

                    <article class="mt-6 rounded-[2rem] border border-slate-200/80 bg-[linear-gradient(180deg,#ffffff_0%,#f8fafc_100%)] p-6 shadow-[0_18px_50px_-34px_rgba(15,23,42,0.22)]">
                        <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-3xl font-semibold tracking-tight text-brand-navy" x-text="recommendedOrder.customer_name"></h3>
                                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600" x-text="recommendedOrder.order_number"></span>
                                    <span class="rounded-full bg-brand-primary/10 px-3 py-1 text-xs font-semibold text-brand-primary" x-text="recommendedOrder.focus_state"></span>
                                </div>

                                <div class="mt-4 space-y-2">
                                    <template x-for="line in recommendedOrder.item_lines" :key="line.id ?? line.text">
                                        <div class="rounded-2xl border border-slate-100 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm" x-text="line.text"></div>
                                    </template>
                                    <div class="text-sm font-medium text-slate-500" x-show="recommendedOrder.more_items_label" x-text="recommendedOrder.more_items_label"></div>
                                </div>

                                <div class="mt-5 flex flex-wrap gap-2">
                                    <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700" x-text="recommendedOrder.commitment_label"></span>
                                    <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700" x-text="recommendedOrder.delivery_label"></span>
                                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700" x-text="recommendedOrder.payment_label"></span>
                                    <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700" x-text="recommendedOrder.channel_label"></span>
                                    <span class="rounded-full bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-700" x-text="recommendedOrder.priority_label"></span>
                                </div>
                            </div>

                            <div class="w-full max-w-[22rem] rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
                                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Qué sigue</div>
                                <div class="mt-3 text-2xl font-semibold text-brand-navy" x-text="recommendedActionLabel"></div>
                                <p class="mt-2 text-sm leading-6 text-slate-600" x-text="recommendedOrder.summary"></p>
                                <button
                                    type="button"
                                    class="brand-btn-primary mt-5 w-full justify-center px-5 py-4 text-base"
                                    @click="openRecommendedOrder()"
                                    x-text="recommendedActionLabel"
                                ></button>
                                <p class="mt-3 text-xs leading-5 text-slate-500">
                                    Abrí el pedido para seguir con una sola pantalla y sin volver a buscarlo.
                                </p>
                            </div>
                        </div>
                    </article>
                </section>
            </template>

            <template x-if="!workspaceOpen && !recommendedOrder">
                <section class="rounded-[2rem] border border-slate-200/80 bg-white/90 p-6 shadow-[0_18px_50px_-34px_rgba(15,23,42,0.32)] backdrop-blur" data-focus-empty>
                    <div class="rounded-[1.75rem] border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center">
                        <div class="text-2xl font-semibold text-brand-navy">Todo al día</div>
                        <p class="mt-2 text-sm text-slate-600">Ya no hay pedidos pendientes por trabajar.</p>

                        <div class="mt-6 grid gap-3 text-left sm:grid-cols-2">
                            <div class="rounded-[1.25rem] border border-slate-200 bg-white p-4 shadow-sm">
                                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Completados hoy</div>
                                <div class="mt-2 text-2xl font-semibold text-brand-navy" x-text="completedCount"></div>
                            </div>

                            <div class="rounded-[1.25rem] border border-slate-200 bg-white p-4 shadow-sm">
                                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Próximo programado</div>
                                <template x-if="nextScheduledOrder">
                                    <div class="mt-2 space-y-1">
                                        <div class="text-lg font-semibold text-brand-navy" x-text="nextScheduledOrder.customer_name"></div>
                                        <div class="text-sm text-slate-600" x-text="nextScheduledOrder.commitment_label"></div>
                                    </div>
                                </template>
                                <template x-if="!nextScheduledOrder">
                                    <div class="mt-2 text-sm text-slate-600">No hay pedidos programados visibles.</div>
                                </template>
                            </div>
                        </div>

                        <button
                            type="button"
                            class="brand-btn-primary mt-6 inline-flex justify-center px-5 py-3"
                            @click="refreshFocusQueue()"
                        >
                            Actualizar pedidos
                        </button>
                    </div>
                </section>
            </template>
        </div>

        <div class="fixed right-4 top-4 z-[70] w-[22rem] max-w-[calc(100vw-2rem)]" aria-live="polite" aria-atomic="true" x-cloak>
            <div
                x-show="toast.visible"
                x-transition
                class="rounded-2xl border p-4 shadow-[0_16px_32px_-18px_rgba(15,23,42,0.35)]"
                :class="toast.type === 'error' ? 'border-rose-200 bg-rose-50 text-rose-900' : 'border-emerald-200 bg-white text-slate-900'"
            >
                <div class="text-sm font-semibold" x-text="toast.message"></div>
            </div>
        </div>

        <div
            x-show="workspaceOpen"
            x-cloak
            class="fixed inset-0 z-[60] flex items-stretch justify-center bg-slate-950/60 backdrop-blur-sm"
            role="dialog"
            aria-modal="true"
            data-focus-workspace
        >
            <div class="absolute inset-0" @click="closeWorkspace()"></div>

            <div class="relative z-[61] flex h-full w-full max-w-7xl flex-col bg-white shadow-[0_30px_120px_-40px_rgba(15,23,42,0.55)]">
                <header class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4 sm:px-6">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="truncate text-2xl font-semibold text-brand-navy" x-text="currentWorkspaceOrder?.customer_name ?? 'Pedido'"></h2>
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600" x-text="currentWorkspaceOrder?.order_number ?? ''"></span>
                            <span class="rounded-full bg-brand-primary/10 px-3 py-1 text-xs font-semibold text-brand-primary" data-focus-stage x-text="workspaceStageLabel"></span>
                        </div>
                        <p class="mt-2 text-sm text-slate-500" x-text="currentWorkspaceOrder?.summary ?? ''"></p>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        <button type="button" class="brand-btn-secondary" @click="closeWorkspace()">
                            Cerrar
                        </button>
                    </div>
                </header>

                <main class="flex-1 overflow-y-auto px-5 py-6 sm:px-6">
                    <template x-if="workspaceLoading">
                        <div class="rounded-[2rem] border border-slate-200 bg-slate-50 px-6 py-10 text-center text-sm text-slate-500">
                            Cargando pedido...
                        </div>
                    </template>

                    <template x-if="workspaceError">
                        <div class="rounded-[2rem] border border-rose-200 bg-rose-50 px-6 py-6 text-sm text-rose-800" x-text="workspaceError"></div>
                    </template>

                    <template x-if="currentWorkspaceOrder && !workspaceLoading">
                        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.45fr)_minmax(0,0.95fr)]">
                            <section class="space-y-6">
                                <template x-if="workspaceNotice">
                                    <div class="rounded-[1.75rem] border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-medium text-emerald-900" x-text="workspaceNotice"></div>
                                </template>

                                <div class="rounded-[2rem] border border-slate-200 bg-[linear-gradient(180deg,#ffffff_0%,#f8fafc_100%)] p-6 shadow-sm">
                                    <div class="flex items-start justify-between gap-4">
                                        <div>
                                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500" x-text="workspaceStageLabel"></div>
                                            <h3 class="mt-2 text-2xl font-semibold text-brand-navy" x-text="currentStatus === 'pending_review' ? 'Revisá el pedido' : (currentStatus === 'confirmed' ? 'Pedido confirmado' : (currentStatus === 'preparing' ? 'Preparando pedido' : (currentStatus === 'ready_for_dispatch' ? 'Pedido listo para entregar' : 'Pedido en curso')))"></h3>
                                        </div>

                                        <div class="text-right">
                                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Entrega</div>
                                            <div class="mt-1 text-lg font-semibold text-brand-navy" x-text="orderDeliveryLabel(currentWorkspaceOrder)"></div>
                                        </div>
                                    </div>

                                    <div class="mt-5 grid gap-3 sm:grid-cols-2">
                                        <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                                            <div class="text-xs uppercase tracking-wide text-slate-500">Cliente</div>
                                            <div class="mt-1 text-sm font-semibold text-brand-navy" x-text="currentWorkspaceOrder?.customer_name"></div>
                                        </div>
                                        <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                                            <div class="text-xs uppercase tracking-wide text-slate-500">Teléfono</div>
                                            <div class="mt-1 text-sm font-semibold text-brand-navy" x-text="currentWorkspaceOrder?.customer_phone ?? 'Sin teléfono'"></div>
                                        </div>
                                        <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                                            <div class="text-xs uppercase tracking-wide text-slate-500">Fecha</div>
                                            <div class="mt-1 text-sm font-semibold text-brand-navy" x-text="formatOrderDate(currentWorkspaceOrder)"></div>
                                        </div>
                                        <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                                            <div class="text-xs uppercase tracking-wide text-slate-500">Hora</div>
                                            <div class="mt-1 text-sm font-semibold text-brand-navy" x-text="formatOrderTime(currentWorkspaceOrder)"></div>
                                        </div>
                                        <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                                            <div class="text-xs uppercase tracking-wide text-slate-500">Pago</div>
                                            <div class="mt-1 text-sm font-semibold text-brand-navy" x-text="orderPaymentLabel(currentWorkspaceOrder)"></div>
                                        </div>
                                        <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                                            <div class="text-xs uppercase tracking-wide text-slate-500">Prioridad</div>
                                            <div class="mt-1 text-sm font-semibold text-brand-navy" x-text="orderPriorityLabel(currentWorkspaceOrder)"></div>
                                        </div>
                                    </div>

                                    <div class="mt-4 rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                        <div class="text-xs uppercase tracking-wide text-slate-500">Dirección o sucursal</div>
                                        <div class="mt-1 text-sm font-semibold text-brand-navy" x-text="currentWorkspaceOrder?.fulfillment_plan?.delivery_address ?? currentWorkspaceOrder?.fulfillment_plan?.pickup_branch ?? 'Sin dirección confirmada'"></div>
                                    </div>
                                </div>

                                <template x-if="currentStatus === 'pending_review' || currentStatus === 'confirmed'">
                                    <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                                        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Pedido completo</div>
                                        <div class="mt-4 space-y-3">
                                            <template x-for="item in orderItemLines(currentWorkspaceOrder)" :key="item.id ?? item.text">
                                                <div class="rounded-2xl border border-slate-100 bg-slate-50 px-4 py-4 shadow-sm">
                                                    <div class="flex items-start justify-between gap-3">
                                                        <div class="min-w-0">
                                                            <div class="font-semibold text-brand-navy" x-text="item.name"></div>
                                                            <div class="mt-1 text-sm text-slate-600" x-text="item.text"></div>
                                                        </div>
                                                        <div class="rounded-full bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-600">
                                                            <span x-text="item.quantity_label"></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </template>
                                            <div class="rounded-2xl border border-dashed border-slate-200 px-4 py-8 text-center text-sm text-slate-500" x-show="orderItemLines(currentWorkspaceOrder).length === 0">
                                                No hay artículos visibles.
                                            </div>
                                        </div>
                                    </div>
                                </template>

                                <template x-if="currentStatus === 'preparing'">
                                    <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                                        <div class="flex items-start justify-between gap-4">
                                            <div>
                                                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Preparación</div>
                                                <h3 class="mt-2 text-2xl font-semibold text-brand-navy">Revisá cada artículo</h3>
                                            </div>
                                            <div class="text-right">
                                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Progreso</div>
                                            <div class="mt-1 text-lg font-semibold text-brand-navy" data-focus-progress x-text="preparationProgressLabel"></div>
                                            <div class="mt-1 text-sm font-semibold text-brand-primary" x-text="`${preparationProgressPercent}%`"></div>
                                        </div>
                                        </div>

                                        <div class="mt-4 rounded-full bg-slate-100">
                                            <div class="h-2 rounded-full bg-brand-primary transition-all" :style="`width: ${preparationProgressPercent}%`"></div>
                                        </div>

                                        <div class="mt-5 space-y-3">
                                            <template x-for="item in currentItems" :key="item.id ?? item.text">
                                                <label
                                                    class="flex cursor-pointer items-start gap-3 rounded-2xl border border-slate-100 bg-slate-50 px-4 py-4 shadow-sm transition hover:bg-white"
                                                    data-focus-item
                                                >
                                                    <input
                                                        type="checkbox"
                                                        class="mt-1 h-5 w-5 rounded border-slate-300 text-brand-primary focus:ring-brand-primary"
                                                        data-focus-item-checkbox
                                                        :checked="Boolean((preparedItemStateByOrderId[currentWorkspaceOrder?.id] ?? {})[item.id])"
                                                        @change="togglePreparedItem(item.id)"
                                                    >
                                                    <div class="min-w-0 flex-1">
                                                        <div class="font-semibold text-brand-navy" x-text="item.name"></div>
                                                        <div class="mt-1 text-sm text-slate-600" x-text="item.text"></div>
                                                    </div>
                                                </label>
                                            </template>
                                        </div>

                                        <template x-if="currentItems.length === 0">
                                            <div class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-900">
                                                Este pedido no tiene artículos visibles. No podés marcarlo como listo sin revisar el detalle.
                                                <div class="mt-4">
                                                    <a
                                                        class="brand-btn-secondary inline-flex justify-center px-4 py-3"
                                                        :href="currentWorkspaceOrder?.show_url ?? '#'"
                                                    >
                                                        Revisar detalle
                                                    </a>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </template>

                                <template x-if="currentStatus === 'ready_for_dispatch'">
                                    <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                                        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Listo para salir</div>
                                        <h3 class="mt-2 text-2xl font-semibold text-brand-navy">Pedido listo para entregar</h3>
                                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                            <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                                <div class="text-xs uppercase tracking-wide text-slate-500">Cliente</div>
                                                <div class="mt-1 text-sm font-semibold text-brand-navy" x-text="currentWorkspaceOrder?.customer_name"></div>
                                            </div>
                                            <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                                <div class="text-xs uppercase tracking-wide text-slate-500">Entrega o retiro</div>
                                                <div class="mt-1 text-sm font-semibold text-brand-navy" x-text="orderDeliveryLabel(currentWorkspaceOrder)"></div>
                                            </div>
                                            <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                                <div class="text-xs uppercase tracking-wide text-slate-500">Compromiso</div>
                                                <div class="mt-1 text-sm font-semibold text-brand-navy" x-text="currentWorkspaceOrder?.commitment_label ?? orderCommitmentLabel(currentWorkspaceOrder)"></div>
                                            </div>
                                            <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                                <div class="text-xs uppercase tracking-wide text-slate-500">Teléfono</div>
                                                <div class="mt-1 text-sm font-semibold text-brand-navy" x-text="currentWorkspaceOrder?.customer_phone ?? 'Sin teléfono'"></div>
                                            </div>
                                        </div>
                                        <div class="mt-4 rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                            <div class="text-xs uppercase tracking-wide text-slate-500">Dirección o sucursal</div>
                                            <div class="mt-1 text-sm font-semibold text-brand-navy" x-text="currentWorkspaceOrder?.fulfillment_plan?.delivery_address ?? currentWorkspaceOrder?.fulfillment_plan?.pickup_branch ?? 'Sin dirección confirmada'"></div>
                                        </div>
                                    </div>
                                </template>

                                <template x-if="currentStatus === 'dispatched' || completionVisible">
                                    <div class="rounded-[2rem] border border-emerald-200 bg-emerald-50 p-6 shadow-sm" data-focus-success>
                                        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">Pedido completado</div>
                                        <h3 class="mt-2 text-2xl font-semibold text-emerald-950">Pedido completado</h3>
                                        <p class="mt-3 text-sm leading-6 text-emerald-900">
                                            El pedido de <span class="font-semibold" x-text="currentWorkspaceOrder?.customer_name"></span> quedó despachado.
                                        </p>
                                    </div>
                                </template>

                                <details class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm" x-show="currentStatus === 'pending_review'">
                                    <summary class="cursor-pointer list-none text-sm font-semibold text-brand-primary">
                                        Más opciones
                                    </summary>

                                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                        <button
                                            type="button"
                                            class="brand-btn-secondary justify-center px-4 py-3 text-rose-700 ring-1 ring-inset ring-rose-200"
                                            @click="currentWorkspaceOrder?.secondary_actions?.find((action) => action.key === 'reject') ? performWorkflowAction(currentWorkspaceOrder.secondary_actions.find((action) => action.key === 'reject')) : null"
                                        >
                                            Rechazar
                                        </button>
                                        <button
                                            type="button"
                                            class="brand-btn-secondary justify-center px-4 py-3 text-rose-700 ring-1 ring-inset ring-rose-200"
                                            @click="currentWorkspaceOrder?.secondary_actions?.find((action) => action.key === 'cancel') ? performWorkflowAction(currentWorkspaceOrder.secondary_actions.find((action) => action.key === 'cancel')) : null"
                                        >
                                            Cancelar
                                        </button>
                                    </div>
                                </details>
                            </section>

                            <aside class="space-y-6">
                                <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Acción principal</div>

                                    <template x-if="currentStatus !== 'dispatched' && !completionVisible">
                                        <button
                                            type="button"
                                            class="brand-btn-primary mt-4 w-full justify-center px-5 py-4 text-base"
                                            data-focus-primary-action
                                            :disabled="submittingActionKey !== null || (currentStatus === 'preparing' && !preparationCanProceed) || (currentStatus === 'preparing' && currentItems.length === 0)"
                                            @click="performPrimaryAction()"
                                            x-text="submittingActionKey !== null ? 'Procesando...' : currentPrimaryActionLabel"
                                        ></button>
                                    </template>

                                    <template x-if="currentStatus === 'dispatched' || completionVisible">
                                        <button
                                            type="button"
                                            class="brand-btn-primary mt-4 w-full justify-center px-5 py-4 text-base"
                                            data-focus-continue-next
                                            @click="continueToNextOrder()"
                                            x-text="'Continuar con el siguiente pedido'"
                                        ></button>
                                    </template>

                                    <template x-if="currentStatus === 'preparing' && currentItems.length === 0">
                                        <p class="mt-3 text-sm leading-6 text-amber-700">
                                            No podés marcarlo como listo hasta revisar el detalle.
                                        </p>
                                    </template>

                                    <template x-if="workspaceNotice">
                                        <p class="mt-3 text-sm leading-6 text-slate-600" x-text="workspaceNotice"></p>
                                    </template>
                                </div>

                                <details class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                                    <summary class="cursor-pointer list-none text-sm font-semibold text-brand-primary">
                                        Más detalles
                                    </summary>

                                    <div class="mt-4 space-y-4">
                                        <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                            <div class="text-xs uppercase tracking-wide text-slate-500">Mensaje original</div>
                                            <p class="mt-2 whitespace-pre-wrap text-sm leading-7 text-slate-700" x-text="currentWorkspaceOrder?.original_message ?? currentWorkspaceOrder?.preview ?? 'Sin mensaje original'"></p>
                                        </div>

                                        <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                            <div class="text-xs uppercase tracking-wide text-slate-500">Contexto del cliente</div>
                                            <div class="mt-1 text-sm font-semibold text-brand-navy" x-text="currentWorkspaceOrder?.customer_context?.name ?? 'Sin contexto'"></div>
                                            <div class="mt-1 text-sm text-slate-600">
                                                <span x-text="currentWorkspaceOrder?.customer_context?.total_orders ?? 0"></span>
                                                pedidos totales
                                            </div>
                                        </div>

                                        <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                            <div class="text-xs uppercase tracking-wide text-slate-500">Metadatos técnicos</div>
                                            <div class="mt-1 text-sm font-semibold text-brand-navy" x-text="currentWorkspaceOrder?.parser_details?.decision_version ?? 'Sin dato'"></div>
                                        </div>
                                    </div>
                                </details>
                            </aside>
                        </div>
                    </template>
                </main>
            </div>
        </div>
    </div>
</x-app-layout>
