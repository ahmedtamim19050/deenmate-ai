<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One chat message. "content" stays the full text for older app versions; assistant replies also
 * carry the structured parts: question understanding, the explanation alone, the evidence
 * (exact source text and credits from the database), limitations and an evidence confidence.
 */
class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $analysis = $this->analysis ?? [];
        $evidence = $this->citations ?? [];
        $question = $analysis['question'] ?? null;

        return [
            'id' => $this->id,
            'role' => $this->role,
            'content' => $this->content,
            'status' => $this->status,
            'intent' => $this->intent,
            'citations' => $evidence,
            'question' => $question === null ? null : [
                'original' => $question['original'] ?? null,
                'normalized' => $question['normalized'] ?? null,
                'language' => $question['language'] ?? null,
                'intent' => $question['intent'] ?? null,
                'secondary_intents' => $question['secondary_intents'] ?? [],
                'entities' => $question['entities'] ?? null,
                'confidence' => $question['confidence'] ?? null,
            ],
            'answer' => isset($analysis['answer']) ? [
                'text' => $analysis['answer']['text'] ?? null,
                'scholarly_differences' => ($analysis['answer']['scholarly_differences'] ?? '') ?: null,
                'language' => $analysis['answer']['language'] ?? null,
            ] : null,
            'evidence' => $evidence,
            'limitations' => $analysis['limitations'] ?? [],
            'confidence' => $analysis['confidence'] ?? null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
