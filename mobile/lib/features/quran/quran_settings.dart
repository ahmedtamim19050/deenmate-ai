import 'dart:convert';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../shared/providers/providers.dart';

/// Reader choices that persist across restarts. Stored on this device only.
class QuranSettings {
  const QuranSettings({
    this.arabicSize = 30,
    this.translationSize = 17,
    this.tafsirSize = 17,
    this.showTranslation = true,
    this.showTransliteration = false,
    this.wordByWord = false,
    this.wordMeaning = 'bn',
    this.wordTransliteration = false,
    this.secondEdition,
    this.tafsirId,
  });

  final double arabicSize;
  final double translationSize;
  final double tafsirSize;
  final bool showTranslation;
  final bool showTransliteration;
  final bool wordByWord;

  /// "bn", "en" or "both".
  final String wordMeaning;
  final bool wordTransliteration;

  /// A second translation shown under the main one, such as en.pickthall.
  final String? secondEdition;
  final int? tafsirId;

  static const minArabic = 22.0, maxArabic = 44.0, minText = 14.0, maxText = 26.0;

  QuranSettings copyWith({
    double? arabicSize,
    double? translationSize,
    double? tafsirSize,
    bool? showTranslation,
    bool? showTransliteration,
    bool? wordByWord,
    String? wordMeaning,
    bool? wordTransliteration,
    String? Function()? secondEdition,
    int? tafsirId,
  }) => QuranSettings(
    arabicSize: arabicSize ?? this.arabicSize,
    translationSize: translationSize ?? this.translationSize,
    tafsirSize: tafsirSize ?? this.tafsirSize,
    showTranslation: showTranslation ?? this.showTranslation,
    showTransliteration: showTransliteration ?? this.showTransliteration,
    wordByWord: wordByWord ?? this.wordByWord,
    wordMeaning: wordMeaning ?? this.wordMeaning,
    wordTransliteration: wordTransliteration ?? this.wordTransliteration,
    secondEdition: secondEdition != null ? secondEdition() : this.secondEdition,
    tafsirId: tafsirId ?? this.tafsirId,
  );

  Map<String, dynamic> toJson() => {
    'arabic_size': arabicSize,
    'translation_size': translationSize,
    'tafsir_size': tafsirSize,
    'show_translation': showTranslation,
    'show_transliteration': showTransliteration,
    'word_by_word': wordByWord,
    'word_meaning': wordMeaning,
    'word_transliteration': wordTransliteration,
    'second_edition': secondEdition,
    'tafsir_id': tafsirId,
  };

  factory QuranSettings.fromJson(Map<String, dynamic> j) {
    double size(String k, double fallback, double min, double max) => ((j[k] as num?)?.toDouble() ?? fallback).clamp(min, max);
    return QuranSettings(
      arabicSize: size('arabic_size', 30, minArabic, maxArabic),
      translationSize: size('translation_size', 17, minText, maxText),
      tafsirSize: size('tafsir_size', 17, minText, maxText),
      showTranslation: j['show_translation'] as bool? ?? true,
      showTransliteration: j['show_transliteration'] as bool? ?? false,
      wordByWord: j['word_by_word'] as bool? ?? false,
      wordMeaning: const {'bn', 'en', 'both'}.contains(j['word_meaning']) ? j['word_meaning'] as String : 'bn',
      wordTransliteration: j['word_transliteration'] as bool? ?? false,
      secondEdition: j['second_edition'] as String?,
      tafsirId: (j['tafsir_id'] as num?)?.toInt(),
    );
  }
}

class QuranSettingsController extends Notifier<QuranSettings> {
  static const _key = 'quran_settings';

  SharedPreferences get _prefs => ref.read(sharedPrefsProvider);

  @override
  QuranSettings build() {
    final raw = _prefs.getString(_key);
    if (raw == null) return const QuranSettings();
    try {
      return QuranSettings.fromJson(jsonDecode(raw) as Map<String, dynamic>);
    } catch (_) {
      return const QuranSettings();
    }
  }

  void update(QuranSettings Function(QuranSettings s) change) {
    state = change(state);
    _prefs.setString(_key, jsonEncode(state.toJson()));
  }
}

final quranSettingsProvider = NotifierProvider<QuranSettingsController, QuranSettings>(QuranSettingsController.new);

/// Private notes keyed by "surah:verse". Kept on this device only and never sent anywhere.
class QuranNotesController extends Notifier<Map<String, String>> {
  static const _key = 'quran_notes';

  SharedPreferences get _prefs => ref.read(sharedPrefsProvider);

  @override
  Map<String, String> build() {
    final raw = _prefs.getString(_key);
    if (raw == null) return const {};
    try {
      return (jsonDecode(raw) as Map<String, dynamic>).map((k, v) => MapEntry(k, '$v'));
    } catch (_) {
      return const {};
    }
  }

  void save(String verseKey, String text) {
    final next = Map<String, String>.of(state);
    if (text.trim().isEmpty) {
      next.remove(verseKey);
    } else {
      next[verseKey] = text.trim();
    }
    state = next;
    _prefs.setString(_key, jsonEncode(next));
  }
}

final quranNotesProvider = NotifierProvider<QuranNotesController, Map<String, String>>(QuranNotesController.new);
