<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ActivityResource;
use App\Http\Resources\GoalResource;
use App\Models\ActivityLog;
use App\Models\DailySummary;
use App\Models\Lesson;
use App\Models\SalahRecord;
use App\Models\UserAchievement;
use App\Models\UserGoal;
use App\Models\UserLessonProgress;
use App\Models\UserPointBalance;
use App\Models\UserPrayerSetting;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $timezone = UserPrayerSetting::query()->where('user_id', $user->id)->value('timezone') ?? 'UTC';
        $today = CarbonImmutable::now($timezone)->toDateString();

        $summary = DailySummary::query()->where('user_id', $user->id)->whereDate('date', $today)->first();

        return response()->json(['data' => [
            'date' => $today,
            'points' => [
                'balance' => (int) (UserPointBalance::query()->whereKey($user->id)->value('balance') ?? 0),
                'earned_today' => (int) ($summary->points_earned ?? 0),
            ],
            'today' => $this->presentSummary($summary),
            'salah_today' => SalahRecord::query()
                ->where('user_id', $user->id)->whereDate('prayer_date', $today)->where('status', 'active')
                ->pluck('prayer')->values(),
            'weekly' => $this->series($user->id, $timezone, 7),
            'goals' => GoalResource::collection(
                UserGoal::query()->where('user_id', $user->id)->where('status', 'active')->orderByDesc('id')->limit(4)->get()
            )->resolve(),
            'recent' => ActivityResource::collection(
                ActivityLog::query()->with('type')->withSum('transactions', 'points')
                    ->where('user_id', $user->id)->where('status', 'active')
                    ->orderByDesc('occurred_at')->limit(5)->get()
            )->resolve(),
            'achievements_earned' => UserAchievement::query()->where('user_id', $user->id)->count(),
            'next_lesson' => $this->nextLesson($user->id),
        ]]);
    }

    public function progress(Request $request): JsonResponse
    {
        $data = $request->validate(['days' => ['sometimes', 'integer', 'min:7', 'max:90']]);
        $user = $request->user();
        $timezone = UserPrayerSetting::query()->where('user_id', $user->id)->value('timezone') ?? 'UTC';
        $days = (int) ($data['days'] ?? 30);
        $series = $this->series($user->id, $timezone, $days);

        return response()->json(['data' => [
            'days' => $days,
            'series' => $series,
            'totals' => [
                'quran_pages' => collect($series)->sum('quran_pages'),
                'dhikr_count' => collect($series)->sum('dhikr_count'),
                'fasting_days' => collect($series)->where('fasted', true)->count(),
                'lessons_completed' => collect($series)->sum('lessons_completed'),
                'good_deeds' => collect($series)->sum('good_deeds'),
                'salah_records' => collect($series)->sum('salah_count'),
                'points_earned' => collect($series)->sum('points_earned'),
            ],
        ]]);
    }

    private function series(int $userId, string $timezone, int $days): array
    {
        $end = CarbonImmutable::now($timezone)->startOfDay();
        $start = $end->subDays($days - 1);
        $rows = DailySummary::query()->where('user_id', $userId)
            ->where('date', '>=', $start->toDateString())
            ->get()
            ->keyBy(fn (DailySummary $s) => $s->date->toDateString());

        $series = [];
        for ($d = $start; $d->lte($end); $d = $d->addDay()) {
            $series[] = ['date' => $d->toDateString()] + $this->presentSummary($rows->get($d->toDateString()));
        }

        return $series;
    }

    private function presentSummary(?DailySummary $s): array
    {
        return [
            'activity_count' => (int) ($s->activity_count ?? 0),
            'salah_count' => (int) ($s->salah_count ?? 0),
            'quran_pages' => (int) ($s->quran_pages ?? 0),
            'dhikr_count' => (int) ($s->dhikr_count ?? 0),
            'good_deeds' => (int) ($s->good_deeds ?? 0),
            'lessons_completed' => (int) ($s->lessons_completed ?? 0),
            'fasted' => (bool) ($s->fasted ?? false),
            'points_earned' => (int) ($s->points_earned ?? 0),
        ];
    }

    /** Suggestion is taken from published courses. No personal data leaves the server. */
    private function nextLesson(int $userId): ?array
    {
        $done = UserLessonProgress::query()->where('user_id', $userId)->whereNotNull('completed_at')->pluck('lesson_id');
        $lesson = Lesson::query()
            ->with('course')
            ->where('lessons.is_published', true)
            ->whereHas('course', fn ($q) => $q->where('is_published', true))
            ->whereNotIn('id', $done)
            ->join('courses', 'courses.id', '=', 'lessons.course_id')
            ->orderBy('courses.sort_order')->orderBy('lessons.sort_order')
            ->select('lessons.*')
            ->first();

        return $lesson ? ['id' => $lesson->id, 'title' => $lesson->title, 'course' => $lesson->course->title] : null;
    }
}
