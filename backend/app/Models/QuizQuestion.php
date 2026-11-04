<?php

namespace App\Models;

use App\Models\Concerns\AuditsAdminChanges;
use Illuminate\Database\Eloquent\Model;

class QuizQuestion extends Model
{
    use AuditsAdminChanges;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'options' => 'array',
        ];
    }

    public function quiz()
    {
        return $this->belongsTo(Quiz::class);
    }
}
