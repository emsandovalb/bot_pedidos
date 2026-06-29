<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderNotificationLog;
use App\Services\Messaging\DTO\OutgoingMessageDTO;
use App\Services\Messaging\Manager\MessagingManager;
use Throwable;

class OrderNotificationDispatcher
{
    public function __construct(
        private readonly OrderNotificationService $orderNotificationService,
        private readonly NotificationTemplateRenderer $notificationTemplateRenderer,
        private readonly MessagingManager $messagingManager,
    ) {
    }

    public function dispatch(Order $order, string $event): OrderNotificationLog
    {
        try {
            $evaluation = $this->orderNotificationService->evaluate($order, $event);
            $setting = $evaluation['setting'];
            $channel = strtolower(trim((string) $setting->channel));
            $renderedMessage = $this->renderMessage($order, $setting->message_body);
            $shouldAttemptSending = $this->shouldAttemptSending($channel);
            $status = $this->statusFromEvaluation((bool) $evaluation['should_send'], (bool) $evaluation['requires_template']);
            $providerMessageId = null;
            $sentAt = null;
            $errorMessage = null;
            $reason = $evaluation['reason'];

            if ($evaluation['should_send'] && $shouldAttemptSending) {
                $recipientResolution = $this->resolveRecipient($order, $channel);

                if ($recipientResolution['status'] === 'failed') {
                    $status = OrderNotificationLog::STATUS_FAILED;
                    $reason = $recipientResolution['reason'];
                    $errorMessage = $recipientResolution['reason'];
                } else {
                    $provider = $this->messagingManager->driver($channel);
                    $outgoingMessage = new OutgoingMessageDTO(
                        provider: $channel,
                        phone: (string) ($order->customer?->phone ?? ''),
                        message: $renderedMessage,
                        attachments: [],
                        metadata: array_merge([
                            'order_id' => $order->id,
                            'customer_id' => $order->customer_id,
                            'event' => $event,
                            'setting_id' => $setting->id,
                        ], $recipientResolution['metadata']),
                    );
                    $result = $provider->sendMessage($outgoingMessage);

                    if ($result->success) {
                        $status = OrderNotificationLog::STATUS_SENT;
                        $providerMessageId = $result->provider_message_id;
                        $sentAt = now();
                        $reason = 'Notification sent through ' . $channel . '.';
                    } else {
                        $status = OrderNotificationLog::STATUS_FAILED;
                        $errorMessage = $result->error;
                        $reason = $result->error ?? 'Notification send failed.';
                    }
                }
            }

            return OrderNotificationLog::create([
                'organization_id' => $order->organization_id,
                'order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'channel' => $channel,
                'event' => $event,
                'status' => $status,
                'should_send' => (bool) $evaluation['should_send'],
                'requires_template' => (bool) $evaluation['requires_template'],
                'message_body' => $renderedMessage !== '' ? $renderedMessage : null,
                'reason' => $reason,
                'provider' => $channel !== '' ? $channel : null,
                'provider_message_id' => $providerMessageId,
                'sent_at' => $sentAt,
                'error_message' => $errorMessage,
                'metadata_json' => [
                    'dry_run' => ! $shouldAttemptSending || ! $evaluation['should_send'] || $status !== OrderNotificationLog::STATUS_SENT,
                    'sending_enabled' => $shouldAttemptSending,
                    'setting_id' => $setting->id,
                    'setting_channel' => $setting->channel,
                    'setting_event' => $setting->event,
                    'should_send' => (bool) $evaluation['should_send'],
                    'requires_template' => (bool) $evaluation['requires_template'],
                    'event' => $event,
                    'provider_message_id' => $providerMessageId,
                ],
                'evaluated_at' => now(),
            ]);
        } catch (Throwable $throwable) {
            return OrderNotificationLog::create([
                'organization_id' => $order->organization_id,
                'order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'channel' => strtolower(trim((string) ($order->source_channel ?: 'telegram'))),
                'event' => $event,
                'status' => OrderNotificationLog::STATUS_FAILED,
                'should_send' => false,
                'requires_template' => false,
                'message_body' => null,
                'reason' => $throwable->getMessage(),
                'provider' => null,
                'provider_message_id' => null,
                'sent_at' => null,
                'error_message' => $throwable->getMessage(),
                'metadata_json' => [
                    'dry_run' => true,
                    'event' => $event,
                    'exception' => $throwable::class,
                ],
                'evaluated_at' => now(),
            ]);
        }
    }

    private function renderMessage(Order $order, ?string $template): string
    {
        if (! is_string($template) || trim($template) === '') {
            return '';
        }

        return $this->notificationTemplateRenderer->render($template, [
            'order_id' => $order->id,
            'customer_name' => $order->customer?->name,
            'status' => $order->status,
            'business_name' => $order->organization?->name,
        ]);
    }

    private function statusFromEvaluation(bool $shouldSend, bool $requiresTemplate): string
    {
        if (! $shouldSend) {
            return OrderNotificationLog::STATUS_SKIPPED;
        }

        if ($requiresTemplate) {
            return OrderNotificationLog::STATUS_QUEUED;
        }

        return OrderNotificationLog::STATUS_SIMULATED;
    }

    private function shouldAttemptSending(string $channel): bool
    {
        if (! (bool) config('messaging.notifications_sending_enabled', false)) {
            return false;
        }

        if ($channel === 'telegram') {
            return (bool) config('messaging.telegram_notifications_enabled', false);
        }

        return false;
    }

    /**
     * @return array{status: string, reason: string, metadata: array<string, mixed>}
     */
    private function resolveRecipient(Order $order, string $channel): array
    {
        if ($channel !== 'telegram') {
            return [
                'status' => 'ok',
                'reason' => '',
                'metadata' => [],
            ];
        }

        $customerIdentities = $order->customer?->customerIdentities();

        if ($customerIdentities === null) {
            return [
                'status' => 'failed',
                'reason' => 'Missing Telegram identity',
                'metadata' => [],
            ];
        }

        $customerIdentities = $customerIdentities->where('provider', 'telegram');

        $customerIdentity = (clone $customerIdentities)
            ->whereNotNull('external_chat_id')
            ->latest('last_seen_at')
            ->first()
            ?? $customerIdentities->latest('last_seen_at')->first();

        if ($customerIdentity === null) {
            return [
                'status' => 'failed',
                'reason' => 'Missing Telegram identity',
                'metadata' => [],
            ];
        }

        $chatId = $customerIdentity->external_chat_id;

        return [
            'status' => 'ok',
            'reason' => '',
            'metadata' => [
                'external_chat_id' => $chatId,
                'customer_identity_id' => $customerIdentity->id,
            ],
        ];
    }
}
