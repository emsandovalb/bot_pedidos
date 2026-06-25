<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class IncomingMessage extends Model
{
    use HasFactory;

    public const STATUS_RECEIVED = 'received';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_PROCESSED = 'processed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_DUPLICATE = 'duplicate';
    public const STATUS_IGNORED = 'ignored';
    public const STATUS_ERROR = self::STATUS_FAILED;

    protected $fillable = [
        'organization_id',
        'branch_id',
        'customer_id',
        'provider',
        'channel_type',
        'from_identifier',
        'to_identifier',
        'raw_text',
        'payload_json',
        'external_message_id',
        'status',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
            'parser_result_json' => 'array',
            'parser_confidence' => 'decimal:2',
            'processed_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (IncomingMessage $incomingMessage): void {
            if (blank($incomingMessage->provider)) {
                $incomingMessage->provider = (string) $incomingMessage->channel_type;
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function request(): HasOne
    {
        return $this->hasOne(IntakeRequest::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(IntakeRequest::class);
    }

    public function response(): HasOne
    {
        return $this->hasOne(MessageResponse::class);
    }
}
