<?php

namespace App\Services;

use App\Models\Order;

class OrderWorkflowActionPresenter
{
    /**
     * @return array{
     *     allowed_actions: array<int, array<string, mixed>>,
     *     primary_action: array<string, mixed>|null,
     *     secondary_actions: array<int, array<string, mixed>>,
     *     terminal_message: string|null
     * }
     */
    public function present(Order $order): array
    {
        return match ($order->status) {
            Order::STATUS_PENDING_REVIEW => $this->workflow(
                primary: $this->action(
                    key: 'confirm',
                    label: 'Confirmar pedido',
                    method: 'POST',
                    url: route('orders.confirm', $order),
                    style: 'primary',
                ),
                secondary: [
                    $this->action(
                        key: 'reject',
                        label: 'Rechazar',
                        method: 'POST',
                        url: route('orders.reject', $order),
                        style: 'danger',
                        requiresConfirmation: true,
                    ),
                    $this->action(
                        key: 'cancel',
                        label: 'Cancelar',
                        method: 'POST',
                        url: route('orders.cancel', $order),
                        style: 'danger',
                        requiresConfirmation: true,
                    ),
                ],
            ),
            Order::STATUS_CONFIRMED => $this->workflow(
                primary: $this->action(
                    key: 'prepare',
                    label: 'Iniciar preparación',
                    method: 'POST',
                    url: route('orders.prepare', $order),
                    style: 'primary',
                ),
                secondary: [
                    $this->action(
                        key: 'cancel',
                        label: 'Cancelar',
                        method: 'POST',
                        url: route('orders.cancel', $order),
                        style: 'danger',
                        requiresConfirmation: true,
                    ),
                ],
            ),
            Order::STATUS_PREPARING => $this->workflow(
                primary: $this->action(
                    key: 'ready',
                    label: 'Marcar listo',
                    method: 'POST',
                    url: route('orders.ready-for-dispatch', $order),
                    style: 'primary',
                ),
                secondary: [
                    $this->action(
                        key: 'cancel',
                        label: 'Cancelar',
                        method: 'POST',
                        url: route('orders.cancel', $order),
                        style: 'danger',
                        requiresConfirmation: true,
                    ),
                ],
            ),
            Order::STATUS_READY_FOR_DISPATCH => $this->workflow(
                primary: $this->action(
                    key: 'dispatch',
                    label: 'Despachar',
                    method: 'POST',
                    url: route('orders.dispatch', $order),
                    style: 'primary',
                ),
                secondary: [
                    $this->action(
                        key: 'cancel',
                        label: 'Cancelar',
                        method: 'POST',
                        url: route('orders.cancel', $order),
                        style: 'danger',
                        requiresConfirmation: true,
                    ),
                ],
            ),
            Order::STATUS_DISPATCHED => $this->workflow(
                terminalMessage: 'Pedido despachado',
                secondary: [
                    $this->action(
                        key: 'view_history',
                        label: 'Ver historial',
                        method: 'GET',
                        url: route('orders.show', $order),
                        style: 'secondary',
                    ),
                ],
            ),
            Order::STATUS_CANCELLED => $this->workflow(
                terminalMessage: 'Pedido cancelado',
            ),
            Order::STATUS_REJECTED => $this->workflow(
                terminalMessage: 'Pedido rechazado',
            ),
            default => $this->workflow(),
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $secondary
     * @return array{
     *     allowed_actions: array<int, array<string, mixed>>,
     *     primary_action: array<string, mixed>|null,
     *     secondary_actions: array<int, array<string, mixed>>,
     *     terminal_message: string|null
     * }
     */
    private function workflow(array $secondary = [], ?array $primary = null, ?string $terminalMessage = null): array
    {
        $allowedActions = $primary !== null
            ? array_merge([$primary], $secondary)
            : $secondary;

        return [
            'allowed_actions' => $allowedActions,
            'primary_action' => $primary,
            'secondary_actions' => $secondary,
            'terminal_message' => $terminalMessage,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function action(
        string $key,
        string $label,
        string $method,
        string $url,
        string $style,
        bool $requiresConfirmation = false,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'method' => $method,
            'url' => $url,
            'style' => $style,
            'requires_confirmation' => $requiresConfirmation,
        ];
    }
}
