<?php

namespace App\Services\Fulfillment\DTO;

final class FulfillmentIntent
{
    /**
     * @param array<int, string> $matchedPhrases
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly ?string $requested_date = null,
        public readonly ?string $requested_time_window = null,
        public readonly ?string $delivery_method = null,
        public readonly ?string $payment_method = null,
        public readonly ?string $delivery_address = null,
        public readonly ?string $delivery_notes = null,
        public readonly ?string $priority_level = null,
        public readonly ?string $priority_reason = null,
        public readonly int $confidence = 0,
        public readonly array $matched_phrases = [],
        public readonly array $metadata = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'requested_date' => $this->requested_date,
            'requested_time_window' => $this->requested_time_window,
            'delivery_method' => $this->delivery_method,
            'payment_method' => $this->payment_method,
            'delivery_address' => $this->delivery_address,
            'delivery_notes' => $this->delivery_notes,
            'priority_level' => $this->priority_level,
            'priority_reason' => $this->priority_reason,
            'confidence' => $this->confidence,
            'matched_phrases' => $this->matched_phrases,
            'metadata' => $this->metadata,
        ];
    }
}
