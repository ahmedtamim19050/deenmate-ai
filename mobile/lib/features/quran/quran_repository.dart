import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../shared/providers/providers.dart';
import '../../shared/repositories/cached_fetch.dart';

/// Bangla is the default translation for every app language. Others can be picked in the reader.
const defaultTranslationEdition = 'bn.bengali';

class Surah {
  const Surah(this.id, this.arabic, this.name, this.english, this.place, this.verses);

  final int id;
  final String arabic;
  final String name;
  final String english;
  final String place;
  final int verses;

  factory Surah.fromJson(Map<String, dynamic> j) => Surah(
    (j['id'] as num).toInt(),
    j['name_arabic'] as String,
    j['name_transliterated'] as String,
    j['name_english'] as String,
    j['revelation_place'] as String,
    (j['verse_count'] as num).toInt(),
  );
}

class Verse {
  const Verse(this.id, this.number, this.page, this.arabic, this.transliteration, this.translation);

  final int id;
  final int number;
  final int? page;
  final String arabic;
  final String? transliteration;
  final String? translation;

  factory Verse.fromJson(Map<String, dynamic> j) => Verse(
    (j['id'] as num).toInt(),
    (j['number'] as num).toInt(),
    (j['page'] as num?)?.toInt(),
    j['arabic'] as String,
    j['transliteration'] as String?,
    j['translation'] as String?,
  );
}

class SurahDetail {
  const SurahDetail(this.surah, this.basmala, this.verses, this.editionName, this.editionTranslator, this.textSource, {this.editionAttribution});

  final Surah surah;
  final String? basmala;
  final List<Verse> verses;
  final String? editionName;
  final String? editionTranslator;

  /// The credit the translation's licence requires, such as "Translation by …".
  final String? editionAttribution;
  final String? textSource;
}

class TranslationEdition {
  const TranslationEdition(this.identifier, this.language, this.name);

  final String identifier;
  final String language;
  final String name;
}

/// Text only ever comes from the server's imported datasets. Each surah opened stays cached for offline use.
final surahListProvider = FutureProvider<List<Surah>>((ref) async {
  final data = await cachedFetch(ref.read(databaseProvider), 'quran:surahs', () => ref.read(apiClientProvider).get('/quran/surahs'));
  return (data['data'] as List).map((e) => Surah.fromJson(e as Map<String, dynamic>)).toList();
});

final editionsProvider = FutureProvider<List<TranslationEdition>>((ref) async {
  final data = await cachedFetch(ref.read(databaseProvider), 'quran:editions', () => ref.read(apiClientProvider).get('/quran/editions'));
  return (data['data'] as List)
      .cast<Map<String, dynamic>>()
      .map((e) => TranslationEdition(e['identifier'] as String, e['language'] as String, e['name'] as String))
      .toList();
});

final surahDetailProvider = FutureProvider.family<SurahDetail, ({int id, String? edition})>((ref, q) async {
  final data = await cachedFetch(
    ref.read(databaseProvider),
    'quran:surah:${q.id}:${q.edition ?? 'default'}',
    () => ref.read(apiClientProvider).get('/quran/surahs/${q.id}', query: {'edition': ?q.edition}),
  );
  final d = data['data'] as Map<String, dynamic>;
  final verses = (d['verses'] as List).cast<Map<String, dynamic>>();
  final edition = d['edition'] as Map<String, dynamic>?;
  return SurahDetail(
    Surah.fromJson(d['surah'] as Map<String, dynamic>),
    d['basmala'] as String?,
    verses.map(Verse.fromJson).toList(),
    edition?['name'] as String?,
    edition?['translator'] as String?,
    verses.isEmpty ? null : verses.first['text_source'] as String?,
    editionAttribution: edition?['attribution_text'] as String?,
  );
});

/// A credit the app must show for its Quran text or a translation.
class QuranCredit {
  const QuranCredit({required this.title, this.attribution, this.licence, this.url});

  final String title;
  final String? attribution;
  final String? licence;
  final String? url;
}

/// Quran text sources and the translations shown in the app, each with its required credit.
final quranCreditsProvider = FutureProvider<List<QuranCredit>>((ref) async {
  final data = await cachedFetch(ref.read(databaseProvider), 'quran:credits', () => ref.read(apiClientProvider).get('/quran/credits'));
  final d = data['data'] as Map<String, dynamic>;
  return [
    for (final s in (d['text_sources'] as List? ?? const []).cast<Map<String, dynamic>>())
      QuranCredit(title: s['name'] as String, attribution: s['attribution_text'] as String?, licence: s['license_name'] as String?, url: s['website'] as String?),
    for (final t in (d['translations'] as List? ?? const []).cast<Map<String, dynamic>>())
      QuranCredit(
        title: t['name'] as String,
        attribution: t['attribution_text'] as String?,
        licence: t['license'] as String?,
        url: t['source_url'] as String?,
      ),
  ];
});

class VerseBookmark {
  const VerseBookmark(this.id, this.verseId, this.surahId, this.surahName, this.verseNumber);

  final int id;
  final int verseId;
  final int surahId;
  final String surahName;
  final int verseNumber;
}

final verseBookmarkListProvider = FutureProvider<List<VerseBookmark>>((ref) async {
  ref.watch(dataVersionProvider);
  if (!ref.watch(authProvider).signedIn) return const [];
  final data = await cachedFetch(ref.read(databaseProvider), 'user:bookmarks', () => ref.read(apiClientProvider).get('/bookmarks'));
  return [
    for (final b in (data['data'] as List).cast<Map<String, dynamic>>())
      if (b['target_type'] == 'verse' && b['verse'] != null)
        VerseBookmark(
          (b['id'] as num).toInt(),
          (b['target_id'] as num).toInt(),
          (b['verse']['surah_id'] as num).toInt(),
          b['verse']['surah'] as String,
          (b['verse']['number'] as num).toInt(),
        ),
  ];
});

/// Verse id to bookmark id, for the reader's bookmark toggle.
final verseBookmarksProvider = FutureProvider<Map<int, int>>((ref) async {
  final list = await ref.watch(verseBookmarkListProvider.future);
  return {for (final b in list) b.verseId: b.id};
});
