<?php

namespace App\Services\Salah;

use App\Models\ActivityLog;
use App\Models\AuditLog;
use App\Models\PointTransaction;
use App\Models\SalahRecord;
use App\Models\User;
use App\Services\Activity\ActivityService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Salah records are always self-reported and user-confirmed. Nothing here can verify
 * worship, and nothing in the notification or AI layers can reach this service.
 */
class SalahService
{
    public const ACTIVITY_KEY = 'salah_rakat';

    public function __construct(private ActivityService $activities) {}

    /** @return array{record: SalahRecord, points: int, duplicate: bool, achievements: array} */
    public function confirm(User $user, string $prayer, string $date, array $items, string $idempotencyKey, ?Carbon $confirmedAt = null): array
    {
        $existing = SalahRecord::withTrashed()->with('items')
            ->where('user_id', $user->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();
        if ($existing !== null) {
            return ['record' => $existing, 'points' => $this->pointsFor($existing->activity_log_id), 'duplicate' => true, 'achievements' => []];
        }

        $alreadyRecorded = SalahRecord::query()
            ->where('user_id', $user->id)
            ->where('prayer', $prayer)
            ->whereDate('prayer_date', $date)
            ->where('status', 'active')
            ->exists();
        if ($alreadyRecorded) {
            throw ValidationException::withMessages([
                'prayer' => 'You already have a record for this prayer on this date. You can edit it from your history.',
            ]);
        }

        $total = (int) array_sum(array_column($items, 'rakats'));
        $confirmedAt ??= now();

        return DB::transaction(function () use ($user, $prayer, $date, $items, $idempotencyKey, $confirmedAt, $total) {
            $result = $this->activities->record(
                user: $user,
                typeKey: self::ACTIVITY_KEY,
                quantity: $total,
                source: ActivityService::SOURCE_MANUAL,
                idempotencyKey: $idempotencyKey,
                occurredAt: $confirmedAt,
                localDate: $date,
                metadata: ['prayer' => $prayer],
                trusted: true,
            );

            $record = SalahRecord::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $user->id,
                'activity_log_id' => $result->log->id,
                'prayer' => $prayer,
                'prayer_date' => $date,
                'confirmed_at' => $confirmedAt,
                'total_rakats' => $total,
                'idempotency_key' => $idempotencyKey,
            ]);
            $record->items()->createMany($this->normaliseItems($items));

            return ['record' => $record->load('items'), 'points' => $result->pointsAwarded, 'duplicate' => false, 'achievements' => $result->newAchievements];
        });
    }

    public function update(SalahRecord $record, array $items): array
    {
        if ($record->status !== 'active') {
            throw ValidationException::withMessages(['record' => 'A removed record cannot be edited.']);
        }
        $total = (int) array_sum(array_column($items, 'rakats'));

        return DB::transaction(function () use ($record, $items, $total) {
            $before = $record->items->map->only(['label', 'category', 'rakats'])->all();
            $result = $this->activities->update(ActivityLog::findOrFail($record->activity_log_id), $total, ['prayer' => $record->prayer], 'salah_edited');
            $record->items()->delete();
            $record->items()->createMany($this->normaliseItems($items));
            $record->update(['total_rakats' => $total]);
            AuditLog::record('salah.updated', $record, ['before' => $before], $record->user_id);

            return ['record' => $record->refresh()->load('items'), 'points' => $result->pointsAwarded];
        });
    }

    public function reverse(SalahRecord $record): int
    {
        return DB::transaction(function () use ($record) {
            if ($record->status !== 'active') {
                return 0;
            }
            $reversed = $this->activities->reverse(ActivityLog::findOrFail($record->activity_log_id), 'salah_removed');
            $record->update(['status' => 'reversed']);
            AuditLog::record('salah.reversed', $record, ['points_reversed' => $reversed], $record->user_id);

            return $reversed;
        });
    }

    public function pointsFor(int $activityLogId): int
    {
        return (int) PointTransaction::query()->where('activity_log_id', $activityLogId)->sum('points');
    }

    private function normaliseItems(array $items): array
    {
        return array_map(fn (array $i) => [
            'template_key' => $i['template_key'] ?? null,
            'label' => $i['label'],
            'category' => $i['category'],
            'rakats' => $i['rakats'],
        ], $items);
    }
}
