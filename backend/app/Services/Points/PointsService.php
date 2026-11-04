<?php

namespace App\Services\Points;

use App\Models\ActivityLog;
use App\Models\PointRule;
use App\Models\PointTransaction;
use App\Models\User;
use App\Models\UserPointBalance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Server-authoritative Deen Points. These are motivational app points only:
 * they say nothing about worship, sincerity or reward from Allah.
 */
class PointsService
{
    public function activeRule(int $activityTypeId): ?PointRule
    {
        return PointRule::query()
            ->where('activity_type_id', $activityTypeId)
            ->where('is_active', true)
            ->orderByDesc('version')
            ->first();
    }

    /** Pure calculation, so the same value is used for previews and awards. */
    public function calculate(?PointRule $rule, int $quantity): int
    {
        if ($rule === null || $rule->per_quantity < 1) {
            return 0;
        }

        return (int) floor($quantity / $rule->per_quantity) * $rule->points;
    }

    /** Must run inside the caller's transaction. */
    public function awardForActivity(ActivityLog $log): ?PointTransaction
    {
        $rule = $this->activeRule($log->activity_type_id);
        $points = $this->calculate($rule, $log->quantity);

        if ($rule?->daily_cap !== null) {
            $already = (int) PointTransaction::query()
                ->join('activity_logs', 'activity_logs.id', '=', 'point_transactions.activity_log_id')
                ->where('point_transactions.user_id', $log->user_id)
                ->where('activity_logs.activity_type_id', $log->activity_type_id)
                ->whereDate('activity_logs.local_date', $log->local_date)
                ->where('activity_logs.id', '!=', $log->id)
                ->sum('point_transactions.points');
            $points = max(0, min($points, $rule->daily_cap - $already));
        }

        if ($points <= 0) {
            return null;
        }

        return $this->post(
            userId: $log->user_id,
            type: PointTransaction::TYPE_EARN,
            points: $points,
            idempotencyKey: "activity:{$log->id}:r{$log->revision}",
            attributes: [
                'activity_log_id' => $log->id,
                'point_rule_id' => $rule->id,
                'rule_version' => $rule->version,
            ],
        );
    }

    /** Reverses every not-yet-reversed earn for this activity. Never deletes. */
    public function reverseForActivity(ActivityLog $log, string $reason): int
    {
        $total = 0;
        $earns = PointTransaction::query()
            ->where('activity_log_id', $log->id)
            ->where('type', PointTransaction::TYPE_EARN)
            ->whereDoesntHave('reversal')
            ->orderBy('id')
            ->get();

        foreach ($earns as $earn) {
            $reversal = $this->post(
                userId: $log->user_id,
                type: PointTransaction::TYPE_REVERSE,
                points: -$earn->points,
                idempotencyKey: "reverse:{$earn->id}",
                attributes: [
                    'activity_log_id' => $log->id,
                    'point_rule_id' => $earn->point_rule_id,
                    'rule_version' => $earn->rule_version,
                    'reverses_transaction_id' => $earn->id,
                    'reason' => $reason,
                ],
            );
            $total += abs($reversal->points);
        }

        return $total;
    }

    public function adminAdjust(User $user, int $points, string $reason, User $actor): PointTransaction
    {
        return DB::transaction(fn () => $this->post(
            userId: $user->id,
            type: PointTransaction::TYPE_ADMIN,
            points: $points,
            idempotencyKey: 'admin:'.Str::uuid(),
            attributes: ['reason' => $reason, 'actor_id' => $actor->id],
        ));
    }

    /** Locks the balance row, clamps at zero and appends one ledger row. */
    private function post(int $userId, string $type, int $points, string $idempotencyKey, array $attributes = []): PointTransaction
    {
        return DB::transaction(function () use ($userId, $type, $points, $idempotencyKey, $attributes) {
            $existing = PointTransaction::query()
                ->where('user_id', $userId)
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existing !== null) {
                return $existing;
            }

            UserPointBalance::query()->insertOrIgnore([
                'user_id' => $userId,
                'balance' => 0,
                'lifetime_earned' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $balance = UserPointBalance::query()->whereKey($userId)->lockForUpdate()->firstOrFail();

            // The balance can never go negative, so a reduction takes at most what is there.
            $applied = $points < 0 ? -min(abs($points), (int) $balance->balance) : $points;
            $balance->balance = (int) $balance->balance + $applied;
            if ($type === PointTransaction::TYPE_EARN) {
                $balance->lifetime_earned = (int) $balance->lifetime_earned + $applied;
            } elseif ($type === PointTransaction::TYPE_REVERSE) {
                $balance->lifetime_earned = max(0, (int) $balance->lifetime_earned + $applied);
            }
            $balance->save();

            return PointTransaction::create($attributes + [
                'uuid' => (string) Str::uuid(),
                'user_id' => $userId,
                'type' => $type,
                'points' => $applied,
                'balance_after' => $balance->balance,
                'idempotency_key' => $idempotencyKey,
            ]);
        });
    }
}
