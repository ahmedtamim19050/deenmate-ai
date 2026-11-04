<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPreference extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'haptics_enabled' => 'boolean',
            'show_points' => 'boolean',
            'ai_personalization_enabled' => 'boolean',
            'ads_personalized' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
