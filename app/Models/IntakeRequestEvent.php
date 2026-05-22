<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntakeRequestEvent extends Model
{
    use HasFactory;

    public const EVENT_CREATED = 'created';
    public const EVENT_EDITED = 'edited';
    public const EVENT_CONFIRMED = 'confirmed';
    public const EVENT_REJECTED = 'rejected';
    public const EVENT_STATUS_CHANGED = 'status_changed';

    public $timestamps = false;

    protected $fillable = [
        'intake_request_id',
        'user_id',
        'event_type',
        'old_values',
        'new_values',
        'notes',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function intakeRequest(): BelongsTo
    {
        return $this->belongsTo(IntakeRequest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
