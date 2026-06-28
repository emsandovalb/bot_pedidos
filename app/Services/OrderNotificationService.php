<?php

namespace App\Services;

use App\Models\Order;
use App\Models\NotificationSetting;

class OrderNotificationService
{
    public function __construct(
        private readonly NotificationPolicyService $notificationPolicyService,
    ) {
    }

    /**
     * @return array{
     *     should_send: bool,
     *     reason: string,
     *     requires_template: bool,
     *     setting: NotificationSetting
     * }
     */
    public function evaluate(Order $order, string $event): array
    {
        $order->loadMissing(['organization', 'customer.customerIdentities', 'incomingMessage']);

        $channel = strtolower(trim((string) ($order->source_channel ?: $order->incomingMessage?->provider ?: 'telegram')));
        $customerIdentity = $order->customer === null
            ? null
            : $order->customer->customerIdentities()
                ->where('provider', $channel)
                ->latest('last_seen_at')
                ->first();

        return $this->notificationPolicyService->evaluate(
            organization: $order->organization,
            channel: $channel,
            event: $event,
            customerIdentity: $customerIdentity,
        );
    }
}
