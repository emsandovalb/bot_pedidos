<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LlmUsage extends Model
{
    protected $fillable = [
        'organization_id',
        'conversation_id',
        'message_id',
        'agent_type',
        'channel',
        'model',
        'tokens_input',
        'tokens_output',
        'cost',
    ];

    protected $casts = [
        'cost' => 'decimal:6',
    ];

    public const AGENT_CHAT = 'chat';
    public const AGENT_EMBEDDINGS = 'embeddings';
    public const AGENT_CLASSIFIER = 'classifier';
    public const AGENT_TRANSCRIPTION = 'transcription';
    public const AGENT_MODERATION = 'moderation';

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
