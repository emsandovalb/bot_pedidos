<?php

namespace App\Services\Developer;

use App\Models\Branch;
use App\Models\Organization;

/**
 * Canales e identificadores compartidos por el simulador de webhooks y el
 * generador de datos del toolkit.
 */
class ToolkitChannels
{
    public const PHONE_NUMBER_ID_TEST = 'PHONE_NUMBER_ID_TEST';

    public function branchForProvider(Organization $organization, string $provider): Branch
    {
        $provider = strtolower(trim($provider));
        $channelType = $provider === 'telegram' ? Branch::CHANNEL_TYPE_TELEGRAM : Branch::CHANNEL_TYPE_WHATSAPP;
        $identifier = $provider === 'telegram'
            ? '@developer-toolkit-'.$organization->id
            : 'developer-toolkit-whatsapp-'.$organization->id;

        return Branch::query()->firstOrCreate(
            [
                'organization_id' => $organization->id,
                'channel_identifier' => $identifier,
            ],
            [
                'name' => $provider === 'telegram' ? 'Developer Toolkit Telegram' : 'Developer Toolkit WhatsApp',
                'channel_type' => $channelType,
                'status' => Branch::STATUS_ACTIVE,
            ]
        );
    }

    public function telegramChatId(string $seed): string
    {
        $digits = preg_replace('/\D+/', '', $seed) ?? '';

        if ($digits === '') {
            $digits = (string) random_int(1000, 9999);
        }

        return $digits;
    }

    public function telegramUsername(string $name): ?string
    {
        $username = strtolower(preg_replace('/[^a-z0-9]+/i', '', $name) ?? '');

        return $username !== '' ? $username.'_cr' : null;
    }

    public function stringValue(mixed $value): ?string
    {
        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }

        if (is_int($value) || is_float($value)) {
            return trim((string) $value);
        }

        return null;
    }
}
