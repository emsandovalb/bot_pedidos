<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationSetting extends Model
{
    use HasFactory;

    public const CHANNEL_WHATSAPP = 'whatsapp';
    public const CHANNEL_TELEGRAM = 'telegram';

    public const EVENT_ORDER_CREATED = 'order_created';
    public const EVENT_ORDER_CONFIRMED = 'order_confirmed';
    public const EVENT_ORDER_PREPARING = 'order_preparing';
    public const EVENT_ORDER_READY_FOR_DISPATCH = 'order_ready_for_dispatch';
    public const EVENT_ORDER_DISPATCHED = 'order_dispatched';
    public const EVENT_ORDER_CANCELLED = 'order_cancelled';
    public const EVENT_ORDER_REJECTED = 'order_rejected';

    public const CHANNELS = [
        self::CHANNEL_WHATSAPP,
        self::CHANNEL_TELEGRAM,
    ];

    public const EVENTS = [
        self::EVENT_ORDER_CREATED,
        self::EVENT_ORDER_CONFIRMED,
        self::EVENT_ORDER_PREPARING,
        self::EVENT_ORDER_READY_FOR_DISPATCH,
        self::EVENT_ORDER_DISPATCHED,
        self::EVENT_ORDER_CANCELLED,
        self::EVENT_ORDER_REJECTED,
    ];

    public const CHANNEL_LABELS = [
        self::CHANNEL_WHATSAPP => 'WhatsApp',
        self::CHANNEL_TELEGRAM => 'Telegram',
    ];

    public const EVENT_LABELS = [
        self::EVENT_ORDER_CREATED => 'Pedido creado',
        self::EVENT_ORDER_CONFIRMED => 'Pedido confirmado',
        self::EVENT_ORDER_PREPARING => 'Pedido en preparacion',
        self::EVENT_ORDER_READY_FOR_DISPATCH => 'Pedido listo para despacho',
        self::EVENT_ORDER_DISPATCHED => 'Pedido despachado',
        self::EVENT_ORDER_CANCELLED => 'Pedido cancelado',
        self::EVENT_ORDER_REJECTED => 'Pedido rechazado',
    ];

    protected $fillable = [
        'organization_id',
        'channel',
        'event',
        'is_enabled',
        'requires_open_service_window',
        'use_template_if_window_closed',
        'template_name',
        'message_body',
        'metadata_json',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'requires_open_service_window' => 'boolean',
            'use_template_if_window_closed' => 'boolean',
            'metadata_json' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultAttributes(string $channel, string $event): array
    {
        $channel = strtolower(trim($channel));
        $event = strtolower(trim($event));

        $isTelegram = $channel === self::CHANNEL_TELEGRAM;
        $enabledByDefault = $isTelegram
            || in_array($event, [self::EVENT_ORDER_CREATED, self::EVENT_ORDER_READY_FOR_DISPATCH], true);

        return [
            'channel' => $channel,
            'event' => $event,
            'is_enabled' => $enabledByDefault,
            'requires_open_service_window' => $channel === self::CHANNEL_WHATSAPP && $enabledByDefault,
            'use_template_if_window_closed' => false,
            'template_name' => null,
            'message_body' => null,
            'metadata_json' => null,
        ];
    }

    public static function channelLabel(string $channel): string
    {
        return self::CHANNEL_LABELS[strtolower(trim($channel))] ?? ucfirst($channel);
    }

    public static function eventLabel(string $event): string
    {
        return self::EVENT_LABELS[strtolower(trim($event))] ?? str_replace('_', ' ', ucfirst($event));
    }
}
