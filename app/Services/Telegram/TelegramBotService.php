<?php

namespace App\Services\Telegram;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TelegramBotService
{
    private ?array $botInfo = null;

    public function enabled(): bool
    {
        return (bool) config('services.telegram.enabled', false);
    }

    public function botToken(): string
    {
        $token = (string) config('services.telegram.bot_token', '');

        if ($token === '') {
            throw new RuntimeException('TELEGRAM_BOT_TOKEN is not configured.');
        }

        return $token;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getUpdates(?int $offset = null): array
    {
        $payload = array_filter([
            'offset' => $offset,
            'timeout' => 0,
            'allowed_updates' => ['message'],
        ], static fn ($value) => $value !== null);

        $response = $this->request('getUpdates', $payload);

        return $response['result'] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function sendMessage(string $chatId, string $text): array
    {
        return $this->request('sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
        ]);
    }

    public function getBotIdentifier(): string
    {
        $botInfo = $this->getBotInfo();

        if (! empty($botInfo['username'])) {
            return '@' . $botInfo['username'];
        }

        if (! empty($botInfo['id'])) {
            return 'telegram:' . $botInfo['id'];
        }

        return 'telegram:bot';
    }

    public function extractUpdateId(array $update): ?string
    {
        $updateId = Arr::get($update, 'update_id');

        return $updateId === null ? null : (string) $updateId;
    }

    public function extractChatId(array $update): ?string
    {
        $chatId = Arr::get($update, 'message.chat.id');

        return $chatId === null ? null : (string) $chatId;
    }

    public function extractMessageText(array $update): ?string
    {
        $text = Arr::get($update, 'message.text');

        if (! is_string($text)) {
            return null;
        }

        $text = trim($text);

        return $text === '' ? null : $text;
    }

    public function extractSenderName(array $update): ?string
    {
        $username = Arr::get($update, 'message.from.username');

        if (is_string($username) && $username !== '') {
            return '@' . $username;
        }

        $firstName = trim((string) Arr::get($update, 'message.from.first_name', ''));
        $lastName = trim((string) Arr::get($update, 'message.from.last_name', ''));
        $name = trim($firstName . ' ' . $lastName);

        if ($name !== '') {
            return $name;
        }

        $chatTitle = Arr::get($update, 'message.chat.title');

        return is_string($chatTitle) && $chatTitle !== '' ? $chatTitle : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function getBotInfo(): array
    {
        if ($this->botInfo !== null) {
            return $this->botInfo;
        }

        $response = $this->request('getMe');
        $this->botInfo = is_array($response['result'] ?? null) ? $response['result'] : [];

        return $this->botInfo;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function request(string $method, array $payload = []): array
    {
        $response = Http::baseUrl('https://api.telegram.org/bot' . $this->botToken())
            ->withOptions([
                'verify' => config('services.telegram.verify_ssl', true),
            ])
            ->acceptJson()
            ->asJson()
            ->post($method, $payload)
            ->throw()
            ->json();

        if (! is_array($response)) {
            throw new RuntimeException('Telegram API returned an invalid response.');
        }

        if (($response['ok'] ?? true) !== true) {
            $description = is_string($response['description'] ?? null)
                ? $response['description']
                : 'unknown error';

            throw new RuntimeException('Telegram API request failed: ' . $description);
        }

        return $response;
    }
}
