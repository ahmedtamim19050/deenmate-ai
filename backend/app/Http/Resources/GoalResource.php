<?php

namespace App\Http\Resources;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GoalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'description' => $this->description,
            'activity_type_key' => $this->activity_type_key,
            'target_value' => $this->target_value,
            'period' => $this->period,
            'status' => $this->status,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'current_value' => $this->currentValue(),
        ];
    }

    /** Null for custom goals, which have no linked activity to measure. */
    private function currentValue(): ?int
    {
        if ($this->activity_type_key === null) {
            return null;
        }
        $from = match ($this->period) {
            'weekly' => now()->subDays(6)->toDateString(),
            'total' => $this->start_date?->toDateString() ?? '2000-01-01',
            default => now()->toDateString(),
        };

        return (int) ActivityLog::query()
            ->where('user_id', $this->user_id)
            ->where('status', 'active')
            ->where('local_date', '>=', $from)
            ->whereHas('type', fn ($q) => $q->where('key', $this->activity_type_key))
            ->sum('quantity');
    }
}
