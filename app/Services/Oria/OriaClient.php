<?php

namespace App\Services\Oria;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * La API de Oria para apps socias, con la llave de bot_pedidos. Una respuesta 4xx que Oria explica (código vencido,
 * negocio sin Plus) vuelve como OriaRejectedException; si Oria no responde o falla, OriaUnavailableException.
 */
class OriaClient
{
    public function isConfigured(): bool
    {
        return filled(config('oria.api_url')) && filled(config('oria.partner_key'));
    }

    /**
     * @return array{approveUrl: string, expiresAt: string}
     */
    public function requestLink(string $externalId, string $externalName, string $returnUrl, string $state): array
    {
        return $this->send('post', '/partner/link-requests', [
            'externalId' => $externalId,
            'externalName' => $externalName,
            'returnUrl' => $returnUrl,
            'state' => $state,
        ]);
    }

    /**
     * @return array{id: int, externalId: string, plan: string, active: bool, account: array{name: ?string, email: ?string}}
     */
    public function exchange(string $code): array
    {
        return $this->send('post', '/partner/links/exchange', ['code' => $code]);
    }

    /**
     * @return array{id: int, externalId: string, plan: string, active: bool, account: array{name: ?string, email: ?string}}
     */
    public function link(int $linkId): array
    {
        return $this->send('get', "/partner/links/{$linkId}");
    }

    public function signInUrl(int $linkId): string
    {
        return (string) $this->send('post', "/partner/links/{$linkId}/sign-in-links")['url'];
    }

    public function unlink(int $linkId): void
    {
        $this->send('delete', "/partner/links/{$linkId}");
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function send(string $method, string $path, array $data = []): array
    {
        if (! $this->isConfigured()) {
            throw new OriaUnavailableException('Oria no está configurada.');
        }

        try {
            /** @var Response $response */
            $response = $this->http()->{$method}($path, $data);
        } catch (ConnectionException $exception) {
            throw new OriaUnavailableException('Oria no respondió.', previous: $exception);
        }

        if ($response->status() === 404 || $response->status() === 409 || $response->status() === 422) {
            throw new OriaRejectedException($response->status(), $this->firstMessage($response));
        }

        if (! $response->successful()) {
            throw new OriaUnavailableException("Oria respondió {$response->status()}.");
        }

        return (array) $response->json();
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('oria.api_url'), '/'))
            ->withToken((string) config('oria.partner_key'))
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('oria.timeout_seconds', 5));
    }

    private function firstMessage(Response $response): string
    {
        $errors = (array) $response->json('errors', []);
        $first = collect($errors)->flatten()->first();

        return (string) ($first ?? $response->json('message') ?? 'Oria rechazó la solicitud.');
    }
}
