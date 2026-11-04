<?php

namespace App\Models;

use App\Models\Concerns\AuditsAdminChanges;
use Illuminate\Database\Eloquent\Model;

class AchievementRule extends Model
{
    use AuditsAdminChanges;

    protected $guarded = ['id'];

    public function achievement()
    {
        return $this->belongsTo(Achievement::class);
    }
}
