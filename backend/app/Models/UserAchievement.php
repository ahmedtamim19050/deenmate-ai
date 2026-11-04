<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserAchievement extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'awarded_at' => 'datetime',
        ];
    }

    public function achievement()
    {
        return $this->belongsTo(Achievement::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
