<?php

namespace App\Models;

use App\Models\Concerns\AuditsAdminChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lesson extends Model
{
    use AuditsAdminChanges, SoftDeletes;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(function (Lesson $lesson) {
            if ($lesson->isDirty(['body', 'references', 'title']) && $lesson->review_status === 'approved' && ! $lesson->isDirty('review_status')) {
                $lesson->review_status = 'pending';
            }
        });
    }

    protected function casts(): array
    {
        return [
            'references' => 'array',
            'is_published' => 'boolean',
        ];
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function quiz()
    {
        return $this->hasOne(Quiz::class);
    }
}
