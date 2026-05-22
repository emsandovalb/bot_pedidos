<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageResponse extends Model
{
    use HasFactory;

    protected $fillable = [
        'incoming_message_id',
        'response_type',
        'response_text',
        'parser_result_json',
    ];

    protected function casts(): array
    {
        return [
            'parser_result_json' => 'array',
        ];
    }

    public function incomingMessage(): BelongsTo
    {
        return $this->belongsTo(IncomingMessage::class);
    }
}
