<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPrayerSetting extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
