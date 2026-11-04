<?php

namespace App\Services\Activity;

use App\Models\ActivityLog;

class ActivityResult
{
    public function __construct(
        public readonly ActivityLog $log,
        public readonly int $pointsAwarded,
        public readonly bool $duplicate,
        public readonly array $newAchievements = [],
    ) {}
}
