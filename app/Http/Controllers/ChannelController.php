<?php

namespace App\Http\Controllers;

use App\Models\ChannelConnection;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;

class ChannelController extends Controller
{
    public function index(): View
    {
        $connection = $this->channelConnection();

        return view('channels.index', [
            'channelHighlights' => [
                [
                    'label' => 'WhatsApp Business',
                    'value' => $this->connectionValue($connection),
                    'description' => $connection
                        ? 'Registro persistido para esta organizacion.'
                        : 'Todavia no existe un registro persistido para esta organizacion.',
                    'tone' => 'green',
                ],
                [
                    'label' => 'Estado del canal',
                    'value' => $this->statusLabel($connection),
                    'description' => $connection
                        ? 'Estado actual guardado en la base de datos.'
                        : 'Borrador inicial sin conexion activa.',
                    'tone' => 'emerald',
                ],
                [
                    'label' => 'Proveedor',
                    'value' => $connection?->provider ?? 'Pendiente',
                    'description' => 'Preparado para Embedded Signup sin integrar Meta todavia.',
                    'tone' => 'blue',
                ],
            ],
            'channelJourney' => [
                [
                    'step' => '01',
                    'title' => 'Conecta el numero',
                    'description' => 'Define el canal WhatsApp que representara la marca en Benditio.',
                ],
                [
                    'step' => '02',
                    'title' => 'Configura el saludo',
                    'description' => 'Ajusta el mensaje de bienvenida, horarios y derivacion manual.',
                ],
                [
                    'step' => '03',
                    'title' => 'Valida el estado',
                    'description' => 'Revisa salud, actividad y proximos pasos antes de salir a produccion.',
                ],
            ],
            'activityFeed' => [
                [
                    'title' => 'Registro de canal preparado',
                    'detail' => $connection
                        ? 'La conexion ya vive en la base de datos de la organizacion.'
                        : 'Aun no existe un registro persistido.',
                    'time' => 'Ahora',
                ],
                [
                    'title' => 'Derivacion manual activada',
                    'detail' => 'Los casos sensibles se pueden mover al equipo operativo.',
                    'time' => 'Hace 1 h',
                ],
                [
                    'title' => 'Arquitectura lista',
                    'detail' => 'Sin Meta ni WhatsApp API todavia, solo la base del modulo.',
                    'time' => 'Hoy',
                ],
            ],
            'nextActions' => [
                [
                    'label' => 'Abrir asistente de WhatsApp',
                    'href' => route('channels.whatsapp'),
                    'tone' => 'primary',
                ],
                [
                    'label' => 'Ver estado del canal',
                    'href' => route('channels.whatsapp.status'),
                    'tone' => 'secondary',
                ],
            ],
        ]);
    }

    public function whatsapp(): View
    {
        $connection = $this->channelConnection(true);
        $metadata = $this->normalizedMetadata($connection?->metadata_json);
        $readiness = $this->readinessState($metadata);

        return view('channels.whatsapp', [
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
        ]);
    }

    public function status(): View
    {
        $connection = $this->channelConnection();

        return view('channels.whatsapp-status', [
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
        ]);
    }

    protected function channelConnection(bool $create = false): ?ChannelConnection
    {
        $organizationId = request()->user()?->organization_id;

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

    protected function connectionValue(?ChannelConnection $connection): string
    {
        return $connection?->display_name
            ?? $connection?->phone_number
            ?? 'No conectado';
    }

    protected function statusLabel(?ChannelConnection $connection): string
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

    protected function defaultMetadata(): array
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

    protected function normalizedMetadata(?array $metadata): array
    {
        return array_merge($this->defaultMetadata(), $metadata ?? []);
    }

    protected function readinessState(array $metadata): array
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

    protected function stepperStatus(int $step, array $readiness): string
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
