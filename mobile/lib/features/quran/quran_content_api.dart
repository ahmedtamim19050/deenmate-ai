import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/network/api_exception.dart';
import 'quran_text.dart';

export 'quran_text.dart' show htmlToPlainText;

/// Tafsir and word-by-word data come from the Quran.com API (Quran Foundation). Its developer
/// terms allow showing this content inside the app but not redistributing it, so it is kept in
/// memory for the session only and is never written to the device.
final quranComDioProvider = Provider<Dio>(
  (_) => Dio(
    BaseOptions(
      baseUrl: 'https://api.quran.com/api/v4',
      connectTimeout: const Duration(seconds: 12),
      receiveTimeout: const Duration(seconds: 40),
      headers: {'Accept': 'application/json'},
    ),
  ),
);

const quranComWordAudioBase = 'https://audio.qurancdn.com/';

Future<Map<String, dynamic>> _get(Dio dio, String path, [Map<String, dynamic>? query]) async {
  try {
    final response = await dio.get<Map<String, dynamic>>(path, queryParameters: query);
    return response.data ?? const {};
  } on DioException catch (e) {
    if (e.response == null) throw ApiException(ApiErrorKind.offline, 'No connection');
    throw ApiException(ApiErrorKind.server, 'Quran.com returned ${e.response?.statusCode}');
  }
}

class TafsirSource {
  const TafsirSource({required this.id, required this.name, required this.author, required this.language});

  final int id;
  final String name;
  final String author;

  /// "bengali" or "english", as the API names them.
  final String language;
}

/// Bangla and English tafsir only, Bangla first.
final tafsirSourcesProvider = FutureProvider<List<TafsirSource>>((ref) async {
  final data = await _get(ref.read(quranComDioProvider), '/resources/tafsirs');
  final list = [
    for (final t in (data['tafsirs'] as List? ?? const []).cast<Map<String, dynamic>>())
      if (t['language_name'] == 'bengali' || t['language_name'] == 'english')
        TafsirSource(
          id: (t['id'] as num).toInt(),
          name: (t['translated_name'] as Map?)?['name'] as String? ?? t['name'] as String,
          author: t['author_name'] as String? ?? '',
          language: t['language_name'] as String,
        ),
  ];
  list.sort((a, b) => a.language == b.language ? a.id.compareTo(b.id) : (a.language == 'bengali' ? -1 : 1));
  return list;
});

class TafsirEntry {
  const TafsirEntry({required this.sourceName, required this.text, required this.coveredVerses});

  final String sourceName;

  /// Plain text with paragraphs separated by blank lines. Empty when the source explains
  /// this verse together with another one.
  final String text;
  final List<String> coveredVerses;
}

final tafsirProvider = FutureProvider.family<TafsirEntry, ({int tafsirId, String verseKey})>((ref, q) async {
  final data = await _get(ref.read(quranComDioProvider), '/tafsirs/${q.tafsirId}/by_ayah/${q.verseKey}');
  final t = data['tafsir'] as Map<String, dynamic>? ?? const {};
  return TafsirEntry(
    sourceName: t['resource_name'] as String? ?? '',
    text: htmlToPlainText(t['text'] as String? ?? ''),
    coveredVerses: ((t['verses'] as Map?)?.keys ?? const []).map((k) => '$k').toList(),
  );
});

class QuranWord {
  const QuranWord({
    required this.id,
    required this.verse,
    required this.position,
    required this.arabic,
    this.bangla,
    this.english,
    this.transliteration,
    this.audioUrl,
  });

  final int id;
  final int verse;
  final int position;
  final String arabic;
  final String? bangla;
  final String? english;
  final String? transliteration;
  final String? audioUrl;
}

/// Every word of a surah, keyed by verse number. Verse-end markers are left out.
final surahWordsProvider = FutureProvider.family<Map<int, List<QuranWord>>, int>((ref, surah) async {
  final dio = ref.read(quranComDioProvider);

  Future<List<Map<String, dynamic>>> load(String language) async {
    final verses = <Map<String, dynamic>>[];
    var page = 1;
    while (true) {
      final data = await _get(dio, '/verses/by_chapter/$surah', {
        'language': language,
        'words': 'true',
        'word_translation_language': language,
        'word_fields': 'text_uthmani',
        'per_page': 50,
        'page': page,
      });
      verses.addAll((data['verses'] as List? ?? const []).cast<Map<String, dynamic>>());
      final next = (data['pagination'] as Map?)?['next_page'];
      if (next == null) return verses;
      page = (next as num).toInt();
    }
  }

  final results = await Future.wait([load('bn'), load('en')]);
  final english = <int, String?>{
    for (final v in results[1])
      for (final w in (v['words'] as List).cast<Map<String, dynamic>>()) (w['id'] as num).toInt(): (w['translation'] as Map?)?['text'] as String?,
  };

  return {
    for (final v in results[0])
      (v['verse_number'] as num).toInt(): [
        for (final w in (v['words'] as List).cast<Map<String, dynamic>>())
          if (w['char_type_name'] == 'word')
            QuranWord(
              id: (w['id'] as num).toInt(),
              verse: (v['verse_number'] as num).toInt(),
              position: (w['position'] as num).toInt(),
              arabic: (w['text_uthmani'] ?? w['text']) as String,
              bangla: (w['translation'] as Map?)?['text'] as String?,
              english: english[(w['id'] as num).toInt()],
              transliteration: (w['transliteration'] as Map?)?['text'] as String?,
              audioUrl: w['audio_url'] == null ? null : '$quranComWordAudioBase${w['audio_url']}',
            ),
      ],
  };
});
