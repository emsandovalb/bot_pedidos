<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChannelConnection extends Model
{
    use HasFactory;

    public const CHANNEL_WHATSAPP = 'whatsapp';

    public const STATUS_DRAFT = 'draft';

    protected $fillable = [
        'organization_id',
        'channel',
        'status',
        'display_name',
        'phone_number',
        'provider',
        'external_business_id',
        'external_phone_number_id',
        'quality_rating',
        'metadata_json',
        'connected_at',
        'last_sync_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata_json' => 'array',
            'connected_at' => 'datetime',
            'last_sync_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
