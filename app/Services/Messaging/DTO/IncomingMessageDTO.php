<?php

namespace App\Services\Messaging\DTO;

use DateTimeInterface;

readonly class IncomingMessageDTO
{
    /**
     * @param  array<int, mixed>  $attachments
     * @param  array<string, mixed>  $raw_payload
     */
    public function __construct(
        public string $provider,
        public string $external_message_id,
        public string $external_chat_id,
        public ?string $customer_name,
        public ?string $customer_phone,
        public ?string $message,
        public DateTimeInterface $received_at,
        public array $raw_payload = [],
        public array $attachments = [],
    ) {
    }
}
