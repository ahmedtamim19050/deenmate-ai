<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalahRecord extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'prayer_date' => 'date:Y-m-d',
            'confirmed_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function activityLog()
    {
        return $this->belongsTo(ActivityLog::class);
    }

    public function items()
    {
        return $this->hasMany(SalahRecordItem::class);
    }
}
