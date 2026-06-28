<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerIdentity extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'customer_id',
        'provider',
        'external_user_id',
        'external_chat_id',
        'provider_username',
        'phone',
        'normalized_phone',
        'email',
        'display_name',
        'confidence_score',
        'is_primary',
        'metadata_json',
        'first_seen_at',
        'last_seen_at',
        'last_customer_message_at',
        'service_window_expires_at',
    ];

    protected $attributes = [
        'confidence_score' => 100,
        'is_primary' => false,
    ];

    protected function casts(): array
    {
        return [
            'metadata_json' => 'array',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'last_customer_message_at' => 'datetime',
            'service_window_expires_at' => 'datetime',
            'is_primary' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
