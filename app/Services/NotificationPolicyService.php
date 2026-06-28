<?php

namespace App\Services;

use App\Models\CustomerIdentity;
use App\Models\NotificationSetting;
use App\Models\Organization;

class NotificationPolicyService
{
    /**
     * @return array{
     *     should_send: bool,
     *     reason: string,
     *     requires_template: bool,
     *     setting: NotificationSetting
     * }
     */
    public function evaluate(
        Organization $organization,
        string $channel,
        string $event,
        ?CustomerIdentity $customerIdentity = null,
    ): array {
        $channel = strtolower(trim($channel));
        $event = strtolower(trim($event));

        $setting = NotificationSetting::query()->firstOrCreate(
            [
                'organization_id' => $organization->id,
                'channel' => $channel,
                'event' => $event,
            ],
            NotificationSetting::defaultAttributes($channel, $event),
        );

        if (! $setting->is_enabled) {
            return [
                'should_send' => false,
                'reason' => 'Notification disabled for this event.',
                'requires_template' => false,
                'setting' => $setting,
            ];
        }

        if ($channel === NotificationSetting::CHANNEL_TELEGRAM) {
            return [
                'should_send' => true,
                'reason' => 'Telegram does not enforce a customer service window.',
                'requires_template' => false,
                'setting' => $setting,
            ];
        }

        if ($channel === NotificationSetting::CHANNEL_WHATSAPP && $setting->requires_open_service_window) {
            $windowExpiresAt = $customerIdentity?->service_window_expires_at;

            if ($windowExpiresAt !== null && $windowExpiresAt->isFuture()) {
                return [
                    'should_send' => true,
                    'reason' => 'WhatsApp service window is open.',
                    'requires_template' => false,
                    'setting' => $setting,
                ];
            }

            if ($setting->use_template_if_window_closed && filled($setting->template_name)) {
                return [
                    'should_send' => true,
                    'reason' => 'WhatsApp service window is closed, but a template is configured.',
                    'requires_template' => true,
                    'setting' => $setting,
                ];
            }

            return [
                'should_send' => false,
                'reason' => 'WhatsApp service window is closed.',
                'requires_template' => false,
                'setting' => $setting,
            ];
        }

        return [
            'should_send' => true,
            'reason' => 'Notification enabled.',
            'requires_template' => false,
            'setting' => $setting,
        ];
    }
}
