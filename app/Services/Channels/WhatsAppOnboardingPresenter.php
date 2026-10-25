<?php

namespace App\Services\Channels;

use App\Models\ChannelConnection;
use App\Models\SetupRequest;
use Illuminate\Support\Str;

/**
 * Estado de preparación del canal WhatsApp (checklist, avance, borrador de conexión) y sus páginas.
 */
class WhatsAppOnboardingPresenter
{
    /**
     * Datos del asistente de preparación de WhatsApp; crea el borrador si no existe.
     *
     * @return array<string, mixed>
     */
    public function setupPage(?int $organizationId): array
    {
        $connection = $this->channelConnection($organizationId, true);
        $metadata = $this->normalizedMetadata($connection?->metadata_json);
        $readiness = $this->readinessState($metadata);

        return [
            'connection' => $connection,
            'metadata' => $metadata,
            'readiness' => $readiness,
            'statusLabel' => $this->statusLabel($connection),
            'stepper' => [
                [
                    'index' => 1,
                    'title' => 'Requisitos',
                    'description' => 'Checklist base para saber si el negocio esta listo.',
                    'status' => $this->stepperStatus(1, $readiness),
                ],
                [
                    'index' => 2,
                    'title' => 'Numero',
                    'description' => 'Nombre y numero principal del canal.',
                    'status' => $this->stepperStatus(2, $readiness),
                ],
                [
                    'index' => 3,
                    'title' => 'Meta/Facebook',
                    'description' => 'Accesos y capacidad operativa para continuar.',
                    'status' => $this->stepperStatus(3, $readiness),
                ],
                [
                    'index' => 4,
                    'title' => 'Ayuda',
                    'description' => 'Indicaciones para configuracion asistida.',
                    'status' => $this->stepperStatus(4, $readiness),
                ],
                [
                    'index' => 5,
                    'title' => 'Resumen',
                    'description' => 'Vista final de preparacion del canal.',
                    'status' => 'current',
                ],
            ],
            'checklistItems' => [
                [
                    'key' => 'has_whatsapp_business',
                    'label' => 'Tengo WhatsApp Business',
                    'description' => 'La cuenta del negocio ya existe y puede operar.',
                ],
                [
                    'key' => 'has_dedicated_number',
                    'label' => 'Tengo un numero exclusivo para el negocio',
                    'description' => 'El canal no comparte linea con uso personal.',
                ],
                [
                    'key' => 'has_facebook_access',
                    'label' => 'Tengo acceso a Facebook/Meta',
                    'description' => 'Puedo entrar al ecosistema de Meta cuando sea necesario.',
                ],
                [
                    'key' => 'has_meta_business',
                    'label' => 'Tengo o puedo crear Meta Business Manager',
                    'description' => 'El negocio puede gestionar activos y permisos.',
                ],
            ],
        ];
    }

    /**
     * Datos de la página de estado del canal WhatsApp.
     *
     * @return array<string, mixed>
     */
    public function statusPage(?int $organizationId): array
    {
        $connection = $this->channelConnection($organizationId);
        $openSetupRequest = SetupRequest::query()
            ->where('organization_id', $organizationId)
            ->where('type', SetupRequest::TYPE_WHATSAPP_ASSISTED_SETUP)
            ->where('status', SetupRequest::STATUS_OPEN)
            ->latest('requested_at')
            ->first();

        return [
            'openSetupRequest' => $openSetupRequest,
            'connectionStatus' => [
                'label' => 'Canal WhatsApp',
                'value' => $this->connectionValue($connection),
                'description' => $connection
                    ? 'Registro persistido listo para futuras integraciones.'
                    : 'No existe un registro persistido para esta organizacion.',
                'lastChecked' => $connection?->last_sync_at?->format('d/m/Y H:i') ?? 'Sin sincronizacion',
            ],
            'statusMetrics' => [
                [
                    'label' => 'Estado',
                    'value' => $this->statusLabel($connection),
                    'detail' => 'Base persistida, lista para ampliar el flujo.',
                ],
                [
                    'label' => 'Proveedor',
                    'value' => $connection?->provider ?? 'Pendiente',
                    'detail' => 'Preparado para Embedded Signup.',
                ],
                [
                    'label' => 'Business ID',
                    'value' => $connection?->external_business_id ?? 'Sin dato',
                    'detail' => 'Campo externo reservado para Meta.',
                ],
                [
                    'label' => 'Phone Number ID',
                    'value' => $connection?->external_phone_number_id ?? 'Sin dato',
                    'detail' => 'Campo externo reservado para WhatsApp.',
                ],
            ],
            'healthChecks' => [
                [
                    'title' => 'Numero verificado',
                    'state' => $connection?->phone_number ? 'Ok' : 'Pendiente',
                    'detail' => $connection?->phone_number ?? 'Numero pendiente de configuracion.',
                ],
                [
                    'title' => 'Mensajes de bienvenida',
                    'state' => $connection ? 'Ok' : 'Demo',
                    'detail' => $connection
                        ? 'Estructura lista para persistir plantillas.'
                        : 'Plantilla de entrada cargada en el asistente.',
                ],
                [
                    'title' => 'Derivacion manual',
                    'state' => 'Pendiente',
                    'detail' => 'Lista para conexion con el flujo operativo real.',
                ],
                [
                    'title' => 'Monitoreo de entregas',
                    'state' => 'Demo',
                    'detail' => 'Panel visual para cuando exista integracion real.',
                ],
            ],
            'timeline' => [
                [
                    'time' => $connection?->connected_at?->format('H:i') ?? 'Sin dato',
                    'title' => 'Conexion registrada',
                    'detail' => $connection
                        ? 'El canal quedo persistido en la base de datos.'
                        : 'Todavia no existe una conexion registrada.',
                ],
                [
                    'time' => $connection?->last_sync_at?->format('H:i') ?? 'Sin sincronizacion',
                    'title' => 'Ultima sincronizacion',
                    'detail' => 'Lista para alimentar Embedded Signup y estados externos.',
                ],
                [
                    'time' => 'Ahora',
                    'title' => 'Preparacion arquitectonica',
                    'detail' => 'Sin integrar Meta ni WhatsApp todavia, solo persistencia local.',
                ],
            ],
        ];
    }

    public function channelConnection(?int $organizationId, bool $create = false): ?ChannelConnection
    {
        if ($organizationId === null) {
            return null;
        }

        $connection = ChannelConnection::query()
            ->where('organization_id', $organizationId)
            ->where('channel', ChannelConnection::CHANNEL_WHATSAPP)
            ->latest('id')
            ->first();

        if ($connection !== null || ! $create) {
            return $connection;
        }

        return ChannelConnection::query()->create([
            'organization_id' => $organizationId,
            'channel' => ChannelConnection::CHANNEL_WHATSAPP,
            'status' => ChannelConnection::STATUS_DRAFT,
            'metadata_json' => $this->defaultMetadata(),
        ]);
    }

    public function normalizedMetadata(?array $metadata): array
    {
        return array_merge($this->defaultMetadata(), $metadata ?? []);
    }

    public function readinessState(array $metadata): array
    {
        $requiredKeys = [
            'has_whatsapp_business',
            'has_dedicated_number',
            'has_facebook_access',
            'has_meta_business',
        ];

        $completedRequired = collect($requiredKeys)->filter(
            fn (string $key): bool => (bool) ($metadata[$key] ?? false)
        )->count();

        $percentage = intdiv($completedRequired * 100, count($requiredKeys));

        $status = match ($completedRequired) {
            0 => ChannelConnection::STATUS_DRAFT,
            count($requiredKeys) => ChannelConnection::STATUS_READY_FOR_SETUP,
            default => ChannelConnection::STATUS_PENDING,
        };

        return [
            'percentage' => $percentage,
            'completed_required' => $completedRequired,
            'required_total' => count($requiredKeys),
            'status' => $status,
            'assisted_setup' => (bool) ($metadata['needs_assisted_setup'] ?? false),
        ];
    }

    private function connectionValue(?ChannelConnection $connection): string
    {
        return $connection?->display_name
            ?? $connection?->phone_number
            ?? 'No conectado';
    }

    private function statusLabel(?ChannelConnection $connection): string
    {
        if ($connection === null) {
            return 'No conectado';
        }

        return match ($connection->status) {
            ChannelConnection::STATUS_DRAFT => 'Borrador',
            ChannelConnection::STATUS_PENDING => 'Pendiente',
            ChannelConnection::STATUS_READY_FOR_SETUP => 'Listo para configuracion asistida',
            ChannelConnection::STATUS_CONNECTED => 'Conectado',
            default => Str::headline($connection->status),
        };
    }

    private function defaultMetadata(): array
    {
        return [
            'has_whatsapp_business' => false,
            'has_dedicated_number' => false,
            'has_facebook_access' => false,
            'has_meta_business' => false,
            'needs_assisted_setup' => false,
            'business_category' => null,
            'expected_monthly_orders' => null,
            'notes' => null,
        ];
    }

    private function stepperStatus(int $step, array $readiness): string
    {
        $percentage = $readiness['percentage'];

        return match ($step) {
            1 => $percentage >= 25 ? 'completed' : 'active',
            2 => $percentage >= 50 ? 'completed' : ($percentage >= 25 ? 'active' : 'pending'),
            3 => $percentage >= 75 ? 'completed' : ($percentage >= 50 ? 'active' : 'pending'),
            4 => $percentage >= 100 ? 'completed' : ($percentage >= 75 ? 'active' : 'pending'),
            default => $percentage >= 100 ? 'completed' : 'current',
        };
    }
}
