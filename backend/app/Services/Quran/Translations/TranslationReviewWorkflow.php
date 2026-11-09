<?php

namespace App\Services\Quran\Translations;

use App\Models\QuranTranslationEdition as Edition;
use App\Models\QuranTranslationFlag;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Human review of translation editions. Every change is made by a named reviewer and recorded
 * with a timestamp. Nothing is ever approved automatically, and an edition with unresolved flags
 * cannot be approved. Only approved editions can be switched on or used by AI retrieval.
 */
class TranslationReviewWorkflow
{
    /** Allowed moves. "imported" is entered only by an import, never by hand. */
    public const TRANSITIONS = [
        Edition::STATUS_IMPORTED => [Edition::STATUS_UNDER_REVIEW, Edition::STATUS_REJECTED],
        Edition::STATUS_UNDER_REVIEW => [Edition::STATUS_APPROVED, Edition::STATUS_REJECTED],
        Edition::STATUS_APPROVED => [Edition::STATUS_INACTIVE, Edition::STATUS_UNDER_REVIEW],
        Edition::STATUS_INACTIVE => [Edition::STATUS_UNDER_REVIEW],
        Edition::STATUS_REJECTED => [Edition::STATUS_UNDER_REVIEW],
        Edition::STATUS_PENDING => [],
    ];

    public function __construct(private TranslationHistory $history) {}

    public function transition(Edition $edition, string $to, User $reviewer, ?string $notes = null): Edition
    {
        $this->assertReviewer($reviewer);
        $from = $edition->review_status;
        if (! in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
            throw new DomainException("An edition cannot move from {$from} to {$to}.");
        }
        if (in_array($to, [Edition::STATUS_APPROVED, Edition::STATUS_REJECTED], true) && blank($notes)) {
            throw new DomainException('Approving or rejecting needs notes explaining the decision.');
        }
        if ($to === Edition::STATUS_APPROVED) {
            $open = $this->openFlags($edition);
            if ($open > 0) {
                throw new DomainException("{$edition->identifier} has {$open} unresolved flags. Resolve each one before approving.");
            }
            if ($edition->current_revision === null) {
                throw new DomainException('Only content imported with revision history can be approved.');
            }
        }

        DB::transaction(function () use ($edition, $from, $to, $reviewer, $notes) {
            $edition->forceFill([
                'review_status' => $to,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_notes' => $notes,
                // Leaving "approved" always takes the edition out of the app.
                'is_active' => $to === Edition::STATUS_APPROVED ? $edition->is_active : false,
            ])->save();
            $this->history->logReview($edition, $from, $to, $reviewer, $edition->current_revision, $notes);
        });

        return $edition->refresh();
    }

    /** Shows an approved edition in the app. */
    public function activate(Edition $edition, User $reviewer): Edition
    {
        $this->assertReviewer($reviewer);
        if ($edition->review_status !== Edition::STATUS_APPROVED) {
            throw new DomainException('Only an approved edition can be switched on.');
        }
        $edition->forceFill(['is_active' => true])->save();
        $this->history->logReview($edition, Edition::STATUS_APPROVED, Edition::STATUS_APPROVED, $reviewer, $edition->current_revision, 'Switched on in the app.');

        return $edition;
    }

    /** Marks flags as reviewed. The verse text is never changed by this. */
    public function resolveFlags(Edition $edition, array $flagIds, User $reviewer, string $notes): int
    {
        $this->assertReviewer($reviewer);
        if (blank($notes)) {
            throw new DomainException('Resolving a flag needs notes saying what was checked.');
        }

        return QuranTranslationFlag::query()
            ->where('quran_translation_edition_id', $edition->id)
            ->whereIn('id', $flagIds)
            ->whereNull('resolved_at')
            ->update(['resolved_at' => now(), 'resolved_by' => $reviewer->id, 'resolution_notes' => $notes]);
    }

    public function openFlags(Edition $edition): int
    {
        return QuranTranslationFlag::query()->where('quran_translation_edition_id', $edition->id)->whereNull('resolved_at')->count();
    }

    private function assertReviewer(User $user): void
    {
        if (! $user->hasAdminRole(User::ROLE_SCHOLAR_REVIEWER, User::ROLE_SUPER_ADMIN)) {
            throw new DomainException('Only a Scholar Reviewer or Super Administrator can review translations.');
        }
    }
}
