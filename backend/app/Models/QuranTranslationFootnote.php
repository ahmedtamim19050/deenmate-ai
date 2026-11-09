<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuranTranslationFootnote extends Model
{
    protected $guarded = ['id'];

    public function translation()
    {
        return $this->belongsTo(QuranTranslation::class, 'quran_translation_id');
    }
}
