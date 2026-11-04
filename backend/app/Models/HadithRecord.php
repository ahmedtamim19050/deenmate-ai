<?php

namespace App\Models;

use App\Models\Concerns\AuditsAdminChanges;
use Illuminate\Database\Eloquent\Model;

class HadithRecord extends Model
{
    use AuditsAdminChanges;

    protected $guarded = ['id'];

    public function collection()
    {
        return $this->belongsTo(HadithCollection::class, 'hadith_collection_id');
    }
}
