<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Commentary on verses [verse_from]..[verse_to] of one surah. */
class QuranTafsirEntry extends Model
{
    protected $guarded = ['id'];

    public function tafsir()
    {
        return $this->belongsTo(QuranTafsir::class, 'quran_tafsir_id');
    }

    public function reference(): string
    {
        return $this->verse_from === $this->verse_to
            ? "{$this->surah_id}:{$this->verse_from}"
            : "{$this->surah_id}:{$this->verse_from}-{$this->verse_to}";
    }
}
