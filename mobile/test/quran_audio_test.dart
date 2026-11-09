import 'package:deenmate_ai/features/quran/quran_audio.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('the verse counts cover the whole Quran', () {
    expect(surahVerseCounts, hasLength(114));
    expect(surahVerseCounts.reduce((a, b) => a + b), 6236);
  });

  test('verse numbers match the audio provider', () {
    // Reference values from AlQuran Cloud's ar.alafasy edition.
    const reference = {
      (1, 1): 1,
      (2, 1): 8,
      (2, 255): 262,
      (9, 1): 1236,
      (36, 1): 3706,
      (114, 6): 6236,
    };
    reference.forEach((key, expected) => expect(globalVerseNumber(key.$1, key.$2), expected));
  });

  test('the url points at the verse file', () {
    expect(verseAudioUrl(2, 255), 'https://cdn.islamic.network/quran/audio/128/ar.alafasy/262.mp3');
  });

  test('a verse that does not exist is refused rather than playing the wrong one', () {
    expect(() => globalVerseNumber(1, 8), throwsRangeError);
    expect(() => globalVerseNumber(115, 1), throwsRangeError);
  });
}
