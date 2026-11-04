<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\IslamicSource;
use App\Models\ScholarReview;
use App\Models\User;
use Illuminate\Console\Command;

/** CLI equivalent of the admin review action. It is attributed to a real reviewer account. */
class ReviewSource extends Command
{
    protected $signature = 'sources:review {source : Source id} {reviewer : Email of a Scholar Reviewer or Super Administrator} {--reject} {--notes=}';

    protected $description = 'Approve or reject an Islamic source on behalf of a named reviewer';

    public function handle(): int
    {
        $reviewer = User::query()->where('email', $this->argument('reviewer'))->first();
        if ($reviewer === null || ! $reviewer->hasAdminRole(User::ROLE_SCHOLAR_REVIEWER, User::ROLE_SUPER_ADMIN)) {
            $this->error('That account is not a Scholar Reviewer or Super Administrator.');

            return self::FAILURE;
        }

        $source = IslamicSource::query()->findOrFail($this->argument('source'));
        $status = $this->option('reject') ? 'rejected' : 'approved';
        $source->update([
            'verification_status' => $status,
            'reviewer_id' => $reviewer->id,
            'reviewed_at' => now(),
            'review_notes' => $this->option('notes'),
        ]);
        ScholarReview::query()->create([
            'reviewable_type' => $source->getMorphClass(),
            'reviewable_id' => $source->id,
            'reviewer_id' => $reviewer->id,
            'status' => $status,
            'notes' => $this->option('notes'),
            'reviewed_at' => now(),
        ]);
        AuditLog::record('source.'.$status, $source, [], $reviewer->id);
        $this->info("Source {$source->id} is now {$status}.");

        return self::SUCCESS;
    }
}
