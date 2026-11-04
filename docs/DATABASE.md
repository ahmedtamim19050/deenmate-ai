# Database

MySQL 8 with `utf8mb4_unicode_ci`. Migrations are in `backend/database/migrations`. The tests run on in-memory SQLite, so FULLTEXT indexes are only created on MySQL, and retrieval has a fallback.

## Tables

**Core**: `users`, `user_preferences`, `activity_types`, `activity_logs`, `activity_sessions`, `user_goals`, `daily_summaries`

**Points**: `point_rules`, `point_transactions`, `user_point_balances`, `achievements`, `achievement_rules`, `user_achievements`

**Prayer**: `user_prayer_settings`, `prayer_schedules`, `prayer_notification_preferences`, `user_prayer_templates`, `salah_records`, `salah_record_items`

**AI**: `ai_conversations`, `ai_messages`, `ai_prompts`, `islamic_sources`, `islamic_source_chunks`, `ai_response_feedback`, `scholar_reviews`

**Learning**: `courses`, `lessons`, `quizzes`, `quiz_questions`, `quiz_attempts`, `user_lesson_progress`, `quran_surahs`, `quran_verses`, `quran_translation_editions`, `quran_translations`, `hadith_collections`, `hadith_records`, `bookmarks`

**Other**: `notification_logs`, `user_devices`, `app_settings`, `audit_logs`, `data_export_requests`

## Constraints that matter

| Constraint | Purpose |
| --- | --- |
| `activity_logs (user_id, idempotency_key)` unique | One record per client attempt |
| `salah_records (user_id, idempotency_key)` unique | Same, for Salah |
| `point_transactions (user_id, idempotency_key)` unique | One ledger row per award or reversal |
| `point_transactions.reverses_transaction_id` unique | An earn can only be reversed once |
| `user_point_balances.balance` unsigned | The balance cannot go below zero |
| `user_achievements (user_id, achievement_id)` unique | An achievement is awarded once |
| `user_prayer_templates (user_id, prayer, template_key)` unique | Template keys are unique per prayer |
| `point_rules (activity_type_id, version)` unique | Rule versioning |
| `quran_verses (surah_id, verse_number)` unique | No duplicate verses |
| `islamic_sources (source_type, reference_identifier, language)` unique | Re-import updates in place |

Personal tables use `user_id` foreign keys with `cascadeOnDelete`, so deleting an account removes all personal rows. `activity_types` is `restrictOnDelete`, as ledger history depends on it. `audit_logs.actor_id` is `nullOnDelete`, so the trail survives without the identity.

## Soft deletes

`users`, `user_goals`, `salah_records`, `ai_conversations`, `courses`, `lessons`. Account deletion and "clear AI history" use `forceDelete`, because those are privacy actions.

## Point flow

```
activity_logs row created
  -> rule = latest active point_rules row for the type
  -> points = floor(quantity / per_quantity) * rule.points, limited by daily_cap
  -> lock user_point_balances row
  -> append point_transactions (rule version recorded)
  -> rebuild daily_summaries for that date
  -> evaluate achievements
```

Edits and removals append `reverse` rows and never change earlier rows.

## Indexes

User and date queries use `activity_logs (user_id, local_date)`, `(user_id, activity_type_id, local_date)`, `salah_records (user_id, prayer_date, prayer)`, `point_transactions (user_id, created_at)` and `daily_summaries (user_id, date)`.
