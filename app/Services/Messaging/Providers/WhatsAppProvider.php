<?php

namespace App\Services\Messaging\Providers;

use App\Services\Messaging\Contracts\MessagingProvider;
use App\Services\Messaging\DTO\MessagingSendResult;
use App\Services\Messaging\DTO\OutgoingMessageDTO;
use App\Services\Messaging\DTO\ProviderCapabilities;
use App\Services\Messaging\DTO\ProviderHealth;
use App\Services\Messaging\DTO\ProviderValidationResult;
use App\Services\Messaging\DTO\WebhookVerificationResult;
use App\Services\WhatsAppConfigurationService;
use Illuminate\Http\Request;

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

    public function receiveWebhook(Request $request): WebhookVerificationResult
    {
        return new WebhookVerificationResult(
            success: false,
            status: 501,
            challenge: null,
            provider: $this->providerName(),
            message: 'WhatsApp webhook ingestion is not implemented yet.',
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
}
