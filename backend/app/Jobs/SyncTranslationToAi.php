<?php

namespace App\Jobs;

use App\Models\QuranTranslationEdition;
use App\Services\Quran\Translations\TranslationAiSync;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Copies an approved edition into the AI sources, off the reviewer's request. */
class SyncTranslationToAi implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 600;

    public function __construct(public int $editionId) {}

    public function uniqueId(): string
    {
        return (string) $this->editionId;
    }

    public function handle(TranslationAiSync $sync): void
    {
        $edition = QuranTranslationEdition::query()->find($this->editionId);
        if ($edition) {
            $sync->sync($edition);
        }
    }
}
