<?php

namespace App\Services;

use App\Exceptions\WhatsappApiException;
use App\Models\ChannelConnection;
use Illuminate\Support\Facades\Http;

class WhatsappService
{
    public function __construct(
        private readonly ChannelConnection $connection,
    ) {
    }

    public function phoneNumberId(): string
    {
        return (string) $this->connection->provider_phone_number_id;
    }

    public function businessAccountId(): string
    {
        return (string) $this->connection->provider_business_account_id;
    }

    /**
     * Send a request to the WhatsApp Cloud API and return the decoded response.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function sendRequest(string $endpoint, array $payload): array
    {
        $token = (string) $this->connection->provider_access_token;
        $apiVersion = (string) ($this->connection->provider_api_version ?: config('services.whatsapp.api_version'));

        $response = Http::withToken($token)
            ->baseUrl("https://graph.facebook.com/{$apiVersion}")
            ->post(ltrim($endpoint, '/'), $payload);

        if ($response->failed()) {
            throw WhatsappApiException::fromResponse($response);
        }

        return (array) $response->json();
    }
}
