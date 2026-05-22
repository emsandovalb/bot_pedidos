<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchDailyClosure extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'branch_id',
        'closed_by',
        'closure_date',
        'total_requests',
        'total_confirmed',
        'total_rejected',
        'total_pending',
        'total_amount_confirmed',
        'notes',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'closure_date' => 'date',
            'closed_at' => 'datetime',
            'total_requests' => 'integer',
            'total_confirmed' => 'integer',
            'total_rejected' => 'integer',
            'total_pending' => 'integer',
            'total_amount_confirmed' => 'decimal:2',
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

    public function closedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
