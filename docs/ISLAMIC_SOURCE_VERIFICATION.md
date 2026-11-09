# Islamic source verification

This is a human process that software cannot replace. **Nothing imported is treated as verified until a qualified reviewer approves it.**

## Current state after a fresh setup

| Content | Status |
| --- | --- |
| Quran Arabic text | Imported from AlQuran Cloud (`quran-uthmani`). Needs checking by a qualified person against a trusted mushaf before publishing |
| Quran translations | `pending`. The AI will not use them until approved |
| Hadith collections | `pending`. The AI will not use them until approved |
| Starter lessons and quizzes | `pending`. Written for this project and not reviewed by a scholar. They show "Awaiting scholar review" in the app |

The references in the starter lessons were written from general knowledge and were not checked against the source texts. A reviewer needs to verify each one.

## Roles

Create reviewers with:

```bash
php artisan deenmate:make-admin reviewer@example.com --role=scholar_reviewer
```

Only a **Scholar Reviewer** or **Super Administrator** can approve. Content Editors can draft and edit but cannot approve. Every decision records the reviewer and date, and appears in **Scholar Reviews**.

## Reviewing a source

In the admin panel: **Scholar Review > Islamic Sources > Review**. Or by command:

```bash
php artisan sources:review 3 reviewer@example.com --notes="Checked against ..."
php artisan sources:review 3 reviewer@example.com --reject --notes="Reason"
```

The command refuses an account without a reviewer role.

### Checklist

- Is the source one your scholars accept for this use?
- Are the author, publisher and URL correct?
- Is the licence recorded, and does it permit your use?
- Have sample passages been checked against a printed or trusted edition?
- For Hadith: are grades attributed to the scholar who gave them?

## Reviewing lessons

**Content > Lessons > Review**, and the same for Courses. Editing an approved lesson's title, body or references returns it to pending automatically.

## Quran text integrity

`quran:import` protects the text as follows:

- It stops unless there are exactly 114 surahs and 6,236 verses.
- The upstream `quran-uthmani` data prepends the Basmala to verse 1 of every surah except 1 and 9, though it is not part of those verses' text. `BasmalaSeparator` removes only those four opening words, and stops the import if any surah does not begin with them as expected.
- Al-Fatihah 1:1 and the Basmala within An-Naml 27:30 are untouched.
- An invisible byte-order mark found at the start of some upstream strings is removed. Nothing else is altered.
- Quran text is read-only in the admin panel.
- The source of the text is stored on every verse and shown in the reader.

After importing, these checks should pass:

```sql
SELECT COUNT(*) FROM quran_verses;                 -- 6236
SELECT text_arabic FROM quran_verses WHERE surah_id = 112 AND verse_number = 1;  -- begins with قُلْ
```

## Licensing: confirm before production

Open data is not automatically licensed for your use. Confirm each of these, then record the result in the admin panel:

| Item | Source | Note |
| --- | --- | --- |
| Quran Arabic text | Tanzil Project, Uthmani 1.1 (`php artisan quran:import-arabic`) | CC BY 3.0, verbatim copies only. Credit Tanzil and link to tanzil.net wherever the text is shown. Recorded in `quran_data_sources` |
| `quran-uthmani` | AlQuran Cloud | Earlier source of the Arabic text, now replaced by Tanzil's own files. Still used for transliteration and the two translations by `quran:import` |
| `en.pickthall` | Pickthall, 1930 | Believed to be out of copyright in many countries, but this varies. Confirm for your markets |
| `bn.bengali` | Muhiuddin Khan | Rights are held separately. Confirm you may redistribute it. This is not the Islamic Foundation Bangladesh translation, so do not label it as one |
| `en.transliteration` | AlQuran Cloud | Confirm terms |
| Hadith datasets | `fawazahmed0/hadith-api` | The dataset is under The Unlicense, but underlying translations may have their own rights |
| Recitation audio | `cdn.islamic.network` (Alafasy) | Confirm terms for use in the app (it is ad-free), or host licensed audio yourself |

To use a different edition, change `QURAN_TRANSLATION_EDITIONS` and run the import again.

## Hadith grades

Grades are stored only when the dataset documents them, along with who gave them. Otherwise the app shows "not documented in this dataset". Never fill one in by estimate. An-Nawawi's Forty currently imports with no grades in this dataset.

## Adding verified content later

New Quran editions, Hadith collections, articles and answers can all be added through the importers or the admin panel, with no app release. Each goes through the same review.
