<?php

namespace App\Services\Messaging\DTO;

readonly class WebhookVerificationResult
{
    public function __construct(
        public bool $success,
        public int $status,
        public ?string $challenge = null,
        public string $provider = 'unknown',
        public ?string $message = null,
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
        ];
    }
}
