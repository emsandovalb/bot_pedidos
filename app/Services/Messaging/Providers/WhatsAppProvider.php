<?php

namespace App\Services\Messaging\Providers;

use App\Models\Branch;
use App\Models\ChannelConnection;
use App\Models\WebhookEvent;
use App\Services\Messaging\Contracts\MessagingProvider;
use App\Services\Messaging\DTO\IncomingMessageDTO;
use App\Services\Messaging\DTO\MessagingSendResult;
use App\Services\Messaging\DTO\OutgoingMessageDTO;
use App\Services\Messaging\DTO\ProviderCapabilities;
use App\Services\Messaging\DTO\ProviderHealth;
use App\Services\Messaging\DTO\ProviderValidationResult;
use App\Services\Messaging\MessagingIngestionService;
use App\Services\Messaging\DTO\WebhookReceiveResult;
use App\Services\Messaging\DTO\WebhookVerificationResult;
use App\Services\WhatsAppConfigurationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use JsonException;
use Throwable;

class WhatsAppProvider implements MessagingProvider
{
    public function providerName(): string
    {
        return 'whatsapp';
    }

    public function connect(): ProviderHealth
    {
        return $this->placeholderHealth('warning');
    }

    public function disconnect(): ProviderHealth
    {
        return $this->placeholderHealth('disconnected', false, 'WhatsApp Cloud is not integrated yet.');
    }

    public function health(): ProviderHealth
    {
        return $this->placeholderHealth('warning');
    }

    public function capabilities(): ProviderCapabilities
    {
        return new ProviderCapabilities(
            provider: $this->providerName(),
            receive_messages: true,
            send_messages: true,
            images: true,
            files: true,
            audio: true,
            video: true,
            templates: true,
            catalog: true,
            reactions: true,
            buttons: true,
            location: true,
            contacts: true,
            send_images: true,
            send_documents: true,
            send_audio: true,
            send_video: true,
            interactive_buttons: true,
            reaction_support: true,
        );
    }

    public function validateConfiguration(): ProviderValidationResult
    {
        return new ProviderValidationResult(
            valid: false,
            errors: ['WhatsApp Cloud integration is not implemented yet.'],
            warnings: ['Provider is available as a placeholder only.'],
            configuration_checked_at: now(),
        );
    }

    public function supports(string $capability): bool
    {
        return (bool) ($this->capabilities()->toArray()[strtolower(trim($capability))] ?? false);
    }

    public function verifyWebhook(Request $request): WebhookVerificationResult
    {
        $mode = strtolower(trim($this->requestValue($request, 'hub.mode')));
        $verifyToken = $this->requestValue($request, 'hub.verify_token');
        $challenge = $this->requestValue($request, 'hub.challenge');
        $service = app(WhatsAppConfigurationService::class);
        $connection = $service->resolveWebhookConfiguration($verifyToken);

        if ($mode !== 'subscribe') {
            return new WebhookVerificationResult(
                success: false,
                status: 403,
                challenge: null,
                provider: $this->providerName(),
                message: 'Invalid webhook mode.',
            );
        }

        if ($connection === null || ! $service->isReadyForWebhook($connection)) {
            return new WebhookVerificationResult(
                success: false,
                status: 403,
                challenge: null,
                provider: $this->providerName(),
                message: 'Webhook verification failed.',
            );
        }

        if (! hash_equals((string) $connection->provider_verify_token, $verifyToken)) {
            return new WebhookVerificationResult(
                success: false,
                status: 403,
                challenge: null,
                provider: $this->providerName(),
                message: 'Webhook verification failed.',
            );
        }

        if ($challenge === '') {
            return new WebhookVerificationResult(
                success: false,
                status: 403,
                challenge: null,
                provider: $this->providerName(),
                message: 'Missing webhook challenge.',
            );
        }

        return new WebhookVerificationResult(
            success: true,
            status: 200,
            challenge: $challenge,
            provider: $this->providerName(),
            message: null,
        );
    }

    public function receive(Request $request)
    {
        return $this->receiveWebhook($request);
    }

    public function send(OutgoingMessageDTO $message): MessagingSendResult
    {
        return $this->sendMessage($message);
    }

    public function refreshCredentials(): ProviderValidationResult
    {
        return $this->validateConfiguration();
    }

    public function receiveWebhook(Request $request): WebhookReceiveResult|WebhookVerificationResult
    {
        $payload = $this->decodeWebhookPayload($request);

        if ($payload === null) {
            return new WebhookReceiveResult(
                success: false,
                status: 400,
                challenge: null,
                provider: $this->providerName(),
                message: 'Malformed WhatsApp webhook payload.',
                failed_count: 1,
            );
        }

        $processedCount = 0;
        $ignoredCount = 0;
        $failedCount = 0;
        $resolvedOrganizationId = null;

        foreach ($this->arrayValue($payload['entry'] ?? []) as $entryIndex => $entry) {
            if (! is_array($entry)) {
                continue;
            }

            foreach ($this->arrayValue($entry['changes'] ?? []) as $changeIndex => $change) {
                if (! is_array($change)) {
                    continue;
                }

                $value = is_array($change['value'] ?? null) ? $change['value'] : [];
                $phoneNumberId = $this->stringValue(data_get($value, 'metadata.phone_number_id'));
                $connection = $this->resolveConnection($phoneNumberId);
                $baseMetadata = $this->sanitizedWebhookMetadata(
                    payload: $payload,
                    entryIndex: $entryIndex,
                    changeIndex: $changeIndex,
                    phoneNumberId: $phoneNumberId,
                );

                if ($connection === null) {
                    $ignoredCount++;
                    $this->logWebhookEvent(
                        organizationId: null,
                        eventType: 'unknown_connection',
                        status: 200,
                        request: $request,
                        payload: $baseMetadata,
                    );

                    continue;
                }

                $resolvedOrganizationId = $connection->organization_id;
                $branch = $this->resolveBranch($connection);

                if ($branch === null) {
                    $failedCount++;
                    $this->logWebhookEvent(
                        organizationId: $connection->organization_id,
                        eventType: 'failed',
                        status: 200,
                        request: $request,
                        payload: $baseMetadata + [
                            'failure_reason' => 'No branch is available for the WhatsApp connection.',
                        ],
                    );

                    continue;
                }

                $statusItems = $this->arrayValue(data_get($value, 'statuses', []));
                foreach ($statusItems as $statusIndex => $statusItem) {
                    if (! is_array($statusItem)) {
                        continue;
                    }

                    $ignoredCount++;
                    $this->logWebhookEvent(
                        organizationId: $connection->organization_id,
                        eventType: 'ignored_status',
                        status: 200,
                        request: $request,
                        payload: $baseMetadata + [
                            'status_index' => $statusIndex,
                            'status_id' => $this->stringValue($statusItem['id'] ?? null),
                            'status_type' => $this->stringValue($statusItem['status'] ?? null),
                        ],
                    );
                }

                $messages = $this->arrayValue(data_get($value, 'messages', []));

                if ($messages === []) {
                    continue;
                }

                foreach ($messages as $messageIndex => $message) {
                    if (! is_array($message)) {
                        continue;
                    }

                    $messageType = strtolower(trim((string) ($message['type'] ?? '')));

                    if ($messageType !== 'text') {
                        $ignoredCount++;
                        $this->logWebhookEvent(
                            organizationId: $connection->organization_id,
                            eventType: 'ignored_non_text',
                            status: 200,
                            request: $request,
                            payload: $baseMetadata + [
                                'message_index' => $messageIndex,
                                'message_id' => $this->stringValue($message['id'] ?? null),
                                'message_type' => $messageType !== '' ? $messageType : null,
                            ],
                        );

                        continue;
                    }

                    $textBody = $this->stringValue(data_get($message, 'text.body'));

                    if ($textBody === null || $textBody === '') {
                        $failedCount++;
                        $this->logWebhookEvent(
                            organizationId: $connection->organization_id,
                            eventType: 'failed',
                            status: 200,
                            request: $request,
                            payload: $baseMetadata + [
                                'message_index' => $messageIndex,
                                'message_id' => $this->stringValue($message['id'] ?? null),
                                'message_type' => 'text',
                                'failure_reason' => 'Missing WhatsApp text body.',
                            ],
                        );

                        continue;
                    }

                    $contactName = $this->contactProfileName($value);
                    $receivedAt = $this->receivedAt($message['timestamp'] ?? null);
                    $externalMessageId = $this->stringValue($message['id'] ?? null);
                    $externalChatId = $this->stringValue($message['from'] ?? null);

                    if ($externalMessageId === null || $externalMessageId === '' || $externalChatId === null || $externalChatId === '') {
                        $failedCount++;
                        $this->logWebhookEvent(
                            organizationId: $connection->organization_id,
                            eventType: 'failed',
                            status: 200,
                            request: $request,
                            payload: $baseMetadata + [
                                'message_index' => $messageIndex,
                                'message_id' => $externalMessageId,
                                'message_type' => 'text',
                                'failure_reason' => 'Missing WhatsApp sender or message id.',
                            ],
                        );

                        continue;
                    }

                    try {
                        $ingestionResult = app(MessagingIngestionService::class)->ingest(
                            organization: $connection->organization,
                            branch: $branch,
                            message: new IncomingMessageDTO(
                                provider: $this->providerName(),
                                external_message_id: $externalMessageId,
                                external_chat_id: $externalChatId,
                                received_at: $receivedAt,
                                external_user_id: $externalChatId,
                                customer_name: $contactName,
                                customer_phone: $externalChatId,
                                message: $textBody,
                                raw_payload: [
                                    'entry' => $entry,
                                    'change' => $change,
                                    'value' => $value,
                                    'message' => $message,
                                ],
                                attachments: [],
                            )
                        );

                        $duplicate = (bool) ($ingestionResult['duplicate'] ?? false);
                        $status = (string) ($ingestionResult['status'] ?? '');

                        if ($duplicate) {
                            $ignoredCount++;
                        } elseif ($status === 'failed') {
                            $failedCount++;
                        } else {
                            $processedCount++;
                        }

                        $incomingMessage = $ingestionResult['incoming_message'] ?? null;
                        $order = $ingestionResult['order'] ?? null;

                        $this->logWebhookEvent(
                            organizationId: $connection->organization_id,
                            eventType: $status === 'failed' ? 'failed' : 'incoming_message',
                            status: 200,
                            request: $request,
                            payload: $baseMetadata + [
                                'message_index' => $messageIndex,
                                'message_id' => $externalMessageId,
                                'message_type' => 'text',
                                'incoming_message_id' => $incomingMessage?->id,
                                'order_id' => $order?->id,
                                'duplicate' => $duplicate,
                                'status' => $status !== '' ? $status : null,
                            ],
                        );
                    } catch (Throwable $throwable) {
                        $failedCount++;
                        Log::warning('WhatsApp webhook message ingestion failed.', [
                            'provider' => $this->providerName(),
                            'organization_id' => $connection->organization_id,
                            'message_id' => $externalMessageId,
                            'exception' => $throwable->getMessage(),
                        ]);

                        $this->logWebhookEvent(
                            organizationId: $connection->organization_id,
                            eventType: 'failed',
                            status: 200,
                            request: $request,
                            payload: $baseMetadata + [
                                'message_index' => $messageIndex,
                                'message_id' => $externalMessageId,
                                'message_type' => 'text',
                                'failure_reason' => 'Exception while ingesting WhatsApp message.',
                            ],
                        );
                    }
                }
            }
        }

        return new WebhookReceiveResult(
            success: true,
            status: 200,
            challenge: null,
            provider: $this->providerName(),
            message: $this->summaryMessage($processedCount, $ignoredCount, $failedCount),
            processed_count: $processedCount,
            ignored_count: $ignoredCount,
            failed_count: $failedCount,
            organization_id: $resolvedOrganizationId,
            metadata: [
                'provider_phone_number_id' => $this->stringValue(data_get($payload, 'entry.0.changes.0.value.metadata.phone_number_id')),
            ],
        );
    }

    public function sendMessage(OutgoingMessageDTO $message): MessagingSendResult
    {
        return new MessagingSendResult(
            success: false,
            provider: $this->providerName(),
            raw_response: null,
            error: 'WhatsApp Cloud sendMessage is not implemented yet.',
        );
    }

    public function markAsRead(string $externalMessageId)
    {
        return null;
    }

    public function healthCheck()
    {
        return $this->health()->toArray();
    }

    protected function placeholderHealth(string $status, bool $connected = false, ?string $error = null): ProviderHealth
    {
        return new ProviderHealth(
            provider: $this->providerName(),
            status: $status,
            connected: $connected,
            webhook_status: $connected ? 'pending' : 'failed',
            credentials_status: 'missing',
            last_error: $error ?? 'WhatsApp Cloud is not integrated yet.',
            latency_ms: null,
            capabilities: $this->capabilities()->toArray(),
            metadata: [
                'provider_type' => 'placeholder',
            ],
            healthy: false,
            last_ping: now(),
            version: 'v1-placeholder',
            token_status: 'missing',
            last_health_check_at: now(),
        );
    }

    private function requestValue(Request $request, string $key): string
    {
        $fallbackKey = str_replace('.', '_', $key);

        return trim((string) $request->query($key, $request->query($fallbackKey, '')));
    }

    private function decodeWebhookPayload(Request $request): ?array
    {
        $content = trim((string) $request->getContent());

        if ($content === '') {
            return [];
        }

        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, mixed>
     */
    private function arrayValue(mixed $payload): array
    {
        return is_array($payload) ? array_values($payload) : [];
    }

    private function stringValue(mixed $value): ?string
    {
        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }

        if (is_int($value) || is_float($value)) {
            return trim((string) $value);
        }

        return null;
    }

    private function resolveConnection(?string $phoneNumberId): ?ChannelConnection
    {
        if ($phoneNumberId === null || $phoneNumberId === '') {
            return null;
        }

        return ChannelConnection::query()
            ->where('channel', ChannelConnection::CHANNEL_WHATSAPP)
            ->where('provider_phone_number_id', $phoneNumberId)
            ->first();
    }

    private function resolveBranch(ChannelConnection $connection): ?Branch
    {
        $organizationId = $connection->organization_id;

        if ($organizationId === null) {
            return null;
        }

        return Branch::query()
            ->where('organization_id', $organizationId)
            ->where('status', Branch::STATUS_ACTIVE)
            ->orderBy('id')
            ->first()
            ?? Branch::query()
                ->where('organization_id', $organizationId)
                ->orderBy('id')
                ->first();
    }

    private function contactProfileName(array $value): ?string
    {
        $contacts = $this->arrayValue(data_get($value, 'contacts', []));

        foreach ($contacts as $contact) {
            if (! is_array($contact)) {
                continue;
            }

            $profileName = $this->stringValue(data_get($contact, 'profile.name'));

            if ($profileName !== null) {
                return $profileName;
            }
        }

        return null;
    }

    private function receivedAt(mixed $timestamp): Carbon
    {
        if (is_numeric($timestamp)) {
            return Carbon::createFromTimestampUTC((int) $timestamp);
        }

        return now();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function sanitizedWebhookMetadata(array $payload, int $entryIndex, int $changeIndex, ?string $phoneNumberId): array
    {
        $entry = $this->arrayValue($payload['entry'] ?? []);
        $currentEntry = is_array($entry[$entryIndex] ?? null) ? $entry[$entryIndex] : [];
        $currentChange = is_array(data_get($currentEntry, "changes.$changeIndex")) ? data_get($currentEntry, "changes.$changeIndex") : [];
        $value = is_array(data_get($currentChange, 'value')) ? data_get($currentChange, 'value') : [];
        $messages = $this->arrayValue(data_get($value, 'messages', []));
        $statuses = $this->arrayValue(data_get($value, 'statuses', []));

        $messageIds = [];
        $messageTypes = [];
        foreach ($messages as $message) {
            if (! is_array($message)) {
                continue;
            }

            $messageId = $this->stringValue($message['id'] ?? null);
            $messageType = $this->stringValue($message['type'] ?? null);

            if ($messageId !== null) {
                $messageIds[] = $messageId;
            }

            if ($messageType !== null) {
                $messageTypes[] = $messageType;
            }
        }

        return array_filter([
            'entry_index' => $entryIndex,
            'change_index' => $changeIndex,
            'entry_id' => $this->stringValue($currentEntry['id'] ?? null),
            'phone_number_id' => $phoneNumberId,
            'message_count' => count($messages),
            'status_count' => count($statuses),
            'message_ids' => $messageIds,
            'message_types' => $messageTypes,
            'has_contacts' => $this->arrayValue(data_get($value, 'contacts', [])) !== [],
            'has_messages' => $messages !== [],
            'has_statuses' => $statuses !== [],
        ], static fn (mixed $value): bool => $value !== null && $value !== []);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function logWebhookEvent(?int $organizationId, string $eventType, int $status, Request $request, array $payload): void
    {
        WebhookEvent::query()->create([
            'organization_id' => $organizationId,
            'provider' => $this->providerName(),
            'event_type' => $eventType,
            'method' => 'POST',
            'ip' => $request->ip(),
            'status' => (string) $status,
            'payload_json' => $payload,
            'created_at' => now(),
        ]);
    }

    private function summaryMessage(int $processedCount, int $ignoredCount, int $failedCount): string
    {
        return sprintf(
            'WhatsApp webhook handled: %d processed, %d ignored, %d failed.',
            $processedCount,
            $ignoredCount,
            $failedCount,
        );
    }
}
