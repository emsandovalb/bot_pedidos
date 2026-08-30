<?php

namespace App\Services;

use App\Models\Order;

class OrderConfirmationReadinessService
{
    public const BLOCKED_MESSAGE = 'No se puede confirmar el pedido: hay productos pendientes de revisión.';

    /**
     * A confirmation requires every line to be resolved in the active catalog and
     * the parser outcome to be free of the review markers defined by the parser
     * output contract.
     */
    public function canConfirm(Order $order): bool
    {
        return $this->blockingReasons($order) === [];
    }

    /**
     * @return array<int, string>
     */
    public function blockingReasons(Order $order): array
    {
        $order->loadMissing('orderItems');

        $reasons = [];

        if ($order->orderItems->contains(static fn ($item): bool => $item->product_id === null)) {
            $reasons[] = 'unresolved_catalog_product';
        }

        $parserResult = is_array($order->parsed_payload_json) ? $order->parsed_payload_json : [];
        $parserStatus = $parserResult['status'] ?? null;

        if (($parserResult['needs_review'] ?? false) === true
            || in_array($parserStatus, [
                OrderParserService::STATUS_PENDING_REVIEW,
                OrderParserService::STATUS_UNMATCHED,
            ], true)) {
            $reasons[] = 'parser_review_required';
        }

        return $reasons;
    }
}
