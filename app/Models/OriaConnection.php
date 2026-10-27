<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Una organización conectada con su cuenta de Oria, con el último plan que Oria le dio. */
class OriaConnection extends Model
{
    public const PLAN_BASIC = 'basico';

    public const PLAN_PLUS = 'plus';

    protected $fillable = [
        'organization_id',
        'oria_link_id',
        'plan',
        'account_name',
        'account_email',
        'connected_by',
        'plan_checked_at',
    ];

    protected function casts(): array
    {
        return ['plan_checked_at' => 'datetime'];
    }

    public function isPlus(): bool
    {
        return $this->plan === self::PLAN_PLUS;
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
