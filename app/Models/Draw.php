<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Draw extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'organization_id',
        'name',
        'draw_time',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'draw_time' => 'string',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(IntakeRequest::class);
    }

    public function numberLimits(): HasMany
    {
        return $this->hasMany(NumberLimit::class);
    }
}
