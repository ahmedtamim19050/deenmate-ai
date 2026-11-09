<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A verse a reviewer should look at, and how it was resolved. */
class QuranTranslationFlag extends Model
{
    public const ERROR = 'error';

    public const WARNING = 'warning';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function edition()
    {
        return $this->belongsTo(QuranTranslationEdition::class, 'quran_translation_edition_id');
    }

    public function verse()
    {
        return $this->belongsTo(QuranVerse::class, 'quran_verse_id');
    }
}
