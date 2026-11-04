<?php

namespace App\Models;

use App\Models\Concerns\AuditsAdminChanges;
use Illuminate\Database\Eloquent\Model;

class HadithCollection extends Model
{
    use AuditsAdminChanges;

    protected $guarded = ['id'];

    public function records()
    {
        return $this->hasMany(HadithRecord::class);
    }
}
