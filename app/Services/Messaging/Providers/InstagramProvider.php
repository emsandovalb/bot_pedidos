<?php

namespace App\Services\Messaging\Providers;

use App\Services\Messaging\Contracts\MessagingProvider;
use App\Services\Messaging\DTO\MessagingSendResult;
use App\Services\Messaging\DTO\OutgoingMessageDTO;
use App\Services\Messaging\DTO\ProviderCapabilities;
use App\Services\Messaging\DTO\ProviderHealth;
use App\Services\Messaging\DTO\ProviderValidationResult;
use App\Services\Messaging\DTO\WebhookVerificationResult;
use Illuminate\Http\Request;

class InstagramProvider implements MessagingProvider
{
    public function providerName(): string
    {
        return 'instagram';
    }

    public function connect(): ProviderHealth
    {
        return $this->placeholderHealth('coming_soon');
    }

    public function disconnect(): ProviderHealth
    {
        return $this->placeholderHealth('disconnected', false);
    }

    public function health(): ProviderHealth
    {
        return $this->placeholderHealth('coming_soon');
    }

    public function capabilities(): ProviderCapabilities
    {
        return new ProviderCapabilities(
            provider: $this->providerName(),
        );
    }

    public function validateConfiguration(): ProviderValidationResult
    {
        return new ProviderValidationResult(
            valid: false,
            errors: ['Instagram provider is coming soon.'],
            warnings: ['No Meta integration is enabled for Instagram yet.'],
            configuration_checked_at: now(),
        );
    }

    public function supports(string $capability): bool
    {
        return false;
    }

    public function verifyWebhook(Request $request): WebhookVerificationResult
    {
        return new WebhookVerificationResult(
            success: false,
            status: 501,
            challenge: null,
            provider: $this->providerName(),
            message: 'Instagram webhook verification is not implemented yet.',
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
            message: 'Instagram webhook ingestion is not implemented yet.',
        );
    }

    public function sendMessage(OutgoingMessageDTO $message): MessagingSendResult
    {
        return new MessagingSendResult(
            success: false,
            provider: $this->providerName(),
            raw_response: null,
            error: 'Instagram provider is coming soon.',
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

    private function placeholderHealth(string $status, bool $connected = false): ProviderHealth
    {
        return new ProviderHealth(
            provider: $this->providerName(),
            status: $status,
            connected: $connected,
            webhook_status: 'failed',
            credentials_status: 'missing',
            last_error: 'Instagram provider is coming soon.',
            latency_ms: null,
            capabilities: $this->capabilities()->toArray(),
            metadata: [
                'provider_type' => 'placeholder',
                'launch_state' => 'coming_soon',
            ],
            healthy: false,
            last_ping: now(),
            version: 'v1-placeholder',
            token_status: 'missing',
            last_health_check_at: now(),
        );
    }
}
