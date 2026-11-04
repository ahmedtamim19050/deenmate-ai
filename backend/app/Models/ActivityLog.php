<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'local_date' => 'date:Y-m-d',
            'metadata' => 'array',
            'reversed_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function type()
    {
        return $this->belongsTo(ActivityType::class, 'activity_type_id');
    }

    public function transactions()
    {
        return $this->hasMany(PointTransaction::class);
    }

    public function salahRecord()
    {
        return $this->hasOne(SalahRecord::class);
    }
}
