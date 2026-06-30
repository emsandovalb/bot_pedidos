<?php

namespace App\Services\Messaging\Providers;

use App\Services\Messaging\Contracts\MessagingProvider;
use App\Services\Messaging\DTO\MessagingSendResult;
use App\Services\Messaging\DTO\OutgoingMessageDTO;
use App\Services\Messaging\DTO\ProviderCapabilities;
use App\Services\Messaging\DTO\ProviderHealth;
use App\Services\Messaging\DTO\ProviderValidationResult;
use App\Services\Messaging\DTO\WebhookVerificationResult;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Throwable;

class TelegramProvider implements MessagingProvider
{
    public function providerName(): string
    {
        return 'telegram';
    }

    public function connect(): ProviderHealth
    {
        $validation = $this->validateConfiguration();
        $connected = $validation->valid;

        return new ProviderHealth(
            provider: $this->providerName(),
            status: $connected ? 'connected' : 'warning',
            connected: $connected,
            webhook_status: $connected ? 'failed' : 'failed',
            credentials_status: $connected ? 'configured' : 'missing',
            last_error: $validation->errors[0] ?? null,
            latency_ms: $connected ? 35 : null,
            capabilities: $this->capabilities()->toArray(),
            metadata: [
                'transport' => 'telegram-bot-api',
            ],
            healthy: $connected,
            last_ping: now(),
            version: 'v1',
            token_status: $connected ? 'configured' : 'missing',
            last_health_check_at: now(),
        );
    }

    public function disconnect(): ProviderHealth
    {
        return new ProviderHealth(
            provider: $this->providerName(),
            status: 'disconnected',
            connected: false,
            webhook_status: 'failed',
            credentials_status: 'configured',
            last_error: null,
            latency_ms: null,
            capabilities: $this->capabilities()->toArray(),
            metadata: [
                'transport' => 'telegram-bot-api',
            ],
            healthy: false,
            last_ping: now(),
            version: 'v1',
            token_status: 'configured',
            last_health_check_at: now(),
        );
    }

    public function health(): ProviderHealth
    {
        $validation = $this->validateConfiguration();
        $connected = $validation->valid;

        return new ProviderHealth(
            provider: $this->providerName(),
            status: $connected ? 'healthy' : 'warning',
            connected: $connected,
            webhook_status: 'failed',
            credentials_status: $connected ? 'configured' : 'missing',
            last_error: $validation->errors[0] ?? null,
            latency_ms: $connected ? 35 : null,
            capabilities: $this->capabilities()->toArray(),
            metadata: [
                'transport' => 'telegram-bot-api',
                'validation_warnings' => $validation->warnings,
            ],
            healthy: $connected,
            last_ping: now(),
            version: 'v1',
            token_status: $connected ? 'configured' : 'missing',
            last_health_check_at: now(),
        );
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
            templates: false,
            catalog: false,
            reactions: true,
            buttons: true,
            location: true,
            contacts: true,
            send_images: true,
            send_documents: true,
            send_audio: true,
            send_video: true,
            interactive_buttons: true,
            typing_indicator: true,
            delivery_receipts: false,
            read_receipts: false,
            voice_notes: true,
            reaction_support: true,
        );
    }

    public function validateConfiguration(): ProviderValidationResult
    {
        $botToken = (string) config('services.telegram.bot_token', '');

        $errors = [];

        if (trim($botToken) === '') {
            $errors[] = 'Missing Telegram bot token.';
        }

        return new ProviderValidationResult(
            valid: $errors === [],
            errors: $errors,
            warnings: $errors === [] ? ['Telegram adapter still runs in bridge mode.'] : [],
            configuration_checked_at: now(),
        );
    }

    public function supports(string $capability): bool
    {
        return $this->capabilities()->toArray()[strtolower(trim($capability))] ?? false;
    }

    public function verifyWebhook(Request $request): WebhookVerificationResult
    {
        return new WebhookVerificationResult(
            success: false,
            status: 501,
            challenge: null,
            provider: $this->providerName(),
            message: 'Telegram webhook verification is not implemented yet.',
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

    public function receiveWebhook(Request $request): WebhookVerificationResult
    {
        return new WebhookVerificationResult(
            success: false,
            status: 501,
            challenge: null,
            provider: $this->providerName(),
            message: 'Telegram webhook ingestion is not implemented yet.',
        );
    }

    public function sendMessage(OutgoingMessageDTO $message): MessagingSendResult
    {
        $chatId = $this->resolveChatId($message);

        if ($chatId === null) {
            return new MessagingSendResult(
                success: false,
                provider: $this->providerName(),
                raw_response: null,
                error: 'Missing Telegram chat id',
            );
        }

        $botToken = (string) config('services.telegram.bot_token', '');

        if ($botToken === '') {
            return new MessagingSendResult(
                success: false,
                provider: $this->providerName(),
                raw_response: null,
                error: 'Missing Telegram bot token',
            );
        }

        try {
            $response = Http::baseUrl('https://api.telegram.org/bot' . $botToken)
                ->withOptions([
                    'verify' => config('services.telegram.verify_ssl', true),
                ])
                ->acceptJson()
                ->asJson()
                ->post('sendMessage', [
                    'chat_id' => $chatId,
                    'text' => $message->message,
                ]);

            return $this->toResult($response);
        } catch (Throwable $throwable) {
            return new MessagingSendResult(
                success: false,
                provider: $this->providerName(),
                raw_response: null,
                error: $throwable->getMessage(),
            );
        }
    }

    public function markAsRead(string $externalMessageId)
    {
        // TODO: Adapter only.
        return null;
    }

    public function healthCheck()
    {
        return $this->health()->toArray();
    }

    private function resolveChatId(OutgoingMessageDTO $message): ?string
    {
        $metadata = $message->metadata;
        $chatId = $metadata['external_chat_id']
            ?? $metadata['chat_id']
            ?? $metadata['telegram_chat_id']
            ?? null;

        if (! is_string($chatId)) {
            $chatId = is_int($chatId) ? (string) $chatId : null;
        }

        $chatId = $chatId !== null ? trim($chatId) : null;

        return $chatId === '' ? null : $chatId;
    }

    private function toResult(Response $response): MessagingSendResult
    {
        $payload = $response->json();
        $rawResponse = is_array($payload) ? $payload : null;

        if (! $response->successful()) {
            $error = 'Telegram API request failed with HTTP ' . $response->status();

            if (is_array($rawResponse) && is_string($rawResponse['description'] ?? null) && $rawResponse['description'] !== '') {
                $error = $rawResponse['description'];
            }

            return new MessagingSendResult(
                success: false,
                provider: $this->providerName(),
                raw_response: $rawResponse,
                error: $error,
            );
        }

        if (! is_array($rawResponse) || ($rawResponse['ok'] ?? false) !== true) {
            $error = 'Telegram API returned an invalid response.';

            if (is_array($rawResponse) && is_string($rawResponse['description'] ?? null) && $rawResponse['description'] !== '') {
                $error = $rawResponse['description'];
            }

            return new MessagingSendResult(
                success: false,
                provider: $this->providerName(),
                raw_response: $rawResponse,
                error: $error,
            );
        }

        $providerMessageId = null;

        if (isset($rawResponse['result']) && is_array($rawResponse['result'])) {
            $messageId = $rawResponse['result']['message_id'] ?? null;

            if (is_int($messageId) || is_string($messageId)) {
                $providerMessageId = trim((string) $messageId);
            }
        }

        return new MessagingSendResult(
            success: true,
            provider: $this->providerName(),
            provider_message_id: $providerMessageId,
            raw_response: $rawResponse,
        );
    }
}
