# Translation imports, history and review

How Quran translations get into DeenQ AI, how they are checked, how a change is undone, and how an edition is reviewed. The audit behind this design is in [TRANSLATION_AUDIT.md](TRANSLATION_AUDIT.md).

**No translation is imported until its licence permits storage and display, either in published terms or in writing.** Dry runs are always allowed.

## Sources and their status (checked 2026-11-09)

| Source | Language | Status | Evidence |
| --- | --- | --- | --- |
| **Clear Quran (Allah Edition), Talal Itani**: `en.clearquran`, last updated 2026-10-09 | English | **Imported, awaiting review** | [clearquran.com/download](https://clearquran.com/download/): CC BY-ND 4.0, plus the copyright holder's permission: "free to use, store, convert, and distribute, including in commercial projects. You don't need to ask for permission." Conditions: no words changed, added or removed, and the credit "Translation by Talal Itani, ClearQuran.com" shown, with a link to clearquran.com where possible |
| Rowwad Translation Center (QuranEnc) | English | Blocked | QuranEnc's terms allow downloading and republishing but say nothing on storing in an app's database. Written confirmation requested |
| Abu Bakr Zakaria (QuranEnc) | Bangla | Blocked | As above. Also no published version, and 4 source typos |
| Muhiuddin Khan (`bn.bengali`, stored before) | Bangla | Blocked for new imports | No rights holder found; Tanzil allows non-commercial use only; 44 damaged verses |
| Zohurul Hoque (Tanzil `bn.hoque`) | Bangla | Blocked | No licence or permission found from the translator or publisher; Tanzil's terms are non-commercial only and require the rights holder's permission otherwise |
| Pickthall, Yusuf Ali, Shakir (Project Gutenberg #16955) | English | Blocked | Listed "public domain in the USA". The Gutenberg licence tells users outside the US to check local law, so status in Bangladesh needs confirming. The file also says its text "originates from a file whose origins we don't know" (re-proofed against print) |
| Saheeh International; Quran.com-only editions | English, Bangla | Blocked | Printed edition forbids storage without written permission; Quran Foundation terms need a signed licence to store |

No Bangla translation with documented permission for database storage was found. The next step is to ask a rights holder directly.

**Clear Quran attribution:** the credit is part of the edition's name, so it appears wherever the edition is listed. Before switching the edition on, also show the link to https://clearquran.com on the app's About screen, as the licence asks. Because the licence is NoDerivatives, the AI should quote this translation word for word, credited, rather than reword it.

Import it with `php artisan quran:import-clearquran /path/to/clearquran-txt-allah-edition.zip` (add `--dry-run` first). The command checks that the file's own licence line, translator and edition name still match the published permission, and stops if they don't.

## Commands

| Command | What it does |
| --- | --- |
| `php artisan quran:sync-translation english_rwwad --dry-run` | Downloads the edition from QuranEnc's API surah by surah into `storage/app/quran/quranenc/<key>/<version>/staging`, then validates it. Resumable, rate limited and retried. Without `--dry-run` it imports through `quran:import-translation`, so the licence gate applies |
| `--restart` | Discards that version's staged files and downloads everything again |
| `php artisan quran:import-translation english_rwwad --dry-run` | Reads a QuranEnc.com edition, checks every verse and reports. Writes no translations |
| `php artisan quran:import-translation english_rwwad` | Imports it. Refused until the licence is confirmed in config |
| `--source-dir=/path` | Reads saved QuranEnc API responses (`sura-001.json` … `sura-114.json`, plus `translations.json`) instead of downloading |
| `--acknowledge=9:24 --acknowledge=55:14` | Imports verses a reviewer has seen to contain source errors, as flagged records. The text is never repaired |
| `php artisan quran:translation-inspect bn.bengali` | Lists damaged or suspicious verses. `--record` stores them as review flags |
| `php artisan quran:translation-inspect bn.bengali --resolve=12 --reviewer=… --notes=…` | Marks flags as checked. The text is not changed |
| `php artisan quran:translation-review en.rwwad start --reviewer=…` | Moves an edition through review: `start`, `approve`, `reject`, `deactivate`, `activate` |
| `php artisan quran:translation-history en.rwwad` | Revisions, review decisions and open flags |
| `php artisan quran:translation-rollback en.rwwad 1 --by=… --dry-run` | Restores an earlier revision exactly. Drop `--dry-run` to apply it |

**How the sync behaves:**
- **Spacing and retries:** it waits `QURANENC_DELAY_MS` (default 500 ms) between requests, times out after `QURANENC_TIMEOUT` seconds, and retries connection failures, HTTP 429 (honouring `Retry-After`) and server errors up to `QURANENC_MAX_ATTEMPTS` times with backoff.
- **Resume:** a `manifest.json` records each surah's SHA-256. A later run downloads only surahs that are missing or don't match, and a new QuranEnc version is staged in its own folder.
- **Safety:** a surah with missing, extra or out-of-order verses stops the run before it is saved, and nothing is imported.
- **Certificates:** if PHP has no CA certificates configured (cURL error 60), point `QURANENC_CA_BUNDLE` at a CA file, such as Laragon's `C:/laragon/etc/ssl/cacert.pem`. Certificate checks always stay on.

Reviewers are users with the Scholar Reviewer or Super Administrator role. To enable a QuranEnc edition, set `QURANENC_BENGALI_ZAKARIA_LICENCE_CONFIRMED` or `QURANENC_ENGLISH_RWWAD_LICENCE_CONFIRMED` in `.env` to the reference of QuranEnc's written permission.

## Damage checks

Every verse's text and footnotes are checked before anything is written. An **error** blocks the whole import (unless that verse is acknowledged), and a **warning** is stored as a flag for a reviewer. Nothing is repaired automatically.

| Check | Severity | Example |
| --- | --- | --- |
| Lost character fused to a broken character reference (Tanzil 2011 damage) | Error | `কতৃꦣ2453;`, `সামর্থ?2480;`, `তা˦#2503;দরকে` |
| Character reference missing its `&` | Error | `#2468;` |
| HTML character reference in verse text | Error | `&amp;` |
| Unsafe markup | Error | `<script>` |
| Replacement character, invalid UTF-8, control, private-use or unassigned characters | Error | U+FFFD |
| Letters from another script | Error | a Gujarati `૧` in Bangla |
| One word in two scripts | Error | `তোমরf`, `সبজদা` |
| Latin combining mark not on a Latin letter | Error | U+0360 after `)` |
| Latin letters in a Bangla edition | Warning | a name or abbreviation |
| Bengali and Arabic run together | Warning | a missing space before a quotation |
| Arabic vowel mark not on an Arabic letter | Warning | a slip in a quotation |
| Markup in verse text | Warning | a bold section heading |
| Footnote marker with no note, or note with no marker | Warning | `[৭]` with no note |

The checks were run against real data, with these results:

| Edition | Verses | With errors | Notes |
| --- | --- | --- | --- |
| Pickthall (stored) | 6,236 | 0 | |
| Rowwad Translation Center (QuranEnc) | 6,236 | 0 | |
| Muhiuddin Khan (stored) | 6,236 | 44 | All 44 are real damage |
| Abu Bakr Zakaria (QuranEnc) | 6,236 | 4 | Real typos in the source: 9:24, 9:61, 55:14, 56:19 |

## Footnotes

- **Exact copy:** each verse keeps the footnote field exactly as published in `quran_translations.footnotes_raw`. This exact copy is what the checksum covers.
- **Markers stay:** markers such as `[১]` or `[2]` remain in the verse text, untouched.
- **Index:** `quran_translation_footnotes` holds an index of notes by marker, built only from the markers that appear in the verse text. A numbered citation inside a note, such as `[বুখারী ৩]`, is never mistaken for a marker.
- **Introductions:** text before the first marker, such as a surah introduction, is kept as a note with no marker.
- **Retrieving:** `QuranTranslation::footnotes()` returns a verse's notes in published order.
- **Real data:** Rowwad has 2,640 markers and 2,640 notes. Zakaria has 5,551 notes, including 289 surah introductions.

Footnote text can contain simple formatting such as `<b>`. Anything that displays it must sanitise it.

## Revision history and rollback

- **Revisions:** every import that changes content creates a revision. A revision is the complete content of the edition, with a SHA-256 checksum, in `quran_translation_revisions` and `quran_translation_revision_texts`.
- **Baseline:** content that existed before history did (such as `bn.bengali` and `en.pickthall`) is saved first as a **baseline** revision, the first time it would be changed.
- **No duplicates:** importing identical content creates no revision.
- **Safe writes:** an import is written in one transaction and read back against its checksum before commit. If anything fails, the stored edition stays exactly as it was.
- **Rollback:**
  1. Checks that the target revision is complete and matches its checksum.
  2. Copies it back as a new revision.
  3. Verifies the checksum.
  4. Sends the edition back for review.

  No revision is ever deleted.
- **Audit:** every import, dry run and rollback writes a `quran_import_logs` row.

## Review states

| State | Meaning | Shown in app | Used by AI |
| --- | --- | --- | --- |
| `pending` | Registered, waiting for licence or content | No | No |
| `imported` | Content stored, not reviewed | No (new editions) | No |
| `under_review` | A reviewer is checking it | No | No |
| `approved` | A reviewer approved it | Only after `activate` | Yes |
| `rejected` | Not to be used | No | No |
| `inactive` | Withdrawn after approval | No | No |

- **Allowed moves:** imported → under_review → approved or rejected; approved → inactive or under_review; rejected or inactive → under_review.
- **Approving or rejecting:** needs notes, and approval is blocked while any flag is unresolved.
- **Re-importing:** new content for an approved edition sends it back to `imported` and hides it.
- **Recording:** every decision is recorded in `quran_translation_reviews` with the reviewer and time. Nothing is approved automatically.
- **AI gate:** retrieval (`SourceRetriever`, `QuranLookup`) uses an edition's text only when it is `approved`.

**Existing editions:** `bn.bengali` and `en.pickthall` start as `imported` but stay shown in the app as before, since they predate this workflow. Neither can reach the AI until reviewed and approved.

## Making an approved translation available to the AI

The AI searches `islamic_sources`. An approved edition is copied there automatically, and withdrawn automatically:

- **On approval:** the `SyncTranslationToAi` job runs on the queue, so the reviewer's request doesn't wait. It checks the reviewer, notes, open flags, attribution, all 6,236 verse references and the checksum. It then copies every verse word for word into one `islamic_sources` record (`edition:<identifier>`, one chunk per verse, `Quran s:v`). The record is titled "<name>, version <version>", with the translator, publisher, source URL and licence. The copy is written switched off, read back verse by verse, and switched on only when everything matches, all in one transaction. A failure rolls back and is recorded in `ai_sync_status` and `ai_sync_error` on the edition.
- **On leaving approval** (to under review, rejected, inactive or imported, including a re-import or rollback): the source is switched off at once (`pending`, or `rejected` for rejected editions). Nothing is deleted, so provenance stays.
- **At every query:** retrieval uses an edition's text only if the edition is `approved`, its copy is `synced`, and the copy's checksum matches the edition's current content. A stale copy, or a source approved by hand in the admin panel, is never used.
- **In answers:** quoted verses are shown word for word under "From the Quran", followed by the translation's title and version as the credit. The AI is told never to present its own wording as a translation, and to keep explanation separate from quotation. This matters for NoDerivatives licences such as Clear Quran's.

Run a queue worker (`php artisan queue:work`) so approvals are copied. To check, retry or withdraw by hand: `php artisan quran:translation-ai-sync en.clearquran --status`, `… en.clearquran` (retry, approved editions only) or `… --revoke`. This command never approves anything.

## Questions for QuranEnc.com (send before importing)

1. **Storage:** may DeenQ AI store your translations (starting with `english_rwwad` and `bengali_zakaria`) in its own database and show them in its Android app, including offline?
2. **Advertising:** DeenQ AI is a personal-use, ad-free app with no advertising SDK. Please confirm the advertising condition raises no further requirement.
3. **Attribution:** is this attribution correct: "[Title], version [x]. Source: QuranEnc.com ([link])"? Where should it appear?
4. **Bangla version:** `bengali_zakaria` is served by your API but not listed in `/api/v1/translations/list`. What is its current version and last update date, and is it still maintained?
5. **Footnotes:** may footnotes be stored and shown with the verse, and may HTML such as `<b>` be shown as formatting?
6. **Updates:** how should we learn of new versions? Is checking `last_update` in your list enough?
7. **Source typos:** we found possible typos in `bengali_zakaria`: 9:24 (`তোমরf`), 9:61 (a Gujarati digit), 55:14 (`সبজদা`) and 56:19 (`নাG`). Can you confirm them, and will they be corrected?
8. **Copyright holder:** who should be named as copyright holder for each edition, if not QuranEnc.com?

Record the reply's reference in the matching `*_LICENCE_CONFIRMED` setting only after a written answer allows storage and display.
