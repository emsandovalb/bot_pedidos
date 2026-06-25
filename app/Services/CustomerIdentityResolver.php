<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerIdentity;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CustomerIdentityResolver
{
    /**
     * @return array{
     *     customer: Customer,
     *     customer_identity: CustomerIdentity,
     *     match_type: string,
     *     confidence_score: int
     * }
     */
    public function resolve(
        int $organizationId,
        string $provider,
        ?string $externalUserId = null,
        ?string $externalChatId = null,
        ?string $providerUsername = null,
        ?string $phone = null,
        ?string $displayName = null,
        ?string $email = null,
        ?array $metadata = null,
    ): array {
        $provider = strtolower(trim($provider));

        if ($provider === '') {
            throw new InvalidArgumentException('Customer identity provider is required.');
        }

        $externalUserId = $this->normalizeNullableString($externalUserId);
        $externalChatId = $this->normalizeNullableString($externalChatId);
        $providerUsername = $this->normalizeNullableString($providerUsername);
        $phone = $this->normalizeNullableString($phone);
        $displayName = $this->normalizeNullableString($displayName);
        $email = $this->normalizeNullableString($email);
        $normalizedPhone = $this->normalizePhone($phone);
        $now = now();

        return DB::transaction(function () use (
            $organizationId,
            $provider,
            $externalUserId,
            $externalChatId,
            $providerUsername,
            $phone,
            $displayName,
            $email,
            $metadata,
            $normalizedPhone,
            $now,
        ): array {
            $exactIdentity = null;

            if ($externalUserId !== null) {
                $exactIdentity = CustomerIdentity::query()
                    ->where('organization_id', $organizationId)
                    ->where('provider', $provider)
                    ->where('external_user_id', $externalUserId)
                    ->first();
            }

            if ($exactIdentity === null && $externalChatId !== null) {
                $exactIdentity = CustomerIdentity::query()
                    ->where('organization_id', $organizationId)
                    ->where('provider', $provider)
                    ->where('external_chat_id', $externalChatId)
                    ->first();
            }

            if ($exactIdentity !== null) {
                $customer = $exactIdentity->customer ?? Customer::create([
                    'organization_id' => $organizationId,
                    'branch_id' => null,
                    'name' => $displayName,
                    'phone' => $phone,
                    'external_id' => null,
                ]);

                $this->syncCustomerFields($customer, $displayName, $phone);

                $exactIdentity->forceFill([
                    'customer_id' => $customer->id,
                    'provider_username' => blank($exactIdentity->provider_username) && filled($providerUsername)
                        ? $providerUsername
                        : $exactIdentity->provider_username,
                    'phone' => blank($exactIdentity->phone) && filled($phone)
                        ? $phone
                        : $exactIdentity->phone,
                    'normalized_phone' => blank($exactIdentity->normalized_phone) && filled($normalizedPhone)
                        ? $normalizedPhone
                        : $exactIdentity->normalized_phone,
                    'display_name' => blank($exactIdentity->display_name) && filled($displayName)
                        ? $displayName
                        : $exactIdentity->display_name,
                    'email' => blank($exactIdentity->email) && filled($email)
                        ? $email
                        : $exactIdentity->email,
                    'confidence_score' => 100,
                    'first_seen_at' => $exactIdentity->first_seen_at ?? $now,
                    'last_seen_at' => $now,
                    'metadata_json' => $this->mergeMetadata($exactIdentity->metadata_json, $metadata),
                ])->save();

                return [
                    'customer' => $customer->fresh(),
                    'customer_identity' => $exactIdentity->fresh(['customer']),
                    'match_type' => 'exact_provider_match',
                    'confidence_score' => 100,
                ];
            }

            $phoneMatches = $normalizedPhone === null
                ? collect()
                : CustomerIdentity::query()
                    ->where('organization_id', $organizationId)
                    ->where('normalized_phone', $normalizedPhone)
                    ->get();

            if ($phoneMatches->count() === 1) {
                $matchedIdentity = $phoneMatches->first();
                $customer = $matchedIdentity->customer;

                if ($customer === null) {
                    $customer = Customer::create([
                        'organization_id' => $organizationId,
                        'branch_id' => null,
                        'name' => $displayName,
                        'phone' => $phone,
                        'external_id' => null,
                    ]);

                    $matchedIdentity->forceFill([
                        'customer_id' => $customer->id,
                    ])->save();
                }

                $this->syncCustomerFields($customer, $displayName, $phone);

                $identity = $this->createIdentity(
                    organizationId: $organizationId,
                    customerId: $customer->id,
                    provider: $provider,
                    externalUserId: $externalUserId,
                    externalChatId: $externalChatId,
                    providerUsername: $providerUsername,
                    phone: $phone,
                    normalizedPhone: $normalizedPhone,
                    displayName: $displayName,
                    email: $email,
                    metadata: $metadata,
                    confidenceScore: 90,
                    now: $now,
                );

                return [
                    'customer' => $customer->fresh(),
                    'customer_identity' => $identity->fresh(['customer']),
                    'match_type' => 'phone_match',
                    'confidence_score' => 90,
                ];
            }

            if ($phoneMatches->count() > 1) {
                $customer = Customer::create([
                    'organization_id' => $organizationId,
                    'branch_id' => null,
                    'name' => $displayName,
                    'phone' => $phone,
                    'external_id' => null,
                ]);

                $identity = $this->createIdentity(
                    organizationId: $organizationId,
                    customerId: $customer->id,
                    provider: $provider,
                    externalUserId: $externalUserId,
                    externalChatId: $externalChatId,
                    providerUsername: $providerUsername,
                    phone: $phone,
                    normalizedPhone: $normalizedPhone,
                    displayName: $displayName,
                    email: $email,
                    metadata: $metadata,
                    confidenceScore: 50,
                    now: $now,
                );

                return [
                    'customer' => $customer->fresh(),
                    'customer_identity' => $identity->fresh(['customer']),
                    'match_type' => 'ambiguous_phone',
                    'confidence_score' => 50,
                ];
            }

            $customer = Customer::create([
                'organization_id' => $organizationId,
                'branch_id' => null,
                'name' => $displayName,
                'phone' => $phone,
                'external_id' => null,
            ]);

            $identity = $this->createIdentity(
                organizationId: $organizationId,
                customerId: $customer->id,
                provider: $provider,
                externalUserId: $externalUserId,
                externalChatId: $externalChatId,
                providerUsername: $providerUsername,
                phone: $phone,
                normalizedPhone: $normalizedPhone,
                displayName: $displayName,
                email: $email,
                metadata: $metadata,
                confidenceScore: 100,
                now: $now,
            );

            return [
                'customer' => $customer->fresh(),
                'customer_identity' => $identity->fresh(['customer']),
                'match_type' => 'new_customer',
                'confidence_score' => 100,
            ];
        });
    }

    private function createIdentity(
        int $organizationId,
        int $customerId,
        string $provider,
        ?string $externalUserId,
        ?string $externalChatId,
        ?string $providerUsername,
        ?string $phone,
        ?string $normalizedPhone,
        ?string $displayName,
        ?string $email,
        ?array $metadata,
        int $confidenceScore,
        mixed $now,
    ): CustomerIdentity {
        $identity = CustomerIdentity::create([
            'organization_id' => $organizationId,
            'customer_id' => $customerId,
            'provider' => $provider,
            'external_user_id' => $externalUserId,
            'external_chat_id' => $externalChatId,
            'provider_username' => $providerUsername,
            'phone' => $phone,
            'normalized_phone' => $normalizedPhone,
            'email' => $email,
            'display_name' => $displayName,
            'confidence_score' => $confidenceScore,
            'is_primary' => false,
            'metadata_json' => $this->mergeMetadata(null, $metadata),
            'first_seen_at' => $now,
            'last_seen_at' => $now,
        ]);

        return $identity;
    }

    private function normalizePhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $value = trim($phone);

        if ($value === '') {
            return null;
        }

        $value = preg_replace('/[\s\-\(\)]+/u', '', $value) ?? $value;

        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, '+')) {
            $digits = preg_replace('/\D+/u', '', substr($value, 1)) ?? '';

            return $digits === '' ? null : '+' . $digits;
        }

        $digits = preg_replace('/\D+/u', '', $value) ?? '';

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 8) {
            return '+506' . $digits;
        }

        if (str_starts_with($digits, '506') && strlen($digits) === 11) {
            return '+' . $digits;
        }

        return '+' . $digits;
    }

    private function normalizeNullableString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<string, mixed>|null  $existingMetadata
     * @param  array<string, mixed>|null  $incomingMetadata
     * @return array<string, mixed>|null
     */
    private function mergeMetadata(?array $existingMetadata, ?array $incomingMetadata): ?array
    {
        if ($existingMetadata === null && $incomingMetadata === null) {
            return null;
        }

        $existingMetadata ??= [];
        $incomingMetadata ??= [];

        return array_replace_recursive($incomingMetadata, $existingMetadata);
    }

    private function syncCustomerFields(Customer $customer, ?string $displayName, ?string $phone): void
    {
        $updates = [];

        if (blank($customer->name) && filled($displayName)) {
            $updates['name'] = $displayName;
        }

        if (blank($customer->phone) && filled($phone)) {
            $updates['phone'] = $phone;
        }

        if ($updates !== []) {
            $customer->forceFill($updates)->save();
        }
    }
}
