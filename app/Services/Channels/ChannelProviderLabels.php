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
            'telegram' => 'Telegram is live and exposes the real lifecycle surface.',
            'whatsapp' => 'WhatsApp is locally configured and waiting for Meta verification.',
            'instagram' => 'Coming soon. The provider shell is ready, but Meta integration is not.',
            default => 'Proveedor sin descripcion registrada.',
        };
    }

    public function providerBanner(string $provider, string $status): array
    {
        return [
            'label' => $this->providerLabel($provider),
            'status' => $this->providerStatusLabel($status),
            'description' => match ($provider) {
                'telegram' => 'Telegram runs on the real messaging provider lifecycle.',
                'whatsapp' => 'WhatsApp now exposes local readiness and keeps Meta verification as the next step.',
                'instagram' => 'Instagram is reserved as a future provider in the same lifecycle.',
                default => 'Lifecycle surface available for this provider.',
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
            'ready' => 'Ready',
            'coming_soon' => 'Coming Soon',
            'warning' => 'Warning',
            'disconnected' => 'Disconnected',
            'expired' => 'Expired',
            default => 'Unknown',
        };
    }

    public function healthLabel(string $status): string
    {
        return match ($status) {
            'healthy' => 'Healthy',
            'connected' => 'Connected',
            'ready' => 'Ready',
            'coming_soon' => 'Coming Soon',
            'warning' => 'Warning',
            'disconnected' => 'Disconnected',
            'expired' => 'Expired',
            default => 'Unknown',
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
            'send_messages' => 'Send',
            'receive_messages' => 'Receive',
            'buttons' => 'Buttons',
            'templates' => 'Templates',
            'catalog' => 'Catalog',
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
            'receive_messages' => 'Receive Messages',
            'send_messages' => 'Send Messages',
            'images' => 'Images',
            'files' => 'Files',
            'audio' => 'Audio',
            'video' => 'Video',
            'templates' => 'Templates',
            'catalog' => 'Catalog',
            'reactions' => 'Reactions',
            'buttons' => 'Buttons',
            'location' => 'Location',
            'contacts' => 'Contacts',
            'send_images' => 'Images',
            'send_documents' => 'Files',
            'send_audio' => 'Audio',
            'send_video' => 'Video',
            'interactive_buttons' => 'Interactive Buttons',
            'typing_indicator' => 'Typing Indicator',
            'delivery_receipts' => 'Delivery Receipts',
            'read_receipts' => 'Read Receipts',
            'voice_notes' => 'Voice Notes',
            'reaction_support' => 'Reaction Support',
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
                ? 'Coming Soon'
                : 'Future Ready',
            'description' => match ($provider) {
                'telegram' => 'Telegram is live. New providers can reuse this lifecycle immediately.',
                'whatsapp' => 'WhatsApp configuration is ready locally and now waits for Meta verification.',
                'instagram' => 'Instagram is reserved and ready to plug into the same provider interface.',
                default => 'This provider uses the shared lifecycle surface.',
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
                'label' => 'View Details',
                'href' => route('channels.show', $provider),
                'style' => 'primary',
            ],
            [
                'label' => 'Configure',
                'href' => $provider === 'whatsapp' ? route('channels.whatsapp.configuration') : route('channels.show', $provider).'#connection',
                'style' => 'secondary',
            ],
        ];
    }
}
