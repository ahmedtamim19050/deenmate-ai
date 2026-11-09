<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A tafsir work and edition, with its author, language and licence. */
class QuranTafsir extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function entries()
    {
        return $this->hasMany(QuranTafsirEntry::class);
    }

    public function source()
    {
        return $this->belongsTo(QuranDataSource::class, 'quran_data_source_id');
    }
}
