import 'package:deenmate_ai/features/quran/quran_content_api.dart';
import 'package:deenmate_ai/features/quran/quran_settings.dart';
import 'package:deenmate_ai/features/quran/word_by_word.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('tafsir html', () {
    test('paragraphs and headings become separate blocks', () {
      expect(htmlToPlainText('<h2>The Virtue</h2><p>First.</p><p>Second<br>line.</p>'), 'The Virtue\n\nFirst.\n\nSecond\nline.');
    });

    test('entities are decoded and other tags removed', () {
      expect(htmlToPlainText('<span class="x">A &amp; B &quot;c&quot; &#2453; &#x0995;</span>'), 'A & B "c" ক ক');
    });

    test('an empty tafsir stays empty, so the grouped-verse message shows', () {
      expect(htmlToPlainText(''), '');
      expect(htmlToPlainText('<p> </p>'), '');
    });
  });

  group('quran settings', () {
    test('a round trip keeps every choice', () {
      const s = QuranSettings(
        arabicSize: 34,
        translationSize: 20,
        tafsirSize: 19,
        showTranslation: false,
        showTransliteration: true,
        wordByWord: true,
        wordMeaning: 'both',
        wordTransliteration: true,
        secondEdition: 'en.pickthall',
        tafsirId: 165,
      );
      final back = QuranSettings.fromJson(s.toJson());
      expect(back.toJson(), s.toJson());
    });

    test('out-of-range or unknown saved values fall back safely', () {
      final s = QuranSettings.fromJson({'arabic_size': 500, 'translation_size': 1, 'word_meaning': 'xx'});
      expect(s.arabicSize, QuranSettings.maxArabic);
      expect(s.translationSize, QuranSettings.minText);
      expect(s.wordMeaning, 'bn');
    });

    test('the second translation can be cleared', () {
      const s = QuranSettings(secondEdition: 'en.pickthall');
      expect(s.copyWith(secondEdition: () => null).secondEdition, isNull);
      expect(s.copyWith(wordByWord: true).secondEdition, 'en.pickthall');
    });
  });

  test('word meaning follows the chosen language', () {
    const w = QuranWord(id: 1, verse: 2, position: 1, arabic: 'ٱلْحَمْدُ', bangla: 'সকল প্রশংসা', english: 'All praises');
    expect(wordMeaning(w, 'bn'), 'সকল প্রশংসা');
    expect(wordMeaning(w, 'en'), 'All praises');
    expect(wordMeaning(w, 'both'), 'সকল প্রশংসা · All praises');
  });
}
