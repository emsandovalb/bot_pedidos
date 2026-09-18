<?php

namespace App\Http\Controllers;

use App\Models\IncomingMessage;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Healthcheck de readiness: además de "el proceso vive" (eso lo cubre /up),
 * comprueba la BD y expone métricas para detectar que el poller se cayó
 * (mensajes entrantes que dejan de llegar) o que se acumulan fallos.
 *
 * Devuelve 503 solo si la BD no responde. El resto son métricas para un
 * monitor/alerta externa; un piloto sin tráfico de madrugada no es un fallo.
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [];
        $healthy = true;

        try {
            DB::connection()->select('select 1');
            $checks['database'] = 'ok';
        } catch (Throwable $e) {
            $checks['database'] = 'error';
            $healthy = false;
        }

        if ($healthy) {
            $lastInboundAt = IncomingMessage::query()->max('received_at');
            $lastInboundAt = $lastInboundAt ? Carbon::parse($lastInboundAt) : null;

            $checks['last_inbound_message_at'] = $lastInboundAt?->toIso8601String();
            $checks['minutes_since_last_inbound'] = $lastInboundAt
                ? (int) $lastInboundAt->diffInMinutes(now())
                : null;

            $checks['failed_messages_last_hour'] = IncomingMessage::query()
                ->where('status', IncomingMessage::STATUS_FAILED)
                ->where('updated_at', '>=', now()->subHour())
                ->count();

            $checks['orders_pending_review'] = Order::query()
                ->where('status', Order::STATUS_PENDING_REVIEW)
                ->count();
        }

        return response()->json([
            'status' => $healthy ? 'ok' : 'error',
            'time' => now()->toIso8601String(),
            'checks' => $checks,
        ], $healthy ? 200 : 503);
    }
}
