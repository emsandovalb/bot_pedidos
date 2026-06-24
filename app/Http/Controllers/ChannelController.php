<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class ChannelController extends Controller
{
    public function index(): View
    {
        return view('channels.index', [
            'channelHighlights' => [
                [
                    'label' => 'WhatsApp Business',
                    'value' => 'Canal principal',
                    'description' => 'Onboarding, estado y monitoreo en una sola experiencia.',
                    'tone' => 'green',
                ],
                [
                    'label' => 'Estado del canal',
                    'value' => 'Operativo',
                    'description' => 'Vista de conexión, salud y mensajes demo.',
                    'tone' => 'emerald',
                ],
                [
                    'label' => 'Tiempo de activación',
                    'value' => '15 min',
                    'description' => 'Flujo guiado para avanzar sin integración real todavía.',
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
                    'title' => 'Mensaje de bienvenida listo',
                    'detail' => 'Plantilla demo aprobada para el flujo inicial.',
                    'time' => 'Hace 8 min',
                ],
                [
                    'title' => 'Derivación manual activada',
                    'detail' => 'Los casos sensibles se pueden mover al equipo operativo.',
                    'time' => 'Hace 1 h',
                ],
                [
                    'title' => 'Estado del canal revisado',
                    'detail' => 'Sin integración todavía, pero con checklist preparado.',
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
        return view('channels.whatsapp', [
            'steps' => [
                [
                    'index' => '1',
                    'title' => 'Identidad del canal',
                    'description' => 'Nombre, número demo y horarios visibles para el equipo.',
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
                    'label' => 'Número principal',
                    'value' => '+502 5555 0101',
                ],
                [
                    'label' => 'Nombre público',
                    'value' => 'Benditio Pedidos',
                ],
                [
                    'label' => 'Horario',
                    'value' => 'Lun - Sáb, 8:00 a 20:00',
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
        return view('channels.whatsapp-status', [
            'connectionStatus' => [
                'label' => 'Canal demo',
                'value' => 'Listo para activar',
                'description' => 'Sin API conectada todavía, pero con el flujo preparado para operar.',
                'lastChecked' => 'Actualizado hace 5 min',
            ],
            'statusMetrics' => [
                [
                    'label' => 'Salud del canal',
                    'value' => '98%',
                    'detail' => 'Configuración, acceso y flujo base en buen estado.',
                ],
                [
                    'label' => 'Mensajes en espera',
                    'value' => '12',
                    'detail' => 'Casos demo listos para la primera lectura del equipo.',
                ],
                [
                    'label' => 'Respuestas rápidas',
                    'value' => '06',
                    'detail' => 'Plantillas internas para acelerar la operación.',
                ],
                [
                    'label' => 'Tiempo de respuesta',
                    'value' => '2m 14s',
                    'detail' => 'Promedio demo para la vista de monitoreo.',
                ],
            ],
            'healthChecks' => [
                [
                    'title' => 'Número verificado',
                    'state' => 'Ok',
                    'detail' => 'Número demo visible en la configuración.',
                ],
                [
                    'title' => 'Mensajes de bienvenida',
                    'state' => 'Ok',
                    'detail' => 'Plantilla de entrada cargada en el asistente.',
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
                    'time' => '09:24',
                    'title' => 'Primer saludo enviado',
                    'detail' => 'Se simula una recepción con el mensaje de bienvenida.',
                ],
                [
                    'time' => '09:31',
                    'title' => 'Consulta derivada',
                    'detail' => 'El caso pasa al equipo operativo por revisión manual.',
                ],
                [
                    'time' => '09:47',
                    'title' => 'Estado revisado',
                    'detail' => 'Sin API todavía, pero con todos los indicadores visibles.',
                ],
            ],
        ]);
    }
}
