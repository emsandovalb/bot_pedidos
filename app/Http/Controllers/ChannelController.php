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
                        ? 'Registro persistido para esta organización.'
                        : 'Todavía no existe un registro persistido para esta organización.',
                    'tone' => 'green',
                ],
                [
                    'label' => 'Estado del canal',
                    'value' => $this->statusLabel($connection),
                    'description' => $connection
                        ? 'Estado actual guardado en la base de datos.'
                        : 'Borrador inicial sin conexión activa.',
                    'tone' => 'emerald',
                ],
                [
                    'label' => 'Proveedor',
                    'value' => $connection?->provider ?? 'Pendiente',
                    'description' => 'Preparado para Embedded Signup sin integrar Meta todavía.',
                    'tone' => 'blue',
                ],
            ],
            'channelJourney' => [
                [
                    'step' => '01',
                    'title' => 'Conecta el número',
                    'description' => 'Define el canal WhatsApp que representará la marca en Benditio.',
                ],
                [
                    'step' => '02',
                    'title' => 'Configura el saludo',
                    'description' => 'Ajusta el mensaje de bienvenida, horarios y derivación manual.',
                ],
                [
                    'step' => '03',
                    'title' => 'Valida el estado',
                    'description' => 'Revisa salud, actividad y próximos pasos antes de salir a producción.',
                ],
            ],
            'activityFeed' => [
                [
                    'title' => 'Registro de canal preparado',
                    'detail' => $connection
                        ? 'La conexión ya vive en la base de datos de la organización.'
                        : 'Aún no existe un registro persistido.',
                    'time' => 'Ahora',
                ],
                [
                    'title' => 'Derivación manual activada',
                    'detail' => 'Los casos sensibles se pueden mover al equipo operativo.',
                    'time' => 'Hace 1 h',
                ],
                [
                    'title' => 'Arquitectura lista',
                    'detail' => 'Sin Meta ni WhatsApp API todavía, solo la base del módulo.',
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
        $connection = $this->channelConnection();

        return view('channels.whatsapp', [
            'steps' => [
                [
                    'index' => '1',
                    'title' => 'Identidad del canal',
                    'description' => 'Nombre, número y estado persistido para el equipo.',
                    'status' => 'completed',
                ],
                [
                    'index' => '2',
                    'title' => 'Mensaje de bienvenida',
                    'description' => 'Crea una respuesta inicial clara, premium y orientada a conversión.',
                    'status' => 'active',
                ],
                [
                    'index' => '3',
                    'title' => 'Operación interna',
                    'description' => 'Define cuándo escalar, quién responde y qué hacer fuera de horario.',
                    'status' => 'pending',
                ],
                [
                    'index' => '4',
                    'title' => 'Revisión final',
                    'description' => 'Checklist de validación antes de conectar una API real.',
                    'status' => 'pending',
                ],
            ],
            'checklist' => [
                'Mensaje de bienvenida con tono comercial y claro.',
                'Horario de atención visible para el equipo operativo.',
                'Escalamiento manual preparado para mensajes complejos.',
                'Etiquetas demo para identificar intentos, consultas y pedidos.',
            ],
            'mockProfiles' => [
                [
                    'label' => 'Estado persistido',
                    'value' => $this->statusLabel($connection),
                ],
                [
                    'label' => 'Nombre público',
                    'value' => $connection?->display_name ?? 'No conectado',
                ],
                [
                    'label' => 'Número principal',
                    'value' => $connection?->phone_number ?? 'Sin número',
                ],
            ],
            'templateSamples' => [
                [
                    'title' => 'Saludo inicial',
                    'body' => 'Hola, gracias por escribir a Benditio. Te ayudamos a resolver tu pedido en pocos pasos.',
                ],
                [
                    'title' => 'Fuera de horario',
                    'body' => 'Estamos fuera de horario. Deja tu consulta y un agente la tomará al siguiente bloque disponible.',
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
                    : 'No existe un registro persistido para esta organización.',
                'lastChecked' => $connection?->last_sync_at?->format('d/m/Y H:i') ?? 'Sin sincronización',
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
                    'title' => 'Número verificado',
                    'state' => $connection?->phone_number ? 'Ok' : 'Pendiente',
                    'detail' => $connection?->phone_number ?? 'Número pendiente de configuración.',
                ],
                [
                    'title' => 'Mensajes de bienvenida',
                    'state' => $connection ? 'Ok' : 'Demo',
                    'detail' => $connection
                        ? 'Estructura lista para persistir plantillas.'
                        : 'Plantilla de entrada cargada en el asistente.',
                ],
                [
                    'title' => 'Derivación manual',
                    'state' => 'Pendiente',
                    'detail' => 'Lista para conexión con el flujo operativo real.',
                ],
                [
                    'title' => 'Monitoreo de entregas',
                    'state' => 'Demo',
                    'detail' => 'Panel visual para cuando exista integración real.',
                ],
            ],
            'timeline' => [
                [
                    'time' => $connection?->connected_at?->format('H:i') ?? 'Sin dato',
                    'title' => 'Conexión registrada',
                    'detail' => $connection
                        ? 'El canal quedó persistido en la base de datos.'
                        : 'Todavía no existe una conexión registrada.',
                ],
                [
                    'time' => $connection?->last_sync_at?->format('H:i') ?? 'Sin sincronización',
                    'title' => 'Última sincronización',
                    'detail' => 'Lista para alimentar Embedded Signup y estados externos.',
                ],
                [
                    'time' => 'Ahora',
                    'title' => 'Preparación arquitectónica',
                    'detail' => 'Sin integrar Meta ni WhatsApp todavía, solo persistencia local.',
                ],
            ],
        ]);
    }

    private function channelConnection(): ?ChannelConnection
    {
        $organizationId = request()->user()?->organization_id;

        if ($organizationId === null) {
            return null;
        }

        return ChannelConnection::query()
            ->where('organization_id', $organizationId)
            ->where('channel', ChannelConnection::CHANNEL_WHATSAPP)
            ->latest('id')
            ->first();
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
            default => Str::headline($connection->status),
        };
    }
}
