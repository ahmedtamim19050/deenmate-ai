<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\CourseResource;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\UserLessonProgress;
use App\Services\Activity\ActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class LearningController extends Controller
{
    public function courses(Request $request): AnonymousResourceCollection
    {
        return CourseResource::collection(
            Course::query()
                ->where('is_published', true)
                ->with(['lessons' => fn ($q) => $q->where('is_published', true)->orderBy('sort_order')])
                ->when($request->query('category'), fn ($q, $v) => $q->where('category', $v))
                ->orderBy('sort_order')
                ->get()
        );
    }

    public function course(Course $course): CourseResource
    {
        abort_unless($course->is_published, 404);

        return new CourseResource($course->load(['lessons' => fn ($q) => $q->where('is_published', true)->orderBy('sort_order')]));
    }

    public function lesson(Lesson $lesson): JsonResponse
    {
        abort_unless($lesson->is_published && $lesson->course?->is_published, 404);
        $user = auth('sanctum')->user();
        $progress = $user ? UserLessonProgress::query()->where('user_id', $user->id)->where('lesson_id', $lesson->id)->first() : null;
        $quiz = $lesson->quiz()->with(['questions' => fn ($q) => $q->orderBy('sort_order')])->first();

        return response()->json(['data' => [
            'id' => $lesson->id,
            'course_id' => $lesson->course_id,
            'course_title' => $lesson->course->title,
            'title' => $lesson->title,
            'body' => $lesson->body,
            'references' => $lesson->references ?? [],
            'audio_url' => $lesson->audio_url,
            'duration_minutes' => $lesson->duration_minutes,
            'review_status' => $lesson->review_status,
            'completed' => $progress?->completed_at !== null,
            // correct_index stays on the server until an attempt is submitted.
            'quiz' => $quiz ? [
                'id' => $quiz->id,
                'title' => $quiz->title,
                'pass_percent' => $quiz->pass_percent,
                'questions' => $quiz->questions->map(fn ($q) => ['id' => $q->id, 'question' => $q->question, 'options' => $q->options])->values(),
            ] : null,
        ]]);
    }

    public function complete(Request $request, Lesson $lesson, ActivityService $activities): JsonResponse
    {
        abort_unless($lesson->is_published, 404);
        $data = $request->validate(['local_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:+1 day']]);
        $user = $request->user();

        // The key is deterministic, so one lesson can only ever award once.
        $key = Uuid::uuid5(Uuid::NAMESPACE_OID, "lesson:{$user->id}:{$lesson->id}")->toString();

        $result = DB::transaction(function () use ($user, $lesson, $activities, $key, $data) {
            UserLessonProgress::query()->updateOrCreate(
                ['user_id' => $user->id, 'lesson_id' => $lesson->id],
                ['progress_percent' => 100, 'completed_at' => now()],
            );

            return $activities->record(
                user: $user,
                typeKey: 'lesson_complete',
                quantity: 1,
                source: ActivityService::SOURCE_IN_APP,
                idempotencyKey: $key,
                occurredAt: now(),
                localDate: $data['local_date'],
                metadata: ['lesson_id' => $lesson->id],
                trusted: true,
            );
        });

        return response()->json([
            'points_awarded' => $result->pointsAwarded,
            'duplicate' => $result->duplicate,
            'new_achievements' => collect($result->newAchievements)->map->only(['key', 'name', 'description', 'icon'])->values(),
        ]);
    }

    public function attempt(Request $request, Quiz $quiz, ActivityService $activities): JsonResponse
    {
        $data = $request->validate([
            'idempotency_key' => ['required', 'uuid'],
            'local_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:+1 day'],
            'answers' => ['required', 'array'],
            'answers.*' => ['integer', 'min:0'],
        ]);
        $user = $request->user();
        $questions = $quiz->questions()->orderBy('sort_order')->get();

        $existing = QuizAttempt::query()->where('user_id', $user->id)->where('idempotency_key', $data['idempotency_key'])->first();
        if ($existing !== null) {
            return response()->json($this->attemptPayload($existing, $questions));
        }

        $score = $questions->filter(fn ($q) => (int) ($data['answers'][$q->id] ?? -1) === $q->correct_index)->count();
        $total = $questions->count();
        $passed = $total > 0 && ($score / $total * 100) >= $quiz->pass_percent;

        $attempt = DB::transaction(function () use ($quiz, $user, $data, $score, $total, $passed, $activities) {
            $attempt = QuizAttempt::create([
                'quiz_id' => $quiz->id,
                'user_id' => $user->id,
                'idempotency_key' => $data['idempotency_key'],
                'answers' => $data['answers'],
                'score' => $score,
                'total' => $total,
                'passed' => $passed,
            ]);

            if ($passed) {
                $key = Uuid::uuid5(Uuid::NAMESPACE_OID, "quiz:{$user->id}:{$quiz->id}")->toString();
                $activities->record(
                    user: $user,
                    typeKey: 'quiz_complete',
                    quantity: 1,
                    source: ActivityService::SOURCE_IN_APP,
                    idempotencyKey: $key,
                    occurredAt: now(),
                    localDate: $data['local_date'],
                    metadata: ['quiz_id' => $quiz->id],
                    trusted: true,
                );
            }

            return $attempt;
        });

        return response()->json($this->attemptPayload($attempt, $questions), 201);
    }

    private function attemptPayload(QuizAttempt $a, $questions): array
    {
        return ['data' => [
            'score' => $a->score,
            'total' => $a->total,
            'passed' => $a->passed,
            'review' => $questions->map(fn ($q) => [
                'question_id' => $q->id,
                'correct_index' => $q->correct_index,
                'explanation' => $q->explanation,
            ])->values(),
        ]];
    }
}
