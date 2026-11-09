<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Where Quran data came from, under which licence, and the attribution it requires. */
class QuranDataSource extends Model
{
    protected $guarded = ['id'];

    public function verses()
    {
        return $this->hasMany(QuranVerse::class);
    }

    public function importLogs()
    {
        return $this->hasMany(QuranImportLog::class);
    }
}
