<?php

namespace App\Models;

use App\Services\Fulfillment\FulfillmentPlannerService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;

    public const STATUS_PENDING_REVIEW = 'pending_review';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_PREPARING = 'preparing';
    public const STATUS_READY_FOR_DISPATCH = 'ready_for_dispatch';
    public const STATUS_DISPATCHED = 'dispatched';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'organization_id',
        'branch_id',
        'customer_id',
        'incoming_message_id',
        'possible_duplicate_of_order_id',
        'source_channel',
        'external_message_id',
        'status',
        'parser_confidence',
        'raw_message_text',
        'parsed_payload_json',
        'duplicate_score',
        'duplicate_reason',
        'duplicate_checked_at',
        'order_fingerprint',
        'notes',
        'reviewed_by',
        'reviewed_at',
        'confirmed_by',
        'confirmed_at',
        'preparing_at',
        'ready_for_dispatch_at',
        'dispatched_at',
        'cancelled_at',
        'rejected_at',
    ];

    protected $attributes = [
        'source_channel' => 'telegram',
        'status' => self::STATUS_PENDING_REVIEW,
    ];

    protected static function booted(): void
    {
        static::created(function (Order $order): void {
            app(FulfillmentPlannerService::class)->createDefaultPlan($order);
        });
    }

    protected function casts(): array
    {
        return [
            'parser_confidence' => 'decimal:2',
            'duplicate_score' => 'decimal:2',
            'duplicate_checked_at' => 'datetime',
            'parsed_payload_json' => 'array',
            'reviewed_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'preparing_at' => 'datetime',
            'ready_for_dispatch_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
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

    public function incomingMessage(): BelongsTo
    {
        return $this->belongsTo(IncomingMessage::class);
    }

    public function possibleDuplicateOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'possible_duplicate_of_order_id');
    }

    public function possibleDuplicates(): HasMany
    {
        return $this->hasMany(self::class, 'possible_duplicate_of_order_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function orderStatusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    public function fulfillmentPlan(): HasOne
    {
        return $this->hasOne(FulfillmentPlan::class);
    }

    public function manualReviews(): HasMany
    {
        return $this->hasMany(ManualReview::class);
    }

    public function notificationLogs(): HasMany
    {
        return $this->hasMany(OrderNotificationLog::class);
    }

    public function isPendingReview(): bool
    {
        return $this->status === self::STATUS_PENDING_REVIEW;
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function isDispatched(): bool
    {
        return $this->status === self::STATUS_DISPATCHED;
    }

    public function canBeReviewed(): bool
    {
        return $this->isPendingReview();
    }

    public function canBeDispatched(): bool
    {
        return in_array($this->status, [
            self::STATUS_CONFIRMED,
            self::STATUS_PREPARING,
            self::STATUS_READY_FOR_DISPATCH,
        ], true);
    }

    public function canBePrepared(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function canMoveToReadyForDispatch(): bool
    {
        return $this->status === self::STATUS_PREPARING;
    }

    public function canBeMarkedDispatched(): bool
    {
        return $this->status === self::STATUS_READY_FOR_DISPATCH;
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING_REVIEW,
            self::STATUS_CONFIRMED,
            self::STATUS_PREPARING,
            self::STATUS_READY_FOR_DISPATCH,
        ], true);
    }
}
