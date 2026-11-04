<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalahRecordItem extends Model
{
    protected $guarded = ['id'];

    public function record()
    {
        return $this->belongsTo(SalahRecord::class, 'salah_record_id');
    }
}
