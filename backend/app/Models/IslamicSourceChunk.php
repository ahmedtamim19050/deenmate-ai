<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IslamicSourceChunk extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['citation' => 'array'];
    }

    protected static function booted(): void
    {
        // Chunks written through Eloquent get the same structure the bulk writers record.
        static::saving(function (IslamicSourceChunk $chunk) {
            $chunk->content_checksum = hash('sha256', (string) $chunk->content);
            if ($chunk->surah_number === null && preg_match('/^Quran (\d{1,3}):(\d{1,3})$/', (string) $chunk->reference, $m)) {
                $chunk->surah_number = (int) $m[1];
                $chunk->ayah_from = $chunk->ayah_to = (int) $m[2];
            }
        });

        static::saved(function (IslamicSourceChunk $chunk) {
            $chunk->source()->where('verification_status', 'approved')->first()?->forceFill([
                'verification_status' => 'pending',
                'reviewer_id' => null,
                'reviewed_at' => null,
            ])->save();
        });
    }

    public function source()
    {
        return $this->belongsTo(IslamicSource::class, 'islamic_source_id');
    }

    public function document()
    {
        return $this->belongsTo(IslamicSourceDocument::class, 'islamic_source_document_id');
    }
}
