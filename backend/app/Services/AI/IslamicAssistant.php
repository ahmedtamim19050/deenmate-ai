<?php

namespace App\Services\AI;

use App\Models\ActivityLog;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use App\Services\AI\Contracts\LlmProvider;
use App\Services\AI\Understanding\QuestionAnalysis;
use App\Services\AI\Understanding\QuestionIntentClassifier as Intent;
use App\Services\AI\Understanding\QuestionUnderstandingService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * question -> understanding -> retrieval -> model -> validation -> evidence-based answer.
 *
 * The model writes only the explanation. Quran text, translations, credits and references shown
 * to the user are read from approved sources, never from model text. It only reads: there is no
 * code path from here to Salah or activity records, and nothing a user writes changes a source.
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
        private QuestionUnderstandingService $understanding,
        private InputGuard $guard,
        private SourceRetriever $retriever,
        private AnswerValidator $validator,
        private EvidenceBuilder $evidence,
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
        $previous = $this->previousAnalysis($conversation);
        $conversation->messages()->create(['role' => 'user', 'content' => $question]);
        $conversation->touch();

        $analysis = $this->understanding->analyze($question, $previous);

        // Replies are written in the answer language, without changing the app-wide locale.
        $locale = App::getLocale();
        App::setLocale(in_array($analysis->answerLanguage(), ['en', 'bn', 'ar'], true) ? $analysis->answerLanguage() : 'en');
        try {
            $message = $this->respond($user, $conversation, $question, $analysis, $history);
        } finally {
            App::setLocale($locale);
        }

        return ['conversation' => $conversation, 'message' => $message];
    }

    private function respond(User $user, AiConversation $conversation, string $question, QuestionAnalysis $analysis, array $history): AiMessage
    {
        $intent = $analysis->primaryIntent;
        $reply = function (string $status, string $text, array $extra = [], array $meta = []) use ($conversation, $intent, $analysis) {
            return $conversation->messages()->create($extra + [
                'role' => 'assistant',
                'content' => $text,
                'status' => $status,
                'intent' => Str::limit($intent, 32, ''),
                'provider' => $this->provider->name(),
                'analysis' => $meta + [
                    'question' => $analysis->toArray(),
                    'answer' => ['text' => $text, 'language' => $analysis->answerLanguage()],
                    'limitations' => [],
                    'confidence' => 0.0,
                ],
            ]);
        };

        if ($intent === Intent::GREETING) {
            return $reply(self::STATUS_GREETING, __('ai.greeting'));
        }
        if ($this->guard->isSuspicious($question)) {
            return $reply(self::STATUS_BLOCKED, __('ai.blocked'));
        }

        $chunks = $this->retriever->retrieve($analysis);
        $hasQuran = $chunks->contains(fn (RetrievedChunk $c) => $c->isQuran());

        if (! $this->provider->isConfigured()) {
            return $this->withoutModel($reply, self::STATUS_NOT_CONFIGURED, __('ai.not_configured'), $analysis, $chunks);
        }
        if ($this->tokensUsedToday($user) >= (int) config('deenmate.ai.daily_token_limit')) {
            return $reply(self::STATUS_LIMITED, __('ai.limit_reached'));
        }
        // Every answer rests on the Quran, so nothing goes to the model without a Quran passage.
        if (! $hasQuran) {
            return $reply(self::STATUS_INSUFFICIENT, __('ai.insufficient'), [], ['limitations' => $this->limitations($analysis, collect(), false)]);
        }

        $started = microtime(true);
        try {
            $llm = $this->provider->complete(
                $this->prompts->systemPrompt(),
                [...$history, ['role' => 'user', 'content' => $this->userTurn($user, $question, $analysis, $chunks)]],
                self::RESPONSE_SCHEMA,
            );
        } catch (LlmException $e) {
            Log::warning('AI provider failure', ['message' => $e->getMessage()]);

            return $this->withoutModel($reply, self::STATUS_ERROR, __('ai.provider_error'), $analysis, $chunks);
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

        $cited = $chunks->filter(fn (RetrievedChunk $c) => in_array($c->id, $outcome->citedIds, true))->values();
        $evidence = $this->evidence->build($cited);
        $explanation = trim($answer['answer']);
        $differences = trim((string) ($answer['scholarly_differences'] ?? ''));
        $limitations = $this->limitations($analysis, $cited, (bool) ($answer['needs_scholar_referral'] ?? false));

        return $reply(self::STATUS_ANSWERED, $this->compose($explanation, $differences, $evidence, $limitations), $usage + ['citations' => $evidence], [
            'answer' => ['text' => $explanation, 'scholarly_differences' => $differences, 'language' => $analysis->answerLanguage()],
            'limitations' => $limitations,
            'confidence' => $this->confidence($analysis, $cited),
        ]);
    }

    /**
     * Without a model, the verses found for clearly recognised references or topics are still
     * shown, labelled as matches rather than as an answer. Word matches alone are not shown.
     */
    private function withoutModel(callable $reply, string $status, string $notice, QuestionAnalysis $analysis, Collection $chunks): AiMessage
    {
        $strong = $chunks->filter(fn (RetrievedChunk $c) => $c->isQuran() && $c->isStrongMatch())->take(3)->values();
        if ($strong->isEmpty()) {
            return $reply($status, $notice);
        }
        $evidence = $this->evidence->build($strong);
        $limitations = [['code' => 'ai_unavailable', 'message' => __('ai.limitations.ai_unavailable')], ...$this->limitations($analysis, $strong, false)];

        return $reply($status, $this->compose($notice, '', $evidence, $limitations), ['citations' => $evidence], [
            'answer' => ['text' => $notice, 'language' => $analysis->answerLanguage()],
            'limitations' => $limitations,
            'confidence' => round(min(0.5, 0.3 + 0.2 * $analysis->confidence), 2),
        ]);
    }

    /** @return array<int, array{code: string, message: string}> */
    private function limitations(QuestionAnalysis $analysis, Collection $cited, bool $referral): array
    {
        $codes = [];
        $quran = $cited->filter(fn (RetrievedChunk $c) => $c->isQuran());
        if ($quran->isNotEmpty() && ! $quran->contains(fn (RetrievedChunk $c) => $c->chunk->source->language === $analysis->answerLanguage())) {
            $codes[] = 'translation_language';
        }
        if (($analysis->is(Intent::TAFSIR) || $analysis->is(Intent::SURAH_SUMMARY)) && ! $cited->contains(fn (RetrievedChunk $c) => $c->type() === 'tafsir')) {
            $codes[] = 'no_approved_tafsir';
        }
        if ($analysis->is(Intent::HADITH) && ! $cited->contains(fn (RetrievedChunk $c) => $c->type() === 'hadith')) {
            $codes[] = 'no_approved_hadith';
        }
        if ($analysis->contextUsed) {
            $codes[] = 'context_from_conversation';
        }
        if ($referral || $analysis->rulingCaution) {
            $codes[] = 'not_a_fatwa';
        }

        return array_map(fn (string $code) => ['code' => $code, 'message' => __("ai.limitations.{$code}")], $codes);
    }

    /**
     * How directly the cited evidence answers the question: not a measure of religious truth.
     * Exact references and recognised topics score higher than word matches.
     */
    private function confidence(QuestionAnalysis $analysis, Collection $cited): float
    {
        $score = 0.45 + 0.2 * $analysis->confidence;
        if ($cited->contains(fn (RetrievedChunk $c) => $c->isQuran() && $c->isStrongMatch())) {
            $score += 0.2;
        }
        if ($cited->filter(fn (RetrievedChunk $c) => $c->isQuran())->count() >= 2) {
            $score += 0.05;
        }
        if ($analysis->contextUsed) {
            $score -= 0.05;
        }

        return round(max(0.0, min(0.95, $score)), 2);
    }

    /**
     * The text stored in "content": the explanation, then each cited verse as Arabic, the exact
     * approved translation and its credit, so app versions without evidence cards show the same.
     */
    private function compose(string $explanation, string $differences, array $evidence, array $limitations): string
    {
        $text = $explanation;
        $verses = collect($evidence)->where('type', 'quran')->take(3)->map(fn (array $e) => implode("\n", array_filter([
            $e['reference'],
            $e['arabic'],
            $e['translation'],
            '— '.($e['attribution'] ?: $e['edition']).($e['version'] ? ' Version '.$e['version'].'.' : ''),
        ])))->implode("\n\n");
        if ($verses !== '') {
            $text .= "\n\n".__('ai.quran_heading')."\n".$verses;
        }
        if ($differences !== '') {
            $text .= "\n\n".__('ai.differences_heading')."\n".$differences;
        }
        foreach ($limitations as $limitation) {
            if ($limitation['code'] !== 'ai_unavailable') {
                $text .= "\n\n".$limitation['message'];
            }
        }

        return $text;
    }

    private function userTurn(User $user, string $question, QuestionAnalysis $analysis, Collection $chunks): string
    {
        $sources = $chunks->map(function (RetrievedChunk $c) {
            $s = $c->chunk->source;
            $attrs = sprintf(
                'id="%s" type="%s" title="%s" translator_or_author="%s" version="%s" reference="%s" language="%s" found_by="%s"',
                $c->id, e($s->source_type), e($s->title), e((string) $s->author), e((string) $s->version),
                e($c->chunk->reference ?? $s->reference_identifier ?? ''), e($s->language), $c->match,
            );

            return "<source {$attrs}>\n".str_replace(['<source', '</source'], ['&lt;source', '&lt;/source'], $c->chunk->content)."\n</source>";
        })->implode("\n");

        $personal = '';
        if ($analysis->primaryIntent === Intent::PERSONAL && $user->preference?->ai_personalization_enabled) {
            $personal = "\n<user_activity_summary>\n".$this->activitySummary($user)."\n</user_activity_summary>";
        }

        $languageName = ['bn' => 'Bangla (Bengali script)', 'en' => 'English', 'ar' => 'Arabic'][$analysis->answerLanguage()] ?? 'English';
        $understanding = e(json_encode([
            'language' => $analysis->question->language,
            'normalized' => $analysis->question->normalized,
            'intent' => $analysis->primaryIntent,
            'secondary_intents' => $analysis->secondaryIntents,
            'negated' => $analysis->question->negated(),
            'follow_up' => $analysis->contextUsed,
        ], JSON_UNESCAPED_UNICODE));

        $requirements = [
            "Write answer and scholarly_differences in {$languageName}.",
            'Translations in <sources> are published works. The app shows each cited verse\'s Arabic text and exact translation with its credit under your answer, so refer to verses by their id, such as [S1], instead of copying them. If you do quote, use the exact words of the passage in quotation marks. Never put your own wording, or your own translation into another language, in quotation marks, and never present it as a translation: describe the meaning as your explanation.',
            'Base the answer on the Quran: cite at least one passage with type="quran". If no Quran passage addresses the question, set insufficient_sources to true.',
            'Passages found_by="keyword" only share words with the question and may not be relevant; cite a passage only if it actually supports what you say.',
            'Earlier turns in this conversation are context, not evidence. Answer from the passages above, even if an earlier reply said something different.',
            'Keep the meaning of the question exactly, including any negation. The normalized form is a helper; the original question is authoritative.',
        ];

        return "<sources>\n{$sources}\n</sources>{$personal}\n<question_understanding>{$understanding}</question_understanding>\n"
            .implode("\n", array_map(fn ($r) => "<requirement>{$r}</requirement>", $requirements))
            ."\n<question_type>{$analysis->primaryIntent}</question_type>\n<question>\n{$question}\n</question>";
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

    /** The question understanding of the last reply, used only to resolve a follow-up. */
    private function previousAnalysis(AiConversation $conversation): ?array
    {
        $analysis = $conversation->messages()->where('role', 'assistant')->whereNotNull('analysis')->orderByDesc('id')->first()?->analysis;

        return $analysis['question'] ?? null;
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
