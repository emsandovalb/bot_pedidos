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
        public DateTimeInterface $received_at,
        public ?string $external_user_id = null,
        public ?string $provider_username = null,
        public ?string $customer_name = null,
        public ?string $customer_phone = null,
        public ?string $email = null,
        public ?array $metadata = null,
        public ?string $message = null,
        public array $raw_payload = [],
        public array $attachments = [],
    ) {
    }
}
