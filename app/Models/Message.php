<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'conversation_id',
        'direction',
        'sender_type',
        'user_id',
        'status',
        'content_type',
        'content',
        'template_name',
        'template_params',
        'media_url',
        'provider_message_id',
        'provider_payload',
        'ai_draft_content',
        'ai_generated',
        'ai_model',
        'ai_tokens_input',
        'ai_tokens_output',
        'ai_cost',
        'reviewed_by',
        'reviewed_at',
        'incoming_message_id',
        'sent_at',
        'delivered_at',
        'read_at',
        'failed_at',
        'failure_reason',
    ];

    protected $casts = [
        'template_params' => 'array',
        'provider_payload' => 'array',
        'ai_generated' => 'boolean',
        'ai_cost' => 'decimal:6',
        'reviewed_at' => 'datetime',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    // --- direction ---
    public const DIRECTION_INBOUND = 'inbound';
    public const DIRECTION_OUTBOUND = 'outbound';

    // --- sender_type ---
    public const SENDER_CUSTOMER = 'customer';
    public const SENDER_AI_AGENT = 'ai_agent';
    public const SENDER_HUMAN_AGENT = 'human_agent';
    public const SENDER_SYSTEM = 'system';

    // --- status ---
    public const STATUS_DRAFT = 'draft';               // IA redactó, no se envió (modo Entrenamiento)
    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_SENT = 'sent';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_READ = 'read';
    public const STATUS_FAILED = 'failed';
    public const STATUS_DISCARDED = 'discarded';

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function incomingMessage(): BelongsTo
    {
        return $this->belongsTo(IncomingMessage::class);
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * El humano edita el borrador de la IA. Guarda el original en
     * ai_draft_content para poder medir cuánto se edita en promedio.
     */
    public function approveWithEdit(string $finalContent, int $reviewerId): void
    {
        $this->ai_draft_content ??= $this->content;
        $this->content = $finalContent;
        $this->status = self::STATUS_APPROVED;
        $this->reviewed_by = $reviewerId;
        $this->reviewed_at = now();
        $this->save();
    }

    public function discard(int $reviewerId): void
    {
        $this->status = self::STATUS_DISCARDED;
        $this->reviewed_by = $reviewerId;
        $this->reviewed_at = now();
        $this->save();
    }
}
