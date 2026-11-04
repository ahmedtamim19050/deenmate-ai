<?php

namespace App\Services\Achievements;

use App\Models\Achievement;
use App\Models\ActivityLog;
use App\Models\User;
use App\Models\UserAchievement;
use App\Models\UserGoal;

/** Private milestones of app activity. They never describe spiritual status. */
class AchievementService
{
    /** @return array<int, Achievement> newly awarded achievements */
    public function evaluate(User $user): array
    {
        $earned = UserAchievement::query()->where('user_id', $user->id)->pluck('achievement_id')->all();
        $candidates = Achievement::query()->with('rules')->where('is_active', true)->whereNotIn('id', $earned)->get();

        $new = [];
        foreach ($candidates as $achievement) {
            if ($achievement->rules->isEmpty()) {
                continue;
            }
            foreach ($achievement->rules as $rule) {
                if (! $this->ruleMet($user, $rule->rule_type, $rule->activity_type_key, $rule->threshold)) {
                    continue 2;
                }
            }
            $row = UserAchievement::query()->firstOrCreate(
                ['user_id' => $user->id, 'achievement_id' => $achievement->id],
                ['awarded_at' => now()],
            );
            if ($row->wasRecentlyCreated) {
                $new[] = $achievement;
            }
        }

        return $new;
    }

    private function ruleMet(User $user, string $type, ?string $activityKey, int $threshold): bool
    {
        return match ($type) {
            'activity_count' => ActivityLog::query()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->whereHas('type', fn ($q) => $q->where('key', $activityKey))
                ->count() >= $threshold,
            'activity_quantity' => (int) ActivityLog::query()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->whereHas('type', fn ($q) => $q->where('key', $activityKey))
                ->sum('quantity') >= $threshold,
            'goal_completed' => UserGoal::query()
                ->where('user_id', $user->id)
                ->where('status', 'completed')
                ->count() >= $threshold,
            default => false,
        };
    }
}
