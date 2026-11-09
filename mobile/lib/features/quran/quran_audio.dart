/// Verses in each surah, in order. The audio CDN numbers verses 1 to 6236 across the whole Quran.
const surahVerseCounts = <int>[
  7, 286, 200, 176, 120, 165, 206, 75, 129, 109, 123, 111, 43, 52, 99, 128, 111, 110, 98, 135, 112, 78, 118, 64,
  77, 227, 93, 88, 69, 60, 34, 30, 73, 54, 45, 83, 182, 88, 75, 85, 54, 53, 89, 59, 37, 35, 38, 29, 18, 45, 60, 49,
  62, 55, 78, 96, 29, 22, 24, 13, 14, 11, 11, 18, 12, 12, 30, 52, 52, 44, 28, 28, 20, 56, 40, 31, 50, 40, 46, 42,
  29, 19, 36, 25, 22, 17, 19, 26, 30, 20, 15, 21, 11, 8, 8, 19, 5, 8, 8, 11, 11, 8, 3, 9, 5, 4, 7, 3, 6, 3, 5, 4,
  5, 6,
];

/// The verse's position in the whole Quran, such as 262 for 2:255.
int globalVerseNumber(int surah, int verse) {
  if (surah < 1 || surah > surahVerseCounts.length) throw RangeError.range(surah, 1, surahVerseCounts.length, 'surah');
  if (verse < 1 || verse > surahVerseCounts[surah - 1]) throw RangeError.range(verse, 1, surahVerseCounts[surah - 1], 'verse');
  return surahVerseCounts.take(surah - 1).fold<int>(0, (sum, count) => sum + count) + verse;
}

String verseAudioUrl(int surah, int verse) =>
    'https://cdn.islamic.network/quran/audio/128/ar.alafasy/${globalVerseNumber(surah, verse)}.mp3';
