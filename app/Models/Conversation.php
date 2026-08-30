<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'branch_id',
        'channel_connection_id',
        'channel',
        'customer_id',
        'customer_identity_id',
        'status',
        'assigned_user_id',
        'service_window_expires_at',
        'last_message_at',
        'last_customer_message_at',
        'snoozed_until',
        'closed_at',
        'ai_summary',
        'ai_summary_updated_at',
        'unread_count',
    ];

    protected $casts = [
        'service_window_expires_at' => 'datetime',
        'last_message_at' => 'datetime',
        'last_customer_message_at' => 'datetime',
        'snoozed_until' => 'datetime',
        'closed_at' => 'datetime',
        'ai_summary_updated_at' => 'datetime',
    ];

    // --- Estados posibles de `status` ---
    public const STATUS_OPEN = 'open';
    public const STATUS_PENDING = 'pending';
    public const STATUS_SNOOZED = 'snoozed';
    public const STATUS_CLOSED = 'closed';

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function channelConnection(): BelongsTo
    {
        return $this->belongsTo(ChannelConnection::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function customerIdentity(): BelongsTo
    {
        return $this->belongsTo(CustomerIdentity::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ConversationEvent::class)->orderBy('created_at');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'conversation_tag');
    }

    public function llmUsages(): HasMany
    {
        return $this->hasMany(LlmUsage::class);
    }

    /**
     * true si la ventana de 24h de WhatsApp sigue abierta: se puede mandar
     * un mensaje de sesión libre; si no, hay que usar plantilla de Meta.
     */
    public function withinServiceWindow(): bool
    {
        return $this->service_window_expires_at !== null
            && $this->service_window_expires_at->isFuture();
    }

    public function needsHumanAttention(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
