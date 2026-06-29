<?php

namespace App\Services\Messaging\DTO;

readonly class MessagingSendResult
{
    /**
     * @param  array<string, mixed>|null  $raw_response
     */
    public function __construct(
        public bool $success,
        public string $provider,
        public ?string $provider_message_id = null,
        public ?array $raw_response = null,
        public ?string $error = null,
    ) {
    }
}
