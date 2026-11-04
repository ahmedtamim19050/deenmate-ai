<?php

namespace App\Models;

use App\Models\Concerns\AuditsAdminChanges;
use Illuminate\Database\Eloquent\Model;

class AiResponseFeedback extends Model
{
    use AuditsAdminChanges;

    protected $table = 'ai_response_feedback';

    protected $guarded = ['id'];

    public function message()
    {
        return $this->belongsTo(AiMessage::class, 'ai_message_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
