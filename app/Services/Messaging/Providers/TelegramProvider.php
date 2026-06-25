<?php

namespace App\Services\Messaging\Providers;

use App\Services\Messaging\Contracts\MessagingProvider;
use App\Services\Messaging\DTO\OutgoingMessageDTO;
use Illuminate\Http\Request;

class TelegramProvider implements MessagingProvider
{
    public function verifyWebhook(Request $request): bool
    {
        return true;
    }

    public function receiveWebhook(Request $request)
    {
        // TODO: Adapter only. Existing Telegram ingestion stays on the current workflow.
        return null;
    }

    public function sendMessage(OutgoingMessageDTO $message)
    {
        // TODO: Adapter only. Existing Telegram sending stays on the current workflow.
        return null;
    }

    public function markAsRead(string $externalMessageId)
    {
        // TODO: Adapter only.
        return null;
    }

    public function healthCheck()
    {
        return [
            'provider' => $this->providerName(),
            'status' => 'ok',
            'mode' => 'adapter',
        ];
    }

    public function providerName(): string
    {
        return 'telegram';
    }
}
