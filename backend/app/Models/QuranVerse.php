<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuranVerse extends Model
{
    protected $guarded = ['id'];

    public function surah()
    {
        return $this->belongsTo(QuranSurah::class, 'surah_id');
    }

    public function translations()
    {
        return $this->hasMany(QuranTranslation::class);
    }
}
