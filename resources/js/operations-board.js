import { LiveQueue } from './live-queue';

const CHANNEL_LABELS = {
    whatsapp: 'WhatsApp',
    telegram: 'Telegram',
    instagram: 'Instagram',
};

const DEFAULT_FILTERS = {
    search: '',
    customer: '',
    channel: 'all',
    priority: 'all',
    time: 'all',
    delivery: 'all',
    payment: 'all',
    vip: false,
    duplicates: false,
    urgent: false,
    status: 'all',
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
    if (!value) {
        return null;
    }

    const parsed = Date.parse(value);
    return Number.isFinite(parsed) ? parsed : null;
}

function formatMinutes(minutes) {
    const rounded = Math.max(1, Math.round(minutes));

    if (rounded < 60) {
        return `${rounded} min`;
    }

    return `${Math.round(rounded / 60)} h`;
}

function commitmentDateKey(order) {
    return String(order.commitment_date ?? '');
}

function dispatchedDateKey(order) {
    return String(order.dispatched_at ?? '').slice(0, 10);
}

function commitmentMinutes(order) {
    const match = String(order.commitment_time ?? '').match(/^(\d{2}):(\d{2})/);
    if (!match) {
        return Number.POSITIVE_INFINITY;
    }

    return (Number(match[1]) * 60) + Number(match[2]);
}

function timeWindowLabel(order) {
    const explicit = String(order.time_window_label ?? order.requested_time_window ?? '').trim();
    if (explicit !== '') {
        return explicit.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
    }

    const match = String(order.commitment_time ?? '').match(/^(\d{2}):/);
    if (!match) {
        return 'Tomorrow';
    }

    const hour = Number(match[1]);
    if (hour < 10) return 'Morning';
    if (hour < 13) return 'Afternoon';
    if (hour < 18) return 'Evening';
    return 'Tomorrow';
}

function isUrgentOrder(order) {
    const risk = String(order.risk_level ?? '');
    const priority = String(order.priority_level ?? '');
    const remaining = order.remaining_sla_minutes;

    return risk === 'critical'
        || risk === 'high'
        || priority === 'urgent'
        || (remaining !== null && remaining !== undefined && Number(remaining) < 0);
}

function sortOrder(left, right) {
    const leftRemaining = left.remaining_sla_minutes === null || left.remaining_sla_minutes === undefined
        ? Number.POSITIVE_INFINITY
        : Number(left.remaining_sla_minutes);
    const rightRemaining = right.remaining_sla_minutes === null || right.remaining_sla_minutes === undefined
        ? Number.POSITIVE_INFINITY
        : Number(right.remaining_sla_minutes);

    if (leftRemaining !== rightRemaining) {
        return leftRemaining - rightRemaining;
    }

    const leftCommitment = commitmentMinutes(left);
    const rightCommitment = commitmentMinutes(right);
    if (leftCommitment !== rightCommitment) {
        return leftCommitment - rightCommitment;
    }

    const leftTime = parseTimestamp(left.created_at_iso);
    const rightTime = parseTimestamp(right.created_at_iso);
    if (leftTime !== null && rightTime !== null && leftTime !== rightTime) {
        return leftTime - rightTime;
    }

    return toNumber(left.id) - toNumber(right.id);
}

function sectionSortLabel(card) {
    return `${String(card.customer_name ?? '')} ${String(card.preview ?? '')}`.trim();
}

function buildChannelPill(order) {
    const key = String(order.channel_key ?? '').toLowerCase();

    return {
        label: CHANNEL_LABELS[key] ?? (order.channel ?? 'Sin canal'),
        glyph: key === 'telegram' ? 'TG' : (key === 'whatsapp' ? 'WA' : 'CH'),
        tone: key === 'telegram'
            ? 'bg-sky-50 text-sky-800 ring-1 ring-sky-100'
            : 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-100',
    };
}

function buildWorkflow(orderId, status, ordersBaseUrl) {
    const base = `${ordersBaseUrl}/${orderId}`;

    switch (status) {
        case 'pending_review':
            return {
                label: 'Prepare',
                key: 'confirm',
                method: 'POST',
                url: `${base}/confirm`,
                style: 'primary',
                requires_confirmation: false,
            };
        case 'confirmed':
            return {
                label: 'Prepare',
                key: 'prepare',
                method: 'POST',
                url: `${base}/prepare`,
                style: 'primary',
                requires_confirmation: false,
            };
        case 'preparing':
            return {
                label: 'Ready',
                key: 'ready',
                method: 'POST',
                url: `${base}/ready-for-dispatch`,
                style: 'primary',
                requires_confirmation: false,
            };
        case 'ready_for_dispatch':
            return {
                label: 'Dispatch',
                key: 'dispatch',
                method: 'POST',
                url: `${base}/dispatch`,
                style: 'primary',
                requires_confirmation: false,
            };
        case 'dispatched':
            return {
                label: 'Complete',
                key: 'view_history',
                method: 'GET',
                url: base,
                style: 'secondary',
                requires_confirmation: false,
            };
        default:
            return {
                label: 'Cancel',
                key: 'cancel',
                method: 'POST',
                url: `${base}/cancel`,
                style: 'danger',
                requires_confirmation: true,
            };
    }
}

function normalizeQueueData(queueData) {
    return {
        sections: Array.isArray(queueData?.sections) ? queueData.sections : [],
        metrics: queueData?.metrics ?? {},
    };
}

function normalizeOrderDetails(orderDetails) {
    const normalized = {};

    for (const [key, value] of Object.entries(orderDetails ?? {})) {
        normalized[key] = value;
    }

    return normalized;
}

function normalizeOrder(order, ordersBaseUrl) {
    const normalizedId = Number(order?.id ?? 0);
    const workflow = buildWorkflow(normalizedId, order?.status ?? 'pending_review', ordersBaseUrl);

        return {
            id: normalizedId,
            status: order?.status ?? 'pending_review',
        status_label: order?.status_label ?? String(order?.status ?? 'pending review').replaceAll('_', ' '),
        status_tone: order?.status_tone ?? 'bg-slate-100 text-slate-700 ring-1 ring-slate-200',
        channel: order?.channel ?? 'Sin canal',
        channel_key: order?.channel_key ?? '',
        customer_name: order?.customer_name ?? 'Sin cliente',
        customer_phone: order?.customer_phone ?? 'Sin telefono',
        branch_name: order?.branch_name ?? 'Sin sucursal',
        elapsed_label: order?.elapsed_label ?? 'Sin fecha',
        created_at_label: order?.created_at_label ?? 'Sin fecha',
        created_at_iso: order?.created_at_iso ?? null,
        dispatched_at: order?.dispatched_at ?? null,
        preview: order?.preview ?? 'Sin mensaje original',
        summary: order?.summary ?? order?.preview ?? 'Sin resumen',
        items_count: Number(order?.items_count ?? 0),
        recognized_items_count: Number(order?.recognized_items_count ?? 0),
        unread: Boolean(order?.unread),
        duplicate: Boolean(order?.duplicate),
        vip: Boolean(order?.vip),
        parser_confidence: order?.parser_confidence === null || order?.parser_confidence === undefined
            ? null
            : Number(order.parser_confidence),
        delivery_method: order?.delivery_method ?? null,
        payment_method: order?.payment_method ?? null,
        commitment_date: order?.commitment_date ?? null,
        commitment_time: order?.commitment_time ?? null,
        remaining_sla_minutes: order?.remaining_sla_minutes === null || order?.remaining_sla_minutes === undefined
            ? null
            : Number(order.remaining_sla_minutes),
        risk_level: order?.risk_level ?? null,
        priority_level: order?.priority_level ?? null,
        priority_score: order?.priority_score === null || order?.priority_score === undefined
            ? null
            : Number(order.priority_score),
        priority_reason: order?.priority_reason ?? null,
        risk_reason: order?.risk_reason ?? null,
        requested_time_window: order?.requested_time_window ?? null,
        notes: order?.notes ?? null,
        decision_version: order?.decision_version ?? null,
        planner_confidence: order?.planner_confidence ?? null,
        planner_notes: order?.planner_notes ?? null,
        sla_minutes: order?.sla_minutes ?? null,
        metadata_json: order?.metadata_json ?? null,
        update_url: order?.update_url ?? `${ordersBaseUrl}/${normalizedId}`,
        show_url: order?.show_url ?? `${ordersBaseUrl}/${normalizedId}`,
        items: Array.isArray(order?.items) ? order.items.map((item) => ({ ...item })) : [],
        fulfillment_plan: order?.fulfillment_plan ?? {
            requested_date: order?.commitment_date ?? null,
            requested_time_window: order?.requested_time_window ?? null,
            delivery_method: order?.delivery_method ?? null,
            payment_method: order?.payment_method ?? null,
            pickup_branch: null,
            delivery_address: null,
            delivery_notes: null,
        },
        parser_details: order?.parser_details ?? {
            parser_confidence: order?.parser_confidence ?? null,
            decision_version: order?.decision_version ?? null,
            planner_confidence: order?.planner_confidence ?? null,
            planner_notes: order?.planner_notes ?? null,
        },
        duplicate_analysis: order?.duplicate_analysis ?? {
            possible_duplicate_of: null,
            duplicate_score: null,
            duplicate_reason: null,
            duplicate_checked_at: null,
        },
        timeline: Array.isArray(order?.timeline) ? order.timeline.map((item) => ({ ...item })) : [],
        notification_history: Array.isArray(order?.notification_history)
            ? order.notification_history.map((item) => ({ ...item }))
            : [],
        customer_context: order?.customer_context ?? {
            name: order?.customer_name ?? 'Sin cliente',
            phone: order?.customer_phone ?? 'Sin telefono',
            total_orders: 0,
            favorite_products: [],
            favorite_channel: { name: 'Unknown', percentage: 0 },
            last_order: null,
            segment: 'Inactive',
            open_notifications: 0,
            recent_activity: [],
            current_order: null,
            current_alerts: [],
        },
        primary_action: order?.primary_action ?? workflow,
        secondary_actions: Array.isArray(order?.secondary_actions) ? order.secondary_actions : [],
        terminal_message: order?.terminal_message ?? (statusTerminalMessage(order?.status ?? '')),
    };
}

function statusTerminalMessage(status) {
    return ({
        dispatched: 'Pedido despachado',
        cancelled: 'Pedido cancelado',
        rejected: 'Pedido rechazado',
    })[status] ?? null;
}

function statusLabel(status) {
    return ({
        pending_review: 'New',
        confirmed: 'Confirmed',
        preparing: 'Preparing',
        ready_for_dispatch: 'Ready',
        dispatched: 'Completed',
        cancelled: 'Cancelled',
        rejected: 'Rejected',
    })[status] ?? String(status ?? '').replaceAll('_', ' ');
}

function statusTone(status) {
    return ({
        pending_review: 'bg-amber-50 text-amber-800 ring-1 ring-amber-100',
        confirmed: 'bg-blue-50 text-blue-800 ring-1 ring-blue-100',
        preparing: 'bg-violet-50 text-violet-800 ring-1 ring-violet-100',
        ready_for_dispatch: 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-100',
        dispatched: 'bg-slate-100 text-slate-700 ring-1 ring-slate-200',
        cancelled: 'bg-red-50 text-red-800 ring-1 ring-red-100',
        rejected: 'bg-slate-100 text-slate-700 ring-1 ring-slate-200',
    })[status] ?? 'bg-slate-100 text-slate-700 ring-1 ring-slate-200';
}

export function createOperationsColumn(column) {
    return {
        column,
        get orders() {
            return Array.isArray(column.orders) ? column.orders : [];
        },
    };
}

export function createOperationsCard(order) {
    return {
        order,
    };
}

export function createOperationsBoard(config = {}) {
    return {
        orders: Array.isArray(config.orders) ? config.orders.map((order) => normalizeOrder(order, config.ordersBaseUrl ?? '/orders')) : [],
        selectedOrder: config.selectedOrder ? normalizeOrder(config.selectedOrder, config.ordersBaseUrl ?? '/orders') : null,
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
        orderDetails: normalizeOrderDetails(config.orderDetails ?? {}),
        queueData: normalizeQueueData(config.queueData ?? { sections: [], metrics: {} }),
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
            description: 'Connected',
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
        operatorName: config.operatorName ?? 'Carlos',
        csrfToken: document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        submittingActionKey: null,
        soundMuted: localStorage.getItem('benditio.operations.live-queue.muted') === '1',

        init() {
            this.queueData = normalizeQueueData(this.queueData);
            this.orderDetails = normalizeOrderDetails(this.orderDetails);
            this.orders = this.orders.map((order) => normalizeOrder(order, this.ordersBaseUrl));
            this.selectedOrder = this.selectedOrder ? normalizeOrder(this.selectedOrder, this.ordersBaseUrl) : null;
            this.activeId = this.activeId ?? this.selectedOrder?.id ?? this.orders[0]?.id ?? null;
            this.drawerOpen = new URL(window.location.href).searchParams.has('order');
            this.applyInitialFilters();

            if (!this.selectedOrder && this.activeId !== null) {
                const existing = this.orderDetails[this.activeId] ?? this.orders.find((order) => Number(order.id) === Number(this.activeId)) ?? null;
                this.selectedOrder = existing ? normalizeOrder(existing, this.ordersBaseUrl) : null;
            }

            if (this.selectedOrder) {
                this.syncDraftItems();
            }

            if (this.drawerOpen && this.activeId !== null) {
                void this.refreshSelectedOrderSnapshot();
            }

            this.syncUrlFromState({ preserveDrawer: true });

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

        get queueMetrics() {
            return this.buildMetrics(this.visibleOrders);
        },

        get queueSections() {
            const sections = this.queueData.sections ?? [];

            return sections.map((section) => {
                if (section.key === 'do_now') {
                    const cards = (section.cards ?? []).filter((card) => this.matchesFilters(card)).slice(0, section.limit ?? 5);

                    return {
                        ...section,
                        cards,
                    };
                }

                if (section.key === 'next') {
                    return {
                        ...section,
                        groups: (section.groups ?? [])
                            .map((group) => ({
                                ...group,
                                cards: (group.cards ?? []).filter((card) => this.matchesFilters(card)),
                            }))
                            .filter((group) => group.cards.length > 0),
                    };
                }

                if (section.key === 'completed') {
                    return {
                        ...section,
                        cards: (section.cards ?? []).filter((card) => this.matchesFilters(card)),
                    };
                }

                return section;
            });
        },

        get doNowCards() {
            return this.queueSections.find((section) => section.key === 'do_now')?.cards ?? [];
        },

        get upcomingGroups() {
            return this.queueSections.find((section) => section.key === 'next')?.groups ?? [];
        },

        get completedCards() {
            return this.queueSections.find((section) => section.key === 'completed')?.cards ?? [];
        },

        get hasQueueWork() {
            return this.doNowCards.length > 0 || this.upcomingGroups.length > 0 || this.completedCards.length > 0;
        },

        get headerDateLabel() {
            return this.formatHeaderDate(this.referenceDate());
        },

        get greetingLabel() {
            const hour = this.referenceDate().getHours();
            if (hour < 12) return 'Good Morning';
            if (hour < 18) return 'Good Afternoon';
            return 'Good Evening';
        },

        get todaySummary() {
            const metrics = this.queueMetrics;
            return {
                urgent: metrics.urgent_orders ?? 0,
                today: metrics.orders_today ?? 0,
                deliveries: metrics.deliveries ?? 0,
                pickups: metrics.pickups ?? 0,
                overdue: metrics.overdue ?? 0,
            };
        },

        referenceDate() {
            const parsed = parseTimestamp(this.serverTime);
            return new Date(parsed ?? Date.now());
        },

        formatHeaderDate(date) {
            const label = new Intl.DateTimeFormat('en-US', {
                weekday: 'long',
                month: 'long',
                day: 'numeric',
            }).format(date);

            return label.replace(',', '');
        },

        formatCommitment(order) {
            if (order.commitment_date && order.commitment_time) {
                return `${order.commitment_date} ${String(order.commitment_time).slice(0, 5)}`;
            }

            if (order.commitment_date) {
                return order.commitment_date;
            }

            return 'No commitment';
        },

        workflowLabel(order) {
            return buildWorkflow(order.id, order.status, this.ordersBaseUrl).label;
        },

        workflowAction(order) {
            const action = buildWorkflow(order.id, order.status, this.ordersBaseUrl);
            return {
                key: action.key,
                label: action.label,
                method: action.method,
                url: action.url,
                style: action.style,
                requires_confirmation: action.requires_confirmation,
            };
        },

        openDrawer(order) {
            this.select(order.id, order);
            this.drawerOpen = true;
            void this.refreshSelectedOrderSnapshot();
            this.syncUrlFromState({ preserveDrawer: true });
        },

        closeDrawer() {
            this.drawerOpen = false;
            this.drawerError = '';
            const url = new URL(window.location.href);
            url.searchParams.delete('order');
            window.history.replaceState({}, '', url);
        },

        select(orderId, order = null) {
            this.activeId = Number(orderId);

            const nextOrder = order
                ?? this.orderDetails[this.activeId]
                ?? this.orders.find((item) => Number(item.id) === Number(this.activeId))
                ?? null;

            this.selectedOrder = nextOrder ? normalizeOrder(nextOrder, this.ordersBaseUrl) : null;
            this.syncDraftItems();

            const nextUrl = new URL(window.location.href);
            nextUrl.searchParams.set('order', String(orderId));
            window.history.replaceState({}, '', nextUrl);
        },

        syncDraftItems() {
            this.draftItems = Array.isArray(this.activeOrder?.items)
                ? this.activeOrder.items.map((item) => ({ ...item }))
                : [];
        },

        decorateSelectedOrder(order) {
            return normalizeOrder(order, this.ordersBaseUrl);
        },

        updateSelectedOrderFromResponse(order) {
            if (!order) {
                return;
            }

            const normalized = normalizeOrder({
                ...order,
                items: this.activeOrder?.items ?? order.items ?? [],
                customer_context: this.activeOrder?.customer_context ?? null,
            }, this.ordersBaseUrl);

            this.selectedOrder = normalized;
            this.orderDetails[normalized.id] = normalized;
            this.syncDraftItems();
        },

        applyOrderUpdate(updatedOrder) {
            if (!updatedOrder) {
                return;
            }

            const normalized = normalizeOrder(updatedOrder, this.ordersBaseUrl);
            this.orderDetails[normalized.id] = normalized;

            const inboxOrder = this.orders.find((order) => Number(order.id) === Number(normalized.id));
            if (inboxOrder) {
                Object.assign(inboxOrder, normalized);
            }

            if (this.activeId === normalized.id) {
                this.selectedOrder = normalized;
                this.syncDraftItems();
            }
        },

        syncSelectedOrder(updatedOrder) {
            if (!this.selectedOrder) {
                this.selectedOrder = normalizeOrder(updatedOrder, this.ordersBaseUrl);
                this.syncDraftItems();
                return;
            }

            const preservedItems = Array.isArray(this.selectedOrder.items)
                ? this.selectedOrder.items.map((item) => ({ ...item }))
                : [];
            const preservedContext = this.selectedOrder.customer_context ?? null;
            const preservedWorkflow = {
                primary_action: this.selectedOrder.primary_action,
                secondary_actions: this.selectedOrder.secondary_actions,
                terminal_message: this.selectedOrder.terminal_message,
            };
            const normalized = normalizeOrder(updatedOrder, this.ordersBaseUrl);

            Object.assign(this.selectedOrder, normalized);
            this.selectedOrder.items = preservedItems;
            this.selectedOrder.customer_context = preservedContext ?? this.selectedOrder.customer_context;
            this.selectedOrder.primary_action = preservedWorkflow.primary_action ?? this.selectedOrder.primary_action;
            this.selectedOrder.secondary_actions = preservedWorkflow.secondary_actions ?? this.selectedOrder.secondary_actions;
            this.selectedOrder.terminal_message = preservedWorkflow.terminal_message ?? this.selectedOrder.terminal_message;
            this.syncDraftItems();
        },

        selectNextOrder({ incomingOrders = [], removedIndex = -1, preferFirst = false, selectedNewOrderId = null } = {}) {
            if (incomingOrders.length === 0) {
                this.activeId = null;
                this.selectedOrder = null;
                this.draftItems = [];
                return null;
            }

            let nextOrder = null;

            if (preferFirst) {
                nextOrder = selectedNewOrderId !== null
                    ? incomingOrders.find((order) => Number(order.id) === Number(selectedNewOrderId)) ?? incomingOrders[0]
                    : incomingOrders[0];
            } else {
                const nextIndex = removedIndex >= 0 ? Math.min(removedIndex, incomingOrders.length - 1) : 0;
                nextOrder = incomingOrders[nextIndex] ?? incomingOrders[0];
            }

            if (nextOrder) {
                this.select(nextOrder.id, nextOrder);
            }

            return nextOrder;
        },

        showNotification(order) {
            if (!order) {
                return;
            }

            this.liveToast = {
                visible: true,
                title: 'New order received',
                customer: order.customer_name ?? 'Sin cliente',
                elapsed: 'A few seconds ago',
            };

            if (this.liveToastTimer !== null) {
                window.clearTimeout(this.liveToastTimer);
            }

            this.liveToastTimer = window.setTimeout(() => {
                this.liveToast.visible = false;
            }, 4000);
        },

        playSound() {
            if (this.isLiveMuted() || !this.liveQueue?.audioUnlocked) {
                return;
            }

            this.liveQueue.playSound();
        },

        setLiveMuted(isMuted) {
            this.liveQueue?.setMuted(isMuted);
            this.soundMuted = Boolean(isMuted);
        },

        isLiveMuted() {
            return this.soundMuted || this.liveQueue?.soundMuted || false;
        },

        toggleLiveSound() {
            this.setLiveMuted(!this.isLiveMuted());
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

        async refreshSelectedOrderSnapshot() {
            if (this.activeId === null) {
                return;
            }

            this.drawerLoading = true;
            this.drawerError = '';
            const requestId = ++this.snapshotRequestId;

            try {
                const response = await fetch(`${this.snapshotUrlBase}/${this.activeId}/snapshot`, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    throw new Error('Snapshot unavailable');
                }

                const payload = await response.json();

                if (requestId !== this.snapshotRequestId) {
                    return;
                }

                const normalized = normalizeOrder(payload, this.ordersBaseUrl);
                this.selectedOrder = normalized;
                this.orderDetails[normalized.id] = normalized;
                this.updateSelectedOrderInOrders(normalized);
                this.syncDraftItems();
            } catch (error) {
                this.drawerError = 'Unable to load order details.';
            } finally {
                if (requestId === this.snapshotRequestId) {
                    this.drawerLoading = false;
                }
            }
        },

        updateSelectedOrderInOrders(order) {
            const inboxOrder = this.orders.find((item) => Number(item.id) === Number(order.id));
            if (inboxOrder) {
                Object.assign(inboxOrder, order);
            }
        },

        async submitWorkflowAction(action) {
            if (!action || !action.url) {
                return;
            }

            if (action.method === 'GET') {
                window.location.href = action.url;
                return;
            }

            if (action.requires_confirmation && !window.confirm('This action will change the order status. Continue?')) {
                return;
            }

            this.submittingActionKey = action.key;

            try {
                const formData = new FormData();
                formData.append('_token', this.csrfToken);

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

                if (!response.ok) {
                    const message = response.status === 422
                        ? 'This action is no longer available.'
                        : payload?.message ?? 'Unable to complete the action.';

                    this.showToast(message, 'error');
                    return;
                }

                if (payload?.order) {
                    this.applyOrderUpdate(payload.order);
                    this.updateSelectedOrderFromResponse(payload.order);
                }

                this.showToast(payload?.message ?? 'Order updated.', 'success');
                void this.liveQueue?.refresh();
            } catch (error) {
                this.showToast('Unable to complete the action.', 'error');
            } finally {
                this.submittingActionKey = null;
            }
        },

        buildMetrics(orders) {
            const todayKey = this.referenceDate().toISOString().slice(0, 10);

            return {
                urgent_orders: orders.filter((order) => isUrgentOrder(order)).length,
                orders_today: orders.filter((order) => commitmentDateKey(order) === todayKey).length,
                deliveries: orders.filter((order) => String(order.delivery_method ?? '') === 'delivery').length,
                pickups: orders.filter((order) => String(order.delivery_method ?? '') === 'pickup').length,
                overdue: orders.filter((order) => commitmentDateKey(order) === todayKey && Number(order.remaining_sla_minutes ?? 0) < 0).length,
                completed_today: orders.filter((order) => String(order.status ?? '') === 'dispatched' && dispatchedDateKey(order) === todayKey).length,
            };
        },

        applyInitialFilters() {
            const url = new URL(window.location.href);
            const filters = this.filters;

            filters.search = url.searchParams.get('search') ?? '';
            filters.customer = url.searchParams.get('customer') ?? '';
            filters.channel = url.searchParams.get('channel') ?? 'all';
            filters.priority = url.searchParams.get('priority') ?? 'all';
            filters.time = url.searchParams.get('time') ?? 'all';
            filters.delivery = url.searchParams.get('delivery') ?? 'all';
            filters.payment = url.searchParams.get('payment') ?? 'all';
            filters.vip = url.searchParams.get('vip') === '1';
            filters.duplicates = url.searchParams.get('duplicates') === '1';
            filters.urgent = url.searchParams.get('urgent') === '1';
        },

        applyFilter(key, value) {
            this.filters[key] = value;
            this.syncUrlFromState();
        },

        setFilter(key, value) {
            this.filters[key] = value;
            this.syncUrlFromState();
        },

        toggleFilter(key) {
            this.filters[key] = !this.filters[key];
            this.syncUrlFromState();
        },

        clearFilters() {
            this.filters = { ...DEFAULT_FILTERS };
            this.syncUrlFromState();
        },

        syncUrlFromState({ preserveDrawer = false } = {}) {
            const url = new URL(window.location.href);
            const filters = this.filters;

            url.searchParams.delete('search');
            url.searchParams.delete('customer');
            url.searchParams.delete('channel');
            url.searchParams.delete('priority');
            url.searchParams.delete('time');
            url.searchParams.delete('delivery');
            url.searchParams.delete('payment');
            url.searchParams.delete('vip');
            url.searchParams.delete('duplicates');
            url.searchParams.delete('urgent');

            if (filters.search.trim() !== '') {
                url.searchParams.set('search', filters.search.trim());
            }

            if (filters.customer.trim() !== '') {
                url.searchParams.set('customer', filters.customer.trim());
            }

            if (filters.channel !== 'all') {
                url.searchParams.set('channel', filters.channel);
            }

            if (filters.priority !== 'all') {
                url.searchParams.set('priority', filters.priority);
            }

            if (filters.time !== 'all') {
                url.searchParams.set('time', filters.time);
            }

            if (filters.delivery !== 'all') {
                url.searchParams.set('delivery', filters.delivery);
            }

            if (filters.payment !== 'all') {
                url.searchParams.set('payment', filters.payment);
            }

            if (filters.vip) {
                url.searchParams.set('vip', '1');
            }

            if (filters.duplicates) {
                url.searchParams.set('duplicates', '1');
            }

            if (filters.urgent) {
                url.searchParams.set('urgent', '1');
            }

            if (preserveDrawer && this.drawerOpen && this.activeId !== null) {
                url.searchParams.set('order', String(this.activeId));
            } else if (!this.drawerOpen) {
                url.searchParams.delete('order');
            }

            window.history.replaceState({}, '', url);
        },

        matchesFilters(order) {
            if (!order) {
                return false;
            }

            if (this.filters.time !== 'all') {
                const today = this.referenceDate().toISOString().slice(0, 10);
                const tomorrow = new Date(this.referenceDate().getTime() + (24 * 60 * 60 * 1000)).toISOString().slice(0, 10);
                const commitmentDate = commitmentDateKey(order);

                if (this.filters.time === 'today' && commitmentDate !== today) {
                    return false;
                }

                if (this.filters.time === 'tomorrow' && commitmentDate !== tomorrow) {
                    return false;
                }

                if (this.filters.time === 'no_commitment' && commitmentDate !== '') {
                    return false;
                }
            }

            if (this.filters.delivery !== 'all' && String(order.delivery_method ?? '') !== this.filters.delivery) {
                return false;
            }

            if (this.filters.payment !== 'all' && String(order.payment_method ?? '') !== this.filters.payment) {
                return false;
            }

            if (this.filters.channel !== 'all' && String(order.channel_key ?? '').toLowerCase() !== this.filters.channel) {
                return false;
            }

            if (this.filters.priority === 'urgent' && !isUrgentOrder(order)) {
                return false;
            }

            if (this.filters.priority === 'normal' && isUrgentOrder(order)) {
                return false;
            }

            if (this.filters.vip && !order.vip) {
                return false;
            }

            if (this.filters.duplicates && !order.duplicate) {
                return false;
            }

            if (this.filters.urgent && !isUrgentOrder(order)) {
                return false;
            }

            const customerTerm = normalizeText(this.filters.customer.trim());
            if (customerTerm !== '') {
                const customerHaystack = normalizeText([order.customer_name, order.customer_phone].join(' '));
                if (!customerHaystack.includes(customerTerm)) {
                    return false;
                }
            }

            const searchTerm = normalizeText(this.filters.search.trim());
            if (searchTerm !== '') {
                const haystack = normalizeText([
                    order.customer_name,
                    order.customer_phone,
                    order.branch_name,
                    order.preview,
                    order.channel,
                    order.status_label,
                    `#${order.id}`,
                ].join(' '));

                if (!haystack.includes(searchTerm)) {
                    return false;
                }
            }

            return true;
        },

        channelPill(order) {
            return buildChannelPill(order);
        },

        workflowActionLabel(order) {
            return this.workflowLabel(order);
        },

        agendaDeliveryLabel(order) {
            return ({
                pickup: 'Pickup',
                delivery: 'Delivery',
                express: 'Express',
                third_party: 'Third party',
            })[String(order.delivery_method ?? '')] ?? 'No delivery';
        },

        agendaPaymentLabel(order) {
            return ({
                sinpe: 'SINPE',
                cash: 'Cash',
                card: 'Card',
                transfer: 'Transfer',
            })[String(order.payment_method ?? '')] ?? 'No payment';
        },

        agendaTimeWindowLabel(order) {
            return timeWindowLabel(order);
        },

        agendaSummary(order) {
            return order.summary ?? order.preview ?? 'Sin resumen';
        },

        agendaRiskLabel(order) {
            const risk = String(order.risk_level ?? '');

            return ({
                critical: 'SLA expired',
                high: order.remaining_sla_minutes !== null && order.remaining_sla_minutes !== undefined
                    ? `Due in ${Number(order.remaining_sla_minutes)} min`
                    : 'High risk',
                medium: 'Medium',
                low: 'Normal',
            })[risk] ?? 'Normal';
        },

        agendaRiskTone(order) {
            const risk = String(order.risk_level ?? '');

            return ({
                critical: 'bg-rose-50 text-rose-800 ring-1 ring-rose-100',
                high: 'bg-orange-50 text-orange-800 ring-1 ring-orange-100',
                medium: 'bg-amber-50 text-amber-800 ring-1 ring-amber-100',
                low: 'bg-slate-100 text-slate-700 ring-1 ring-slate-200',
            })[risk] ?? 'bg-slate-100 text-slate-700 ring-1 ring-slate-200';
        },

        agendaPriorityTone(order) {
            if (String(order.risk_level ?? '') === 'critical' || String(order.risk_level ?? '') === 'high') {
                return 'bg-amber-50 text-amber-800 ring-1 ring-amber-100';
            }

            if (String(order.priority_level ?? '') === 'urgent') {
                return 'bg-orange-50 text-orange-800 ring-1 ring-orange-100';
            }

            if (String(order.priority_level ?? '') === 'low') {
                return 'bg-slate-100 text-slate-700 ring-1 ring-slate-200';
            }

            return 'bg-blue-50 text-blue-800 ring-1 ring-blue-100';
        },

        agendaPriorityLabel(order) {
            if (String(order.risk_level ?? '') === 'critical' || String(order.risk_level ?? '') === 'high') {
                return 'Urgent';
            }

            if (String(order.priority_level ?? '') === 'low') {
                return 'Low';
            }

            return 'Normal';
        },

        agendaChannelGlyph(order) {
            return buildChannelPill(order).glyph;
        },

        agendaSmartLabels(order) {
            const labels = [
                this.agendaDeliveryLabel(order),
                this.agendaPaymentLabel(order),
                this.agendaTimeWindowLabel(order),
                order.vip ? 'VIP' : null,
                order.duplicate ? 'Duplicate' : null,
            ];

            return labels.filter((label) => label && label !== 'No delivery' && label !== 'No payment' && label !== 'No commitment');
        },

        cardAccentClass(order) {
            const risk = String(order.risk_level ?? '');

            if (risk === 'critical') {
                return 'border-l-rose-500';
            }

            if (risk === 'high' || isUrgentOrder(order)) {
                return 'border-l-orange-400';
            }

            return 'border-l-blue-500';
        },

        formatLongDate(date) {
            if (!date) {
                return 'No date';
            }

            const parsed = date instanceof Date ? date : new Date(date);
            if (Number.isNaN(parsed.getTime())) {
                return 'No date';
            }

            return new Intl.DateTimeFormat('en-US', {
                weekday: 'long',
                month: 'long',
                day: 'numeric',
                year: 'numeric',
            }).format(parsed).replace(',', '');
        },

        elapsedLabel(date) {
            if (!date) {
                return 'No date';
            }

            const parsed = date instanceof Date ? date : new Date(date);
            if (Number.isNaN(parsed.getTime())) {
                return 'No date';
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

            if (totalOrders >= 20) {
                return 'VIP';
            }

            if (totalOrders >= 3) {
                return 'Frequent';
            }

            return 'New';
        },

        favoriteProducts(history) {
            const groups = new Map();

            for (const order of history) {
                for (const item of order.items ?? []) {
                    const key = item.product_id !== null && item.product_id !== undefined
                        ? `product:${item.product_id}`
                        : `raw:${normalizeText(item.raw_text ?? '') || 'sin-texto'}`;

                    if (!groups.has(key)) {
                        groups.set(key, {
                            label: this.resolveItemLabel(item.product_id, item.raw_text, item.product_name ?? null),
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

        playSound() {
            if (this.isLiveMuted() || !this.liveQueue?.audioUnlocked) {
                return;
            }

            this.liveQueue.playSound();
        },
    };
}
