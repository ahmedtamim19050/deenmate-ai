<?php

namespace App\Services\Quran;

use App\Models\QuranImportLog;

final class ArabicImportResult
{
    /**
     * @param  list<string>  $problems
     * @param  list<string>  $differences  verse keys whose stored text differed from the source
     * @param  list<string>  $divisionMismatches
     */
    public function __construct(
        public readonly bool $ok,
        public readonly QuranImportLog $log,
        public readonly array $problems,
        public readonly int $inserted = 0,
        public readonly int $updated = 0,
        public readonly int $unchanged = 0,
        public readonly array $differences = [],
        public readonly bool $replacedText = false,
        public readonly array $divisionMismatches = [],
    ) {}

    public static function failed(QuranImportLog $log, array $problems): self
    {
        return new self(false, $log, $problems);
    }
}
