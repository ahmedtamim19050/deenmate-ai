<?php

namespace App\Models;

use App\Models\Concerns\AuditsAdminChanges;
use Illuminate\Database\Eloquent\Model;

class IslamicSource extends Model
{
    use AuditsAdminChanges;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function chunks()
    {
        return $this->hasMany(IslamicSourceChunk::class);
    }

    public function documents()
    {
        return $this->hasMany(IslamicSourceDocument::class);
    }

    /** The translation edition this source is the AI copy of, if any. */
    public function translationEdition()
    {
        return $this->hasOne(QuranTranslationEdition::class, 'islamic_source_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
