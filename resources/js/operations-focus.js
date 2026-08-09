import { LiveQueue } from './live-queue';

const DEFAULT_POLL_INTERVAL_MS = 8000;

function toNumber(value, fallback = 0) {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : fallback;
}

function normalizeQueueData(queueData) {
    return {
        sections: Array.isArray(queueData?.sections) ? queueData.sections : [],
        metrics: queueData?.metrics ?? {},
    };
}

function normalizeOrder(order) {
    if (!order) {
        return null;
    }

    const normalized = { ...order };
    normalized.id = toNumber(normalized.id);
    normalized.items = Array.isArray(normalized.items) ? normalized.items.map((item) => ({ ...item })) : [];
    normalized.notification_history = Array.isArray(normalized.notification_history)
        ? normalized.notification_history.map((item) => ({ ...item }))
        : [];
    normalized.primary_action = normalized.primary_action ?? null;
    normalized.secondary_actions = Array.isArray(normalized.secondary_actions) ? normalized.secondary_actions : [];
    normalized.terminal_message = normalized.terminal_message ?? null;
    normalized.fulfillment_plan = normalized.fulfillment_plan ?? null;
    normalized.parser_details = normalized.parser_details ?? null;
    normalized.duplicate_analysis = normalized.duplicate_analysis ?? null;
    normalized.customer_context = normalized.customer_context ?? null;
    normalized.update_url = normalized.update_url ?? null;
    normalized.show_url = normalized.show_url ?? null;

    return normalized;
}

function formatTime(time) {
    const match = String(time ?? '').match(/^(\d{2}):(\d{2})/);
    return match ? `${match[1]}:${match[2]}` : 'Sin hora definida';
}

function channelLabel(channel) {
    return ({
        whatsapp: 'WhatsApp',
        telegram: 'Telegram',
        instagram: 'Instagram',
    })[String(channel ?? '').toLowerCase()] ?? 'Otro';
}

function deliveryLabel(method) {
    return ({
        pickup: 'Retiro en tienda',
        delivery: 'Entrega',
        express: 'Express',
    })[String(method ?? '')] ?? 'Por confirmar';
}

function paymentLabel(method) {
    return ({
        sinpe: 'SINPE',
        cash: 'Efectivo',
        transfer: 'Transferencia',
        card: 'Tarjeta',
        credit: 'Crédito',
    })[String(method ?? '')] ?? 'Por confirmar';
}

function priorityLabel(order) {
    const risk = String(order?.risk_level ?? '');
    const priority = String(order?.priority_level ?? '');
    const remaining = order?.remaining_sla_minutes;
    const commitmentDate = String(order?.commitment_date ?? '');
    const today = new Date().toISOString().slice(0, 10);

    if (risk === 'critical' || risk === 'high' || priority === 'urgent' || (remaining !== null && remaining !== undefined && Number(remaining) < 0)) {
        return 'Urgente';
    }

    if (commitmentDate === today) {
        return 'Para hoy';
    }

    if (commitmentDate === '') {
        return 'Sin fecha';
    }

    return 'Próximo';
}

function commitmentLabel(order) {
    const commitmentDate = String(order?.commitment_date ?? '');
    const commitmentTime = String(order?.commitment_time ?? '');
    const today = new Date().toISOString().slice(0, 10);
    const tomorrow = new Date(Date.now() + (24 * 60 * 60 * 1000)).toISOString().slice(0, 10);

    if (commitmentDate === '') {
        return 'Sin fecha confirmada';
    }

    if (commitmentDate === today) {
        if (commitmentTime === '') {
            return 'Hoy sin hora definida';
        }

        return Number(String(commitmentTime).slice(0, 2)) < 14
            ? 'Hoy antes de las 2:00 p. m.'
            : 'Hoy sin hora definida';
    }

    if (commitmentDate === tomorrow) {
        return 'Mañana por la mañana';
    }

    return 'Sin fecha confirmada';
}

function buildItemLines(items) {
    return (Array.isArray(items) ? items : []).map((item) => {
        const quantity = Number(item?.quantity ?? 1);
        const qty = Number.isFinite(quantity) ? quantity : 1;
        const name = String(item?.name ?? item?.product_name ?? item?.raw_text ?? 'Sin descripción');
        const parts = [`${qty} x ${name}`];

        if (String(item?.unit ?? '').trim() !== '') {
            parts.push(String(item.unit).trim());
        }

        if (String(item?.notes ?? '').trim() !== '') {
            parts.push(String(item.notes).trim());
        }

        return {
            id: item?.id ?? null,
            text: parts.join(' · '),
            quantity: qty,
            quantity_label: String(qty),
            name,
            unit: String(item?.unit ?? '').trim() !== '' ? String(item.unit).trim() : null,
            notes: String(item?.notes ?? '').trim() !== '' ? String(item.notes).trim() : null,
        };
    });
}

function findSection(queueData, key) {
    return Array.isArray(queueData?.sections)
        ? queueData.sections.find((section) => section?.key === key) ?? null
        : null;
}

function upcomingQueueOrders(queueData) {
    const nextSection = findSection(queueData, 'next');
    const groups = Array.isArray(nextSection?.groups) ? nextSection.groups : [];

    return groups.flatMap((group) => Array.isArray(group?.cards) ? group.cards : []).slice(0, 5);
}

function completedOrders(orders, serverTime) {
    const today = new Date(serverTime ?? Date.now()).toISOString().slice(0, 10);

    return (Array.isArray(orders) ? orders : [])
        .filter((order) => String(order?.status ?? '') === 'dispatched' && String(order?.dispatched_at ?? '').slice(0, 10) === today)
        .sort((left, right) => Date.parse(right.dispatched_at ?? 0) - Date.parse(left.dispatched_at ?? 0))
        .slice(0, 5)
        .map((order) => decorateOrder(order))
        .filter(Boolean);
}

function urgentOrderToastOrders(orders) {
    return (Array.isArray(orders) ? orders : []).filter((order) => {
        const risk = String(order?.risk_level ?? '');
        const priority = String(order?.priority_level ?? '');
        const remaining = order?.remaining_sla_minutes;

        return risk === 'critical'
            || risk === 'high'
            || priority === 'urgent'
            || (remaining !== null && remaining !== undefined && Number(remaining) < 0);
    });
}

function formatDateLabel(dateValue) {
    const parsed = new Date(dateValue);
    if (Number.isNaN(parsed.getTime())) {
        return '';
    }

    return new Intl.DateTimeFormat('es-ES', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    }).format(parsed);
}

function focusStageLabel(status) {
    return ({
        pending_review: 'REVISAR',
        confirmed: 'RECOMENDADO',
        preparing: 'PREPARANDO',
        ready_for_dispatch: 'LISTO',
        dispatched: 'COMPLETADO',
        cancelled: 'COMPLETADO',
        rejected: 'COMPLETADO',
    })[String(status ?? '')] ?? 'NO WORK';
}

function readyActionLabel(order) {
    return String(order?.delivery_method ?? '').toLowerCase() === 'pickup'
        ? 'Entregar al cliente'
        : 'Despachar pedido';
}

function recommendedActionLabel(order) {
    return ({
        pending_review: 'Revisar y confirmar',
        confirmed: 'Comenzar preparación',
        preparing: 'Seguir preparando',
        ready_for_dispatch: readyActionLabel(order),
        dispatched: 'Pedido completado',
        cancelled: 'Pedido finalizado',
        rejected: 'Pedido finalizado',
    })[String(order?.status ?? '')] ?? 'Abrir pedido';
}

function workspaceActionLabel(order) {
    return ({
        pending_review: 'Confirmar pedido',
        confirmed: 'Comenzar preparación',
        preparing: 'Marcar pedido como listo',
        ready_for_dispatch: readyActionLabel(order),
    })[String(order?.status ?? '')] ?? 'Continuar';
}

function buildFocusableOrder(order) {
    const normalized = normalizeOrder(order);

    if (!normalized) {
        return null;
    }

    const itemLines = buildItemLines(normalized.items);
    const stage = focusStageLabel(normalized.status);
    const workspaceLabel = workspaceActionLabel(normalized);

    return {
        ...normalized,
        focus_state: stage,
        focus_state_label: stage,
        recommended_action_label: recommendedActionLabel(normalized),
        workspace_action_label: workspaceLabel,
        order_number: `Pedido #${normalized.id}`,
        channel_label: channelLabel(normalized.source_channel ?? normalized.channel_key ?? null),
        priority_label: priorityLabel(normalized),
        commitment_label: commitmentLabel(normalized),
        delivery_label: deliveryLabel(normalized.delivery_method ?? null),
        payment_label: paymentLabel(normalized.payment_method ?? null),
        summary: String(normalized.summary ?? normalized.preview ?? 'Sin resumen'),
        item_lines: itemLines.slice(0, 3),
        more_items_label: itemLines.length > 3 ? `+ ${itemLines.length - 3} artículos más` : null,
        items_count_label: itemLines.length === 1 ? '1 artículo' : `${itemLines.length} artículos`,
        primary_action_label: workspaceLabel,
        has_items: itemLines.length > 0,
        fulfillment: {
            date: normalized.commitment_date ? formatDateLabel(normalized.commitment_date) : 'Sin fecha confirmada',
            time: formatTime(normalized.commitment_time),
            delivery: deliveryLabel(normalized.delivery_method ?? null),
            payment: paymentLabel(normalized.payment_method ?? null),
            address: normalized.delivery_method === 'pickup'
                ? (normalized.fulfillment_plan?.pickup_branch ?? 'Sucursal por confirmar')
                : (normalized.fulfillment_plan?.delivery_address ?? 'Sin dirección confirmada'),
        },
    };
}

export function createOperationsFocus(config = {}) {
    return {
        orders: Array.isArray(config.orders) ? config.orders.map((order) => normalizeOrder(order)).filter(Boolean) : [],
        queueData: normalizeQueueData(config.queueData ?? { sections: [], metrics: {} }),
        summary: {
            greeting: config.summary?.greeting ?? 'Buenos días',
            date: config.summary?.date ?? '',
            orders_today: Number(config.summary?.orders_today ?? 0),
            urgent_orders: Number(config.summary?.urgent_orders ?? 0),
            deliveries: Number(config.summary?.deliveries ?? 0),
            pickups: Number(config.summary?.pickups ?? 0),
            completed_today: Number(config.summary?.completed_today ?? 0),
        },
        serverTime: config.serverTime ?? new Date().toISOString(),
        recommendedOrderSeed: buildFocusableOrder(config.recommendedOrder ?? null),
        nextScheduledOrderSeed: buildFocusableOrder(config.nextScheduledOrder ?? null),
        visibleName: config.visibleName ?? 'Mi Jornada',
        mainQuestion: config.mainQuestion ?? '¿Qué debo hacer ahora?',
        activeId: null,
        selectedOrderId: null,
        selectedOrder: null,
        workspaceOpen: false,
        workspaceLoading: false,
        workspaceError: '',
        workspaceNotice: '',
        completionVisible: false,
        submittingActionKey: null,
        preparedItemStateByOrderId: {},
        toast: {
            visible: false,
            type: 'success',
            message: '',
        },
        toastTimer: null,
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
        liveQueue: null,
        csrfToken: document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        feedUrl: config.feedUrl ?? '/operations/feed',
        ordersBaseUrl: config.ordersBaseUrl ?? '/orders',
        snapshotUrlBase: config.snapshotUrlBase ?? '/operations/orders',
        pollIntervalMs: Number(config.pollIntervalMs ?? DEFAULT_POLL_INTERVAL_MS),

        init() {
            this.queueData = normalizeQueueData(this.queueData);
            this.orders = this.orders.map((order) => normalizeOrder(order)).filter(Boolean);
            this.syncSummary();
            this.liveQueue = new LiveQueue(this, {
                feedUrl: this.feedUrl,
                ordersBaseUrl: this.ordersBaseUrl,
                pollIntervalMs: this.pollIntervalMs,
                latestOrderId: this.orders[0]?.id ?? 0,
            });
            this.liveQueue.start();

            const requestedOrderId = Number(new URL(window.location.href).searchParams.get('order') ?? 0);
            if (requestedOrderId > 0) {
                void this.openOrder(requestedOrderId);
            }
        },

        destroy() {
            this.liveQueue?.stop();
        },

        get recommendedOrder() {
            const section = findSection(this.queueData, 'do_now');
            const card = Array.isArray(section?.cards) ? section.cards[0] ?? null : null;
            return buildFocusableOrder(card);
        },

        get nextScheduledOrder() {
            return this.upcomingOrders[0] ?? null;
        },

        get recommendedActionLabel() {
            return this.recommendedOrder?.recommended_action_label ?? 'Abrir pedido';
        },

        get workspaceStageLabel() {
            if (this.completionVisible || this.currentStatus === 'dispatched') {
                return 'COMPLETADO';
            }

            return focusStageLabel(this.currentStatus);
        },

        get currentStatus() {
            return String(this.selectedOrder?.status ?? this.recommendedOrder?.status ?? '');
        },

        get currentWorkspaceOrder() {
            return this.selectedOrder;
        },

        get currentPrimaryAction() {
            return this.currentWorkspaceOrder?.primary_action ?? null;
        },

        get currentPrimaryActionLabel() {
            if (this.completionVisible || this.currentStatus === 'dispatched') {
                return 'Continuar con el siguiente pedido';
            }

            return this.currentWorkspaceOrder?.workspace_action_label
                ?? workspaceActionLabel(this.currentWorkspaceOrder ?? this.recommendedOrder ?? null);
        },

        get currentItems() {
            return buildItemLines(this.currentWorkspaceOrder?.items ?? []);
        },

        get checkedCount() {
            const orderId = this.currentWorkspaceOrder?.id;
            const state = orderId ? this.preparedItemStateByOrderId[orderId] ?? {} : {};

            return this.currentItems.filter((item) => Boolean(state[item.id])).length;
        },

        get totalCheckedableItems() {
            return this.currentItems.length;
        },

        get preparationProgressLabel() {
            return `${this.checkedCount} de ${this.totalCheckedableItems} artículos revisados`;
        },

        get preparationProgressPercent() {
            if (this.totalCheckedableItems === 0) {
                return 0;
            }

            return Math.round((this.checkedCount / this.totalCheckedableItems) * 100);
        },

        get preparationCanProceed() {
            return this.totalCheckedableItems > 0 && this.checkedCount === this.totalCheckedableItems;
        },

        get currentMoreItemsLabel() {
            const count = this.currentItems.length;
            return count > 3 ? `+ ${count - 3} artículos más` : null;
        },

        get upcomingOrders() {
            return upcomingQueueOrders(this.queueData).map((order) => buildFocusableOrder(order)).filter(Boolean);
        },

        get completedOrders() {
            return completedOrders(this.orders, this.serverTime);
        },

        get completedCount() {
            return Number(this.queueData.metrics?.completed_today ?? this.summary?.completed_today ?? this.completedOrders.length);
        },

        get isNoWorkState() {
            return !this.workspaceOpen && !this.recommendedOrder;
        },

        openRecommendedOrder() {
            const order = this.recommendedOrder;
            if (!order?.id) {
                return;
            }

            void this.openOrder(order.id);
        },

        async openOrder(orderId) {
            const normalizedId = toNumber(orderId, 0);
            if (!normalizedId) {
                return;
            }

            this.workspaceOpen = true;
            this.workspaceError = '';
            this.workspaceNotice = '';
            this.completionVisible = false;
            this.activeId = normalizedId;
            this.selectedOrderId = normalizedId;

            await this.loadSnapshot(normalizedId);
            this.setLocationOrder(normalizedId);
        },

        select(orderId, order = null) {
            if (order) {
                this.mergeOrderIntoFeed(order);
            }

            return this.openOrder(orderId);
        },

        syncDraftItems() {
            this.syncPreparationProgress();
        },

        async loadSnapshot(orderId) {
            const normalizedId = toNumber(orderId, 0);
            if (!normalizedId) {
                return;
            }

            this.workspaceLoading = true;
            this.workspaceError = '';

            try {
                const response = await fetch(`${this.snapshotUrlBase}/${normalizedId}/snapshot`, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    throw new Error('snapshot');
                }

                const payload = await response.json();
                const decorated = buildFocusableOrder(payload);

                if (!decorated) {
                    throw new Error('snapshot');
                }

                this.selectedOrder = decorated;
                this.selectedOrderId = decorated.id;
                this.activeId = decorated.id;
                this.completionVisible = decorated.status === 'dispatched';
                this.mergeOrderIntoFeed(decorated);
                this.syncPreparationProgress(decorated.id, { forceReset: decorated.status === 'preparing' });
            } catch (error) {
                this.workspaceError = 'No pudimos cargar el pedido. Intenta nuevamente.';
            } finally {
                this.workspaceLoading = false;
            }
        },

        async refreshCurrentOrder() {
            if (!this.selectedOrderId) {
                return;
            }

            await this.loadSnapshot(this.selectedOrderId);
        },

        async refreshFocusQueue() {
            return this.liveQueue?.refresh();
        },

        mergeOrderIntoFeed(order) {
            const normalized = normalizeOrder(order);
            if (!normalized) {
                return;
            }

            const index = this.orders.findIndex((item) => Number(item.id) === Number(normalized.id));

            if (index >= 0) {
                this.orders[index] = {
                    ...this.orders[index],
                    ...normalized,
                };
                return;
            }

            this.orders.unshift(normalized);
        },

        syncSelectedOrder(updatedOrder) {
            const decorated = buildFocusableOrder(updatedOrder);
            if (!decorated) {
                return;
            }

            this.mergeOrderIntoFeed(decorated);

            if (!this.selectedOrder || Number(this.selectedOrder.id) !== Number(decorated.id)) {
                return;
            }

            const preservedItems = Array.isArray(this.selectedOrder.items)
                ? this.selectedOrder.items.map((item) => ({ ...item }))
                : [];
            const preservedState = this.preparedItemStateByOrderId[decorated.id] ?? {};
            const { items: _items, ...rest } = decorated;

            Object.assign(this.selectedOrder, rest);
            this.selectedOrder.items = preservedItems.length > 0 ? preservedItems : decorated.items;
            this.selectedOrder.primary_action = decorated.primary_action;
            this.selectedOrder.secondary_actions = decorated.secondary_actions;
            this.selectedOrder.terminal_message = decorated.terminal_message;

            this.syncPreparationProgress(decorated.id, {
                forceReset: false,
                preserveState: preservedState,
            });
        },

        syncSummary() {
            const metrics = this.queueData.metrics ?? {};
            this.summary = {
                greeting: this.summary?.greeting ?? 'Buenos días',
                date: this.summary?.date ?? '',
                orders_today: Number(metrics.orders_today ?? 0),
                urgent_orders: Number(metrics.urgent_orders ?? 0),
                deliveries: Number(metrics.deliveries ?? 0),
                pickups: Number(metrics.pickups ?? 0),
                completed_today: Number(metrics.completed_today ?? 0),
            };
        },

        async performPrimaryAction() {
            await this.performWorkflowAction(this.currentPrimaryAction, { requireChecklist: true });
        },

        async performWorkflowAction(action, { requireChecklist = false } = {}) {
            if (!action || this.submittingActionKey !== null) {
                return;
            }

            if (requireChecklist && this.currentStatus === 'preparing' && !this.preparationCanProceed) {
                this.showToast('Marca todos los artículos antes de continuar.', 'error');
                return;
            }

            if (action.requires_confirmation && !window.confirm('Esta acción modificará el estado del pedido. ¿Deseás continuar?')) {
                return;
            }

            this.submittingActionKey = action.key ?? 'primary';

            try {
                const result = await this.submitWorkflowAction(action);

                if (result.status === 422) {
                    this.showToast('Este pedido cambió. Actualizamos la información.', 'error');
                    await this.refreshCurrentOrder();
                    await this.refreshFocusQueue();
                    return;
                }

                if (!result.ok) {
                    throw result;
                }

                this.workspaceNotice = result.payload?.message ?? 'Pedido actualizado.';
                this.showToast(result.payload?.message ?? 'Pedido actualizado.', 'success');

                await this.refreshCurrentOrder();
                if (this.currentStatus === 'preparing') {
                    this.resetPreparationProgress(this.currentWorkspaceOrder?.id ?? this.selectedOrderId, true);
                }

                if (this.currentStatus === 'dispatched') {
                    this.completionVisible = true;
                }

                await this.refreshFocusQueue();
            } catch (error) {
                this.handleWorkflowError(error);
            } finally {
                this.submittingActionKey = null;
            }
        },

        async submitWorkflowAction(action) {
            if (!action?.url) {
                return { ok: false, status: 0, payload: null };
            }

            if (String(action.method ?? 'POST').toUpperCase() === 'GET') {
                window.location.href = action.url;
                return { ok: true, status: 200, payload: null };
            }

            const formData = new FormData();
            formData.append('_token', this.csrfToken);

            const method = String(action.method ?? 'POST').toUpperCase();
            if (method !== 'POST') {
                formData.append('_method', method);
            }

            let response;
            let payload = null;

            try {
                response = await fetch(action.url, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: formData,
                    credentials: 'same-origin',
                });
            } catch (error) {
                throw new Error('network');
            }

            const contentType = response.headers.get('content-type') ?? '';
            if (contentType.includes('application/json')) {
                try {
                    payload = await response.json();
                } catch (error) {
                    payload = null;
                }
            }

            return {
                ok: response.ok,
                status: response.status,
                payload,
            };
        },

        async continueToNextOrder() {
            this.completionVisible = false;
            await this.refreshFocusQueue();

            const nextOrder = this.recommendedOrder;

            if (!nextOrder?.id) {
                this.closeWorkspace();
                return;
            }

            await this.openOrder(nextOrder.id);
        },

        togglePreparedItem(itemId) {
            const orderId = this.currentWorkspaceOrder?.id;
            if (!orderId) {
                return;
            }

            const state = this.ensurePreparationState(orderId);
            state[itemId] = !Boolean(state[itemId]);
        },

        resetPreparationProgress(orderId, forceReset = false) {
            const normalizedId = toNumber(orderId, 0);
            if (!normalizedId) {
                return;
            }

            if (forceReset || !this.preparedItemStateByOrderId[normalizedId]) {
                this.preparedItemStateByOrderId[normalizedId] = {};
            }

            const state = this.preparedItemStateByOrderId[normalizedId];
            for (const item of this.currentItems) {
                if (!Object.prototype.hasOwnProperty.call(state, item.id)) {
                    state[item.id] = false;
                }
            }
        },

        syncPreparationProgress(orderId = this.currentWorkspaceOrder?.id, options = {}) {
            const normalizedId = toNumber(orderId, 0);
            if (!normalizedId) {
                return;
            }

            const { forceReset = false, preserveState = null } = options;
            if (forceReset || !this.preparedItemStateByOrderId[normalizedId]) {
                this.preparedItemStateByOrderId[normalizedId] = {};
            }

            if (preserveState && typeof preserveState === 'object') {
                this.preparedItemStateByOrderId[normalizedId] = {
                    ...this.preparedItemStateByOrderId[normalizedId],
                    ...preserveState,
                };
            }

            const state = this.preparedItemStateByOrderId[normalizedId];
            for (const item of this.currentItems) {
                if (!Object.prototype.hasOwnProperty.call(state, item.id)) {
                    state[item.id] = false;
                }
            }
        },

        ensurePreparationState(orderId) {
            const normalizedId = toNumber(orderId, 0);
            if (!normalizedId) {
                return {};
            }

            if (!this.preparedItemStateByOrderId[normalizedId]) {
                this.preparedItemStateByOrderId[normalizedId] = {};
            }

            const state = this.preparedItemStateByOrderId[normalizedId];
            for (const item of this.currentItems) {
                if (!Object.prototype.hasOwnProperty.call(state, item.id)) {
                    state[item.id] = false;
                }
            }

            return state;
        },

        closeWorkspace() {
            this.workspaceOpen = false;
            this.workspaceLoading = false;
            this.workspaceError = '';
            this.workspaceNotice = '';
            this.selectedOrderId = null;
            this.selectedOrder = null;
            this.activeId = null;
            this.completionVisible = false;
            this.submittingActionKey = null;
        },

        onLiveQueueNewOrder(newOrders) {
            if (!urgentOrderToastOrders(newOrders).length) {
                return false;
            }

            this.showToast('Nuevo pedido urgente', 'info');
            return true;
        },

        onLiveQueueApplied(diff) {
            this.syncSummary();

            if (this.workspaceOpen && this.selectedOrderId && !this.submittingActionKey) {
                const updated = Array.isArray(diff?.changedOrders)
                    ? diff.changedOrders.find((order) => toNumber(order.id) === toNumber(this.selectedOrderId))
                    : null;

                if (updated) {
                    this.syncSelectedOrder(updated);
                }
            }
        },

        handleWorkflowError(error) {
            if (error?.status === 422) {
                this.showToast('Este pedido cambió. Actualizamos la información.', 'error');
                return;
            }

            if (error?.message === 'network') {
                this.showToast('Se perdió la conexión. Intenta nuevamente.', 'error');
                return;
            }

            if (error?.kind === 'snapshot') {
                this.showToast('No pudimos cargar el pedido. Intenta nuevamente.', 'error');
                return;
            }

            if (error?.payload?.message) {
                this.showToast(error.payload.message, 'error');
                return;
            }

            this.showToast('No pudimos completar la acción. Intenta nuevamente.', 'error');
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

        formatOrderDate(order) {
            const commitmentDate = String(order?.commitment_date ?? '');
            if (commitmentDate === '') {
                return 'Sin fecha confirmada';
            }

            return formatDateLabel(commitmentDate) || 'Sin fecha confirmada';
        },

        formatOrderTime(order) {
            return formatTime(order?.commitment_time);
        },

        orderPriorityLabel(order) {
            return priorityLabel(order);
        },

        orderCommitmentLabel(order) {
            return commitmentLabel(order);
        },

        orderDeliveryLabel(order) {
            return deliveryLabel(order?.delivery_method ?? null);
        },

        orderPaymentLabel(order) {
            return paymentLabel(order?.payment_method ?? null);
        },

        orderChannelLabel(order) {
            return channelLabel(order?.source_channel ?? order?.channel_key ?? null);
        },

        orderItemLines(order) {
            return buildItemLines(order?.items ?? []);
        },

        orderMoreItemsLabel(order) {
            const count = Array.isArray(order?.items) ? order.items.length : 0;
            return count > 3 ? `+ ${count - 3} artículos más` : null;
        },

        setLocationOrder(orderId) {
            const url = new URL(window.location.href);
            if (orderId) {
                url.searchParams.set('order', orderId);
            } else {
                url.searchParams.delete('order');
            }

            window.history.replaceState({}, '', url);
        },
    };
}
