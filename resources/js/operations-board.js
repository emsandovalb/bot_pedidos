const BOARD_COLUMNS = [
    {
        key: 'new',
        label: 'Nuevos',
        statuses: ['pending_review', 'confirmed'],
        tone: 'border-blue-200 bg-blue-50/70 text-blue-800',
        accent: 'border-l-blue-500',
        dot: 'bg-blue-500',
        emptyLabel: 'No hay pedidos nuevos.',
    },
    {
        key: 'preparing',
        label: 'Preparando',
        statuses: ['preparing'],
        tone: 'border-amber-200 bg-amber-50/70 text-amber-800',
        accent: 'border-l-amber-500',
        dot: 'bg-amber-500',
        emptyLabel: 'No hay pedidos preparando.',
    },
    {
        key: 'ready',
        label: 'Listos',
        statuses: ['ready_for_dispatch'],
        tone: 'border-emerald-200 bg-emerald-50/70 text-emerald-800',
        accent: 'border-l-emerald-500',
        dot: 'bg-emerald-500',
        emptyLabel: 'No hay pedidos listos.',
    },
    {
        key: 'dispatched',
        label: 'Despachados',
        statuses: ['dispatched'],
        tone: 'border-slate-200 bg-slate-50 text-slate-700',
        accent: 'border-l-slate-400',
        dot: 'bg-slate-400',
        emptyLabel: 'No hay pedidos despachados.',
    },
];

const STATUS_TO_COLUMN = new Map(
    BOARD_COLUMNS.flatMap((column) => column.statuses.map((status) => [status, column.key])),
);

const DEFAULT_FILTERS = {
    search: '',
    customer: '',
    channel: 'all',
    priority: 'all',
    vip: false,
    duplicates: false,
    status: 'all',
};

const CHANNEL_LABELS = {
    whatsapp: 'WhatsApp',
    telegram: 'Telegram',
};

function toNumber(value, fallback = 0) {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : fallback;
}

function normalizeText(value) {
    return String(value ?? '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');
}

function parseTimestamp(value) {
    if (! value) {
        return null;
    }

    const parsed = Date.parse(value);
    return Number.isFinite(parsed) ? parsed : null;
}

function formatWaitLabel(minutes) {
    const rounded = Math.max(1, Math.round(minutes));

    if (rounded < 60) {
        return `${rounded} min`;
    }

    const hours = Math.round(rounded / 60);
    return `${hours} h`;
}

function isUrgentOrder(order, referenceTimeMs) {
    const createdAtMs = parseTimestamp(order.created_at_iso);
    if (createdAtMs === null) {
        return false;
    }

    const ageMinutes = (referenceTimeMs - createdAtMs) / 60000;

    return (
        (order.status === 'pending_review' && ageMinutes >= 20)
        || order.parser_confidence === null
        || Number(order.parser_confidence) < 0.5
    );
}

function columnForStatus(status) {
    return STATUS_TO_COLUMN.get(status) ?? 'new';
}

function sortOldestFirst(left, right) {
    const leftTime = parseTimestamp(left.created_at_iso);
    const rightTime = parseTimestamp(right.created_at_iso);

    if (leftTime !== null && rightTime !== null && leftTime !== rightTime) {
        return leftTime - rightTime;
    }

    if (left.id !== right.id) {
        return toNumber(left.id) - toNumber(right.id);
    }

    return 0;
}

function buildChannelPill(order) {
    const key = String(order.channel_key ?? '').toLowerCase();

    return {
        label: order.channel ?? CHANNEL_LABELS[key] ?? 'Sin canal',
        tone: key === 'telegram'
            ? 'bg-sky-50 text-sky-800 ring-1 ring-sky-100'
            : 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-100',
        glyph: key === 'telegram' ? 'TG' : 'WA',
    };
}

function buildPriorityPill(order, referenceTimeMs) {
    return isUrgentOrder(order, referenceTimeMs)
        ? {
            label: 'Urgente',
            tone: 'bg-amber-50 text-amber-800 ring-1 ring-amber-100',
        }
        : {
            label: 'Normal',
            tone: 'bg-slate-100 text-slate-700 ring-1 ring-slate-200',
        };
}

function buildConfidencePill(order) {
    if (order.parser_confidence === null || order.parser_confidence === undefined) {
        return {
            label: 'Sin parser',
            tone: 'bg-slate-100 text-slate-600 ring-1 ring-slate-200',
        };
    }

    const value = Number(order.parser_confidence);
    const tone = value >= 0.8
        ? 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-100'
        : value >= 0.5
            ? 'bg-amber-50 text-amber-800 ring-1 ring-amber-100'
            : 'bg-rose-50 text-rose-800 ring-1 ring-rose-100';

    return {
        label: `${value.toFixed(2)}`,
        tone,
    };
}

export function createOperationsColumn(column) {
    return {
        column,
        get orders() {
            return Array.isArray(column.orders) ? column.orders : [];
        },
        get count() {
            return toNumber(column.count);
        },
    };
}

export function createOperationsCard(order) {
    return {
        order,
        get columnKey() {
            return columnForStatus(this.order.status);
        },
    };
}

export function createOperationsBoard(config = {}) {
    return {
        orders: Array.isArray(config.orders) ? config.orders.map((order) => ({ ...order })) : [],
        selectedOrder: config.selectedOrder ?? null,
        activeId: config.selectedOrderId ?? null,
        counts: config.counts ?? {
            pending_review: 0,
            confirmed: 0,
            preparing: 0,
            ready_for_dispatch: 0,
            dispatched: 0,
        },
        serverTime: config.serverTime ?? null,
        feedUrl: config.feedUrl ?? '/operations/feed',
        ordersBaseUrl: config.ordersBaseUrl ?? '/orders',
        pollIntervalMs: Number(config.pollIntervalMs ?? 8000),
        snapshotUrlBase: config.snapshotUrlBase ?? '/operations/orders',
        orderDetails: config.orderDetails ?? {},
        filters: { ...DEFAULT_FILTERS },
        drawerOpen: false,
        drawerLoading: false,
        drawerError: '',
        snapshotRequestId: 0,
        draftItems: [],
        liveQueue: null,
        liveConnection: {
            state: 'live',
            label: 'Live',
            tone: 'green',
            description: 'Conectado',
        },
        liveToast: {
            visible: false,
            title: '',
            customer: '',
            elapsed: '',
        },
        liveToastTimer: null,
        flashOrderIds: [],
        flashTimer: null,
        toast: {
            visible: false,
            type: 'success',
            message: '',
        },
        toastTimer: null,
        csrfToken: document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        submittingActionKey: null,

        init() {
            this.orderDetails = this.normalizeOrderDetails(this.orderDetails);
            this.selectedOrder = this.selectedOrder ? this.decorateSelectedOrder(this.selectedOrder) : null;
            this.activeId = this.activeId ?? this.selectedOrder?.id ?? this.orders[0]?.id ?? null;
            this.drawerOpen = new URL(window.location.href).searchParams.has('order');
            this.applyInitialFilters(config.filters ?? {});

            if (! this.selectedOrder && this.activeId !== null) {
                this.selectedOrder = this.decorateSelectedOrder(
                    this.orderDetails[this.activeId] ?? this.orders.find((order) => Number(order.id) === Number(this.activeId)) ?? null,
                );
            }

            if (this.selectedOrder) {
                this.selectedOrder = this.decorateSelectedOrder(this.selectedOrder);
            }

            this.syncDraftItems();
            this.syncUrlFromState({ preserveDrawer: true });

            if (this.drawerOpen && this.activeId !== null) {
                void this.refreshSelectedOrderSnapshot();
            }

            this.liveQueue = new LiveQueue(this, {
                feedUrl: this.feedUrl,
                ordersBaseUrl: this.ordersBaseUrl,
                pollIntervalMs: this.pollIntervalMs,
                latestOrderId: this.orders[0]?.id ?? 0,
            });

            this.liveQueue.start();
        },

        destroy() {
            this.liveQueue?.stop();
        },

        get activeOrder() {
            return this.selectedOrder;
        },

        get visibleOrders() {
            return this.orders.filter((order) => this.matchesFilters(order));
        },

        get boardColumns() {
            const referenceTimeMs = parseTimestamp(this.serverTime) ?? Date.now();

            return BOARD_COLUMNS.map((definition) => {
                const orders = this.visibleOrders
                    .filter((order) => definition.statuses.includes(order.status))
                    .slice()
                    .sort(sortOldestFirst);

                const averageWaitingMinutes = orders.length === 0
                    ? 0
                    : orders.reduce((total, order) => {
                        const createdAtMs = parseTimestamp(order.created_at_iso);
                        if (createdAtMs === null) {
                            return total;
                        }

                        return total + Math.max(0, (referenceTimeMs - createdAtMs) / 60000);
                    }, 0) / orders.length;

                return {
                    ...definition,
                    orders,
                    count: orders.length,
                    average_wait_label: orders.length > 0 ? formatWaitLabel(averageWaitingMinutes) : '0 min',
                };
            });
        },

        get boardTotals() {
            return this.boardColumns.map((column) => ({
                key: column.key,
                label: column.label,
                count: column.count,
                average_wait_label: column.average_wait_label,
                tone: column.tone,
            }));
        },

        get visibleOrdersCount() {
            return this.visibleOrders.length;
        },

        applyInitialFilters(filters = {}) {
            this.filters = {
                ...DEFAULT_FILTERS,
                search: String(filters.search ?? '').trim(),
                customer: String(filters.customer ?? '').trim(),
                channel: String(filters.channel ?? 'all'),
                priority: String(filters.priority ?? 'all'),
                status: String(filters.status ?? 'all'),
                vip: this.parseBooleanFilter(filters.vip),
                duplicates: this.parseBooleanFilter(filters.duplicates),
            };
        },

        parseBooleanFilter(value) {
            return value === true || value === 1 || value === '1' || value === 'true' || value === 'on';
        },

        normalizeOrderDetails(orderDetails) {
            return Object.fromEntries(
                Object.entries(orderDetails ?? {}).map(([key, order]) => [key, this.decorateSelectedOrder(order)]),
            );
        },

        normalizeOrder(order) {
            if (! order) {
                return null;
            }

            const normalized = { ...order };
            normalized.id = Number(normalized.id ?? 0);
            normalized.status = normalized.status ?? 'pending_review';
            normalized.status_label = normalized.status_label ?? this.statusLabel(normalized.status);
            normalized.status_tone = normalized.status_tone ?? this.statusTone(normalized.status);
            normalized.source_channel = normalized.source_channel ?? normalized.channel_key ?? '';
            normalized.channel = normalized.channel ?? 'Sin canal';
            normalized.channel_key = normalized.channel_key ?? normalized.source_channel ?? '';
            normalized.customer_name = normalized.customer_name ?? normalized.customer ?? 'Sin cliente';
            normalized.customer = normalized.customer ?? normalized.customer_name;
            normalized.customer_phone = normalized.customer_phone ?? normalized.phone ?? 'Sin telefono';
            normalized.phone = normalized.phone ?? normalized.customer_phone;
            normalized.branch_name = normalized.branch_name ?? normalized.branch ?? 'Sin sucursal';
            normalized.branch = normalized.branch ?? normalized.branch_name;
            normalized.elapsed_label = normalized.elapsed_label ?? 'Sin fecha';
            normalized.created_at_label = normalized.created_at_label ?? 'Sin fecha';
            normalized.created_at_iso = normalized.created_at_iso ?? normalized.created_at ?? null;
            normalized.created_at = normalized.created_at ?? normalized.created_at_iso;
            normalized.received_at = normalized.received_at ?? null;
            normalized.received_at_iso = normalized.received_at_iso ?? normalized.received_at;
            normalized.preview = normalized.preview ?? 'Sin mensaje original';
            normalized.original_message = normalized.original_message ?? normalized.preview;
            normalized.items_count = toNumber(normalized.items_count);
            normalized.recognized_items_count = toNumber(normalized.recognized_items_count);
            normalized.unread = Boolean(normalized.unread);
            normalized.duplicate = Boolean(normalized.duplicate);
            normalized.possible_duplicate = Boolean(normalized.possible_duplicate ?? normalized.duplicate);
            normalized.vip = Boolean(normalized.vip);
            normalized.parser_confidence = normalized.parser_confidence === null || normalized.parser_confidence === undefined
                ? null
                : Number(normalized.parser_confidence);
            normalized.open_notifications = toNumber(normalized.open_notifications);
            normalized.recent_activity = Array.isArray(normalized.recent_activity) ? normalized.recent_activity : [];
            normalized.update_url = normalized.update_url ?? this.buildOrderUrl(normalized.id);
            normalized.show_url = normalized.show_url ?? this.buildOrderShowUrl(normalized.id);

            return normalized;
        },

        decorateSelectedOrder(order) {
            if (! order) {
                return null;
            }

            const normalized = this.normalizeOrder(order);
            normalized.items = Array.isArray(normalized.items) ? normalized.items : [];
            normalized.customer_context = this.normalizeCustomerContext(
                normalized,
                normalized.customer_context ?? this.defaultCustomerContext(normalized.customer_name, normalized.customer_phone),
            );

            if (! normalized.primary_action && ! normalized.secondary_actions && ! normalized.terminal_message) {
                const workflow = this.buildWorkflow(normalized.id, normalized.status);
                normalized.primary_action = workflow.primary_action;
                normalized.secondary_actions = workflow.secondary_actions;
                normalized.terminal_message = workflow.terminal_message;
            }

            return normalized;
        },

        normalizeCustomerContext(order, customerContext) {
            const context = {
                name: customerContext?.name ?? order.customer_name ?? 'Sin cliente',
                phone: customerContext?.phone ?? order.customer_phone ?? 'Sin telefono',
                total_orders: toNumber(customerContext?.total_orders),
                favorite_products: Array.isArray(customerContext?.favorite_products) ? customerContext.favorite_products : [],
                favorite_channel: customerContext?.favorite_channel ?? { name: 'Unknown', percentage: 0 },
                last_order: customerContext?.last_order ?? null,
                segment: customerContext?.segment ?? 'Inactive',
                open_notifications: toNumber(customerContext?.open_notifications),
                recent_activity: Array.isArray(customerContext?.recent_activity) ? customerContext.recent_activity : [],
                current_order: customerContext?.current_order ?? null,
                current_alerts: Array.isArray(customerContext?.current_alerts) ? customerContext.current_alerts : [],
            };

            context.current_order = {
                id: order.id,
                status: order.status_label,
                channel: order.channel,
                elapsed: order.elapsed_label,
                preview: order.preview,
                possible_duplicate: order.duplicate,
            };
            context.current_alerts = this.buildAlerts(order, context);

            return context;
        },

        defaultCustomerContext(name, phone) {
            return {
                name: name ?? 'Sin cliente',
                phone: phone ?? 'Sin telefono',
                total_orders: 0,
                favorite_products: [],
                favorite_channel: { name: 'Unknown', percentage: 0 },
                last_order: null,
                segment: 'Inactive',
                open_notifications: 0,
                recent_activity: [],
                current_order: null,
                current_alerts: [],
            };
        },

        buildAlerts(order, customerContext) {
            const alerts = [];

            if ((customerContext?.segment ?? 'Inactive') === 'VIP') {
                alerts.push('Cliente VIP');
            }

            if ((customerContext?.open_notifications ?? 0) > 0) {
                alerts.push(`${customerContext.open_notifications} notificacion(es) abiertas`);
            }

            if (order.duplicate) {
                alerts.push('Posible duplicado');
            }

            if (order.parser_confidence !== null && Number(order.parser_confidence) < 0.5) {
                alerts.push('Confianza baja');
            }

            if (order.status === 'pending_review' && order.created_at_iso) {
                const ageMinutes = (Date.now() - parseTimestamp(order.created_at_iso)) / 60000;
                if (ageMinutes >= 20) {
                    alerts.push('Pedido envejecido');
                }
            }

            return alerts;
        },

        currentAlerts(customerContext, order) {
            return this.buildAlerts(order, customerContext);
        },

        buildBaseCustomerContext(customer, history, openNotifications) {
            const lastOrder = history[0] ?? null;
            const favoriteProducts = this.favoriteProducts(history);
            const favoriteChannel = this.favoriteChannel(history);
            const segment = this.customerSegment(toNumber(customer.orders_count), lastOrder?.created_at ?? null);
            const recentActivity = history.slice(0, 5).map((order) => ({
                label: `Pedido #${order.id}`,
                status: this.statusLabel(order.status),
                channel: this.channelLabel(order.source_channel),
                elapsed: this.elapsedLabel(order.created_at ?? null),
                duplicate: order.possible_duplicate_of_order_id !== null,
            }));

            return {
                name: customer.name,
                phone: customer.phone,
                total_orders: toNumber(customer.orders_count),
                favorite_products: favoriteProducts,
                favorite_channel: favoriteChannel,
                last_order: lastOrder !== null ? {
                    id: lastOrder.id,
                    label: `Pedido #${lastOrder.id}`,
                    elapsed: this.elapsedLabel(lastOrder.created_at ?? null),
                    status: this.statusLabel(lastOrder.status),
                } : null,
                segment,
                open_notifications: openNotifications,
                recent_activity: recentActivity,
            };
        },

        buildWorkflow(orderId, status) {
            const base = `${this.ordersBaseUrl}/${orderId}`;

            switch (status) {
                case 'pending_review':
                    return {
                        primary_action: this.buildAction('confirm', 'Confirmar pedido', 'POST', `${base}/confirm`, 'primary'),
                        secondary_actions: [
                            this.buildAction('reject', 'Rechazar', 'POST', `${base}/reject`, 'danger', true),
                            this.buildAction('cancel', 'Cancelar', 'POST', `${base}/cancel`, 'danger', true),
                        ],
                        terminal_message: null,
                    };
                case 'confirmed':
                    return {
                        primary_action: this.buildAction('prepare', 'Iniciar preparacion', 'POST', `${base}/prepare`, 'primary'),
                        secondary_actions: [
                            this.buildAction('cancel', 'Cancelar', 'POST', `${base}/cancel`, 'danger', true),
                        ],
                        terminal_message: null,
                    };
                case 'preparing':
                    return {
                        primary_action: this.buildAction('ready', 'Marcar listo', 'POST', `${base}/ready-for-dispatch`, 'primary'),
                        secondary_actions: [
                            this.buildAction('cancel', 'Cancelar', 'POST', `${base}/cancel`, 'danger', true),
                        ],
                        terminal_message: null,
                    };
                case 'ready_for_dispatch':
                    return {
                        primary_action: this.buildAction('dispatch', 'Despachar', 'POST', `${base}/dispatch`, 'primary'),
                        secondary_actions: [
                            this.buildAction('cancel', 'Cancelar', 'POST', `${base}/cancel`, 'danger', true),
                        ],
                        terminal_message: null,
                    };
                case 'dispatched':
                    return {
                        primary_action: null,
                        secondary_actions: [this.buildAction('view_history', 'Ver historial', 'GET', `${base}`, 'secondary')],
                        terminal_message: 'Pedido despachado',
                    };
                case 'cancelled':
                    return {
                        primary_action: null,
                        secondary_actions: [],
                        terminal_message: 'Pedido cancelado',
                    };
                case 'rejected':
                    return {
                        primary_action: null,
                        secondary_actions: [],
                        terminal_message: 'Pedido rechazado',
                    };
                default:
                    return {
                        primary_action: null,
                        secondary_actions: [],
                        terminal_message: null,
                    };
            }
        },

        buildAction(key, label, method, url, style, requiresConfirmation = false) {
            return {
                key,
                label,
                method,
                url,
                style,
                requires_confirmation: requiresConfirmation,
            };
        },

        updateSelectedOrderFromResponse(order) {
            if (! order) {
                return;
            }

            const sourceOrder = this.activeOrder ?? this.orderDetails[this.activeId] ?? this.orders.find((item) => Number(item.id) === Number(order.id)) ?? null;
            const mergedOrder = sourceOrder ? {
                ...sourceOrder,
                ...order,
                items: sourceOrder.items ?? order.items ?? [],
                customer_context: sourceOrder.customer_context ?? order.customer_context ?? null,
            } : order;

            const normalized = this.decorateSelectedOrder(mergedOrder);
            this.selectedOrder = normalized;
            this.orderDetails[normalized.id] = normalized;
            this.syncDraftItems();
        },

        async refreshSelectedOrderSnapshot(orderId = this.activeId) {
            if (orderId === null || orderId === undefined) {
                return;
            }

            const requestId = ++this.snapshotRequestId;
            this.drawerLoading = true;
            this.drawerError = '';

            try {
                const response = await fetch(`${this.snapshotUrlBase}/${orderId}/snapshot`, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });

                if (requestId !== this.snapshotRequestId) {
                    return;
                }

                if (! response.ok) {
                    throw new Error(`Snapshot request failed with status ${response.status}`);
                }

                const payload = await response.json();

                if (requestId !== this.snapshotRequestId) {
                    return;
                }

                const normalized = this.decorateSelectedOrder(payload);
                this.selectedOrder = normalized;
                this.activeId = normalized.id;
                this.orderDetails[normalized.id] = normalized;
                this.drawerLoading = false;
                this.drawerError = '';
                this.syncDraftItems();
                this.syncUrlFromState({ preserveDrawer: true });
            } catch (error) {
                if (requestId !== this.snapshotRequestId) {
                    return;
                }

                this.drawerLoading = false;
                this.drawerError = 'No se pudo cargar el detalle del pedido.';
            }
        },

        applyOrderUpdate(updatedOrder) {
            if (! updatedOrder) {
                return;
            }

            const existingOrder = this.orders.find((order) => Number(order.id) === Number(updatedOrder.id));
            const normalized = this.decorateSelectedOrder(
                existingOrder ? {
                    ...existingOrder,
                    ...updatedOrder,
                } : updatedOrder,
            );
            this.orderDetails[normalized.id] = normalized;

            if (existingOrder) {
                Object.assign(existingOrder, {
                    status: normalized.status,
                    status_label: normalized.status_label,
                    status_tone: normalized.status_tone,
                    channel: normalized.channel,
                    channel_key: normalized.channel_key,
                    customer_name: normalized.customer_name,
                    customer_phone: normalized.customer_phone,
                    branch_name: normalized.branch_name,
                    elapsed_label: normalized.elapsed_label,
                    created_at_label: normalized.created_at_label,
                    created_at_iso: normalized.created_at_iso,
                    preview: normalized.preview,
                    items_count: normalized.items_count,
                    recognized_items_count: normalized.recognized_items_count,
                    unread: normalized.unread,
                    duplicate: normalized.duplicate,
                    vip: normalized.vip,
                    parser_confidence: normalized.parser_confidence,
                    update_url: normalized.update_url,
                    show_url: normalized.show_url,
                });
            }

            if (this.activeId === normalized.id) {
                this.selectedOrder = normalized;
                this.syncDraftItems();
            }
        },

        setLiveMuted(isMuted) {
            this.liveQueue?.setMuted(isMuted);
        },

        isLiveMuted() {
            return this.liveQueue?.soundMuted ?? false;
        },

        toggleLiveSound() {
            this.setLiveMuted(! this.isLiveMuted());
        },

        showToast(message, type = 'success') {
            this.toast.message = message;
            this.toast.type = type;
            this.toast.visible = true;

            if (this.toastTimer) {
                window.clearTimeout(this.toastTimer);
            }

            this.toastTimer = window.setTimeout(() => {
                this.toast.visible = false;
            }, 4000);
        },

        async submitWorkflowAction(action) {
            if (! action || ! action.url) {
                return;
            }

            if (action.method === 'GET') {
                window.location.href = action.url;
                return;
            }

            if (action.requires_confirmation && ! window.confirm('Esta accion modificara el estado del pedido. Deseas continuar?')) {
                return;
            }

            this.submittingActionKey = action.key;

            try {
                const formData = new FormData();
                formData.append('_token', this.csrfToken);

                const method = (action.method ?? 'POST').toUpperCase();
                if (method !== 'POST') {
                    formData.append('_method', method);
                }

                const response = await fetch(action.url, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: formData,
                    credentials: 'same-origin',
                });

                const contentType = response.headers.get('content-type') ?? '';
                const payload = contentType.includes('application/json') ? await response.json() : null;

                if (! response.ok) {
                    const message = response.status === 422
                        ? 'Esta accion ya no esta disponible para este pedido.'
                        : payload?.message ?? 'No se pudo completar la accion.';

                    this.showToast(message, 'error');
                    return;
                }

                if (payload?.order) {
                    this.applyOrderUpdate(payload.order);
                    this.updateSelectedOrderFromResponse(payload.order);
                    void this.refreshSelectedOrderSnapshot(this.activeId);
                }

                this.showToast(payload?.message ?? 'Pedido actualizado.', 'success');
                void this.liveQueue?.refresh();
            } catch (error) {
                this.showToast('No se pudo completar la accion.', 'error');
            } finally {
                this.submittingActionKey = null;
            }
        },

        removeItem(index) {
            this.draftItems.splice(index, 1);
        },

        addItem() {
            this.draftItems.push({
                id: null,
                quantity: 1,
                unit: '',
                raw_text: '',
                notes: '',
            });
        },

        serializedItems() {
            return JSON.stringify(
                this.draftItems.map((item) => ({
                    id: item.id ?? null,
                    quantity: item.quantity ?? 1,
                    unit: item.unit ?? '',
                    raw_text: item.raw_text ?? '',
                    notes: item.notes ?? '',
                })),
            );
        },

        syncDraftItems() {
            this.draftItems = (this.activeOrder?.items ?? []).map((item) => ({ ...item }));
        },

        select(orderId, order = null) {
            this.activeId = Number(orderId);
            this.drawerOpen = true;
            this.drawerLoading = true;
            this.drawerError = '';

            const nextOrder = order ?? this.orderDetails[this.activeId] ?? this.orders.find((item) => Number(item.id) === Number(orderId)) ?? null;
            this.selectedOrder = nextOrder ? this.decorateSelectedOrder(nextOrder) : null;
            this.syncDraftItems();
            this.syncUrlFromState({ preserveDrawer: true });
            void this.refreshSelectedOrderSnapshot(this.activeId);
        },

        closeDrawer() {
            this.drawerOpen = false;
            this.drawerLoading = false;
            this.drawerError = '';
            this.snapshotRequestId += 1;
            this.syncUrlFromState({ preserveDrawer: false });
        },

        applyFilter(key, value) {
            if (key === 'vip' || key === 'duplicates') {
                this.filters[key] = this.parseBooleanFilter(value);
            } else {
                this.filters[key] = String(value ?? '');
            }

            this.syncUrlFromState({ preserveDrawer: true });
            void this.liveQueue?.refresh();
        },

        toggleFilter(key) {
            this.filters[key] = ! this.filters[key];
            this.syncUrlFromState({ preserveDrawer: true });
            void this.liveQueue?.refresh();
        },

        clearFilters() {
            const currentStatus = this.filters.status;
            this.filters = {
                ...DEFAULT_FILTERS,
                status: currentStatus,
            };
            this.syncUrlFromState({ preserveDrawer: true });
            void this.liveQueue?.refresh();
        },

        syncUrlFromState({ preserveDrawer = true } = {}) {
            const url = new URL(window.location.href);
            const managedKeys = ['search', 'customer', 'channel', 'priority', 'vip', 'duplicates', 'page'];

            for (const key of managedKeys) {
                url.searchParams.delete(key);
            }

            if (this.filters.search.trim() !== '') {
                url.searchParams.set('search', this.filters.search.trim());
            }

            if (this.filters.customer.trim() !== '') {
                url.searchParams.set('customer', this.filters.customer.trim());
            }

            if (this.filters.channel !== 'all') {
                url.searchParams.set('channel', this.filters.channel);
            }

            if (this.filters.priority !== 'all') {
                url.searchParams.set('priority', this.filters.priority);
            }

            if (this.filters.vip) {
                url.searchParams.set('vip', '1');
            }

            if (this.filters.duplicates) {
                url.searchParams.set('duplicates', '1');
            }

            if (preserveDrawer && this.drawerOpen && this.activeId !== null) {
                url.searchParams.set('order', String(this.activeId));
            } else {
                url.searchParams.delete('order');
            }

            window.history.replaceState({}, '', url);
        },

        matchesFilters(order) {
            if (! order) {
                return false;
            }

            if (this.filters.channel !== 'all' && String(order.channel_key ?? '').toLowerCase() !== this.filters.channel) {
                return false;
            }

            if (this.filters.priority === 'urgent' && ! isUrgentOrder(order, parseTimestamp(this.serverTime) ?? Date.now())) {
                return false;
            }

            if (this.filters.priority === 'normal' && isUrgentOrder(order, parseTimestamp(this.serverTime) ?? Date.now())) {
                return false;
            }

            if (this.filters.vip && ! order.vip) {
                return false;
            }

            if (this.filters.duplicates && ! order.duplicate) {
                return false;
            }

            const customerTerm = normalizeText(this.filters.customer.trim());
            if (customerTerm !== '') {
                const customerHaystack = normalizeText([order.customer_name, order.customer_phone].join(' '));
                if (! customerHaystack.includes(customerTerm)) {
                    return false;
                }
            }

            const searchTerm = normalizeText(this.filters.search.trim());
            if (searchTerm !== '') {
                const searchHaystack = normalizeText([
                    order.customer_name,
                    order.customer_phone,
                    order.branch_name,
                    order.preview,
                    order.channel,
                    order.status_label,
                    `#${order.id}`,
                ].join(' '));

                if (! searchHaystack.includes(searchTerm)) {
                    return false;
                }
            }

            return true;
        },

        columnKeyForOrder(order) {
            return columnForStatus(order.status);
        },

        columnOrders(columnKey) {
            return this.visibleOrders.filter((order) => this.columnKeyForOrder(order) === columnKey).sort(sortOldestFirst);
        },

        columnCount(columnKey) {
            return this.columnOrders(columnKey).length;
        },

        averageWaitLabel(columnKey) {
            const orders = this.columnOrders(columnKey);
            if (orders.length === 0) {
                return '0 min';
            }

            const referenceTimeMs = parseTimestamp(this.serverTime) ?? Date.now();
            const averageMinutes = orders.reduce((total, order) => {
                const createdAtMs = parseTimestamp(order.created_at_iso);
                if (createdAtMs === null) {
                    return total;
                }

                return total + Math.max(0, (referenceTimeMs - createdAtMs) / 60000);
            }, 0) / orders.length;

            return formatWaitLabel(averageMinutes);
        },

        badgeForChannel(order) {
            return buildChannelPill(order);
        },

        badgeForPriority(order) {
            return buildPriorityPill(order, parseTimestamp(this.serverTime) ?? Date.now());
        },

        badgeForConfidence(order) {
            return buildConfidencePill(order);
        },

        cardAccentClass(order) {
            return ({
                new: 'border-l-blue-500',
                preparing: 'border-l-amber-500',
                ready: 'border-l-emerald-500',
                dispatched: 'border-l-slate-400',
            })[this.columnKeyForOrder(order)] ?? 'border-l-slate-300';
        },

        columnToneClass(columnKey) {
            return BOARD_COLUMNS.find((column) => column.key === columnKey)?.tone ?? 'border-slate-200 bg-white text-slate-700';
        },

        columnDotClass(columnKey) {
            return BOARD_COLUMNS.find((column) => column.key === columnKey)?.dot ?? 'bg-slate-400';
        },

        columnEmptyLabel(columnKey) {
            return BOARD_COLUMNS.find((column) => column.key === columnKey)?.emptyLabel ?? 'No hay pedidos.';
        },

        statusLabel(status) {
            return ({
                pending_review: 'Nuevos',
                confirmed: 'Confirmado',
                preparing: 'Preparando',
                ready_for_dispatch: 'Listo',
                dispatched: 'Despachado',
                cancelled: 'Cancelado',
                rejected: 'Rechazado',
            })[status] ?? String(status ?? '').replaceAll('_', ' ');
        },

        statusTone(status) {
            return ({
                pending_review: 'bg-blue-50 text-blue-800 ring-1 ring-blue-100',
                confirmed: 'bg-sky-50 text-sky-800 ring-1 ring-sky-100',
                preparing: 'bg-amber-50 text-amber-800 ring-1 ring-amber-100',
                ready_for_dispatch: 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-100',
                dispatched: 'bg-slate-100 text-slate-700 ring-1 ring-slate-200',
                cancelled: 'bg-red-50 text-red-800 ring-1 ring-red-100',
                rejected: 'bg-slate-100 text-slate-700 ring-1 ring-slate-200',
            })[status] ?? 'bg-slate-100 text-slate-700 ring-1 ring-slate-200';
        },

        channelLabel(channel) {
            const key = String(channel ?? '').toLowerCase();
            return CHANNEL_LABELS[key] ?? (channel ? String(channel) : 'Sin canal');
        },

        elapsedLabel(date) {
            if (! date) {
                return 'Sin fecha';
            }

            const parsed = date instanceof Date ? date : new Date(date);
            if (Number.isNaN(parsed.getTime())) {
                return 'Sin fecha';
            }

            const minutes = (Date.now() - parsed.getTime()) / 60000;
            if (minutes < 60) {
                return `Hace ${Math.max(1, Math.floor(minutes))} min`;
            }

            const hours = Math.floor(minutes / 60);
            if (hours < 24) {
                return `Hace ${hours} h`;
            }

            return `Hace ${Math.floor(hours / 24)} d`;
        },

        customerSegment(totalOrders, lastOrderAt) {
            if (totalOrders === 0 || lastOrderAt === null) {
                return 'Inactive';
            }

            const lastSeen = lastOrderAt instanceof Date ? lastOrderAt : new Date(lastOrderAt);
            if (Number.isNaN(lastSeen.getTime())) {
                return 'Inactive';
            }

            const inactiveDays = 90;
            const vipMinOrders = 20;
            const frequentMinOrders = 3;
            const newMaxOrders = 2;

            if (lastSeen.getTime() <= Date.now() - (inactiveDays * 86400000)) {
                return 'Inactive';
            }

            if (totalOrders >= vipMinOrders) {
                return 'VIP';
            }

            if (totalOrders >= frequentMinOrders) {
                return 'Frequent';
            }

            if (totalOrders <= newMaxOrders) {
                return 'New';
            }

            return 'Frequent';
        },

        favoriteProducts(history) {
            const groups = new Map();

            for (const order of history) {
                for (const item of order.orderItems ?? []) {
                    const key = item.product_id !== null && item.product_id !== undefined
                        ? `product:${item.product_id}`
                        : `raw:${normalizeText(item.raw_text ?? '') || 'sin-texto'}`;

                    if (! groups.has(key)) {
                        groups.set(key, {
                            label: this.resolveItemLabel(item.product_id, item.raw_text, item.product?.name ?? null),
                            count: 0,
                        });
                    }

                    groups.get(key).count += 1;
                }
            }

            return Array.from(groups.values())
                .sort((left, right) => {
                    if (left.count === right.count) {
                        return left.label.localeCompare(right.label);
                    }

                    return right.count - left.count;
                })
                .slice(0, 3)
                .map((entry) => `${entry.label} x${entry.count}`);
        },

        favoriteChannel(history) {
            if (history.length === 0) {
                return {
                    name: 'Unknown',
                    percentage: 0.0,
                };
            }

            const counts = new Map();
            for (const order of history) {
                const label = this.channelLabel(order.source_channel);
                counts.set(label, (counts.get(label) ?? 0) + 1);
            }

            let favoriteChannel = 'Unknown';
            let favoriteCount = 0;
            for (const [label, count] of counts.entries()) {
                if (count > favoriteCount) {
                    favoriteCount = count;
                    favoriteChannel = label;
                }
            }

            return {
                name: favoriteChannel,
                percentage: Math.round((favoriteCount / history.length) * 10000) / 100,
            };
        },

        resolveItemLabel(productId, rawText, productName) {
            if (productName) {
                return productName;
            }

            if (productId !== null && productId !== undefined) {
                return `Producto #${productId}`;
            }

            const label = String(rawText ?? '').trim();
            return label !== '' ? label : 'Sin texto';
        },

        buildOrderUrl(orderId) {
            return `${this.ordersBaseUrl}/${orderId}`;
        },

        buildOrderShowUrl(orderId) {
            return `${this.ordersBaseUrl}/${orderId}`;
        },

        showNotification(order) {
            if (! order) {
                return;
            }

            this.liveToast = {
                visible: true,
                title: 'Nuevo pedido recibido',
                customer: order.customer_name ?? 'Sin cliente',
                elapsed: 'Hace unos segundos',
            };

            if (this.liveToastTimer !== null) {
                window.clearTimeout(this.liveToastTimer);
            }

            this.liveToastTimer = window.setTimeout(() => {
                this.liveToast.visible = false;
            }, 4000);
        },

        playSound() {
            if (this.isLiveMuted() || ! this.liveQueue?.audioUnlocked) {
                return;
            }

            this.liveQueue.playSound();
        },

        formatColumnTitle(column) {
            return `${column.label}`;
        },

        syncSelectedOrder(updatedOrder) {
            if (! this.selectedOrder) {
                this.selectedOrder = this.decorateSelectedOrder(updatedOrder);
                this.syncDraftItems();
                return;
            }

            const preservedItems = Array.isArray(this.selectedOrder.items)
                ? this.selectedOrder.items.map((item) => ({ ...item }))
                : [];
            const preservedContext = this.selectedOrder.customer_context
                ?? this.defaultCustomerContext(this.selectedOrder.customer_name, this.selectedOrder.customer_phone);
            const { customer_context: _customerContext, items: _items, ...rest } = updatedOrder;

            Object.assign(this.selectedOrder, rest);
            this.selectedOrder.items = preservedItems;
            const workflow = this.buildWorkflow(this.selectedOrder.id, this.selectedOrder.status);
            this.selectedOrder.primary_action = workflow.primary_action;
            this.selectedOrder.secondary_actions = workflow.secondary_actions;
            this.selectedOrder.terminal_message = workflow.terminal_message;
            this.selectedOrder.customer_context = this.normalizeCustomerContext(
                this.selectedOrder,
                preservedContext,
            );
            this.syncDraftItems();
        },

        flashOrders(orderIds) {
            this.flashOrderIds = Array.isArray(orderIds) ? orderIds : [];

            if (this.flashTimer !== null) {
                window.clearTimeout(this.flashTimer);
            }

            this.flashTimer = window.setTimeout(() => {
                this.flashOrderIds = [];
                this.flashTimer = null;
            }, 4000);
        },
    };
}
