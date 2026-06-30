<?php

namespace App\Services\Messaging\DTO;

use DateTimeInterface;

readonly class ProviderHealth
{
    public function __construct(
        public string $provider,
        public ?int $organization_id = null,
        public string $status = 'unknown',
        public bool $connected = false,
        public string $webhook_status = 'unknown',
        public string $credentials_status = 'unknown',
        public ?DateTimeInterface $last_received_message_at = null,
        public ?DateTimeInterface $last_sent_message_at = null,
        public ?string $last_error = null,
        public ?int $latency_ms = null,
        /** @var array<string, bool> */
        public array $capabilities = [],
        /** @var array<string, mixed> */
        public array $metadata = [],
        public bool $healthy = false,
        public ?DateTimeInterface $last_ping = null,
        public ?string $version = null,
        public string $token_status = 'unknown',
        public ?DateTimeInterface $last_health_check_at = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'organization_id' => $this->organization_id,
            'status' => $this->status,
            'connected' => $this->connected,
            'webhook_status' => $this->webhook_status,
            'credentials_status' => $this->credentials_status,
            'last_received_message_at' => $this->last_received_message_at,
            'last_sent_message_at' => $this->last_sent_message_at,
            'last_error' => $this->last_error,
            'latency_ms' => $this->latency_ms,
            'capabilities' => $this->capabilities,
            'metadata' => $this->metadata,
            'healthy' => $this->healthy,
            'last_ping' => $this->last_ping,
            'version' => $this->version,
            'token_status' => $this->token_status,
            'last_health_check_at' => $this->last_health_check_at,
        ];
    }
}
