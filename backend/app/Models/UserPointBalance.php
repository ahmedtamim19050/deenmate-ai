<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPointBalance extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
