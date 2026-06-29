<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderNotificationLog extends Model
{
    use HasFactory;

    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_QUEUED = 'queued';
    public const STATUS_SIMULATED = 'simulated';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'organization_id',
        'order_id',
        'customer_id',
        'channel',
        'event',
        'status',
        'should_send',
        'requires_template',
        'message_body',
        'reason',
        'provider',
        'provider_message_id',
        'sent_at',
        'error_message',
        'metadata_json',
        'evaluated_at',
    ];

    protected function casts(): array
    {
        return [
            'should_send' => 'boolean',
            'requires_template' => 'boolean',
            'sent_at' => 'datetime',
            'metadata_json' => 'array',
            'evaluated_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
