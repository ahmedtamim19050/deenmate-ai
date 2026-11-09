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

  test('each reciter has its own id and the default is Alafasy', () {
    expect(reciters.map((r) => r.id).toSet(), hasLength(reciters.length));
    expect(reciterById(null).id, defaultReciter);
    expect(reciterById('removed.reciter').id, defaultReciter);
  });

  test('a reciter published at 64 kbps uses the 64 kbps path', () {
    expect(verseAudioUrl(2, 255, reciter: reciterById('ar.abdurrahmaansudais')), 'https://cdn.islamic.network/quran/audio/64/ar.abdurrahmaansudais/262.mp3');
    expect(verseAudioUrl(2, 255, reciter: reciterById('ar.husary')), 'https://cdn.islamic.network/quran/audio/128/ar.husary/262.mp3');
  });

  test('quality labels follow the measured bitrate rather than the folder', () {
    expect(reciterById('ar.saoodshuraym').pathBitrate, 64);
    expect(reciterById('ar.saoodshuraym').lowQuality, isFalse);
    expect(reciterById('ar.mahermuaiqly').pathBitrate, 128);
    expect(reciterById('ar.mahermuaiqly').lowQuality, isTrue);
    expect(reciters.where((r) => r.longOpenings).map((r) => r.id), ['ar.abdulsamad']);
    expect(reciters, hasLength(15));
  });
}
