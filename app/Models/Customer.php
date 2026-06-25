<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'branch_id',
        'name',
        'phone',
        'external_id',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function incomingMessages(): HasMany
    {
        return $this->hasMany(IncomingMessage::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(IntakeRequest::class);
    }

    public function customerIdentities(): HasMany
    {
        return $this->hasMany(CustomerIdentity::class);
    }
}
