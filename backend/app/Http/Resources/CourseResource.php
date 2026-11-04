<?php

namespace App\Http\Resources;

use App\Models\UserLessonProgress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = auth('sanctum')->user();
        $lessonIds = $this->whenLoaded('lessons', fn () => $this->lessons->pluck('id'), collect());
        $done = $user ? UserLessonProgress::query()->where('user_id', $user->id)->whereNotNull('completed_at')->whereIn('lesson_id', $lessonIds)->pluck('lesson_id') : collect();

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category,
            'level' => $this->level,
            'language' => $this->language,
            'review_status' => $this->review_status,
            'lesson_count' => $lessonIds->count(),
            'completed_lessons' => $done->count(),
            'lessons' => $this->when($request->routeIs('*.course') || $request->route('course') !== null, fn () => $this->lessons->map(fn ($l) => [
                'id' => $l->id,
                'title' => $l->title,
                'duration_minutes' => $l->duration_minutes,
                'review_status' => $l->review_status,
                'completed' => $done->contains($l->id),
            ])->values()),
        ];
    }
}
