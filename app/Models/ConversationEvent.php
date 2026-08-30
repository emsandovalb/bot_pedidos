<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationEvent extends Model
{
    public $timestamps = true;
    const UPDATED_AT = null; // solo nos interesa created_at, es un log append-only

    protected $fillable = [
        'conversation_id',
        'type',
        'actor_type',
        'actor_user_id',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public const TYPE_ASSIGNED = 'assigned';
    public const TYPE_STATUS_CHANGED = 'status_changed';
    public const TYPE_NOTE_ADDED = 'note_added';
    public const TYPE_ESCALATED = 'escalated';
    public const TYPE_CHANNEL_MODE_CHANGED = 'channel_mode_changed';
    public const TYPE_TAG_ADDED = 'tag_added';
    public const TYPE_TAG_REMOVED = 'tag_removed';
    public const TYPE_SUMMARY_UPDATED = 'summary_updated';

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function actorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
