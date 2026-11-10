<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A surah of a Quran edition, a book of a Hadith collection, or a chapter of a reference work. */
class IslamicSourceDocument extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function source()
    {
        return $this->belongsTo(IslamicSource::class, 'islamic_source_id');
    }

    public function chunks()
    {
        return $this->hasMany(IslamicSourceChunk::class);
    }
}
