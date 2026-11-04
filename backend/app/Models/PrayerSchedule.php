<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrayerSchedule extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'fajr_at' => 'datetime',
            'sunrise_at' => 'datetime',
            'dhuhr_at' => 'datetime',
            'asr_at' => 'datetime',
            'maghrib_at' => 'datetime',
            'isha_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
