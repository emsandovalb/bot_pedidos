<?php

namespace App\Services\Messaging\DTO;

readonly class OutgoingMessageDTO
{
    /**
     * @param  array<int, mixed>  $attachments
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $provider,
        public string $phone,
        public string $message,
        public array $attachments = [],
        public array $metadata = [],
    ) {
    }
}
