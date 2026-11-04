<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'role' => $this->role,
            'content' => $this->content,
            'status' => $this->status,
            'intent' => $this->intent,
            'citations' => $this->citations ?? [],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
