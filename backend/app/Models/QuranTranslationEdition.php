<?php

namespace App\Models;

use App\Jobs\SyncTranslationToAi;
use App\Models\Concerns\AuditsAdminChanges;
use App\Services\Quran\Translations\TranslationAiSync;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class QuranTranslationEdition extends Model
{
    use AuditsAdminChanges;

    /** Registered, licence or content still awaited. */
    public const STATUS_PENDING = 'pending';

    /** Content stored, not yet reviewed. */
    public const STATUS_IMPORTED = 'imported';

    public const STATUS_UNDER_REVIEW = 'under_review';

    /** A reviewer approved it. The only state AI retrieval may use. */
    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    /** Withdrawn after approval. */
    public const STATUS_INACTIVE = 'inactive';

    public const STATUSES = [
        self::STATUS_PENDING, self::STATUS_IMPORTED, self::STATUS_UNDER_REVIEW,
        self::STATUS_APPROVED, self::STATUS_REJECTED, self::STATUS_INACTIVE,
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'source_updated_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'ai_synced_at' => 'datetime',
        ];
    }

    /**
     * Keeps the AI sources in step with review, whichever path changed the edition (review
     * workflow, re-import, rollback or the admin panel). Leaving "approved" switches the AI copy
     * off at once; becoming approved, or new content while approved, queues a fresh copy.
     */
    protected static function booted(): void
    {
        static::updated(function (self $edition) {
            if (! $edition->wasChanged(['review_status', 'content_checksum'])) {
                return;
            }
            if ($edition->review_status === self::STATUS_APPROVED) {
                SyncTranslationToAi::dispatch($edition->id)->afterCommit();
            } else {
                app(TranslationAiSync::class)->revoke($edition);
            }
        });
    }

    public function translations()
    {
        return $this->hasMany(QuranTranslation::class);
    }

    public function source()
    {
        return $this->belongsTo(QuranDataSource::class, 'quran_data_source_id');
    }

    public function revisions()
    {
        return $this->hasMany(QuranTranslationRevision::class)->orderBy('revision');
    }

    public function flags()
    {
        return $this->hasMany(QuranTranslationFlag::class);
    }

    public function reviews()
    {
        return $this->hasMany(QuranTranslationReview::class)->orderBy('id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** Editions approved by a reviewer. */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('review_status', self::STATUS_APPROVED);
    }

    /**
     * Editions the AI may use right now: approved, copied to the AI sources, and the copy matches
     * the current content. Checked at query time, so nothing stale is ever trusted.
     */
    public function scopeAiEligible(Builder $query): Builder
    {
        return $query->approved()
            ->where('ai_sync_status', TranslationAiSync::STATUS_SYNCED)
            ->whereNotNull('ai_synced_checksum')
            ->whereColumn('ai_synced_checksum', 'content_checksum');
    }
}
