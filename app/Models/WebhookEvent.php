<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebhookEvent extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'organization_id',
        'provider',
        'event_type',
        'method',
        'ip',
        'status',
        'payload_json',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
