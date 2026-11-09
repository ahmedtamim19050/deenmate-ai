<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuranTranslation extends Model
{
    protected $guarded = ['id'];

    public function verse()
    {
        return $this->belongsTo(QuranVerse::class, 'quran_verse_id');
    }

    public function edition()
    {
        return $this->belongsTo(QuranTranslationEdition::class, 'quran_translation_edition_id');
    }

    /** Parsed footnotes in their published order. The raw field stays the authoritative copy. */
    public function footnotes()
    {
        return $this->hasMany(QuranTranslationFootnote::class)->orderBy('position');
    }
}
