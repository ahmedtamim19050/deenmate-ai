# Quran translation audit (Phase 2A)

This audit covers the translation editions in the local database, the damaged Bangla verses, and the sources and licences for Bangla and English editions. It is read-only: nothing was imported or changed. Date: 2026-11-09.

## 1. Existing editions

| Edition | Language | Translator | Records | Missing | Duplicates | Wrong references | Licence stored | Active |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `bn.bengali` | Bangla | Muhiuddin Khan | 6,236 | 0 | 0 | 0 | "Verify rights before production use" | Yes |
| `en.pickthall` | English | Mohammed Marmaduke William Pickthall | 6,236 | 0 | 0 | 0 | "Verify rights before production use" | Yes |

- **Origin:** both were imported from AlQuran Cloud by `quran:import`, which takes them from Tanzil.
- **Missing metadata:** neither has a confirmed licence, a licence URL or a `quran_data_sources` link.
- **AI use:** their AI sources (`islamic_sources` #1 and #2) are `pending`, so the assistant doesn't use them.
- **Integrity:** there are no empty verses, and no translation row points to a missing verse.

## 2. The 24 damaged Bangla verses (`bn.bengali`)

Each damaged spot is a character from an unrelated script (Javanese, N'Ko, Thaana, CJK and others), usually followed by a broken HTML character reference such as `#2468;`. Decoding the reference recovers one letter, but the character the stray replaced is lost, so the original wording can't be rebuilt from the data. **No replacement text is proposed.**

The last three columns compare the stored text with Tanzil's own `bn.bengali` file (last updated 2011-04-30), AlQuran Cloud, and the fawazahmed0 dataset.

| # | Verse | Damaged text → what was found | Tanzil file | AlQuran Cloud | fawazahmed0 |
| --- | --- | --- | --- | --- | --- |
| 1 | 2:230 | `্লাহ কতৃꦣ2453; নির্ধ` → stray U+A9A3, fragment 2453; (= `ক`) | identical | identical | identical |
| 2 | 3:33 | `নূহ (আঃ)͠ও ইব্র` → stray U+0360, fragment none | identical | identical | identical |
| 3 | 3:125 | `্তা চিহিߦ#2468; ঘোড়া` → stray U+07E6, fragment #2468; (= `ত`) | identical | identical | identical |
| 4 | 6:83 | `র সম্প্রަ#2470;ায়ের ব` → stray U+07A6, fragment #2470; (= `দ`) | identical | identical | identical |
| 5 | 10:83 | `শময় কর্তꦣ2499;ত্বের ` → stray U+A9A3, fragment 2499; (= `ৃ`) | identical | identical | identical |
| 6 | 11:40 | ` পূর্বহেߦ#2439; হুকুম` → stray U+07E6, fragment #2439; (= `ই`) | identical | identical | identical |
| 7 | 12:76 | ` না, কিু栦#2438;ল্লাহ ` → stray U+6826, fragment #2438; (= `আ`) | identical | identical | identical |
| 8 | 13:4 | `য ও খজ্জꦣ2497;র রয়েছ` → stray U+A9A3, fragment 2497; (= `ু`) | identical | identical | identical |
| 9 | 16:7 | `র পরিশ্রۦ#2478; ব্যতী` → stray U+06E6, fragment #2478; (= `ম`) | identical | identical | identical |
| 10 | 16:44 | `দেরকে নিꦣ2503;দশাবলী` → stray U+A9A3, fragment 2503; (= `ে`) | identical | identical | identical |
| 11 | 17:59 | `তীগণ কতৃꦣ2453; নিদর্` → stray U+A9A3, fragment 2453; (= `ক`) | identical | identical | identical |
| 12 | 20:59 | `পূর্বাহেߠলোকজন` → stray U+07E0, fragment none | identical | identical | identical |
| 13 | 20:96 | `র পদচিহেߦ#2480; নীচ থ` → stray U+07E6, fragment #2480; (= `র`) | identical | identical | identical |
| 14 | 22:3 | `লাহ সম্পꦣ2503;ক বিতর` → stray U+A9A3, fragment 2503; (= `ে`) | identical | identical | identical |
| 15 | 23:68 | `লাম সম্পꦣ2503;ক চিন্` → stray U+A9A3, fragment 2503; (= `ে`) | identical | identical | same damage (only a trailing ? differs) |
| 16 | 23:71 | `মধ্যবর্তꦣ2496; সবকিছ` → stray U+A9A3, fragment 2496; (= `ী`) | identical | identical | identical |
| 17 | 23:88 | `ুর কতৃর্ꦣ2468;্ব, যি` → stray U+A9A3, fragment 2468; (= `ত`) | identical | identical | same damage (only a trailing ? differs) |
| 18 | 24:55 | ` শাসনকতৃꦣ2468;্ব দান` → stray U+A9A3, fragment 2468; (= `ত`) | identical | identical | identical |
| 19 | 28:46 | `আপনার পূꦣ2503;ব কোন` → stray U+A9A3, fragment 2503; (= `ে`) | identical | identical | identical |
| 20 | 28:71 | `বুও কর্ণꦣ2474;াত করব` → stray U+A9A3, fragment 2474; (= `প`) | identical | identical | same damage (only a trailing ? differs) |
| 21 | 30:18 | `ং অপরাহেߠও মধ্য` → stray U+07E0, fragment none<br>` মধ্যাহেߦ#2404; নভোম` → stray U+07E6, fragment #2404; (= `।`) | identical | identical | identical |
| 22 | 51:34 | `াছে চিহিߦ#2468; আছে।` → stray U+07E6, fragment #2468; (= `ত`) | identical | identical | identical |
| 23 | 52:48 | `পালনকর্তꦣ2494;র সপ্র` → stray U+A9A3, fragment 2494; (= `া`) | identical | identical | identical |
| 24 | 82:19 | `ন সব কতৃꦣ2468;্ব হবে` → stray U+A9A3, fragment 2468; (= `ত`) | identical | identical | identical |

**Correction (Phase 2B): 44 damaged verses, not 24.** The stronger checks built in Phase 2B found 20 more verses with the same damage in a form the first audit missed: the lost character was replaced by a literal `?` or a modifier letter, for example `সামর্থ?2480;য়েছে` (3:97), `তা˦#2503;দরকে` (5:9) and `দারিদ্রে?480; কারণে` (6:151). The 20 additional verses are 3:97, 5:9, 5:64, 6:151, 9:28, 9:86, 16:5, 17:111, 18:95, 22:41, 24:60, 26:211, 27:19, 27:64, 28:40, 40:3, 46:15, 47:4, 59:23 and 65:6. All 44 are saved in the rollback file below.

**Finding:** the damage is in Tanzil's 2011 source file, and every public copy found carries it, so there is no clean digital source to correct from. A correction needs the printed edition, or the rights holder's digital text, checked word by word by a Bangla-reading reviewer.

**Rollback:** the stored text of all 44 verses, with SHA-256 hashes, is saved in `backend/storage/app/quran/audits/bn.bengali-damaged-verses-originals.json` (git-ignored).

## 3. Sources and licences

| Edition | Translator | Language | Where available | Copyright holder | Licence or terms | Redistribution | Required attribution | Local storage |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Muhiuddin Khan (`bn.bengali`, current) | Muhiuddin Khan | Bangla | Tanzil, AlQuran Cloud | Not found | Tanzil: non-commercial use only; other uses need the translator's or publisher's permission | Not without the translator's or publisher's permission | Translator; link to Tanzil | Unclear; needs permission |
| Abu Bakr Zakaria (`bengali_zakaria`) | Dr. Abu Bakr Muhammad Zakaria | Bangla | QuranEnc.com (API) | Issued through QuranEnc.com | QuranEnc: may be downloaded and republished under 7 conditions (below) | Yes, under the conditions | Publisher and QuranEnc.com, with the version number | Yes, if kept unmodified and up to date |
| Taisirul Quran, Mujibur Rahman, Rawai al-Bayan | Tawheed Publication, Darussalaam, Bayaan Foundation | Bangla | Quran.com API only | The publishers | Quran Foundation: in-app display only; storing or redistributing needs a signed licence | No | — | No, without a licence |
| Rowwad Translation Center (`english_rwwad`) | Rowwad Translation Center team | English | QuranEnc.com, v1.0.21 | Issued through QuranEnc.com | QuranEnc conditions | Yes, under the conditions | As above | Yes, under the conditions |
| Hilali & Khan (`english_hilali_khan`) | Taqi-ud-Din al-Hilali, Muhammad Muhsin Khan | English | QuranEnc.com, v1.1.3 | Issued through QuranEnc.com | QuranEnc conditions | Yes, under the conditions | As above | Yes, under the conditions |
| Noor International Center (`english_saheeh`) | Noor International Center | English | QuranEnc.com, v1.1.2 | Issued through QuranEnc.com | QuranEnc conditions | Yes, under the conditions | As above | Yes, under the conditions |
| Saheeh International (printed) | Saheeh International | English | Tanzil, Quran.com | Abul-Qasim Publishing House / Al-Muntada Al-Islami | The printed edition forbids storage without the publisher's written permission | No, without permission | — | No, without permission |
| Pickthall (`en.pickthall`, current) | M. M. Pickthall | English | Tanzil, AlQuran Cloud | Published 1930; Pickthall died in 1936 | Likely public domain in the US (1930 works, from 2026) and in life+60/70 countries such as Bangladesh. This is not legal advice | Likely yes for the 1930 text | Translator | Likely yes; confirm which edition the file follows |

**QuranEnc.com conditions** (from the edition page):
1. No modification, addition or deletion.
2. Clearly name the publisher and the source (QuranEnc.com).
3. State the version number.
4. Keep the transcript information.
5. Report notes on the translation to QuranEnc.com.
6. Update to the latest version.
7. Don't show inappropriate advertisements alongside the translation.

DeenQ AI has since become ad-free (no ads or ad SDK), so the advertising condition is met.

## 4. Recommendations

**Bangla**
1. **Abu Bakr Zakaria via QuranEnc.com:** the only Bangla edition found with published republishing terms. It has footnotes, which the schema can't store yet.
2. **Muhiuddin Khan:** keep it only until a licensed, clean text is available, and don't correct the 24 verses by guesswork. Either obtain the publisher's text and permission, or switch the app's default to Zakaria and deactivate this edition.
3. **Taisirul Quran, Mujibur Rahman and Rawai al-Bayan:** only with the publishers' written permission.

**English**
1. **Rowwad Translation Center via QuranEnc.com:** clear terms and modern English.
2. **Hilali & Khan via QuranEnc.com:** widely used, with clear terms.
3. **Pickthall:** keep as an optional classic once its public-domain status is confirmed for your markets.
4. **Saheeh International:** don't use the printed edition without Al-Muntada's written permission. QuranEnc's `english_saheeh` is issued by Noor International Center under QuranEnc's terms; confirm with QuranEnc how it relates to Saheeh International before labelling it with that name.

## 5. TranslationImporter readiness

| Requirement | Status | Evidence |
| --- | --- | --- |
| Complete 6,236-verse editions | Ready | Rejects incomplete editions and names the missing verses (tested with `114:6`) |
| Correct surah and verse references | Ready | Checks every reference against the fixed structure and the stored verses |
| Unique records | Ready | Upsert on (verse, edition), backed by the database's unique key; duplicate references are rejected |
| Safe repeat imports | Ready | A second run reports 0 new records (tested) |
| Source attribution | Partly ready | Requires identifier, language, name and licence; translator is optional; no publisher or version fields |
| Licence metadata | Partly ready | `license`, `license_url` and a data source link; no version, which QuranEnc requires |
| Inactive until reviewed | Ready | New editions are created with `is_active = false` (tested) |
| Damage detection | **Gap** | Rejects only U+FFFD and invalid UTF-8, so it would have accepted these 24 verses |
| Footnotes | **Gap** | Nowhere to store them; QuranEnc editions put `[১]` markers in the text |
| Rollback | **Gap** | Re-importing overwrites earlier text without keeping a copy |
| Source adapters | **Gap** | No reader for QuranEnc's API or files yet, and no command |
| AI use | Not covered | Importing doesn't create `islamic_sources` entries for review |
| Arabic text | Safe | It never writes to `quran_verses` |

## 6. Next steps

1. **Close the importer gaps:** add a per-language script check (reject stray letters from other scripts and broken `#NNNN;` references), `footnotes`, `publisher` and `version` columns, and a revision table that keeps earlier text for rollback.
2. **Build the QuranEnc path:** a QuranEnc reader and a `quran:import-translation` command with `--dry-run`, recording the version and attribution.
3. **Get written confirmation from QuranEnc.com:** that its editions may be stored and shown in the app, and how `english_saheeh` relates to Saheeh International.
4. **Import the first editions:** run Abu Bakr Zakaria and Rowwad in dry-run mode and review them, then import them inactive. Activate them after a reviewer approves.
5. **Resolve Muhiuddin Khan:** contact the rights holder for permission and a clean text, or retire the edition.
6. **Check production:** run the same audit there before changing anything.

## Sources

- Tanzil translation terms: https://tanzil.net/trans/
- QuranEnc.com edition and terms: https://quranenc.com/en/browse/bengali_zakaria
- Quran Foundation developer terms: https://api-docs.quran.foundation/legal/developer-terms/
