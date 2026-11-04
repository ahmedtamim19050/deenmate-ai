<?php

namespace App\Models;

use App\Models\Concerns\AuditsAdminChanges;
use Illuminate\Database\Eloquent\Model;

class ActivityType extends Model
{
    use AuditsAdminChanges;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'allows_in_app_source' => 'boolean',
            'allows_manual_source' => 'boolean',
            'one_per_day' => 'boolean',
            'client_creatable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function rules()
    {
        return $this->hasMany(PointRule::class);
    }
}
