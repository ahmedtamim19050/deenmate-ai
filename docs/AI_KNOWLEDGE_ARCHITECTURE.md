# AI knowledge model and evidence-based answers (Phase 7)

How DeenQ AI understands a question, finds approved evidence and builds an answer whose
quotations and references can all be traced to an approved source.

## 1. Audit of what existed

| Area | Before Phase 7 | Gap |
| --- | --- | --- |
| Sources | `islamic_sources` → `islamic_source_chunks`; translation editions copied in by `TranslationAiSync` (one chunk per verse, read-back verified) | No document level; the surah/ayah lived only inside a `"Quran 2:45"` string; no per-chunk checksum; no version, attribution or licence URL on the source |
| Approval | `TranslationReviewWorkflow`; retrieval checks `QuranTranslationEdition::aiEligible()` (approved + synced + checksum match) on every query | Sound. Kept as is |
| Hadith | `hadith_records` with grade fields; chunks without collection/number/grade | Citation fields lost in the AI copy; re-import could change approved text silently |
| Tafsir | `quran_tafsirs` / `quran_tafsir_entries` (verse ranges), no data | No way for retrieval to attach Tafsir to a verse |
| Understanding | `IntentClassifier`: 6 coarse intents, keyword rules | Banglish not understood; Bangla/Banglish questions could not match the only approved source (English) |
| Retrieval | Keyword: FULLTEXT (English), LIKE with spelling variants (Bangla) | No exact-reference lookup by column, no topic matching, no cross-language search |
| Answer | Model JSON → validator (ids, verse refs, Hadith refs) → verses appended to text | No structured evidence, language, limitations or confidence; quoted text not checked; nothing at all without a provider |
| API | `content` + `citations` (title, excerpt …) | Exposed an internal `source_id` |
| Locale | Backend never applied `Accept-Language` | Bangla users received English messages |

## 2–4. Data model and migration

Migration `2026_11_09_000005_add_knowledge_structure` is **additive**. No text is changed and no row is deleted.

```
islamic_sources (a source: an edition, a Hadith collection, a Tafsir, a book)
  + version, attribution, license_url, content_checksum
  └─ islamic_source_documents  (NEW: surah of an edition, book of a collection, chapter of a book)
       id, islamic_source_id, document_key ("surah:2", "book:1"), title, position, metadata
       └─ islamic_source_chunks (one verse / one Hadith / one Tafsir entry)
            + islamic_source_document_id, surah_number, ayah_from, ayah_to,
              citation (JSON: collection, book, hadith_number, grade, grade_source, page …),
              content_checksum (SHA-256 of the exact text)
            index (surah_number, ayah_from, ayah_to)
ai_messages + analysis (JSON: question understanding, answer, limitations, confidence)
```

- **Quran:** one chunk per verse (`ayah_from = ayah_to`). A verse is never split or merged.
- **Tafsir:** one chunk per entry, with its verse range in `ayah_from`/`ayah_to`.
- **Hadith:** one chunk per narration, with the collection, book, number and the grade as stated by the dataset, plus who graded it.
- **Backfill:**
  - Existing chunks get their location, checksum and document from their stored reference.
  - Hadith chunks get their citation from `hadith_records`.
  - Sources get version, attribution and checksum from their linked edition.
- **Runtime:** about 95 s locally for 18.7k chunks.
- **Rollback:** `down()` removes only the new columns and table.
- **Writers:**
  - `App\Services\Knowledge\QuranChunkWriter` is used by `TranslationAiSync` and `quran:import`. It is idempotent, and the sync's read-back now also checks the location and checksum.
  - `quran:import-hadith` writes citations, and withdraws a source's approval when its text changes.

Approval still happens **only** through `TranslationReviewWorkflow`, and eligibility is still decided at query time:
- An approved source that is the AI copy of an edition is usable only while that edition is `aiEligible()`.
- A pending, rejected, revoked, unsynced or stale source is excluded on the very next question.
- Results are never cached, so there is nothing to invalidate.

## 5–6. Question understanding

`App\Services\AI\Understanding\`, deterministic and without any AI provider:

| Class | Does |
| --- | --- |
| `BanglishQuestionNormalizer` | Detects bn / banglish / en / mixed / ar. It recognises whole words, including their endings (`namajer`, `rojar`, `জান্নাতের`), and folds Bangla spelling variants (য/জ, ী/ি; য় is kept apart so `হয়` never becomes `হজ`). It keeps negations, numbers and names exactly as typed, picks the answer language, and honours explicit requests such as "answer in english" or "বাংলায়". |
| `IslamicEntityExtractor` | Finds references in forms like `2:255`, `২:২৫৫`, `2:1-5`, `surah 2 ayah 255`, `baqarah 153`, `সূরা বাকারা ২৫৫` and `ayatul kursi`, validated against `QuranStructure`. It also finds surah names (short ones like "nas" need the word "surah"), concepts, prophets and Hadith references. |
| `QuestionIntentClassifier` | Gives a primary intent plus secondary intents, a confidence and a ruling-caution flag. Unknown questions stay `general_islamic_question` with low confidence. Replaces the old `IntentClassifier`. |
| `QuestionUnderstandingService` | Runs all of the above. It resolves follow-ups ("er tafsir ki?", "porer ayat") from the previous answer's stored analysis, and builds English and Bangla search terms. |

The vocabulary is in `config/islamic_lexicon.php`, which is reviewable data, not code. For each concept it holds:
- Banglish and Bangla variants.
- English and Bangla search words.
- A few well-known *anchor* verse references.

Anchors are retrieval hints only. A hint returns nothing unless an approved edition contains that verse, and the file holds no Quran, Hadith or Tafsir text.

Examples, run on the local Clear Quran data:

| Question | Language → answer | Intent | Normalized | First evidence |
| --- | --- | --- | --- | --- |
| namaz koy wakt? | banglish → bn | salah | `[salah (prayer)] how many [prayer times]?` | 2:43, 4:103, 2:45 |
| roja vangar karon ki? | banglish → bn | fasting (+ not-a-fatwa) | `[fasting] breaking reasons what?` | 2:183–185 |
| allah amader keno sristi korechen? | banglish → bn | quran_topic_search | `Allah our why [purpose of creation] did?` | 51:56, 67:2, 23:115 |
| gunah maf pawar upay ki? | banglish → bn | repentance | `[sin] [repentance and forgiveness] gaining way what?` | 4:31, 39:53, 66:8 |
| zakat kake dite hoy? | banglish → bn | zakat | `[zakat (obligatory charity)] to whom to give is?` | 9:60, 2:43, 2:110 |
| surah baqarah er main message ki? | banglish → bn | surah_summary | — | 2:1–5 (+ "no approved tafsir") |
| namaz na porle ki hoy? | banglish → bn | salah, negated | `[salah (prayer)] NOT ...` | — |
| নামাজ কেন পড়তে হয়? | bn → bn | salah | `[salah (prayer)] কেন পড়তে হয়?` | 2:43, 2:45 |

## 7. Hybrid retrieval (`SourceRetriever::retrieve`)

1. **Exact references:** looked up through the indexed surah and ayah columns, in the order asked.
2. **Surah summary:** the surah's opening verses.
3. **Topic anchors:** interleaved across the question's topics.
4. **Word search:**
   - English uses FULLTEXT, Bangla uses spelling-tolerant LIKE.
   - Both use the mapped search words, so Banglish questions reach English translations.
5. **Tafsir:** approved Tafsir whose verse range covers a verse that was found.
6. **Other sources:** other approved sources, up to two slots.

- **Deduplication:** one passage per verse. The edition in the answer's language wins, then English.
- **Embeddings:** none. MySQL covers the current data. A vector index can be added later as one more step, behind a config switch, without changing the contract.

## 8. Evidence and citations (`EvidenceBuilder`)

Every evidence record is read from the database:
- **Common to all records:** type, reference, source_key, edition, version, author, translator, language, attribution, licence and licence URL, source URL, approval status, checksum, relevance and match type.
- **Quran:** surah number and names, ayah, verified Arabic, and the exact approved translation.
- **Tafsir:** surah and ayah range, plus the text.
- **Hadith:** collection, book, number, grade and grade source, plus the text.

Records never contain the reviewer identity, review notes or internal IDs.

## 9. Answer flow (`IslamicAssistant`)

1. **Clean and understand.** The reply locale is set from the answer language for this reply only.
2. **Short-circuit:** a greeting or an injection attempt gets its fixed reply.
3. **Retrieve** from approved sources.
4. **No provider, or the provider fails:**
   - Verses found by reference, topic or Surah are shown as matches, with the `ai_unavailable` note.
   - Word-only matches are not shown.
   - The status stays `not_configured` or `provider_error`.
5. **Check:** the daily limit, and that at least one Quran passage was found.
6. **Prompt the model** with the sources (each tagged with how it was found), the question understanding, and these rules:
   - Answer in the target language.
   - Refer to verses by ID.
   - Never put your own wording or translation in quotation marks.
   - Earlier turns are context, not evidence.
   - Keep any negation.
7. **Validate:**
   - The existing checks: known IDs, a Quran citation, verse and Hadith references.
   - New: any quotation of 5 or more words must appear word for word in a cited passage.
8. **Build the reply:** explanation, evidence, limitations and confidence.
   - `content` still holds the full text for older app versions.

**Limitations:**
- `translation_language`: no approved translation exists in the answer language (Bangla today).
- `no_approved_tafsir`
- `no_approved_hadith`
- `context_from_conversation`
- `not_a_fatwa`
- `ai_unavailable`

**Confidence:** measures how directly the evidence matches the question. It is not a judgement of religious truth.

## 10. API response (`POST /api/v1/ai/chat` → `data`)

Existing fields are unchanged. New fields: `question`, `answer`, `evidence`, `limitations`, `confidence`.

```json
{
  "id": 41, "role": "assistant", "status": "answered", "intent": "salah",
  "content": "…full text, for older app versions…",
  "question": {"original": "namaz koy wakt?", "normalized": "[salah (prayer)] how many [prayer times]?",
               "language": "banglish", "intent": "salah", "secondary_intents": [],
               "entities": {"references": [], "surahs": [], "concepts": ["salah", "prayer_times"], "prophets": [], "hadith": []},
               "confidence": 0.75},
  "answer": {"text": "…explanation in Bangla, citing [S2]…", "scholarly_differences": null, "language": "bn"},
  "evidence": [{
    "id": "S2", "type": "quran", "reference": "Quran 4:103", "surah_number": 4, "surah_name": "An-Nisaa", "ayah_number": 103,
    "arabic": "…verified Tanzil text…", "translation": "…exact Clear Quran text…", "translator": "Talal Itani",
    "edition": "Clear Quran (Allah Edition), Translation by Talal Itani, ClearQuran.com", "version": "2026-10-09",
    "attribution": "Translation by Talal Itani, ClearQuran.com (https://clearquran.com). …",
    "license_url": "https://creativecommons.org/licenses/by-nd/4.0/", "source_url": "https://clearquran.com/download/",
    "verification_status": "approved", "checksum": "9df7…", "relevance": 0.88, "match": "topic"
  }],
  "limitations": [{"code": "translation_language", "message": "এখনো কোনো অনুমোদিত বাংলা অনুবাদ নেই, …"}],
  "confidence": 0.8,
  "citations": ["…same records as evidence, for older app versions…"]
}
```

## 11. Security

- **Approval:** enforced at query time. Nothing auto-approves, and user messages never write to sources.
- **What the API never returns:** the API key, the system prompt, reviewer identity or notes, or internal IDs (tested).
- **Conversations:** owner-only (tested). Logs hold rejection reasons only, never question text.
- **Model output:** the verse text, Arabic, credits and references shown to users never come from the model.

## Operations

```bash
php artisan migrate            # additive; ~1–2 min on the full data set
php artisan test               # 188 tests
```

No re-sync is needed after migrating: the backfill structures the existing AI copies.
