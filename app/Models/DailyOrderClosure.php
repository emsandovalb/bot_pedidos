<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyOrderClosure extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'branch_id',
        'closure_date',
        'closed_by',
        'pending_review_count',
        'confirmed_count',
        'preparing_count',
        'ready_for_dispatch_count',
        'dispatched_count',
        'cancelled_count',
        'rejected_count',
        'total_orders',
        'total_items',
        'total_order_value',
        'notes',
        'export_path',
        'exported_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'closure_date' => 'date',
            'pending_review_count' => 'integer',
            'confirmed_count' => 'integer',
            'preparing_count' => 'integer',
            'ready_for_dispatch_count' => 'integer',
            'dispatched_count' => 'integer',
            'cancelled_count' => 'integer',
            'rejected_count' => 'integer',
            'total_orders' => 'integer',
            'total_items' => 'decimal:2',
            'total_order_value' => 'decimal:2',
            'exported_at' => 'datetime',
            'closed_at' => 'datetime',
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
