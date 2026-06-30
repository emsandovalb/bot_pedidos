<?php

namespace App\Services\Messaging\Manager;

use App\Models\ChannelConnection;
use App\Services\Messaging\Contracts\MessagingProvider;
use App\Services\Messaging\DTO\ProviderCapabilities;
use App\Services\Messaging\DTO\ProviderHealth;
use App\Services\Messaging\DTO\ProviderValidationResult;
use App\Services\Messaging\Providers\InstagramProvider;
use App\Services\Messaging\Providers\TelegramProvider;
use App\Services\Messaging\Providers\WhatsAppCloudProvider;
use App\Services\WhatsAppConfigurationService;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class ProviderLifecycleManager
{
    public function __construct(
        private readonly ?WhatsAppConfigurationService $whatsappConfigurationService = null,
    ) {
    }

    public function driver(?string $provider = null): MessagingProvider
    {
        $provider = strtolower(trim($provider ?: (string) config('messaging.default', 'telegram')));

        return match ($provider) {
            'telegram' => new TelegramProvider(),
            'whatsapp' => new WhatsAppCloudProvider(),
            'instagram' => new InstagramProvider(),
            default => throw new InvalidArgumentException("Unsupported messaging provider [{$provider}]."),
        };
    }

    public function connect(?string $provider = null, ?int $organizationId = null): ProviderHealth
    {
        return $this->safeHealth($provider, 'connect', $organizationId, false);
    }

    public function disconnect(?string $provider = null, ?int $organizationId = null): ProviderHealth
    {
        return $this->safeHealth($provider, 'disconnect', $organizationId, false);
    }

    public function validate(?string $provider = null, ?int $organizationId = null): ProviderValidationResult|ProviderHealth
    {
        if ($this->providerName($provider) === ChannelConnection::CHANNEL_WHATSAPP && $organizationId !== null) {
            return $this->whatsappHealth($organizationId);
        }

        return $this->safeProvider($provider)?->validateConfiguration()
            ?? $this->unknownValidation($provider);
    }

    public function health(?string $provider = null, ?int $organizationId = null): ProviderHealth
    {
        if ($this->providerName($provider) === ChannelConnection::CHANNEL_WHATSAPP && $organizationId !== null) {
            return $this->whatsappHealth($organizationId);
        }

        return $this->safeProvider($provider)?->health()
            ?? $this->unknownHealth($provider);
    }

    public function refreshHealth(?string $provider = null, ?int $organizationId = null): ProviderHealth
    {
        if ($this->providerName($provider) === ChannelConnection::CHANNEL_WHATSAPP && $organizationId !== null) {
            return $this->whatsappHealth($organizationId);
        }

        return $this->safeHealth($provider, 'health', $organizationId, false);
    }

    public function getCapabilities(?string $provider = null): ProviderCapabilities
    {
        return $this->safeProvider($provider)?->capabilities()
            ?? $this->unknownCapabilities($provider);
    }

    /**
     * @return array<int, ProviderHealth>
     */
    public function refreshConnected(?int $organizationId = null): array
    {
        return ChannelConnection::query()
            ->where('status', ChannelConnection::STATUS_CONNECTED)
            ->when($organizationId !== null, fn ($query) => $query->where('organization_id', $organizationId))
            ->get()
            ->groupBy('channel')
            ->map(function (Collection $connections, string $provider) use ($organizationId): ProviderHealth {
                return $this->refreshHealth($provider, $organizationId);
            })
            ->values()
            ->all();
    }

    private function safeProvider(?string $provider = null): ?MessagingProvider
    {
        try {
            return $this->driver($provider);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private function safeHealth(?string $provider, string $method, ?int $organizationId, bool $forceConnected): ProviderHealth
    {
        if ($this->providerName($provider) === ChannelConnection::CHANNEL_WHATSAPP && $organizationId !== null) {
            return $this->whatsappHealth($organizationId);
        }

        $safeProvider = $this->safeProvider($provider);

        if ($safeProvider === null) {
            return $this->unknownHealth($provider);
        }

        $health = match ($method) {
            'connect' => $safeProvider->connect(),
            'disconnect' => $safeProvider->disconnect(),
            default => $safeProvider->health(),
        };

        return $this->persistHealth($provider, $health, $organizationId, $forceConnected);
    }

    private function persistHealth(?string $provider, ProviderHealth $health, ?int $organizationId = null, bool $forceConnected = false): ProviderHealth
    {
        if ($organizationId === null) {
            return $health;
        }

        $providerName = $this->providerName($provider);
        $connection = ChannelConnection::query()->firstOrNew([
            'organization_id' => $organizationId,
            'channel' => $providerName,
        ]);

        $metadata = array_merge($connection->metadata_json ?? [], $health->metadata);

        $connection->fill([
            'provider' => $providerName,
            'version' => $health->version,
            'provider_version' => $health->version,
            'status' => match ($health->status) {
                'connected', 'healthy' => ChannelConnection::STATUS_CONNECTED,
                'disconnected' => ChannelConnection::STATUS_PENDING,
                default => $connection->status ?? ChannelConnection::STATUS_PENDING,
            },
            'health_status' => $health->status,
            'webhook_status' => $health->webhook_status,
            'credentials_status' => $health->credentials_status,
            'last_health_check_at' => now(),
            'health_checked_at' => now(),
            'last_error' => $health->last_error,
            'last_ping' => $health->last_ping ?? now(),
            'last_received_message_at' => $health->last_received_message_at,
            'last_sent_message_at' => $health->last_sent_message_at,
            'last_message_received_at' => $health->last_received_message_at,
            'last_message_sent_at' => $health->last_sent_message_at,
            'connected_at' => $health->connected ? ($connection->connected_at ?? now()) : $connection->connected_at,
            'last_sync_at' => now(),
        ]);

        $connection->metadata_json = $metadata;
        $connection->save();

        return new ProviderHealth(
            provider: $health->provider,
            organization_id: $organizationId,
            status: $health->status,
            connected: $health->connected,
            webhook_status: $health->webhook_status,
            credentials_status: $health->credentials_status,
            last_received_message_at: $health->last_received_message_at,
            last_sent_message_at: $health->last_sent_message_at,
            last_error: $health->last_error,
            latency_ms: $health->latency_ms,
            capabilities: $health->capabilities,
            metadata: $metadata,
            healthy: $health->healthy,
            last_ping: $health->last_ping,
            version: $health->version,
            token_status: $health->token_status,
            last_health_check_at: now(),
        );
    }

    private function unknownCapabilities(?string $provider = null): ProviderCapabilities
    {
        return new ProviderCapabilities(
            provider: $this->providerName($provider),
        );
    }

    private function unknownHealth(?string $provider = null): ProviderHealth
    {
        $providerName = $this->providerName($provider);

        return new ProviderHealth(
            provider: $providerName,
            status: 'unknown',
            connected: false,
            webhook_status: 'unknown',
            credentials_status: 'unknown',
            last_error: 'Unsupported messaging provider [' . $providerName . '].',
            latency_ms: null,
            capabilities: $this->unknownCapabilities($providerName)->toArray(),
            metadata: [],
            healthy: false,
            last_ping: now(),
            version: null,
            token_status: 'unknown',
            last_health_check_at: now(),
        );
    }

    private function unknownValidation(?string $provider = null): ProviderValidationResult
    {
        $providerName = $this->providerName($provider);

        return new ProviderValidationResult(
            valid: false,
            errors: ['Unsupported messaging provider [' . $providerName . '].'],
            warnings: [],
            configuration_checked_at: now(),
        );
    }

    private function whatsappHealth(int $organizationId): ProviderHealth
    {
        $service = $this->whatsappConfigurationService ?? app(WhatsAppConfigurationService::class);
        $connection = $service->loadConfiguration($organizationId);
        $validation = $service->validateConfiguration($connection);
        $readyForWebhook = $service->isReadyForWebhook($connection);

        $healthStatus = $validation->valid ? 'ready' : 'warning';
        $webhookStatus = $validation->valid ? 'waiting_meta_verification' : 'missing_credentials';
        $credentialsStatus = $validation->valid ? 'configured' : 'missing';

        $connection->forceFill([
            'health_status' => $healthStatus,
            'webhook_status' => $webhookStatus,
            'credentials_status' => $credentialsStatus,
            'last_health_check_at' => now(),
            'health_checked_at' => now(),
            'last_error' => $validation->errors[0] ?? null,
            'last_ping' => now(),
            'version' => $connection->provider_api_version ?? $connection->provider_version ?? $connection->version,
            'provider_version' => $connection->provider_api_version ?? $connection->provider_version ?? $connection->version,
            'last_sync_at' => now(),
        ]);
        $connection->save();

        return new ProviderHealth(
            provider: ChannelConnection::CHANNEL_WHATSAPP,
            organization_id: $organizationId,
            status: $healthStatus,
            connected: false,
            webhook_status: $webhookStatus,
            credentials_status: $credentialsStatus,
            last_error: $validation->errors[0] ?? null,
            latency_ms: null,
            capabilities: $this->driver(ChannelConnection::CHANNEL_WHATSAPP)->capabilities()->toArray(),
            metadata: [
                'configuration_status' => $connection->provider_configuration_status,
                'provider_status' => $connection->provider_status,
                'ready_for_webhook' => $readyForWebhook,
                'validation_warnings' => $validation->warnings,
                'provider_metadata_json' => $connection->provider_metadata_json ?? [],
            ],
            healthy: $validation->valid,
            last_ping: now(),
            version: $connection->provider_api_version ?? $connection->provider_version ?? $connection->version,
            token_status: $validation->valid ? 'configured' : 'missing',
            last_health_check_at: now(),
        );
    }

    private function providerName(?string $provider = null): string
    {
        $provider = strtolower(trim($provider ?: (string) config('messaging.default', 'telegram')));

        return $provider !== '' ? $provider : 'unknown';
    }
}
