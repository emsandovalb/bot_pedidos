<?php

namespace App\Services\Operations;

use App\Models\Order;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * Etiquetas de estado, canal y tiempo que muestran las vistas de operaciones.
 */
class OrderDisplayLabels
{
    public function statusLabel(string $status): string
    {
        return match ($status) {
            Order::STATUS_PENDING_REVIEW => 'Nuevos',
            Order::STATUS_CONFIRMED => 'Confirmado',
            Order::STATUS_PREPARING => 'Preparando',
            Order::STATUS_READY_FOR_DISPATCH => 'Listo',
            Order::STATUS_DISPATCHED => 'Despachado',
            Order::STATUS_CANCELLED => 'Cancelado',
            Order::STATUS_REJECTED => 'Rechazado',
            default => Str::headline(str_replace('_', ' ', $status)),
        };
    }

    public function statusTone(string $status): string
    {
        return match ($status) {
            Order::STATUS_PENDING_REVIEW => 'bg-amber-50 text-amber-800 ring-1 ring-amber-100',
            Order::STATUS_CONFIRMED => 'bg-blue-50 text-blue-800 ring-1 ring-blue-100',
            Order::STATUS_PREPARING => 'bg-violet-50 text-violet-800 ring-1 ring-violet-100',
            Order::STATUS_READY_FOR_DISPATCH => 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-100',
            Order::STATUS_DISPATCHED => 'bg-slate-100 text-slate-700 ring-1 ring-slate-200',
            Order::STATUS_CANCELLED => 'bg-red-50 text-red-800 ring-1 ring-red-100',
            Order::STATUS_REJECTED => 'bg-slate-100 text-slate-700 ring-1 ring-slate-200',
            default => 'bg-slate-100 text-slate-700 ring-1 ring-slate-200',
        };
    }

    public function channelLabel(?string $channel): string
    {
        return match (strtolower((string) $channel)) {
            'whatsapp' => 'WhatsApp',
            'telegram' => 'Telegram',
            default => $channel !== null && $channel !== '' ? Str::headline($channel) : 'Sin canal',
        };
    }

    public function elapsedLabel(mixed $date): string
    {
        if (! $date instanceof CarbonInterface) {
            return 'Sin fecha';
        }

        $minutes = $date->diffInMinutes(now());

        if ($minutes < 60) {
            return 'Hace '.max(1, (int) $minutes).' min';
        }

        $hours = intdiv((int) $minutes, 60);

        if ($hours < 24) {
            return 'Hace '.$hours.' h';
        }

        $days = intdiv($hours, 24);

        return 'Hace '.$days.' d';
    }

    public function resolveItemLabel(?int $productId, ?string $rawText, ?string $productName): string
    {
        if ($productName !== null && $productName !== '') {
            return $productName;
        }

        if ($productId !== null) {
            return 'Producto #'.$productId;
        }

        $label = Str::of((string) $rawText)->squish()->toString();

        return $label !== '' ? $label : 'Sin texto';
    }
}
