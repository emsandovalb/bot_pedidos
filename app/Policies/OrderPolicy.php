<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

/**
 * Gatea QUIÉN puede actuar sobre un pedido (rol + alcance por organización y
 * sucursal). El QUÉ (si la transición de estado es válida, si hay productos sin
 * resolver, etc.) lo siguen controlando OrderController::assertTransitionAllowed
 * y OrderConfirmationReadinessService.
 */
class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return $this->isVisible($user, $order);
    }

    public function update(User $user, Order $order): bool
    {
        return $this->isManageable($user, $order);
    }

    public function confirm(User $user, Order $order): bool
    {
        return $this->isManageable($user, $order);
    }

    public function reject(User $user, Order $order): bool
    {
        return $this->isManageable($user, $order);
    }

    public function cancel(User $user, Order $order): bool
    {
        return $this->isManageable($user, $order);
    }

    /**
     * Avanzar el pedido en el flujo operativo (preparar, listo, despachado).
     */
    public function advance(User $user, Order $order): bool
    {
        return $this->isManageable($user, $order);
    }

    private function isVisible(User $user, Order $order): bool
    {
        if ($user->organization_id === null || $user->organization_id !== $order->organization_id) {
            return false;
        }

        if ($user->canViewAllBranchesForRead()) {
            return true;
        }

        return $order->branch_id !== null
            && in_array($order->branch_id, $user->visibleBranchIds(), true);
    }

    private function isManageable(User $user, Order $order): bool
    {
        return $this->isVisible($user, $order) && ! $user->isViewer();
    }
}
