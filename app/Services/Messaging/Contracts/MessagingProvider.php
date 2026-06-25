<?php

namespace App\Services\Messaging\Contracts;

use App\Services\Messaging\DTO\OutgoingMessageDTO;
use Illuminate\Http\Request;

interface MessagingProvider
{
    public function verifyWebhook(Request $request): bool;

    public function receiveWebhook(Request $request);

    public function sendMessage(OutgoingMessageDTO $message);

    public function markAsRead(string $externalMessageId);

    public function healthCheck();

    public function providerName(): string;
}
