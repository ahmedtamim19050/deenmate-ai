<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One review decision: who moved the edition from which state to which, and why. */
class QuranTranslationReview extends Model
{
    protected $guarded = ['id'];

    public function edition()
    {
        return $this->belongsTo(QuranTranslationEdition::class, 'quran_translation_edition_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
