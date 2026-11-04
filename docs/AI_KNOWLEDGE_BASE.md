# AI assistant and knowledge base

## Setting it up

The assistant is complete but off until you add a key. With `AI_PROVIDER=none` it replies with a clear "not set up" notice and the app shows a banner. It never produces a made-up answer.

```env
AI_PROVIDER=anthropic
AI_API_KEY=your-key
AI_MODEL=claude-opus-5-5
AI_EFFORT=medium
```

Then run `php artisan config:clear`.

Other providers: set `AI_PROVIDER=openai_compatible`, `AI_BASE_URL` and `AI_MODEL`. The endpoint must support `chat/completions` with `json_schema` output.

For Anthropic the integration uses the official PHP SDK, structured JSON output, and server-side refusal fallback (`fallbacks: "default"`), so a policy decline can be retried on a suitable model. A final decline is treated as "no verified answer". Remove `fallbacks` and `betas` in `AnthropicProvider.php` if you do not want this.

`claude-opus-5-5` is the default model. A lower-cost model or a lower `AI_EFFORT` is a reasonable choice at scale: change the env values, then compare answer quality before committing to it.

**The assistant also needs approved sources.** With none approved, it replies "I cannot give a verified answer" to every question and does not call the model. See [ISLAMIC_SOURCE_VERIFICATION.md](ISLAMIC_SOURCE_VERIFICATION.md).

## Pipeline

```
question
  -> clean input (strip control characters, limit length)
  -> classify intent (deterministic rules)
  -> greeting?             fixed reply, no model call
  -> looks like injection? fixed reply, no model call
  -> provider configured?  otherwise an honest notice
  -> daily token budget?   otherwise an honest notice
  -> retrieve from approved sources only
  -> nothing found?        "no verified answer", no model call
  -> model call with sources as data, JSON schema enforced
  -> validate the answer
  -> format, with citations rebuilt from the database
  -> store, and accept feedback
```

## How answers are validated

`AnswerValidator` rejects an answer, and replaces it with the "no verified answer" message, if:

- it is empty or not valid JSON
- the model says the sources are insufficient
- it cites no sources
- it cites an id that was not retrieved
- an inline `[S2]` marker is not listed as cited
- it mentions a Quran reference (such as `3:200`) that is not on a cited passage
- it mentions a Hadith reference (such as `Bukhari 1234`) that is not on a cited passage

The citation details shown to the person come from the database, so the model cannot alter them. A ruling question always gets a "this is not a fatwa" notice. The reason for a rejection is logged, and the question text is not.

## What this does not guarantee

The validator catches invented references. It cannot prove every sentence is a faithful reading of its source, and paraphrase without a reference number can still be wrong. Review answers reported as incorrect in **AI Feedback**, and revise the prompt or sources in response. The disclaimer in the app stays for this reason.

## Privacy

- Questions go to the configured AI provider, so name that provider in your privacy policy.
- Salah and worship history is not sent by default. Only when `ai_personalization_enabled` is on, and only for a question about the person's own progress, seven-day counts by category are included.
- People can delete a conversation, or clear all of them, permanently.
- There is no path from the assistant to Salah or activity records. A test confirms that asking it to record a prayer creates nothing.

## Abuse protection

| Control | Setting |
| --- | --- |
| Requests per user per minute | `AI_REQUESTS_PER_MINUTE` |
| Tokens per user per day | `AI_DAILY_TOKEN_LIMIT` |
| Question length | `AI_MAX_QUESTION_CHARS` |
| History sent to the model | `AI_HISTORY_TURNS` |
| Output size | `AI_MAX_OUTPUT_TOKENS` |

Prompt injection defences: pattern screening before the model, sources and question sent in delimited blocks with any `<source` tags in the content escaped, a system prompt that treats them as data, and the output validator. Pattern screening alone is easy to evade, so the validator and the read-only design are the controls to rely on.

## Knowledge base structure

`islamic_sources` holds title, type, author, publisher, reference identifier, language, URL, licence, verification status, reviewer and review date. `islamic_source_chunks` holds the passages, each with its `reference`. Retrieval only reads chunks whose source is `approved`.

## Adding sources

1. **Quran**: `php artisan quran:import` creates one source per translation edition, one chunk per verse.
2. **Hadith**: `php artisan hadith:import bukhari` creates one source per collection, one chunk per hadith.
3. **Articles and scholar-reviewed answers**: in **Islamic Sources**, create a source, then add passages.
4. A Scholar Reviewer approves it. It becomes available immediately.

Editing a passage in the admin panel returns the source to pending.

## Prompts

**AI Prompts** in the admin panel keeps versions. Create a new version, then activate it. The default prompt is in `app/Services/AI/PromptRepository.php`, and is used if none is active.

## Retrieval

Retrieval is lexical: MySQL FULLTEXT in boolean mode with quoted terms, plus direct lookup of references such as `2:255`. It matches the language of the indexed text, so a Bangla question finds passages from the Bangla edition once that edition is approved. Embedding search can be added inside `SourceRetriever` without changing the rest of the pipeline.
