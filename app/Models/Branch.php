<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    public const CHANNEL_TYPE_SIMULATED = 'simulated';
    public const CHANNEL_TYPE_TELEGRAM = 'telegram';
    public const CHANNEL_TYPE_WHATSAPP = 'whatsapp';

    protected $fillable = [
        'organization_id',
        'name',
        'channel_type',
        'channel_identifier',
        'status',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function incomingMessages(): HasMany
    {
        return $this->hasMany(IncomingMessage::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(IntakeRequest::class);
    }

    public function numberLimits(): HasMany
    {
        return $this->hasMany(NumberLimit::class);
    }

    public function dailyClosures(): HasMany
    {
        return $this->hasMany(BranchDailyClosure::class);
    }
}
