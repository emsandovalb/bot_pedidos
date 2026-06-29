<?php

namespace App\Services\Messaging\Providers;

use App\Services\Messaging\Contracts\MessagingProvider;
use App\Services\Messaging\DTO\MessagingSendResult;
use App\Services\Messaging\DTO\OutgoingMessageDTO;
use App\Services\Messaging\Exceptions\NotImplementedException;
use Illuminate\Http\Request;

class WhatsAppCloudProvider implements MessagingProvider
{
    public function verifyWebhook(Request $request): bool
    {
        throw new NotImplementedException('WhatsApp Cloud webhook verification is not implemented yet.');
    }

    public function receiveWebhook(Request $request)
    {
        throw new NotImplementedException('WhatsApp Cloud webhook ingestion is not implemented yet.');
    }

    public function sendMessage(OutgoingMessageDTO $message): MessagingSendResult
    {
        return new MessagingSendResult(
            success: false,
            provider: $this->providerName(),
            raw_response: null,
            error: 'WhatsApp Cloud sendMessage is not implemented yet.',
        );
    }

    public function markAsRead(string $externalMessageId)
    {
        throw new NotImplementedException('WhatsApp Cloud markAsRead is not implemented yet.');
    }

    public function healthCheck()
    {
        return [
            'provider' => $this->providerName(),
            'status' => 'not_implemented',
        ];
    }

    public function providerName(): string
    {
        return 'whatsapp';
    }
}
