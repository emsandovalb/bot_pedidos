<?php

namespace App\Services\Channels;

use App\Models\ChannelConnection;
use App\Services\Messaging\DTO\ProviderHealth;
use App\Services\Messaging\DTO\ProviderValidationResult;
use App\Services\Messaging\Manager\MessagingManager;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Arma las vistas del hub de canales: tarjetas por proveedor, resumen de salud y detalle de cada proveedor.
 */
class ChannelHubPresenter
{
    public function __construct(
        private readonly MessagingManager $messagingManager,
        private readonly ChannelProviderLabels $labels,
    ) {}

    /**
     * Datos de la vista de detalle de un proveedor.
     *
     * @return array<string, mixed>
     */
    public function providerDetail(string $provider, ?int $organizationId): array
    {
        $connection = $this->providerConnection($provider, $organizationId);
        $health = $this->messagingManager->health($provider, $organizationId);
        $validation = $this->messagingManager->validate($provider, $organizationId);
        $validationErrors = $this->validationErrors($validation);
        $validationWarnings = $this->validationWarnings($validation);
        $capabilities = $this->messagingManager->capabilities($provider);

        return [
            'provider' => $provider,
            'providerLabel' => $this->labels->providerLabel($provider),
            'providerDescription' => $this->labels->providerDescription($provider),
            'providerBanner' => $this->labels->providerBanner($provider, $health->status),
            'providerLogo' => $this->labels->providerLogo($provider),
            'connection' => $connection,
            'health' => $health,
            'validation' => $validation,
            'capabilities' => $capabilities,
            'capabilityItems' => $this->labels->capabilityItems($capabilities->toArray()),
            'logs' => $this->providerLogs($provider, $connection, $health),
            'futureFeatures' => $this->futureFeatures($provider, $capabilities->toArray()),
            'overview' => $this->overviewItems($provider, $connection, $health),
            'validationErrors' => $validationErrors,
            'validationWarnings' => $validationWarnings,
            'recentErrors' => $this->recentErrors($connection, $validationErrors),
            'recentActivity' => $this->recentActivity($connection, $health),
            'futureReady' => $this->labels->futureReadyBanner($provider, $health->status),
            'actions' => $this->labels->providerActions($provider),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function providerCards(?int $organizationId): array
    {
        return collect(config('messaging.providers', ['telegram', 'whatsapp', 'instagram']))
            ->map(function (string $provider) use ($organizationId): array {
                $provider = $this->labels->normalizeProvider($provider);
                $connection = $this->providerConnection($provider, $organizationId);
                $health = $this->messagingManager->health($provider, $organizationId);
                $capabilities = $this->messagingManager->capabilities($provider);
                $lastWebhookVerificationAt = data_get($connection?->provider_metadata_json ?? [], 'last_webhook_verification_at');
                $lastWebhookVerificationAt = is_string($lastWebhookVerificationAt)
                    ? Carbon::parse($lastWebhookVerificationAt)
                    : ($lastWebhookVerificationAt instanceof DateTimeInterface ? $lastWebhookVerificationAt : null);

                return [
                    'slug' => $provider,
                    'label' => $this->labels->providerLabel($provider),
                    'description' => $this->labels->providerDescription($provider),
                    'logo' => $this->labels->providerLogo($provider),
                    'status' => $this->labels->providerStatusLabel($health->status),
                    'status_tone' => $this->labels->statusTone($health->status),
                    'health' => $health,
                    'health_label' => $this->labels->healthLabel($health->status),
                    'capabilities' => $capabilities,
                    'capability_preview' => $this->labels->capabilityPreview($capabilities->toArray()),
                    'version' => $connection?->provider_version ?? $connection?->version ?? $health->version ?? 'v1',
                    'health_status' => $connection?->health_status ?? $health->status,
                    'webhook_status' => $connection?->webhook_status ?? $health->webhook_status,
                    'credentials_status' => $connection?->credentials_status ?? $health->credentials_status,
                    'last_health_check_at' => $connection?->last_health_check_at ?? $connection?->health_checked_at ?? $health->last_health_check_at,
                    'last_webhook_verification_at' => $lastWebhookVerificationAt,
                    'last_received_message_at' => $connection?->last_received_message_at ?? $connection?->last_message_received_at,
                    'last_sent_message_at' => $connection?->last_sent_message_at ?? $connection?->last_message_sent_at,
                    'route' => route('channels.show', $provider),
                    'health_check_route' => route('channels.health-check', $provider),
                    'connect_route' => route('channels.connect', $provider),
                    'disconnect_route' => route('channels.disconnect', $provider),
                    'verify_endpoint_route' => $provider === 'whatsapp' ? route('channels.whatsapp.verify-endpoint') : null,
                    'configure_route' => $provider === 'whatsapp'
                        ? route('channels.whatsapp.configuration')
                        : route('channels.show', [$provider]).'#connection',
                    'is_placeholder' => in_array($provider, ['whatsapp', 'instagram'], true),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function hubSummary(): array
    {
        $providers = config('messaging.providers', ['telegram', 'whatsapp', 'instagram']);
        $healthCounts = collect($providers)
            ->map(fn (string $provider): string => $this->messagingManager->health($provider)->status)
            ->countBy()
            ->all();

        return [
            'providers' => count($providers),
            'connected' => $healthCounts['connected'] ?? 0,
            'healthy' => $healthCounts['healthy'] ?? 0,
            'warning' => $healthCounts['warning'] ?? 0,
            'coming_soon' => $healthCounts['coming_soon'] ?? 0,
            'unknown' => $healthCounts['unknown'] ?? 0,
        ];
    }

    private function providerConnection(string $provider, ?int $organizationId): ?ChannelConnection
    {
        if ($organizationId === null) {
            return null;
        }

        return ChannelConnection::query()
            ->where('organization_id', $organizationId)
            ->where('channel', $provider)
            ->latest('id')
            ->first();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function providerLogs(string $provider, ?ChannelConnection $connection, ProviderHealth $health): array
    {
        return [
            [
                'title' => 'Connected',
                'detail' => $connection?->connected_at ? 'Conexion registrada en la base de datos.' : 'Sin conexion persistida.',
                'time' => $connection?->connected_at?->format('d/m H:i') ?? 'Sin dato',
            ],
            [
                'title' => 'Webhook Verified',
                'detail' => 'Current webhook state: '.Str::headline($health->webhook_status),
                'time' => 'Ahora',
            ],
            [
                'title' => 'Health Checked',
                'detail' => 'Estado actual: '.$this->labels->healthLabel($health->status),
                'time' => $connection?->last_health_check_at?->format('d/m H:i') ?? $connection?->health_checked_at?->format('d/m H:i') ?? 'Hace poco',
            ],
            [
                'title' => 'Provider Error',
                'detail' => $connection?->last_error ?? $health->last_error ?? 'Sin errores registrados.',
                'time' => $connection?->last_ping?->format('d/m H:i') ?? $health->last_ping?->format('d/m H:i') ?? 'Sin dato',
            ],
        ];
    }

    /**
     * @param  array<string, bool>  $capabilities
     * @return array<int, array<string, mixed>>
     */
    private function futureFeatures(string $provider, array $capabilities): array
    {
        $items = [
            [
                'label' => 'Reconnect',
                'description' => 'Preparado para reactivar el canal desde el mismo lifecycle.',
                'enabled' => (bool) ($capabilities['send_messages'] ?? false),
            ],
            [
                'label' => 'Disconnect',
                'description' => 'Desconexion segura sin tocar parser ni ordenes.',
                'enabled' => true,
            ],
            [
                'label' => 'Webhook flow',
                'description' => 'Listo para la integracion del adaptador cuando llegue Meta o cualquier otro proveedor.',
                'enabled' => (bool) ($capabilities['receive_messages'] ?? false),
            ],
        ];

        if ($provider === 'whatsapp') {
            $items[] = [
                'label' => 'Meta Cloud API',
                'description' => 'Pendiente para el siguiente sprint, sin implementarlo ahora.',
                'enabled' => false,
            ];
        }

        return $items;
    }

    /**
     * @return array<int, array{label: string, value: string, detail: string}>
     */
    private function recentActivity(?ChannelConnection $connection, ProviderHealth $health): array
    {
        return [
            [
                'label' => 'Last received',
                'value' => $connection?->last_received_message_at?->format('d/m/Y H:i') ?? $connection?->last_message_received_at?->format('d/m/Y H:i') ?? 'Sin dato',
                'detail' => 'Most recent incoming lifecycle event.',
            ],
            [
                'label' => 'Last sent',
                'value' => $connection?->last_sent_message_at?->format('d/m/Y H:i') ?? $connection?->last_message_sent_at?->format('d/m/Y H:i') ?? 'Sin dato',
                'detail' => 'Most recent outbound lifecycle event.',
            ],
            [
                'label' => 'Last health check',
                'value' => $connection?->last_health_check_at?->format('d/m/Y H:i') ?? $connection?->health_checked_at?->format('d/m/Y H:i') ?? $health->last_health_check_at?->format('d/m/Y H:i') ?? 'Sin dato',
                'detail' => 'Latest provider health refresh.',
            ],
        ];
    }

    /**
     * @param  array<int, string>  $validationErrors
     * @return array<int, string>
     */
    private function recentErrors(?ChannelConnection $connection, array $validationErrors): array
    {
        $errors = array_filter([
            $connection?->last_error,
            ...$validationErrors,
        ]);

        return $errors !== [] ? array_values(array_unique($errors)) : ['Sin errores recientes.'];
    }

    /**
     * @return array<int, string>
     */
    private function validationErrors(ProviderHealth|ProviderValidationResult $validation): array
    {
        if ($validation instanceof ProviderValidationResult) {
            return $validation->errors;
        }

        return $validation->last_error !== null ? [$validation->last_error] : [];
    }

    /**
     * @return array<int, string>
     */
    private function validationWarnings(ProviderHealth|ProviderValidationResult $validation): array
    {
        if ($validation instanceof ProviderValidationResult) {
            return $validation->warnings;
        }

        return $validation->metadata['validation_warnings'] ?? [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function overviewItems(string $provider, ?ChannelConnection $connection, ProviderHealth $health): array
    {
        return [
            [
                'label' => 'Provider',
                'value' => $this->labels->providerLabel($provider),
            ],
            [
                'label' => 'Health',
                'value' => $this->labels->healthLabel($health->status),
            ],
            [
                'label' => 'Version',
                'value' => $connection?->provider_version ?? $connection?->version ?? $health->version ?? 'v1',
            ],
            [
                'label' => 'Last ping',
                'value' => $connection?->last_ping?->format('d/m/Y H:i') ?? ($health->last_ping?->format('d/m/Y H:i') ?? 'Sin dato'),
            ],
            [
                'label' => 'Credentials',
                'value' => $connection?->credentials_status ?? $health->credentials_status ?? 'unknown',
            ],
            [
                'label' => 'Webhook',
                'value' => $connection?->webhook_status ?? $health->webhook_status ?? 'unknown',
            ],
        ];
    }
}
