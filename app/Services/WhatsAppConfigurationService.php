<?php

namespace App\Services;

use App\Models\ChannelConnection;
use App\Services\Messaging\DTO\ProviderValidationResult;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class WhatsAppConfigurationService
{
    private const REQUIRED_FIELDS = [
        'provider_app_id',
        'provider_app_secret',
        'provider_verify_token',
        'provider_access_token',
        'provider_phone_number_id',
        'provider_business_account_id',
    ];

    private const SENSITIVE_FIELDS = [
        'provider_app_secret',
        'provider_access_token',
        'provider_verify_token',
        'provider_webhook_secret',
    ];

    public function loadConfiguration(int $organizationId): ChannelConnection
    {
        return ChannelConnection::query()->firstOrCreate(
            [
                'organization_id' => $organizationId,
                'channel' => ChannelConnection::CHANNEL_WHATSAPP,
            ],
            [
                'status' => ChannelConnection::STATUS_DRAFT,
                'provider_status' => ChannelConnection::STATUS_DRAFT,
                'provider_configuration_status' => ChannelConnection::STATUS_DRAFT,
                'provider_metadata_json' => [
                    'source' => 'whatsapp_configuration',
                ],
            ]
        );
    }

    public function resolveWebhookConfiguration(?string $verifyToken = null): ?ChannelConnection
    {
        $query = ChannelConnection::query()
            ->where('channel', ChannelConnection::CHANNEL_WHATSAPP)
            ->orderByDesc('updated_at')
            ->orderByDesc('id');

        if ($verifyToken !== null && $verifyToken !== '') {
            $query->whereNotNull('provider_verify_token');
        }

        $connections = $query->get();

        if ($verifyToken === null || $verifyToken === '') {
            return $connections->first(fn (ChannelConnection $connection): bool => $this->isReadyForWebhook($connection));
        }

        return $connections->first(function (ChannelConnection $connection) use ($verifyToken): bool {
            return $this->isReadyForWebhook($connection)
                && hash_equals((string) $connection->provider_verify_token, $verifyToken);
        });
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function saveConfiguration(int $organizationId, array $input): ChannelConnection
    {
        $connection = $this->loadConfiguration($organizationId);
        $payload = $this->normalizedPayload($input);

        foreach (self::SENSITIVE_FIELDS as $field) {
            if (blank($payload[$field] ?? null) && ! blank($connection->{$field})) {
                $payload[$field] = $connection->{$field};
            }
        }

        $connection->fill($payload);
        $this->validateConfiguration($connection);

        return $connection->refresh();
    }

    public function validateConfiguration(ChannelConnection $connection): ProviderValidationResult
    {
        $payload = $this->configurationPayload($connection);
        $errors = [];
        $warnings = [];

        $requiredValues = Arr::only($payload, self::REQUIRED_FIELDS);
        $filledRequired = collect($requiredValues)->filter(fn ($value): bool => blank($value))->keys()->all();

        if ($filledRequired !== []) {
            $status = $this->allFieldsBlank($payload)
                ? ChannelConnection::STATUS_DRAFT
                : ChannelConnection::STATUS_MISSING_CREDENTIALS;

            $errors[] = 'Missing credentials: ' . implode(', ', $this->humanizeFields($filledRequired)) . '.';
        } else {
            $this->validateFormats($payload, $errors, $warnings);

            $status = $errors === []
                ? ChannelConnection::STATUS_READY_FOR_VERIFICATION
                : ChannelConnection::STATUS_ERROR;
        }

        $connection->forceFill([
            'provider_status' => match ($status) {
                ChannelConnection::STATUS_READY_FOR_VERIFICATION => ChannelConnection::STATUS_READY_FOR_VERIFICATION,
                ChannelConnection::STATUS_DRAFT => ChannelConnection::STATUS_DRAFT,
                ChannelConnection::STATUS_MISSING_CREDENTIALS => ChannelConnection::STATUS_WARNING,
                ChannelConnection::STATUS_ERROR => ChannelConnection::STATUS_ERROR,
                default => $status,
            },
            'provider_configuration_status' => $status,
            'provider_last_validation_at' => now(),
            'provider_last_validation_error' => $errors[0] ?? null,
            'provider_metadata_json' => array_merge(
                $connection->provider_metadata_json ?? [],
                [
                    'required_fields_present' => $filledRequired === [],
                    'missing_fields' => $filledRequired,
                    'ready_for_webhook' => $errors === [],
                    'validation_warnings' => $warnings,
                ]
            ),
        ]);
        $connection->save();

        return new ProviderValidationResult(
            valid: $errors === [],
            errors: $errors,
            warnings: $warnings,
            configuration_checked_at: $connection->provider_last_validation_at,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function maskSensitiveData(ChannelConnection $connection): array
    {
        return [
            'provider_app_id' => $connection->provider_app_id,
            'provider_app_secret' => $this->maskValue($connection->provider_app_secret),
            'provider_access_token' => $this->maskValue($connection->provider_access_token),
            'provider_verify_token' => $this->maskValue($connection->provider_verify_token),
            'provider_webhook_secret' => $this->maskValue($connection->provider_webhook_secret),
            'provider_phone_number_id' => $connection->provider_phone_number_id,
            'provider_business_account_id' => $connection->provider_business_account_id,
            'provider_display_phone' => $connection->provider_display_phone,
            'provider_api_version' => $connection->provider_api_version,
            'provider_business_name' => $connection->provider_business_name,
            'provider_business_timezone' => $connection->provider_business_timezone,
            'provider_business_country' => $connection->provider_business_country,
            'provider_status' => $connection->provider_status,
            'provider_configuration_status' => $connection->provider_configuration_status,
            'provider_last_validation_at' => $connection->provider_last_validation_at,
            'provider_last_validation_error' => $connection->provider_last_validation_error,
            'provider_metadata_json' => $connection->provider_metadata_json ?? [],
        ];
    }

    public function isReadyForWebhook(ChannelConnection $connection): bool
    {
        return in_array($connection->provider_configuration_status, [
            ChannelConnection::STATUS_READY_FOR_VERIFICATION,
            ChannelConnection::STATUS_VERIFIED,
            ChannelConnection::STATUS_CONNECTED,
        ], true)
            && $this->missingRequiredFields($this->configurationPayload($connection)) === [];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function normalizedPayload(array $input): array
    {
        $payload = Arr::only($input, [
            'provider_app_id',
            'provider_app_secret',
            'provider_access_token',
            'provider_verify_token',
            'provider_webhook_secret',
            'provider_phone_number_id',
            'provider_business_account_id',
            'provider_display_phone',
            'provider_api_version',
            'provider_business_name',
            'provider_business_timezone',
            'provider_business_country',
            'provider_status',
            'provider_configuration_status',
            'provider_metadata_json',
        ]);

        foreach ($payload as $key => $value) {
            if (is_string($value)) {
                $payload[$key] = trim($value);
            }
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function configurationPayload(ChannelConnection $connection): array
    {
        return [
            'provider_app_id' => $connection->provider_app_id,
            'provider_app_secret' => $connection->provider_app_secret,
            'provider_access_token' => $connection->provider_access_token,
            'provider_verify_token' => $connection->provider_verify_token,
            'provider_webhook_secret' => $connection->provider_webhook_secret,
            'provider_phone_number_id' => $connection->provider_phone_number_id,
            'provider_business_account_id' => $connection->provider_business_account_id,
            'provider_display_phone' => $connection->provider_display_phone,
            'provider_api_version' => $connection->provider_api_version,
            'provider_business_name' => $connection->provider_business_name,
            'provider_business_timezone' => $connection->provider_business_timezone,
            'provider_business_country' => $connection->provider_business_country,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $errors
     * @param  array<int, string>  $warnings
     */
    private function validateFormats(array $payload, array &$errors, array &$warnings): void
    {
        $fieldRules = [
            'provider_app_id' => fn (string $value): bool => (bool) preg_match('/^\d{5,25}$/', $value),
            'provider_phone_number_id' => fn (string $value): bool => (bool) preg_match('/^\d{5,25}$/', $value),
            'provider_business_account_id' => fn (string $value): bool => (bool) preg_match('/^\d{5,25}$/', $value),
            'provider_app_secret' => fn (string $value): bool => strlen($value) >= 8 && strlen($value) <= 255,
            'provider_access_token' => fn (string $value): bool => strlen($value) >= 8 && strlen($value) <= 255,
            'provider_verify_token' => fn (string $value): bool => strlen($value) >= 8 && strlen($value) <= 255,
            'provider_webhook_secret' => fn (string $value): bool => $value === '' || (strlen($value) >= 8 && strlen($value) <= 255),
            'provider_api_version' => fn (string $value): bool => $value === '' || (bool) preg_match('/^v\d+(?:\.\d+)?$/', $value),
            'provider_business_country' => fn (string $value): bool => $value === '' || (bool) preg_match('/^[A-Z]{2}$/', $value),
            'provider_business_timezone' => fn (string $value): bool => $value === '' || in_array($value, timezone_identifiers_list(), true),
            'provider_business_name' => fn (string $value): bool => $value === '' || strlen($value) <= 120,
            'provider_display_phone' => fn (string $value): bool => $value === '' || strlen($value) <= 32,
        ];

        foreach ($fieldRules as $field => $rule) {
            $value = (string) ($payload[$field] ?? '');

            if (! $rule($value)) {
                $errors[] = $this->humanizeField($field) . ' has an invalid format or length.';
            }
        }

        if ($errors === [] && blank($payload['provider_webhook_secret'] ?? null)) {
            $warnings[] = 'Webhook secret is optional but recommended for signature verification.';
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    private function missingRequiredFields(array $payload): array
    {
        return collect(self::REQUIRED_FIELDS)
            ->filter(fn (string $field): bool => blank($payload[$field] ?? null))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function allFieldsBlank(array $payload): bool
    {
        return collect($payload)->every(fn ($value): bool => blank($value));
    }

    private function maskValue(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = trim($value);
        $length = strlen($value);

        if ($length <= 4) {
            return str_repeat('*', max(1, $length));
        }

        return str_repeat('*', $length - 4) . substr($value, -4);
    }

    /**
     * @param  array<int, string>  $fields
     * @return array<int, string>
     */
    private function humanizeFields(array $fields): array
    {
        return array_map(fn (string $field): string => $this->humanizeField($field), $fields);
    }

    private function humanizeField(string $field): string
    {
        return Str::of($field)
            ->after('provider_')
            ->replace('_', ' ')
            ->headline()
            ->toString();
    }
}
