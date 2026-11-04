<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\MessageResource;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\AiResponseFeedback;
use App\Services\AI\IslamicAssistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AiController extends Controller
{
    public function chat(Request $request, IslamicAssistant $assistant): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'min:2', 'max:'.config('deenmate.ai.max_question_chars')],
            'conversation_id' => ['sometimes', 'nullable', 'uuid'],
        ]);

        $conversation = null;
        if (! empty($data['conversation_id'])) {
            $conversation = AiConversation::query()
                ->where('uuid', $data['conversation_id'])
                ->where('user_id', $request->user()->id)
                ->firstOrFail();
        }

        $result = $assistant->ask($request->user(), $conversation, $data['message']);

        return response()->json([
            'data' => (new MessageResource($result['message']))->resolve(),
            'conversation_id' => $result['conversation']->uuid,
        ]);
    }

    public function conversations(Request $request): JsonResponse
    {
        $page = AiConversation::query()->where('user_id', $request->user()->id)->orderByDesc('updated_at')->paginate(30);

        return response()->json([
            'data' => $page->getCollection()->map(fn (AiConversation $c) => [
                'id' => $c->uuid,
                'title' => $c->title,
                'updated_at' => $c->updated_at?->toIso8601String(),
            ])->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
        ]);
    }

    public function conversation(Request $request, string $conversation): JsonResponse
    {
        $found = $this->owned($request, $conversation);

        return response()->json([
            'data' => [
                'id' => $found->uuid,
                'title' => $found->title,
                'messages' => MessageResource::collection($found->messages()->orderBy('id')->get())->resolve(),
            ],
        ]);
    }

    /** Clearing history really removes the text, rather than soft deleting it. */
    public function destroyConversation(Request $request, string $conversation): JsonResponse
    {
        $this->owned($request, $conversation)->forceDelete();

        return response()->json(['message' => 'Conversation removed.']);
    }

    public function clearConversations(Request $request): JsonResponse
    {
        DB::transaction(fn () => AiConversation::query()->withTrashed()->where('user_id', $request->user()->id)->forceDelete());

        return response()->json(['message' => 'Your AI history has been cleared.']);
    }

    public function feedback(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message_id' => ['required', 'integer'],
            'rating' => ['required', 'in:helpful,not_helpful,incorrect'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $message = AiMessage::query()
            ->whereKey($data['message_id'])
            ->where('role', 'assistant')
            ->whereHas('conversation', fn ($q) => $q->where('user_id', $request->user()->id))
            ->firstOrFail();

        AiResponseFeedback::query()->updateOrCreate(
            ['ai_message_id' => $message->id, 'user_id' => $request->user()->id],
            ['rating' => $data['rating'], 'comment' => $data['comment'] ?? null, 'status' => 'open'],
        );

        return response()->json(['message' => 'Thank you for your feedback.'], 201);
    }

    private function owned(Request $request, string $uuid): AiConversation
    {
        return AiConversation::query()->where('uuid', $uuid)->where('user_id', $request->user()->id)->firstOrFail();
    }
}
