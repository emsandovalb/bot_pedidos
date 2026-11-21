<?php

namespace App\Services\Channels;

use Illuminate\Support\Str;

/**
 * Nombres, logos, banners y etiquetas de estado de cada proveedor que muestran las vistas de canales.
 */
class ChannelProviderLabels
{
    public function normalizeProvider(string $provider): string
    {
        return strtolower(trim($provider));
    }

    public function providerLabel(string $provider): string
    {
        return match ($provider) {
            'telegram' => 'Telegram',
            'whatsapp' => 'WhatsApp',
            'instagram' => 'Instagram',
            default => Str::headline($provider),
        };
    }

    public function providerDescription(string $provider): string
    {
        return match ($provider) {
            'telegram' => 'Telegram ya recibe pedidos de verdad.',
            'whatsapp' => 'WhatsApp está configurado aquí y espera la verificación de Meta.',
            'instagram' => 'Próximamente. El canal está preparado, pero falta la integración con Meta.',
            default => 'Canal sin descripción registrada.',
        };
    }

    public function providerBanner(string $provider, string $status): array
    {
        return [
            'label' => $this->providerLabel($provider),
            'status' => $this->providerStatusLabel($status),
            'description' => match ($provider) {
                'telegram' => 'Telegram está conectado y recibe mensajes.',
                'whatsapp' => 'WhatsApp está listo de este lado; el siguiente paso es la verificación de Meta.',
                'instagram' => 'Instagram se sumará más adelante y funcionará igual que los demás canales.',
                default => 'Este canal se maneja igual que los demás.',
            },
        ];
    }

    public function providerLogo(string $provider): array
    {
        return match ($provider) {
            'telegram' => [
                'accent' => 'blue',
                'path' => 'M21.8 4.7 3.6 11.4c-1 .4-1 1.8.1 2.1l4.7 1.4 1.8 5.2c.3.9 1.5 1.1 2.1.3l2.8-3.3 4.8 3.5c.8.6 1.9.1 2.1-.9l2.8-13.8c.2-1.1-.9-2-2-1.7Z',
            ],
            'instagram' => [
                'accent' => 'rose',
                'path' => 'M7.5 3.5h9A4 4 0 0 1 20.5 7.5v9a4 4 0 0 1-4 4h-9a4 4 0 0 1-4-4v-9a4 4 0 0 1 4-4Zm9.5 2.5a1 1 0 1 0 0 2 1 1 0 0 0 0-2ZM12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8Z',
            ],
            default => [
                'accent' => 'emerald',
                'path' => 'M12 2a10 10 0 1 0 5.1 18.6L22 22l-1.4-4.9A10 10 0 0 0 12 2Z',
            ],
        };
    }

    public function providerStatusLabel(string $status): string
    {
        return match ($status) {
            'healthy', 'connected' => 'Activo',
            'ready' => 'Listo',
            'coming_soon' => 'Próximamente',
            'warning' => 'Con alertas',
            'disconnected' => 'Desconectado',
            'expired' => 'Vencido',
            default => 'Sin dato',
        };
    }

    public function healthLabel(string $status): string
    {
        return match ($status) {
            'healthy' => 'Funcionando',
            'connected' => 'Conectado',
            'ready' => 'Listo',
            'coming_soon' => 'Próximamente',
            'warning' => 'Con alertas',
            'disconnected' => 'Desconectado',
            'expired' => 'Vencido',
            default => 'Sin dato',
        };
    }

    public function statusTone(string $status): string
    {
        return match ($status) {
            'healthy', 'connected' => 'emerald',
            'coming_soon' => 'rose',
            'warning' => 'amber',
            'disconnected' => 'slate',
            default => 'slate',
        };
    }

    /**
     * @param  array<string, bool>  $capabilities
     * @return array<int, string>
     */
    public function capabilityPreview(array $capabilities): array
    {
        return collect([
            'send_messages' => 'Enviar',
            'receive_messages' => 'Recibir',
            'buttons' => 'Botones',
            'templates' => 'Plantillas',
            'catalog' => 'Catálogo',
        ])
            ->filter(fn (string $label, string $key): bool => (bool) ($capabilities[$key] ?? false))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, bool>  $capabilities
     * @return array<int, array{key: string, label: string, enabled: bool}>
     */
    public function capabilityItems(array $capabilities): array
    {
        $labels = [
            'receive_messages' => 'Recibir mensajes',
            'send_messages' => 'Enviar mensajes',
            'images' => 'Imágenes',
            'files' => 'Archivos',
            'audio' => 'Audio',
            'video' => 'Video',
            'templates' => 'Plantillas',
            'catalog' => 'Catálogo',
            'reactions' => 'Reacciones',
            'buttons' => 'Botones',
            'location' => 'Ubicación',
            'contacts' => 'Contactos',
            'send_images' => 'Imágenes',
            'send_documents' => 'Archivos',
            'send_audio' => 'Audio',
            'send_video' => 'Video',
            'interactive_buttons' => 'Botones interactivos',
            'typing_indicator' => 'Indicador de escritura',
            'delivery_receipts' => 'Confirmación de entrega',
            'read_receipts' => 'Confirmación de lectura',
            'voice_notes' => 'Notas de voz',
            'reaction_support' => 'Reacciones',
        ];

        return collect($labels)
            ->map(function (string $label, string $key) use ($capabilities): array {
                return [
                    'key' => $key,
                    'label' => $label,
                    'enabled' => (bool) ($capabilities[$key] ?? false),
                ];
            })
            ->values()
            ->all();
    }

    public function futureReadyBanner(string $provider, string $status): array
    {
        return [
            'title' => $provider === 'instagram'
                ? 'Próximamente'
                : 'Listo para crecer',
            'description' => match ($provider) {
                'telegram' => 'Telegram ya recibe pedidos.',
                'whatsapp' => 'WhatsApp está configurado y espera la verificación de Meta.',
                'instagram' => 'Instagram llegará pronto.',
                default => 'Este canal se maneja como los demás.',
            },
            'status' => $this->providerStatusLabel($status),
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function providerActions(string $provider): array
    {
        return [
            [
                'label' => 'Ver detalle',
                'href' => route('channels.show', $provider),
                'style' => 'primary',
            ],
            [
                'label' => 'Configurar',
                'href' => $provider === 'whatsapp' ? route('channels.whatsapp.configuration') : route('channels.show', $provider).'#connection',
                'style' => 'secondary',
            ],
        ];
    }
}
