<?php

namespace App\Console\Commands;

use App\Models\QuranTranslationEdition as Edition;
use App\Models\User;
use App\Services\Quran\Translations\TranslationReviewWorkflow;
use DomainException;
use Illuminate\Console\Command;

class TranslationReview extends Command
{
    private const ACTIONS = [
        'start' => Edition::STATUS_UNDER_REVIEW,
        'approve' => Edition::STATUS_APPROVED,
        'reject' => Edition::STATUS_REJECTED,
        'deactivate' => Edition::STATUS_INACTIVE,
    ];

    protected $signature = 'quran:translation-review
        {identifier : Edition identifier}
        {action : start, approve, reject, deactivate or activate}
        {--reviewer= : Email of a Scholar Reviewer or Super Administrator}
        {--notes= : Reasons for the decision (required to approve or reject)}';

    protected $description = 'Record a review decision for a translation edition';

    public function handle(TranslationReviewWorkflow $workflow): int
    {
        $edition = Edition::query()->where('identifier', $this->argument('identifier'))->first();
        $reviewer = User::query()->where('email', $this->option('reviewer'))->first();
        $action = $this->argument('action');
        if (! $edition || ! $reviewer || ! (isset(self::ACTIONS[$action]) || $action === 'activate')) {
            $this->error('Give an existing edition, a valid action and --reviewer=.');

            return self::FAILURE;
        }
        try {
            $edition = $action === 'activate'
                ? $workflow->activate($edition, $reviewer)
                : $workflow->transition($edition, self::ACTIONS[$action], $reviewer, $this->option('notes'));
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->info("{$edition->identifier} is now {$edition->review_status}".($edition->is_active ? ' and shown in the app.' : ' and hidden.'));

        return self::SUCCESS;
    }
}
