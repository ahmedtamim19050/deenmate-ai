<?php

namespace App\Models;

use App\Models\Concerns\AuditsAdminChanges;
use Illuminate\Database\Eloquent\Model;

class PointRule extends Model
{
    use AuditsAdminChanges;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::creating(function (PointRule $rule) {
            $rule->version ??= (int) self::query()->where('activity_type_id', $rule->activity_type_id)->max('version') + 1;
        });
        static::created(function (PointRule $rule) {
            if ($rule->is_active ?? true) {
                self::query()
                    ->where('activity_type_id', $rule->activity_type_id)
                    ->whereKeyNot($rule->id)
                    ->update(['is_active' => false]);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function activityType()
    {
        return $this->belongsTo(ActivityType::class);
    }
}
