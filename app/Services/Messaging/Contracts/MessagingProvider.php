<?php

namespace App\Services\Messaging\Contracts;

use App\Services\Messaging\DTO\MessagingSendResult;
use App\Services\Messaging\DTO\OutgoingMessageDTO;
use App\Services\Messaging\DTO\ProviderCapabilities;
use App\Services\Messaging\DTO\ProviderHealth;
use App\Services\Messaging\DTO\ProviderValidationResult;
use Illuminate\Http\Request;

interface MessagingProvider
{
    public function providerName(): string;

    public function connect(): ProviderHealth;

    public function disconnect(): ProviderHealth;

    public function health(): ProviderHealth;

    public function capabilities(): ProviderCapabilities;

    public function validateConfiguration(): ProviderValidationResult;

    public function supports(string $capability): bool;

    public function verifyWebhook(Request $request): bool;

    public function receive(Request $request);

    public function send(OutgoingMessageDTO $message): MessagingSendResult;

    public function refreshCredentials(): ProviderValidationResult;

    public function receiveWebhook(Request $request);

    public function sendMessage(OutgoingMessageDTO $message): MessagingSendResult;

    public function markAsRead(string $externalMessageId);

    public function healthCheck();
}
