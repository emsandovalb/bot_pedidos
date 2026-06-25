<?php

namespace App\Services\Messaging\Manager;

use App\Services\Messaging\Contracts\MessagingProvider;
use App\Services\Messaging\Providers\TelegramProvider;
use App\Services\Messaging\Providers\WhatsAppCloudProvider;
use InvalidArgumentException;

class MessagingManager
{
    public function driver(?string $provider = null): MessagingProvider
    {
        $provider = strtolower(trim($provider ?: (string) config('messaging.default', 'telegram')));

        return match ($provider) {
            'telegram' => new TelegramProvider(),
            'whatsapp' => new WhatsAppCloudProvider(),
            default => throw new InvalidArgumentException("Unsupported messaging provider [{$provider}]."),
        };
    }
}
