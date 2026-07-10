const DEFAULT_POLL_INTERVAL_MS = 8000;
const MUTED_STORAGE_KEY = 'benditio.operations.live-queue.muted';

export class LiveQueue {
    constructor(component, options = {}) {
        this.component = component;
        this.feedUrl = options.feedUrl ?? '/operations/feed';
        this.ordersBaseUrl = options.ordersBaseUrl ?? '/orders';
        this.pollIntervalMs = Number(options.pollIntervalMs ?? DEFAULT_POLL_INTERVAL_MS);
        this.timer = null;
        this.retryTimer = null;
        this.inFlight = false;
        this.latestOrderId = Number(options.latestOrderId ?? 0);
        this.audioContext = null;
        this.audioUnlocked = false;
        this.unlockHandler = null;
        this.visibilityHandler = null;
        this.soundMuted = this.readMutedPreference();
    }

    start() {
        if (this.timer !== null) {
            return;
        }

        this.bindAudioUnlock();
        this.updateConnectionState('live');
        this.timer = window.setInterval(() => {
            void this.refresh();
        }, this.pollIntervalMs);
        void this.refresh();
    }

    stop() {
        if (this.timer !== null) {
            window.clearInterval(this.timer);
            this.timer = null;
        }

        if (this.retryTimer !== null) {
            window.clearTimeout(this.retryTimer);
            this.retryTimer = null;
        }

        this.unbindAudioUnlock();
    }

    async refresh() {
        if (this.inFlight) {
            return null;
        }

        this.inFlight = true;

        try {
            const response = await fetch(this.buildFeedRequestUrl(), {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                this.handleConnectionFailure(response.status);
                return null;
            }

            const payload = await response.json();
            const diff = this.applyDiff(payload);
            this.latestOrderId = Number(payload.latest_order_id ?? this.latestOrderId);
            this.updateConnectionState('live');

            if (this.retryTimer !== null) {
                window.clearTimeout(this.retryTimer);
                this.retryTimer = null;
            }

            return diff;
        } catch (error) {
            this.handleConnectionFailure();
            return null;
        } finally {
            this.inFlight = false;
        }
    }

    applyDiff(payload) {
        const incomingOrders = Array.isArray(payload?.inbox)
            ? payload.inbox.map((order) => this.normalizeOrder(order))
            : [];
        const existingOrders = Array.isArray(this.component.orders) ? this.component.orders : [];
        const activeId = this.component.activeId ?? null;
        const diff = this.diffOrders(existingOrders, incomingOrders);

        this.component.orders = diff.orders;
        this.component.counts = {
            ...this.component.counts,
            ...(payload?.counts ?? {}),
        };
        this.component.serverTime = payload?.server_time ?? this.component.serverTime;

        const activeExists = activeId !== null && incomingOrders.some((order) => order.id === activeId);
        const activeWasRemoved = activeId !== null && !activeExists && existingOrders.some((order) => order.id === activeId);

        if (activeExists) {
            const updatedActive = incomingOrders.find((order) => order.id === activeId);
            if (updatedActive) {
                this.syncSelectedOrder(updatedActive);
            }
        } else if (activeWasRemoved && incomingOrders.length > 0) {
            this.selectNextOrder({
                incomingOrders,
                removedIndex: existingOrders.findIndex((order) => order.id === activeId),
            });
        } else if (activeId === null && diff.newOrders.length > 0) {
            this.selectNextOrder({
                incomingOrders,
                selectedNewOrderId: diff.newOrders[0]?.id ?? null,
                preferFirst: true,
            });
        }

        if (diff.newOrders.length > 0) {
            this.showNotification(diff.newOrders[0]);
            this.playSound();
            this.flashOrders(diff.newOrders.map((order) => order.id));
        }

        return diff;
    }

    diffOrders(existingOrders, incomingOrders) {
        const existingById = new Map(existingOrders.map((order) => [Number(order.id), order]));
        const seenIds = new Set();
        const newOrders = [];
        const changedOrders = [];
        const orders = incomingOrders.map((incomingOrder) => {
            const existingOrder = existingById.get(Number(incomingOrder.id));

            if (!existingOrder) {
                newOrders.push(incomingOrder);
                seenIds.add(Number(incomingOrder.id));
                return { ...incomingOrder };
            }

            seenIds.add(Number(incomingOrder.id));

            if (this.hasOrderChanged(existingOrder, incomingOrder)) {
                changedOrders.push(incomingOrder);
                Object.assign(existingOrder, incomingOrder);
            }

            return existingOrder;
        });

        const removedIds = existingOrders
            .filter((order) => !seenIds.has(Number(order.id)))
            .map((order) => Number(order.id));

        return {
            orders,
            newOrders,
            changedOrders,
            removedIds,
        };
    }

    hasOrderChanged(existingOrder, incomingOrder) {
        return JSON.stringify(this.snapshotOrder(existingOrder)) !== JSON.stringify(this.snapshotOrder(incomingOrder));
    }

    snapshotOrder(order) {
        return {
            id: Number(order.id ?? 0),
            status: order.status ?? null,
            status_label: order.status_label ?? null,
            status_tone: order.status_tone ?? null,
            channel: order.channel ?? null,
            channel_key: order.channel_key ?? null,
            customer_name: order.customer_name ?? null,
            customer_phone: order.customer_phone ?? null,
            branch_name: order.branch_name ?? null,
            elapsed_label: order.elapsed_label ?? null,
            created_at_label: order.created_at_label ?? null,
            created_at_iso: order.created_at_iso ?? null,
            preview: order.preview ?? null,
            items_count: Number(order.items_count ?? 0),
            recognized_items_count: Number(order.recognized_items_count ?? 0),
            unread: Boolean(order.unread),
            duplicate: Boolean(order.duplicate),
            vip: Boolean(order.vip),
            parser_confidence: order.parser_confidence === null || order.parser_confidence === undefined
                ? null
                : Number(order.parser_confidence),
            update_url: order.update_url ?? null,
            show_url: order.show_url ?? null,
        };
    }

    normalizeOrder(order) {
        const normalizedId = Number(order?.id ?? 0);
        const workflow = this.resolveWorkflow(normalizedId, order?.status ?? 'pending_review');
        const normalized = {
            id: normalizedId,
            status: order?.status ?? 'pending_review',
            status_label: order?.status_label ?? this.resolveStatusLabel(order?.status ?? 'pending_review'),
            status_tone: order?.status_tone ?? this.resolveStatusTone(order?.status ?? 'pending_review'),
            channel: order?.channel ?? 'Sin canal',
            channel_key: order?.channel_key ?? '',
            customer_name: order?.customer_name ?? 'Sin cliente',
            customer_phone: order?.customer_phone ?? 'Sin telefono',
            branch_name: order?.branch_name ?? 'Sin sucursal',
            elapsed_label: order?.elapsed_label ?? 'Sin fecha',
            created_at_label: order?.created_at_label ?? 'Sin fecha',
            created_at_iso: order?.created_at_iso ?? null,
            preview: order?.preview ?? 'Sin mensaje original',
            items_count: Number(order?.items_count ?? 0),
            recognized_items_count: Number(order?.recognized_items_count ?? 0),
            unread: Boolean(order?.unread),
            duplicate: Boolean(order?.duplicate),
            vip: Boolean(order?.vip),
            parser_confidence: order?.parser_confidence === null || order?.parser_confidence === undefined
                ? null
                : Number(order.parser_confidence),
            update_url: order?.update_url ?? this.buildOrderUrl(normalizedId),
            show_url: order?.show_url ?? this.buildOrderShowUrl(normalizedId),
            items: Array.isArray(order?.items) ? order.items.map((item) => ({ ...item })) : [],
            customer_context: this.normalizeCustomerContext(
                {
                    id: normalizedId,
                    status: order?.status ?? 'pending_review',
                    status_label: order?.status_label ?? this.resolveStatusLabel(order?.status ?? 'pending_review'),
                    channel: order?.channel ?? 'Sin canal',
                    elapsed_label: order?.elapsed_label ?? 'Sin fecha',
                    preview: order?.preview ?? 'Sin mensaje original',
                    duplicate: Boolean(order?.duplicate),
                    customer_name: order?.customer_name ?? 'Sin cliente',
                    customer_phone: order?.customer_phone ?? 'Sin telefono',
                    parser_confidence: order?.parser_confidence === null || order?.parser_confidence === undefined
                        ? null
                        : Number(order.parser_confidence),
                },
                order?.customer_context ?? this.defaultCustomerContext(order?.customer_name, order?.customer_phone),
            ),
            primary_action: order?.primary_action ?? workflow.primary_action,
            secondary_actions: order?.secondary_actions ?? workflow.secondary_actions,
            terminal_message: order?.terminal_message ?? workflow.terminal_message,
        };

        return normalized;
    }

    normalizeCustomerContext(order, customerContext) {
        const context = {
            name: customerContext?.name ?? order.customer_name ?? 'Sin cliente',
            phone: customerContext?.phone ?? order.customer_phone ?? 'Sin telefono',
            total_orders: Number(customerContext?.total_orders ?? 0),
            favorite_products: Array.isArray(customerContext?.favorite_products) ? customerContext.favorite_products : [],
            favorite_channel: customerContext?.favorite_channel ?? { name: 'Unknown', percentage: 0 },
            last_order: customerContext?.last_order ?? null,
            segment: customerContext?.segment ?? 'Inactive',
            open_notifications: Number(customerContext?.open_notifications ?? 0),
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
    }

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
    }

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

        return alerts;
    }

    resolveWorkflow(orderId, status) {
        const base = `${this.ordersBaseUrl}/${orderId}`;

        switch (status) {
            case 'pending_review':
                return {
                    primary_action: this.action('confirm', 'Confirmar pedido', 'POST', `${base}/confirm`, 'primary'),
                    secondary_actions: [
                        this.action('reject', 'Rechazar', 'POST', `${base}/reject`, 'danger', true),
                        this.action('cancel', 'Cancelar', 'POST', `${base}/cancel`, 'danger', true),
                    ],
                    terminal_message: null,
                };
            case 'confirmed':
                return {
                    primary_action: this.action('prepare', 'Iniciar preparacion', 'POST', `${base}/prepare`, 'primary'),
                    secondary_actions: [
                        this.action('cancel', 'Cancelar', 'POST', `${base}/cancel`, 'danger', true),
                    ],
                    terminal_message: null,
                };
            case 'preparing':
                return {
                    primary_action: this.action('ready', 'Marcar listo', 'POST', `${base}/ready-for-dispatch`, 'primary'),
                    secondary_actions: [
                        this.action('cancel', 'Cancelar', 'POST', `${base}/cancel`, 'danger', true),
                    ],
                    terminal_message: null,
                };
            case 'ready_for_dispatch':
                return {
                    primary_action: this.action('dispatch', 'Despachar', 'POST', `${base}/dispatch`, 'primary'),
                    secondary_actions: [
                        this.action('cancel', 'Cancelar', 'POST', `${base}/cancel`, 'danger', true),
                    ],
                    terminal_message: null,
                };
            case 'dispatched':
                return {
                    primary_action: null,
                    secondary_actions: [
                        this.action('view_history', 'Ver historial', 'GET', base, 'secondary'),
                    ],
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
    }

    action(key, label, method, url, style, requiresConfirmation = false) {
        return {
            key,
            label,
            method,
            url,
            style,
            requires_confirmation: requiresConfirmation,
        };
    }

    resolveStatusLabel(status) {
        const map = {
            pending_review: 'Nuevos',
            confirmed: 'Confirmado',
            preparing: 'Preparando',
            ready_for_dispatch: 'Listo',
            dispatched: 'Despachado',
            cancelled: 'Cancelado',
            rejected: 'Rechazado',
        };

        return map[status] ?? this.titleCase(status);
    }

    resolveStatusTone(status) {
        const map = {
            pending_review: 'bg-amber-50 text-amber-800 ring-1 ring-amber-100',
            confirmed: 'bg-blue-50 text-blue-800 ring-1 ring-blue-100',
            preparing: 'bg-violet-50 text-violet-800 ring-1 ring-violet-100',
            ready_for_dispatch: 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-100',
            dispatched: 'bg-slate-100 text-slate-700 ring-1 ring-slate-200',
            cancelled: 'bg-red-50 text-red-800 ring-1 ring-red-100',
            rejected: 'bg-slate-100 text-slate-700 ring-1 ring-slate-200',
        };

        return map[status] ?? 'bg-slate-100 text-slate-700 ring-1 ring-slate-200';
    }

    titleCase(value) {
        return String(value ?? '')
            .replaceAll('_', ' ')
            .replace(/\b\w/g, (letter) => letter.toUpperCase());
    }

    selectNextOrder({ incomingOrders = [], removedIndex = -1, preferFirst = false, selectedNewOrderId = null } = {}) {
        if (incomingOrders.length === 0) {
            this.component.activeId = null;
            this.component.selectedOrder = null;
            this.component.draftItems = [];
            return null;
        }

        let nextOrder = null;

        if (preferFirst) {
            nextOrder = selectedNewOrderId !== null
                ? incomingOrders.find((order) => order.id === selectedNewOrderId) ?? incomingOrders[0]
                : incomingOrders[0];
        } else {
            const nextIndex = removedIndex >= 0 ? Math.min(removedIndex, incomingOrders.length - 1) : 0;
            nextOrder = incomingOrders[nextIndex] ?? incomingOrders[0];
        }

        if (nextOrder) {
            this.component.select(nextOrder.id, nextOrder);
        }

        return nextOrder;
    }

    showNotification(order) {
        if (!order) {
            return;
        }

        const customerName = order.customer_name ?? 'Sin cliente';

        this.component.liveToast = {
            visible: true,
            title: 'Nuevo pedido recibido',
            customer: customerName,
            elapsed: 'Hace unos segundos',
        };

        if (this.component.liveToastTimer !== null) {
            window.clearTimeout(this.component.liveToastTimer);
        }

        this.component.liveToastTimer = window.setTimeout(() => {
            this.component.liveToast.visible = false;
        }, 4000);
    }

    playSound() {
        if (this.soundMuted || !this.audioUnlocked) {
            return;
        }

        const AudioContextImpl = window.AudioContext ?? window.webkitAudioContext;
        if (!AudioContextImpl) {
            return;
        }

        if (!this.audioContext) {
            this.audioContext = new AudioContextImpl();
        }

        const context = this.audioContext;
        if (context.state === 'suspended') {
            void context.resume();
        }

        const oscillator = context.createOscillator();
        const gain = context.createGain();

        oscillator.type = 'sine';
        oscillator.frequency.value = 880;
        gain.gain.value = 0.0001;

        oscillator.connect(gain);
        gain.connect(context.destination);

        const startedAt = context.currentTime;
        gain.gain.exponentialRampToValueAtTime(0.016, startedAt + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.0001, startedAt + 0.14);

        oscillator.start(startedAt);
        oscillator.stop(startedAt + 0.16);
    }

    setMuted(isMuted) {
        this.soundMuted = Boolean(isMuted);

        if (this.soundMuted) {
            localStorage.setItem(MUTED_STORAGE_KEY, '1');
        } else {
            localStorage.removeItem(MUTED_STORAGE_KEY);
        }
    }

    readMutedPreference() {
        return localStorage.getItem(MUTED_STORAGE_KEY) === '1';
    }

    bindAudioUnlock() {
        this.unlockHandler = () => {
            this.audioUnlocked = true;

            if (this.audioContext && this.audioContext.state === 'suspended') {
                void this.audioContext.resume();
            }
        };

        this.visibilityHandler = () => {
            if (document.visibilityState === 'visible') {
                void this.refresh();
            }
        };

        document.addEventListener('pointerdown', this.unlockHandler, { passive: true, once: true });
        document.addEventListener('keydown', this.unlockHandler, { passive: true, once: true });
        document.addEventListener('touchstart', this.unlockHandler, { passive: true, once: true });
        document.addEventListener('visibilitychange', this.visibilityHandler);
    }

    unbindAudioUnlock() {
        if (this.unlockHandler) {
            document.removeEventListener('pointerdown', this.unlockHandler);
            document.removeEventListener('keydown', this.unlockHandler);
            document.removeEventListener('touchstart', this.unlockHandler);
            this.unlockHandler = null;
        }

        if (this.visibilityHandler) {
            document.removeEventListener('visibilitychange', this.visibilityHandler);
            this.visibilityHandler = null;
        }
    }

    buildFeedRequestUrl() {
        const url = new URL(this.feedUrl, window.location.origin);
        const params = new URLSearchParams(window.location.search);
        params.delete('order');

        for (const [key, value] of params.entries()) {
            url.searchParams.set(key, value);
        }

        return url.toString();
    }

    handleConnectionFailure(status = null) {
        if (status !== null && status >= 500) {
            this.updateConnectionState('offline');
        } else if (!navigator.onLine) {
            this.updateConnectionState('offline');
        } else {
            this.updateConnectionState('reconnecting');
        }

        if (this.retryTimer !== null) {
            window.clearTimeout(this.retryTimer);
        }

        this.retryTimer = window.setTimeout(() => {
            void this.refresh();
        }, 4000);
    }

    updateConnectionState(state) {
        const variants = {
            live: {
                state: 'live',
                label: 'Live',
                tone: 'green',
                description: 'Conectado',
            },
            reconnecting: {
                state: 'reconnecting',
                label: 'Reconectando...',
                tone: 'yellow',
                description: 'Reintentando',
            },
            offline: {
                state: 'offline',
                label: 'Sin conexion',
                tone: 'red',
                description: 'Servidor no disponible',
            },
        };

        this.component.liveConnection = variants[state] ?? variants.reconnecting;
    }

    syncSelectedOrder(updatedOrder) {
        if (!this.component.selectedOrder) {
            this.component.selectedOrder = this.normalizeOrder(updatedOrder);
            this.component.syncDraftItems();
            return;
        }

        const preservedItems = Array.isArray(this.component.selectedOrder.items)
            ? this.component.selectedOrder.items.map((item) => ({ ...item }))
            : [];
        const preservedContext = this.component.selectedOrder.customer_context
            ?? this.defaultCustomerContext(this.component.selectedOrder.customer_name, this.component.selectedOrder.customer_phone);
        const { customer_context: _customerContext, items: _items, ...rest } = updatedOrder;

        Object.assign(this.component.selectedOrder, rest);
        this.component.selectedOrder.items = preservedItems;
        const workflow = this.resolveWorkflow(this.component.selectedOrder.id, this.component.selectedOrder.status);
        this.component.selectedOrder.primary_action = workflow.primary_action;
        this.component.selectedOrder.secondary_actions = workflow.secondary_actions;
        this.component.selectedOrder.terminal_message = workflow.terminal_message;
        this.component.selectedOrder.customer_context = this.normalizeCustomerContext(
            this.component.selectedOrder,
            preservedContext,
        );
        this.component.syncDraftItems();
    }

    flashOrders(orderIds) {
        this.component.flashOrderIds = Array.isArray(orderIds) ? orderIds : [];

        if (this.component.flashTimer !== null) {
            window.clearTimeout(this.component.flashTimer);
        }

        this.component.flashTimer = window.setTimeout(() => {
            this.component.flashOrderIds = [];
            this.component.flashTimer = null;
        }, 4000);
    }
}
