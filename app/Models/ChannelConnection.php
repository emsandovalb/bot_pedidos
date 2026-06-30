<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChannelConnection extends Model
{
    use HasFactory;

    public const CHANNEL_TELEGRAM = 'telegram';
    public const CHANNEL_WHATSAPP = 'whatsapp';
    public const CHANNEL_INSTAGRAM = 'instagram';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING = 'pending';
    public const STATUS_READY_FOR_SETUP = 'ready_for_setup';
    public const STATUS_CONNECTED = 'connected';
    public const STATUS_MISSING_CREDENTIALS = 'missing_credentials';
    public const STATUS_READY_FOR_VERIFICATION = 'ready_for_verification';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_WARNING = 'warning';
    public const STATUS_ERROR = 'error';

    protected $fillable = [
        'organization_id',
        'channel',
        'status',
        'display_name',
        'phone_number',
        'provider',
        'version',
        'provider_version',
        'provider_app_id',
        'provider_app_secret',
        'provider_access_token',
        'provider_verify_token',
        'provider_webhook_secret',
        'provider_phone_number_id',
        'provider_business_account_id',
        'provider_display_phone',
        'provider_api_version',
        'provider_business_name',
        'provider_business_timezone',
        'provider_business_country',
        'provider_status',
        'provider_configuration_status',
        'provider_last_validation_at',
        'provider_last_validation_error',
        'provider_metadata_json',
        'external_business_id',
        'external_phone_number_id',
        'quality_rating',
        'health_status',
        'webhook_status',
        'credentials_status',
        'last_health_check_at',
        'health_checked_at',
        'last_error',
        'last_ping',
        'last_received_message_at',
        'last_sent_message_at',
        'last_message_received_at',
        'last_message_sent_at',
        'metadata_json',
        'connected_at',
        'last_sync_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata_json' => 'array',
            'provider_metadata_json' => 'array',
            'provider_app_secret' => 'encrypted',
            'provider_access_token' => 'encrypted',
            'provider_verify_token' => 'encrypted',
            'provider_webhook_secret' => 'encrypted',
            'provider_last_validation_at' => 'datetime',
            'connected_at' => 'datetime',
            'last_sync_at' => 'datetime',
            'last_health_check_at' => 'datetime',
            'health_checked_at' => 'datetime',
            'last_ping' => 'datetime',
            'last_received_message_at' => 'datetime',
            'last_sent_message_at' => 'datetime',
            'last_message_received_at' => 'datetime',
            'last_message_sent_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function setupRequests(): HasMany
    {
        return $this->hasMany(SetupRequest::class);
    }
}
