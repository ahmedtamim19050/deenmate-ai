<?php

namespace App\Http\Resources;

use App\Models\PointTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalahRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'prayer' => $this->prayer,
            'prayer_date' => $this->prayer_date?->toDateString(),
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'total_rakats' => $this->total_rakats,
            'status' => $this->status,
            'items' => $this->items->map->only(['template_key', 'label', 'category', 'rakats'])->values(),
            'points' => (int) PointTransaction::query()->where('activity_log_id', $this->activity_log_id)->sum('points'),
        ];
    }
}
