# Architecture

## Overview

```
Flutter app ── HTTPS, bearer token ──> Laravel /api/v1 ──> MySQL
   │                                     │
   ├─ Drift: outbox and caches           ├─ Queue: data exports
   ├─ Local notifications + adhan        ├─ Scheduler: pruning
   └─ No ads or ad SDK                   └─ LLM provider (server only)

Admin browser ──> Filament /admin ──> same database, role gated
```

## Key decisions

**Server-authoritative points.** The client never sends a point value. `ActivityService` validates a record, then `PointsService` calculates from the active rule inside a database transaction, locks the balance row, and appends a ledger row. Any `points` field a client sends is ignored, and there is a test for it.

**Immutable ledger.** `PointTransaction` throws on update and delete. An edit appends a reversal and a new earn. Balances are clamped at zero by reducing the amount applied, and the ledger records what was actually applied. The only delete path is the database cascade when someone deletes their account.

**Idempotency.** Each record carries a client-generated UUID, unique per user. A repeat returns the first result with `duplicate: true` and awards nothing. A race between two simultaneous requests is settled by the unique index, and the loser returns the winner's result.

**Offline first.** Salah and activity records are written to the Drift outbox before any network call, then sent. A record stays "Pending Sync" with no points until the server replies. `SyncService` stops at the first retryable failure and keeps order. A validation rejection is recorded on that item and does not block the others. Guests keep records in the outbox until they create an account.

**Worship records are only self-reported.** `salah_rakat` has `client_creatable=false`, so it can only be created through `POST /salah/confirm`, which requires `confirmed: true`. Manual activity types require `confirmed: true`. Activity types that do not allow the `in_app` source reject it. Notifications only ever navigate. The AI code path has no write access to these tables.

**Two prayer calculators.** The device calculates with `adhan` so times and reminders work offline and for guests. The server calculates with `islamic-network/prayer-times` for `GET /prayer/today`. Both use the same method parameters, and `mobile/test/prayer_calculator_test.dart` checks device results against reference values from the server library, within 3 minutes.

**Admin privacy by omission.** There is no Filament resource at all for Salah records, activity logs or the per-user ledger, and a test enforces this. Dashboard widgets show only operational totals. AI feedback hides who sent it. The audit log screen leaves out personal `salah.*` and `activity.*` entries.

**AI cannot be steered into records or citations.** See [AI_KNOWLEDGE_BASE.md](AI_KNOWLEDGE_BASE.md).

## Backend layout

```
app/Http/Controllers/Api/V1   thin controllers, validation
app/Http/Resources            response shapes
app/Services/Activity         recording, editing, daily summaries
app/Services/Points           rules and ledger
app/Services/Salah            confirm, edit, reverse
app/Services/Prayer           calculation, default templates
app/Services/AI               pipeline, providers, retrieval, validation
app/Services/Quran            Basmala separation for imports
app/Services/Privacy          account deletion
app/Filament                  admin panel
app/Policies                  owner-only access to personal records
app/Console/Commands          importers, review, admin creation
```

## Mobile layout

```
lib/app        theme, router, lifecycle
lib/core       network, storage, sync, notifications, prayer, widgets
lib/features   one folder per feature
lib/shared     shared providers, models, repositories
lib/l10n       en, ar, bn strings
```

State is Riverpod. `dataVersionProvider` is bumped after a write so personal screens refetch.

## Decisions made where the brief left a choice

| Topic | Decision | Reason |
| --- | --- | --- |
| Project location | `deenmate-ai/` inside the workspace | The workspace held unrelated files, which were left alone |
| Roles | `users.admin_role` column | Five fixed roles do not need a permissions package |
| `admin_role` assignment | Not mass assignable. CLI or Super Administrator only | Registration can never grant it |
| Quran translations in the AI index | One source per edition, one chunk per verse | A reviewer approves an edition in one step |
| Retrieval | MySQL FULLTEXT plus direct `2:255` reference lookup | Works with no extra infrastructure. Embeddings can be added inside `SourceRetriever` later |
| Default translations | Pickthall (en) and Muhiuddin Khan (bn) | Available from the source API. Licences still need confirming |
| Quran page tracking | Pages scrolled past, at most one per 20 seconds | Opening a surah or leaving it open earns nothing |
| Tasbih points | `floor(count / 10)` per saved session | Uses the configured rule, and remainders do not carry over |
| Hard delete on account deletion | Hard delete with cascade | Deletion should actually delete |
| One record per prayer per date | Second attempt is rejected with a prompt to edit | Keeps history clean. Removing a record frees the slot |
