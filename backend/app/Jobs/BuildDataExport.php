<?php

namespace App\Jobs;

use App\Models\ActivityLog;
use App\Models\AiConversation;
use App\Models\Bookmark;
use App\Models\DataExportRequest;
use App\Models\PointTransaction;
use App\Models\SalahRecord;
use App\Models\UserAchievement;
use App\Models\UserGoal;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Storage;
use Throwable;

class BuildDataExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public function __construct(public int $exportId) {}

    public function handle(): void
    {
        $export = DataExportRequest::with('user')->find($this->exportId);
        if ($export === null || $export->user === null) {
            return;
        }
        $export->update(['status' => 'processing']);

        $user = $export->user;
        $id = $user->id;
        $payload = [
            'generated_at' => now()->toIso8601String(),
            'profile' => $user->only(['name', 'email', 'country', 'created_at']),
            'preferences' => $user->preference?->makeHidden(['id', 'user_id']),
            'prayer_settings' => $user->prayerSetting?->makeHidden(['id', 'user_id']),
            'prayer_templates' => $user->prayerTemplates()->get()->makeHidden(['id', 'user_id']),
            'notification_preferences' => $user->notificationPreferences()->get()->makeHidden(['id', 'user_id']),
            'salah_records' => SalahRecord::query()->with('items')->where('user_id', $id)->get()->makeHidden(['id', 'user_id', 'activity_log_id']),
            'activities' => ActivityLog::query()->with('type:id,key,name')->where('user_id', $id)->get()->makeHidden(['id', 'user_id', 'activity_type_id']),
            'points' => [
                'balance' => $user->pointBalance?->balance ?? 0,
                'ledger' => PointTransaction::query()->where('user_id', $id)->orderBy('id')->get()->makeHidden(['id', 'user_id']),
            ],
            'goals' => UserGoal::query()->where('user_id', $id)->get()->makeHidden(['id', 'user_id']),
            'achievements' => UserAchievement::query()->with('achievement:id,key,name')->where('user_id', $id)->get()->makeHidden(['id', 'user_id']),
            'bookmarks' => Bookmark::query()->where('user_id', $id)->get()->makeHidden(['id', 'user_id']),
            'ai_conversations' => AiConversation::query()->with('messages:id,ai_conversation_id,role,content,status,citations,created_at')->where('user_id', $id)->get()->makeHidden(['id', 'user_id']),
        ];

        $path = "exports/{$export->uuid}.json";
        Storage::disk('local')->put($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        $export->update([
            'status' => 'ready',
            'file_path' => $path,
            'completed_at' => now(),
            'expires_at' => now()->addHours((int) config('deenmate.exports.ttl_hours')),
        ]);
    }

    public function failed(Throwable $e): void
    {
        DataExportRequest::query()->whereKey($this->exportId)->update(['status' => 'failed', 'error' => class_basename($e)]);
    }
}
