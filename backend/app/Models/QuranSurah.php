<?php

namespace App\Models;

use App\Models\Concerns\AuditsAdminChanges;
use Illuminate\Database\Eloquent\Model;

class QuranSurah extends Model
{
    use AuditsAdminChanges;

    public $incrementing = false;

    protected $guarded = [];

    public function verses()
    {
        return $this->hasMany(QuranVerse::class, 'surah_id');
    }
}
