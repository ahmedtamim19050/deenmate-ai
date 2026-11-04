<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataExportRequest extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
