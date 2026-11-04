<?php

namespace App\Services\Activity;

use App\Models\ActivityLog;
use App\Models\ActivityType;
use App\Models\AuditLog;
use App\Models\DailySummary;
use App\Models\PointTransaction;
use App\Models\User;
use App\Services\Achievements\AchievementService;
use App\Services\Points\PointsService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ActivityService
{
    public const SOURCE_IN_APP = 'in_app';

    public const SOURCE_MANUAL = 'manual';

    public function __construct(
        private PointsService $points,
        private AchievementService $achievements,
    ) {}

    /**
     * Idempotent: the same key always returns the first result and awards nothing new.
     * $trusted is true only for server-side callers such as Salah confirmation and lesson completion.
     */
    public function record(
        User $user,
        string $typeKey,
        int $quantity,
        string $source,
        string $idempotencyKey,
        Carbon $occurredAt,
        string $localDate,
        array $metadata = [],
        bool $trusted = false,
    ): ActivityResult {
        $existing = $this->findExisting($user, $idempotencyKey);
        if ($existing !== null) {
            return $this->asDuplicate($existing);
        }

        $type = ActivityType::query()->where('key', $typeKey)->where('is_active', true)->first();
        $this->validate($user, $type, $typeKey, $quantity, $source, $localDate, $trusted);

        try {
            return DB::transaction(function () use ($user, $type, $quantity, $source, $idempotencyKey, $occurredAt, $localDate, $metadata) {
                $log = ActivityLog::create([
                    'uuid' => (string) Str::uuid(),
                    'user_id' => $user->id,
                    'activity_type_id' => $type->id,
                    'idempotency_key' => $idempotencyKey,
                    'quantity' => $quantity,
                    'source' => $source,
                    'occurred_at' => $occurredAt,
                    'local_date' => $localDate,
                    'metadata' => $metadata ?: null,
                ]);
                $transaction = $this->points->awardForActivity($log);
                $this->rebuildDailySummary($user->id, $localDate);
                $new = $this->achievements->evaluate($user);

                return new ActivityResult($log->setRelation('type', $type), $transaction?->points ?? 0, false, $new);
            });
        } catch (QueryException $e) {
            // A concurrent request with the same key won the unique index race.
            $winner = $this->findExisting($user, $idempotencyKey);
            if ($winner !== null) {
                return $this->asDuplicate($winner);
            }
            throw $e;
        }
    }

    public function update(ActivityLog $log, int $quantity, array $metadata, ?string $reason = null): ActivityResult
    {
        return DB::transaction(function () use ($log, $quantity, $metadata, $reason) {
            $log = ActivityLog::query()->whereKey($log->id)->lockForUpdate()->firstOrFail();
            $before = $log->only(['quantity', 'metadata', 'revision']);

            $this->points->reverseForActivity($log, $reason ?? 'activity_edited');
            $log->fill(['quantity' => $quantity, 'metadata' => $metadata ?: $log->metadata, 'revision' => $log->revision + 1])->save();
            $transaction = $this->points->awardForActivity($log);
            $this->rebuildDailySummary($log->user_id, $log->local_date->toDateString());

            AuditLog::record('activity.updated', $log, ['before' => $before, 'after' => $log->only(['quantity', 'revision'])], $log->user_id);

            return new ActivityResult($log->load('type'), $transaction?->points ?? 0, false);
        });
    }

    public function reverse(ActivityLog $log, string $reason = 'activity_removed'): int
    {
        return DB::transaction(function () use ($log, $reason) {
            $log = ActivityLog::query()->whereKey($log->id)->lockForUpdate()->firstOrFail();
            if ($log->status === 'reversed') {
                return 0;
            }
            $reversed = $this->points->reverseForActivity($log, $reason);
            $log->update(['status' => 'reversed', 'reversed_at' => now()]);
            $this->rebuildDailySummary($log->user_id, $log->local_date->toDateString());
            AuditLog::record('activity.reversed', $log, ['points_reversed' => $reversed], $log->user_id);

            return $reversed;
        });
    }

    public function rebuildDailySummary(int $userId, string $date): DailySummary
    {
        $rows = ActivityLog::query()
            ->join('activity_types', 'activity_types.id', '=', 'activity_logs.activity_type_id')
            ->where('activity_logs.user_id', $userId)
            ->whereDate('activity_logs.local_date', $date)
            ->where('activity_logs.status', 'active')
            ->selectRaw('activity_types.category as category, count(*) as records, sum(activity_logs.quantity) as quantity')
            ->groupBy('activity_types.category')
            ->get()
            ->keyBy('category');

        $points = (int) PointTransaction::query()
            ->join('activity_logs', 'activity_logs.id', '=', 'point_transactions.activity_log_id')
            ->where('point_transactions.user_id', $userId)
            ->whereDate('activity_logs.local_date', $date)
            ->sum('point_transactions.points');

        return DailySummary::query()->updateOrCreate(
            ['user_id' => $userId, 'date' => $date],
            [
                'activity_count' => (int) $rows->sum('records'),
                'salah_count' => (int) ($rows['salah']->records ?? 0),
                'quran_pages' => (int) ($rows['quran']->quantity ?? 0),
                'dhikr_count' => (int) ($rows['dhikr']->quantity ?? 0),
                'good_deeds' => (int) ($rows['good_deed']->records ?? 0),
                'lessons_completed' => (int) ($rows['learning']->records ?? 0),
                'fasted' => isset($rows['fasting']),
                'points_earned' => max(0, $points),
            ],
        );
    }

    private function findExisting(User $user, string $key): ?ActivityLog
    {
        return ActivityLog::query()->with('type')
            ->where('user_id', $user->id)
            ->where('idempotency_key', $key)
            ->first();
    }

    private function asDuplicate(ActivityLog $log): ActivityResult
    {
        $points = (int) PointTransaction::query()
            ->where('activity_log_id', $log->id)
            ->where('type', PointTransaction::TYPE_EARN)
            ->sum('points');

        return new ActivityResult($log, $points, true);
    }

    private function validate(User $user, ?ActivityType $type, string $typeKey, int $quantity, string $source, string $localDate, bool $trusted): void
    {
        $fail = fn (string $field, string $message) => throw ValidationException::withMessages([$field => $message]);

        if ($type === null) {
            $fail('type', "Unknown activity type: {$typeKey}.");
        }
        if (! $trusted && ! $type->client_creatable) {
            $fail('type', 'This activity can only be recorded through its dedicated flow.');
        }
        if ($source === self::SOURCE_IN_APP && ! $type->allows_in_app_source) {
            $fail('source', 'This activity must be confirmed by you and cannot be recorded automatically.');
        }
        if ($source === self::SOURCE_MANUAL && ! $type->allows_manual_source) {
            $fail('source', 'This activity can only be recorded from inside the app.');
        }
        if ($quantity < 1 || $quantity > $type->max_quantity) {
            $fail('quantity', "Quantity must be between 1 and {$type->max_quantity}.");
        }
        if ($type->one_per_day) {
            $exists = ActivityLog::query()
                ->where('user_id', $user->id)
                ->where('activity_type_id', $type->id)
                ->whereDate('local_date', $localDate)
                ->where('status', 'active')
                ->exists();
            if ($exists) {
                $fail('local_date', 'You already have a record for this date. You can edit it instead.');
            }
        }
    }
}
