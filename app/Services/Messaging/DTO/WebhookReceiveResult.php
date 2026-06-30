<?php

namespace App\Services\Messaging\DTO;

readonly class WebhookReceiveResult
{
    public function __construct(
        public bool $success,
        public int $status,
        public ?string $challenge = null,
        public string $provider = 'unknown',
        public ?string $message = null,
        public int $processed_count = 0,
        public int $ignored_count = 0,
        public int $failed_count = 0,
        public ?int $organization_id = null,
        /** @var array<string, mixed> */
        public array $metadata = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'status' => $this->status,
            'challenge' => $this->challenge,
            'provider' => $this->provider,
            'message' => $this->message,
            'processed_count' => $this->processed_count,
            'ignored_count' => $this->ignored_count,
            'failed_count' => $this->failed_count,
            'organization_id' => $this->organization_id,
            'metadata' => $this->metadata,
        ];
    }
}
