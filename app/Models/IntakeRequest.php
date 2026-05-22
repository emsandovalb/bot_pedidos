<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IntakeRequest extends Model
{
    use HasFactory;

    protected $table = 'requests';

    public const STATUS_PENDING = 'pending';
    public const STATUS_NEEDS_REVIEW = 'needs_review';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_REJECTED = 'rejected';

    public const EVENT_CREATED = 'created';
    public const EVENT_EDITED = 'edited';
    public const EVENT_CONFIRMED = 'confirmed';
    public const EVENT_REJECTED = 'rejected';
    public const EVENT_STATUS_CHANGED = 'status_changed';

    protected $fillable = [
        'organization_id',
        'branch_id',
        'draw_id',
        'customer_id',
        'incoming_message_id',
        'detected_number',
        'detected_amount',
        'raw_text',
        'status',
        'confirmed_by',
        'confirmed_at',
        'rejected_by',
        'rejected_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'detected_amount' => 'decimal:2',
            'confirmed_at' => 'datetime',
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

    public function draw(): BelongsTo
    {
        return $this->belongsTo(Draw::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function incomingMessage(): BelongsTo
    {
        return $this->belongsTo(IncomingMessage::class);
    }

    public function confirmedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function rejectedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(IntakeRequestEvent::class);
    }
}
