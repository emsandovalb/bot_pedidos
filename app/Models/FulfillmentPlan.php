<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FulfillmentPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'order_id',
        'requested_date',
        'requested_time_window',
        'delivery_method',
        'payment_method',
        'pickup_branch_id',
        'delivery_address',
        'delivery_notes',
        'priority_score',
        'priority_level',
        'priority_reason',
        'commitment_date',
        'commitment_time',
        'sla_minutes',
        'planner_confidence',
        'planner_notes',
        'metadata_json',
    ];

    protected function casts(): array
    {
        return [
            'requested_date' => 'date',
            'priority_score' => 'integer',
            'commitment_date' => 'date',
            'sla_minutes' => 'integer',
            'planner_confidence' => 'integer',
            'metadata_json' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function pickupBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'pickup_branch_id');
    }

    public function scopeForOrganization(Builder $query, Organization|int $organization): Builder
    {
        $organizationId = $organization instanceof Organization ? $organization->id : $organization;

        return $query->where('organization_id', $organizationId);
    }
}
