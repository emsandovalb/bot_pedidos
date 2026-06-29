<?php

namespace App\Services\Messaging\Providers;

use App\Services\Messaging\Contracts\MessagingProvider;
use App\Services\Messaging\DTO\MessagingSendResult;
use App\Services\Messaging\DTO\OutgoingMessageDTO;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Throwable;

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

    public function sendMessage(OutgoingMessageDTO $message): MessagingSendResult
    {
        $chatId = $this->resolveChatId($message);

        if ($chatId === null) {
            return new MessagingSendResult(
                success: false,
                provider: $this->providerName(),
                raw_response: null,
                error: 'Missing Telegram chat id',
            );
        }

        $botToken = (string) config('services.telegram.bot_token', '');

        if ($botToken === '') {
            return new MessagingSendResult(
                success: false,
                provider: $this->providerName(),
                raw_response: null,
                error: 'Missing Telegram bot token',
            );
        }

        try {
            $response = Http::baseUrl('https://api.telegram.org/bot' . $botToken)
                ->withOptions([
                    'verify' => config('services.telegram.verify_ssl', true),
                ])
                ->acceptJson()
                ->asJson()
                ->post('sendMessage', [
                    'chat_id' => $chatId,
                    'text' => $message->message,
                ]);

            return $this->toResult($response);
        } catch (Throwable $throwable) {
            return new MessagingSendResult(
                success: false,
                provider: $this->providerName(),
                raw_response: null,
                error: $throwable->getMessage(),
            );
        }
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

    private function resolveChatId(OutgoingMessageDTO $message): ?string
    {
        $metadata = $message->metadata;
        $chatId = $metadata['external_chat_id']
            ?? $metadata['chat_id']
            ?? $metadata['telegram_chat_id']
            ?? null;

        if (! is_string($chatId)) {
            $chatId = is_int($chatId) ? (string) $chatId : null;
        }

        $chatId = $chatId !== null ? trim($chatId) : null;

        return $chatId === '' ? null : $chatId;
    }

    private function toResult(Response $response): MessagingSendResult
    {
        $payload = $response->json();
        $rawResponse = is_array($payload) ? $payload : null;

        if (! $response->successful()) {
            $error = 'Telegram API request failed with HTTP ' . $response->status();

            if (is_array($rawResponse) && is_string($rawResponse['description'] ?? null) && $rawResponse['description'] !== '') {
                $error = $rawResponse['description'];
            }

            return new MessagingSendResult(
                success: false,
                provider: $this->providerName(),
                raw_response: $rawResponse,
                error: $error,
            );
        }

        if (! is_array($rawResponse) || ($rawResponse['ok'] ?? false) !== true) {
            $error = 'Telegram API returned an invalid response.';

            if (is_array($rawResponse) && is_string($rawResponse['description'] ?? null) && $rawResponse['description'] !== '') {
                $error = $rawResponse['description'];
            }

            return new MessagingSendResult(
                success: false,
                provider: $this->providerName(),
                raw_response: $rawResponse,
                error: $error,
            );
        }

        $providerMessageId = null;

        if (isset($rawResponse['result']) && is_array($rawResponse['result'])) {
            $messageId = $rawResponse['result']['message_id'] ?? null;

            if (is_int($messageId) || is_string($messageId)) {
                $providerMessageId = trim((string) $messageId);
            }
        }

        return new MessagingSendResult(
            success: true,
            provider: $this->providerName(),
            provider_message_id: $providerMessageId,
            raw_response: $rawResponse,
        );
    }
}
