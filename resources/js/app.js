import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

window.operationsCenter = (config) => ({
    orders: config.orders ?? [],
    activeId: config.activeId ?? null,
    draftItems: [],

    init() {
        if (this.activeId === null && this.orders.length > 0) {
            this.activeId = this.orders[0].id;
        }

        this.syncDraftItems();
    },

    get activeOrder() {
        return this.orders.find((order) => order.id === this.activeId) ?? this.orders[0] ?? null;
    },

    select(orderId) {
        this.activeId = orderId;
        this.syncDraftItems();

        const nextUrl = new URL(window.location.href);
        nextUrl.searchParams.set('order', orderId);
        window.history.replaceState({}, '', nextUrl);
    },

    syncDraftItems() {
        this.draftItems = (this.activeOrder?.items ?? []).map((item) => ({ ...item }));
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

    removeItem(index) {
        this.draftItems.splice(index, 1);
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

Alpine.start();
