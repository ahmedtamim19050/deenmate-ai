<?php

namespace App\Models;

use App\Models\Concerns\AuditsAdminChanges;
use Illuminate\Database\Eloquent\Model;

class Quiz extends Model
{
    use AuditsAdminChanges;

    protected $table = 'quizzes';

    protected $guarded = ['id'];

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    public function questions()
    {
        return $this->hasMany(QuizQuestion::class);
    }
}
