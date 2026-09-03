<?php

namespace App\Services\Developer;

use Illuminate\Http\Request;

/**
 * Construye una petición de webhook de WhatsApp Cloud sintética para el
 * developer toolkit y el simulador de escenarios.
 *
 * Cuando hay App Secret configurado (`services.whatsapp.app_secret`) firma el
 * cuerpo con `X-Hub-Signature-256` igual que lo haría Meta, para que la
 * verificación del webhook real (`WhatsAppProvider::validateWebhookSignature`)
 * lo acepte. Sin secreto configurado no firma y el webhook lo rechazará, que es
 * el comportamiento correcto en un entorno sin credenciales.
 */
class SimulatedWhatsAppWebhook
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public static function request(array $payload): Request
    {
        $content = (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $server = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ];

        $appSecret = trim((string) config('services.whatsapp.app_secret', ''));

        if ($appSecret !== '') {
            $server['HTTP_X_HUB_SIGNATURE_256'] = 'sha256='.hash_hmac('sha256', $content, $appSecret);
        }

        return Request::create(
            uri: '/webhooks/whatsapp',
            method: 'POST',
            parameters: [],
            cookies: [],
            files: [],
            server: $server,
            content: $content,
        );
    }
}
