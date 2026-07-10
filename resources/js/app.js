import './bootstrap';

import Alpine from 'alpinejs';
import { LiveQueue } from './live-queue';
import { createOperationsBoard, createOperationsCard, createOperationsColumn } from './operations-board';

window.Alpine = Alpine;

window.operationsCenter = (config) => ({
    orders: config.orders ?? [],
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
    orderDetails: config.orderDetails ?? {},
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
        this.liveQueue = new LiveQueue(this, {
            feedUrl: this.feedUrl,
            ordersBaseUrl: this.ordersBaseUrl,
            pollIntervalMs: this.pollIntervalMs,
            latestOrderId: this.orders[0]?.id ?? 0,
        });

        if (this.activeId === null && this.orders.length > 0) {
            this.activeId = this.orders[0].id;
        }

        if (!this.selectedOrder && this.activeId !== null) {
            this.selectedOrder = this.orderDetails[this.activeId] ?? this.orders[0] ?? null;
        }

        if (this.selectedOrder) {
            this.selectedOrder = this.decorateSelectedOrder(this.selectedOrder);
        }

        this.syncDraftItems();
        this.liveQueue.start();
    },

    destroy() {
        this.liveQueue?.stop();
    },

    get activeOrder() {
        return this.selectedOrder;
    },

    select(orderId, order = null) {
        this.activeId = orderId;

        const nextOrder = order ?? this.orderDetails[orderId] ?? this.orders.find((item) => item.id === orderId) ?? null;
        this.selectedOrder = nextOrder ? this.decorateSelectedOrder(nextOrder) : null;
        this.syncDraftItems();

        const nextUrl = new URL(window.location.href);
        nextUrl.searchParams.set('order', orderId);
        window.history.replaceState({}, '', nextUrl);
    },

    syncDraftItems() {
        this.draftItems = (this.activeOrder?.items ?? []).map((item) => ({ ...item }));
    },

    decorateSelectedOrder(order) {
        const normalized = { ...order };

        if (!normalized.customer_context) {
            normalized.customer_context = {
                name: normalized.customer_name ?? 'Sin cliente',
                phone: normalized.customer_phone ?? 'Sin telefono',
                total_orders: 0,
                favorite_products: [],
                favorite_channel: { name: 'Unknown', percentage: 0 },
                last_order: null,
                segment: 'Inactive',
                open_notifications: 0,
                recent_activity: [],
                current_order: {
                    id: normalized.id,
                    status: normalized.status_label ?? normalized.status ?? 'Sin estado',
                    channel: normalized.channel ?? 'Sin canal',
                    elapsed: normalized.elapsed_label ?? 'Sin fecha',
                    preview: normalized.preview ?? 'Sin mensaje original',
                    possible_duplicate: Boolean(normalized.duplicate),
                },
                current_alerts: [],
            };
        }

        if (!Array.isArray(normalized.items)) {
            normalized.items = [];
        }

        if (!normalized.update_url) {
            normalized.update_url = `${this.ordersBaseUrl}/${normalized.id}`;
        }

        if (!normalized.show_url) {
            normalized.show_url = `${this.ordersBaseUrl}/${normalized.id}`;
        }

        if (!normalized.primary_action && !normalized.secondary_actions && !normalized.terminal_message) {
            const workflow = this.buildWorkflow(normalized.id, normalized.status);
            normalized.primary_action = workflow.primary_action;
            normalized.secondary_actions = workflow.secondary_actions;
            normalized.terminal_message = workflow.terminal_message;
        }

        return normalized;
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
        if (!order) {
            return;
        }

        const normalized = this.decorateSelectedOrder({
            ...order,
            items: this.activeOrder?.items ?? order.items ?? [],
            customer_context: this.activeOrder?.customer_context ?? null,
        });

        this.selectedOrder = normalized;
        this.orderDetails[normalized.id] = normalized;
        this.syncDraftItems();
    },

    applyOrderUpdate(updatedOrder) {
        if (!updatedOrder) {
            return;
        }

        const normalized = this.decorateSelectedOrder(updatedOrder);
        this.orderDetails[normalized.id] = normalized;

        const inboxOrder = this.orders.find((order) => order.id === normalized.id);
        if (inboxOrder) {
            Object.assign(inboxOrder, {
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
        const nextMuted = !this.isLiveMuted();
        this.setLiveMuted(nextMuted);
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
        if (!action || !action.url) {
            return;
        }

        if (action.method === 'GET') {
            window.location.href = action.url;
            return;
        }

        if (action.requires_confirmation && !window.confirm('Esta accion modificara el estado del pedido. Deseas continuar?')) {
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

            if (!response.ok) {
                const message = response.status === 422
                    ? 'Esta accion ya no esta disponible para este pedido.'
                    : payload?.message ?? 'No se pudo completar la accion.';

                this.showToast(message, 'error');
                return;
            }

            if (payload?.order) {
                this.applyOrderUpdate(payload.order);
                this.updateSelectedOrderFromResponse(payload.order);
                this.syncDraftItems();
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
});

window.operationsBoard = createOperationsBoard;
window.operationsColumn = createOperationsColumn;
window.operationsCard = createOperationsCard;
window.operationsCenter = createOperationsBoard;

Alpine.start();
