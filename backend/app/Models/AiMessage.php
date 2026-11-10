<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiMessage extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'citations' => 'array',
            'analysis' => 'array',
        ];
    }

    public function conversation()
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }

    public function feedback()
    {
        return $this->hasMany(AiResponseFeedback::class);
    }
}
