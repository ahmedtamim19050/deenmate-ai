<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrayerNotificationPreference extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'vibration' => 'boolean',
            'private_preview' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
