<?php

namespace App\Services\Messaging\DTO;

use DateTimeInterface;

readonly class ProviderValidationResult
{
    /**
     * @param  array<int, string>  $errors
     * @param  array<int, string>  $warnings
     */
    public function __construct(
        public bool $valid,
        public array $errors = [],
        public array $warnings = [],
        public ?DateTimeInterface $configuration_checked_at = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'valid' => $this->valid,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'configuration_checked_at' => $this->configuration_checked_at,
        ];
    }
}
