<?php

namespace App\Services\AI;

use App\Models\ActivityLog;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\QuranVerse;
use App\Models\User;
use App\Services\AI\Contracts\LlmProvider;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * question -> intent -> retrieval -> model -> validation -> formatted answer.
 * It only reads: there is no code path from here to Salah or activity records.
 */
class IslamicAssistant
{
    public const STATUS_ANSWERED = 'answered';

    public const STATUS_INSUFFICIENT = 'insufficient_sources';

    public const STATUS_NOT_CONFIGURED = 'not_configured';

    public const STATUS_BLOCKED = 'blocked';

    public const STATUS_LIMITED = 'limit_reached';

    public const STATUS_ERROR = 'provider_error';

    public const STATUS_GREETING = 'greeting';

    public const RESPONSE_SCHEMA = [
        'type' => 'object',
        'additionalProperties' => false,
        'required' => ['answer', 'cited_source_ids', 'scholarly_differences', 'needs_scholar_referral', 'insufficient_sources'],
        'properties' => [
            'answer' => ['type' => 'string'],
            'cited_source_ids' => ['type' => 'array', 'items' => ['type' => 'string']],
            'scholarly_differences' => ['type' => 'string'],
            'needs_scholar_referral' => ['type' => 'boolean'],
            'insufficient_sources' => ['type' => 'boolean'],
        ],
    ];

    public function __construct(
        private LlmProvider $provider,
        private IntentClassifier $intents,
        private InputGuard $guard,
        private SourceRetriever $retriever,
        private AnswerValidator $validator,
        private PromptRepository $prompts,
    ) {}

    /** @return array{conversation: AiConversation, message: AiMessage} */
    public function ask(User $user, ?AiConversation $conversation, string $rawQuestion): array
    {
        $question = $this->guard->clean($rawQuestion);
        $conversation ??= AiConversation::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'title' => Str::limit($question, 60),
        ]);
        $history = $this->history($conversation);
        $conversation->messages()->create(['role' => 'user', 'content' => $question]);
        $conversation->touch();

        $intent = $this->intents->classify($question);

        return ['conversation' => $conversation, 'message' => $this->respond($user, $conversation, $question, $intent, $history)];
    }

    private function respond(User $user, AiConversation $conversation, string $question, string $intent, array $history): AiMessage
    {
        $reply = fn (string $status, string $text, array $extra = []) => $conversation->messages()->create($extra + [
            'role' => 'assistant',
            'content' => $text,
            'status' => $status,
            'intent' => $intent,
            'provider' => $this->provider->name(),
        ]);

        if ($intent === IntentClassifier::GREETING) {
            return $reply(self::STATUS_GREETING, __('ai.greeting'));
        }
        if ($this->guard->isSuspicious($question)) {
            return $reply(self::STATUS_BLOCKED, __('ai.blocked'));
        }
        if (! $this->provider->isConfigured()) {
            return $reply(self::STATUS_NOT_CONFIGURED, __('ai.not_configured'));
        }
        if ($this->tokensUsedToday($user) >= (int) config('deenmate.ai.daily_token_limit')) {
            return $reply(self::STATUS_LIMITED, __('ai.limit_reached'));
        }

        $chunks = $this->retriever->search($question);
        // Every answer rests on the Quran, so nothing goes to the model without a Quran passage.
        if (! $chunks->contains(fn (RetrievedChunk $c) => $c->isQuran())) {
            return $reply(self::STATUS_INSUFFICIENT, __('ai.insufficient'));
        }

        $started = microtime(true);
        try {
            $llm = $this->provider->complete(
                $this->prompts->systemPrompt(),
                [...$history, ['role' => 'user', 'content' => $this->userTurn($user, $question, $intent, $chunks)]],
                self::RESPONSE_SCHEMA,
            );
        } catch (LlmException $e) {
            Log::warning('AI provider failure', ['message' => $e->getMessage()]);

            return $reply(self::STATUS_ERROR, __('ai.provider_error'));
        }

        $usage = [
            'model' => $llm->model,
            'input_tokens' => $llm->inputTokens,
            'output_tokens' => $llm->outputTokens,
            'latency_ms' => (int) ((microtime(true) - $started) * 1000),
        ];
        if ($llm->refused) {
            return $reply(self::STATUS_INSUFFICIENT, __('ai.insufficient'), $usage);
        }

        $answer = json_decode($llm->text, true);
        $outcome = is_array($answer) ? $this->validator->validate($answer, $chunks) : ValidationOutcome::reject('invalid_json');
        if (! $outcome->accepted) {
            // Log the reason only: question text is private.
            Log::info('AI answer rejected by validator', ['reason' => $outcome->reason, 'conversation' => $conversation->id]);

            return $reply(self::STATUS_INSUFFICIENT, __('ai.insufficient'), $usage);
        }

        $cited = $chunks->filter(fn (RetrievedChunk $c) => in_array($c->id, $outcome->citedIds, true));

        return $reply(self::STATUS_ANSWERED, $this->format($answer, $intent, $cited), $usage + [
            'citations' => $cited->map(fn (RetrievedChunk $c) => $c->toCitation())->values()->all(),
        ]);
    }

    /** @param Collection<int, RetrievedChunk> $cited */
    private function format(array $answer, string $intent, Collection $cited): string
    {
        $text = trim($answer['answer']);
        $verses = $this->quranVerses($cited);
        if ($verses !== '') {
            $text .= "\n\n".__('ai.quran_heading')."\n".$verses;
        }
        if (filled($answer['scholarly_differences'] ?? null)) {
            $text .= "\n\n".__('ai.differences_heading')."\n".trim($answer['scholarly_differences']);
        }
        if (($answer['needs_scholar_referral'] ?? false) || $intent === IntentClassifier::RULING) {
            $text .= "\n\n".__('ai.not_a_fatwa');
        }

        return $text;
    }

    /**
     * The verses shown under an answer come from the database, never from model text.
     *
     * @param  Collection<int, RetrievedChunk>  $cited
     */
    private function quranVerses(Collection $cited): string
    {
        return $cited->filter(fn (RetrievedChunk $c) => $c->isQuran())
            ->take(3)
            ->map(function (RetrievedChunk $c) {
                $reference = (string) $c->chunk->reference;
                $arabic = preg_match('/^Quran (\d+):(\d+)$/', $reference, $m)
                    ? QuranVerse::query()->where('surah_id', $m[1])->where('verse_number', $m[2])->value('text_arabic')
                    : null;

                // The published translation, word for word, with its source and version as the credit.
                $credit = $c->chunk->source->title ? '— '.$c->chunk->source->title : null;

                return implode("\n", array_filter([$reference, $arabic, $c->chunk->content, $credit]));
            })
            ->implode("\n\n");
    }

    private function userTurn(User $user, string $question, string $intent, Collection $chunks): string
    {
        $sources = $chunks->map(function (RetrievedChunk $c) {
            $s = $c->chunk->source;
            $attrs = sprintf('id="%s" title="%s" type="%s" reference="%s" language="%s"', $c->id, e($s->title), e($s->source_type), e($c->chunk->reference ?? $s->reference_identifier ?? ''), e($s->language));

            return "<source {$attrs}>\n".str_replace(['<source', '</source'], ['&lt;source', '&lt;/source'], $c->chunk->content)."\n</source>";
        })->implode("\n");

        $personal = '';
        if ($intent === IntentClassifier::PERSONAL && $user->preference?->ai_personalization_enabled) {
            $personal = "\n<user_activity_summary>\n".$this->activitySummary($user)."\n</user_activity_summary>";
        }

        return "<sources>\n{$sources}\n</sources>{$personal}\n<requirement>Translations in <sources> are published works: when you quote one, use its exact words in quotation marks and name its title; never present your own wording as that translation; keep your explanation clearly separate from the quotation.</requirement>\n<requirement>Base the answer on the Quran: cite at least one passage with type=\"quran\". If no Quran passage addresses the question, set insufficient_sources to true.</requirement>\n<question_type>{$intent}</question_type>\n<question>\n{$question}\n</question>";
    }

    /** Sent only when the user opted in, and only as seven-day totals by category. */
    private function activitySummary(User $user): string
    {
        return ActivityLog::query()
            ->join('activity_types', 'activity_types.id', '=', 'activity_logs.activity_type_id')
            ->where('activity_logs.user_id', $user->id)
            ->where('activity_logs.status', 'active')
            ->where('activity_logs.local_date', '>=', now()->subDays(7)->toDateString())
            ->selectRaw('activity_types.category as category, count(*) as records')
            ->groupBy('activity_types.category')
            ->get()
            ->map(fn ($r) => "{$r->category}: {$r->records} self-reported records in the last 7 days")
            ->implode("\n");
    }

    private function history(AiConversation $conversation): array
    {
        return $conversation->messages()
            ->whereIn('status', [self::STATUS_ANSWERED])
            ->orWhere(fn ($q) => $q->where('ai_conversation_id', $conversation->id)->where('role', 'user'))
            ->orderByDesc('id')
            ->limit((int) config('deenmate.ai.history_turns') * 2)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (AiMessage $m) => ['role' => $m->role, 'content' => Str::limit($m->content, 1500)])
            ->all();
    }

    private function tokensUsedToday(User $user): int
    {
        return (int) AiMessage::query()
            ->whereHas('conversation', fn ($q) => $q->withTrashed()->where('user_id', $user->id))
            ->where('created_at', '>=', now()->startOfDay())
            ->selectRaw('COALESCE(SUM(input_tokens + output_tokens), 0) as total')
            ->value('total');
    }
}
