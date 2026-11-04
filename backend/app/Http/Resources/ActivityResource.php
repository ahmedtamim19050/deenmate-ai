<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'type' => $this->type?->key,
            'type_name' => $this->type?->name,
            'category' => $this->type?->category,
            'quantity' => $this->quantity,
            'unit' => $this->type?->unit,
            'source' => $this->source,
            'occurred_at' => $this->occurred_at?->toIso8601String(),
            'local_date' => $this->local_date?->toDateString(),
            'metadata' => $this->metadata,
            'status' => $this->status,
            'points' => (int) ($this->transactions_sum_points ?? 0),
        ];
    }
}
