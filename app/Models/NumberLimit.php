<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NumberLimit extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'branch_id',
        'draw_id',
        'number',
        'max_amount',
    ];

    protected function casts(): array
    {
        return [
            'max_amount' => 'decimal:2',
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
}
