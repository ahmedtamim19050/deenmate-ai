<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPrayerTemplate extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
