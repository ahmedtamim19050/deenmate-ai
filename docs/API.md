# API

Base path `/api/v1`. JSON only. Authenticate with `Authorization: Bearer <token>`. Send `Accept-Language: en|ar|bn` for localized AI notices.

Successful responses put the payload in `data`. Paginated lists add `meta`. Errors look like:

```json
{ "message": "The given data was invalid.", "errors": { "prayer": ["..."] } }
```

| Status | Meaning |
| --- | --- |
| 401 | Missing or invalid token |
| 403 | Not your record |
| 404 | Not found, including another person's private item |
| 422 | Validation failed |
| 429 | Rate limited |

Rate limits per minute: auth 10, public 120, signed-in 180, sync 30, AI configurable (default 6).

## Public

| Method | Path | Notes |
| --- | --- | --- |
| GET | `/config` | `ai_configured` and settings marked public |
| GET | `/courses` | `?category=` |
| GET | `/courses/{id}` | |
| GET | `/lessons/{id}` | Quiz answers are not included |
| GET | `/quran/surahs` | |
| GET | `/quran/surahs/{id}` | `?edition=`. Includes `basmala` heading and source metadata |
| GET | `/quran/editions` | |
| GET | `/quran/search` | `?q=&edition=` |
| GET | `/hadith/collections` | |
| GET | `/hadith/collections/{id}/records` | Paginated. `grade` is null when not documented |

## Authentication

| Method | Path | Body |
| --- | --- | --- |
| POST | `/auth/register` | `name, email, password, password_confirmation, locale?` |
| POST | `/auth/login` | `email, password` |
| POST | `/auth/forgot-password` | `email`. The same reply whether or not the account exists |
| POST | `/auth/reset-password` | `token, email, password, password_confirmation` |
| POST | `/auth/logout` | |
| POST | `/auth/refresh` | Rotates the token |
| GET | `/auth/me` | |
| POST | `/auth/devices` | `device_uuid, platform, push_token?, app_version?` |

## Profile and dashboard

| Method | Path | Notes |
| --- | --- | --- |
| PUT | `/profile` | `name?, country?, onboarded?` |
| GET, PUT | `/profile/preferences` | `locale, theme, haptics_enabled, show_points, ai_personalization_enabled, ads_personalized` |
| GET | `/dashboard` | Today, week, goals, recent, next lesson |
| GET | `/progress` | `?days=7..90` |

## Activities

| Method | Path | Notes |
| --- | --- | --- |
| GET | `/activities/types` | With the active rule, for previews |
| POST | `/activities` | See below |
| POST | `/activities/sync` | `items: []` up to 100. Each item returns `created`, `duplicate` or `rejected` |
| GET | `/activities/history` | `?category=&type=&from=&to=` |
| PUT | `/activities/{id}` | `quantity, metadata?`. Reverses and re-awards |
| DELETE | `/activities/{id}` | Reverses points and keeps the ledger |

```json
{
  "type": "quran_page",
  "quantity": 3,
  "source": "in_app",
  "idempotency_key": "uuid",
  "occurred_at": "2026-03-20T10:00:00Z",
  "local_date": "2026-03-20",
  "confirmed": false,
  "metadata": {}
}
```

`source: manual` requires `confirmed: true`. The response has `points_awarded`, `duplicate`, `balance` and `new_achievements`. A repeated key returns 200 with `duplicate: true`.

## Prayer and Salah

| Method | Path | Notes |
| --- | --- | --- |
| GET | `/prayer/today` | `?date=`. `configured: false` until a location is saved |
| GET | `/prayer/methods` | |
| GET, PUT | `/prayer/settings` | Location, method, Asr, high latitude, offsets, `notifications[]` |
| GET, PUT | `/prayer/templates` | `templates[]` |
| POST | `/prayer/templates/restore` | |
| GET | `/prayer/history` | `?from=&to=&prayer=` |
| POST | `/salah/confirm` | See below |
| PUT | `/salah/{id}` | `items[]`. Reverses, re-awards and writes an audit record |
| POST | `/salah/{id}/reverse` | |
| GET | `/salah/summary` | Counts only. There is never a "missed" figure |

```json
{
  "prayer": "fajr",
  "prayer_date": "2026-03-20",
  "idempotency_key": "uuid",
  "confirmed": true,
  "items": [{ "template_key": "fard", "label": "Fard", "category": "fard", "rakats": 2 }]
}
```

## Points, achievements, goals

`GET /points/balance`, `GET /points/history`, `GET /points/summary`, `GET /achievements`, `GET /achievements/mine`, `GET|POST /goals`, `PUT|DELETE /goals/{id}`.

Goal status is `active`, `paused`, `completed` or `archived`.

## AI

| Method | Path | Notes |
| --- | --- | --- |
| POST | `/ai/chat` | `message, conversation_id?` |
| GET | `/ai/conversations` | |
| GET, DELETE | `/ai/conversations/{uuid}` | Delete is permanent |
| DELETE | `/ai/conversations` | Clears all history |
| POST | `/ai/feedback` | `message_id, rating (helpful, not_helpful, incorrect), comment?` |

`data.status` on a chat response: `answered`, `insufficient_sources`, `not_configured`, `blocked`, `limit_reached`, `provider_error`, `greeting`. Citations are built by the server from the database.

## Learning

`POST /lessons/{id}/complete` (`local_date`), `POST /quizzes/{id}/attempts` (`idempotency_key, local_date, answers: {questionId: optionIndex}`), `GET|POST /bookmarks`, `DELETE /bookmarks/{id}`.

## Privacy

| Method | Path | Notes |
| --- | --- | --- |
| POST | `/privacy/export` | Queued. Returns an id |
| GET | `/privacy/export/{uuid}` | Status |
| GET | `/privacy/export/{uuid}/download` | Owner only, from private storage |
| POST | `/privacy/delete-account` | `password, confirm`. Permanent |
