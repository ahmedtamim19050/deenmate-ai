<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IslamicSourceChunk extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::saved(function (IslamicSourceChunk $chunk) {
            $chunk->source()->where('verification_status', 'approved')->first()?->forceFill([
                'verification_status' => 'pending',
                'reviewer_id' => null,
                'reviewed_at' => null,
            ])->save();
        });
    }

    public function source()
    {
        return $this->belongsTo(IslamicSource::class, 'islamic_source_id');
    }
}
