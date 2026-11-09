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

class Reciter {
  const Reciter(this.id, this.name, this.arabicName, {this.pathBitrate = 128, this.lowQuality = false, this.longOpenings = false});

  /// The AlQuran Cloud edition identifier.
  final String id;
  final String name;
  final String arabicName;

  /// The bitrate folder the CDN publishes this reciter under.
  final int pathBitrate;

  /// The files themselves are encoded at about 64 kbps, whatever folder they are in.
  final bool lowQuality;

  /// The first verse of each surah is far longer than the verse itself, so it likely
  /// contains extra recitation. Its Al-Fatihah 1:1 file is not used as the basmala.
  final bool longOpenings;
}

/// Each file was downloaded and its MP3 frames decoded on 2026-11-09. Quality labels follow
/// the measured bitrate, not the folder name.
const reciters = <Reciter>[
  Reciter('ar.alafasy', 'Mishary Rashid Alafasy', 'مشاري العفاسي'),
  Reciter('ar.husary', 'Mahmoud Khalil Al-Husary', 'محمود خليل الحصري'),
  Reciter('ar.husarymujawwad', 'Al-Husary (Mujawwad)', 'الحصري (المجود)'),
  Reciter('ar.hudhaify', 'Ali Al-Hudhaify', 'علي بن عبدالرحمن الحذيفي'),
  Reciter('ar.shaatree', 'Abu Bakr Ash-Shatri', 'أبو بكر الشاطري'),
  Reciter('ar.ahmedajamy', 'Ahmed Al-Ajamy', 'أحمد بن علي العجمي'),
  Reciter('ar.saoodshuraym', 'Saud Ash-Shuraim', 'سعود الشريم', pathBitrate: 64),
  Reciter('ar.mahermuaiqly', 'Maher Al-Muaiqly', 'ماهر المعيقلي', lowQuality: true),
  Reciter('ar.muhammadayyoub', 'Muhammad Ayyoub', 'محمد أيوب', lowQuality: true),
  Reciter('ar.muhammadjibreel', 'Muhammad Jibreel', 'محمد جبريل', lowQuality: true),
  Reciter('ar.abdurrahmaansudais', 'Abdur Rahman As-Sudais', 'عبدالرحمن السديس', pathBitrate: 64, lowQuality: true),
  Reciter('ar.abdullahbasfar', 'Abdullah Basfar', 'عبد الله بصفر', pathBitrate: 64, lowQuality: true),
  Reciter('ar.hanirifai', 'Hani Ar-Rifai', 'هاني الرفاعي', pathBitrate: 64, lowQuality: true),
  Reciter('ar.aymanswoaid', 'Ayman Suwaid', 'أيمن سويد', pathBitrate: 64, lowQuality: true),
  Reciter('ar.abdulsamad', 'Abdul Basit Abdus Samad', 'عبدالباسط عبدالصمد', pathBitrate: 64, lowQuality: true, longOpenings: true),
];

const defaultReciter = 'ar.alafasy';

/// An unknown or removed id falls back to the default rather than failing.
Reciter reciterById(String? id) => reciters.firstWhere((r) => r.id == id, orElse: () => reciters.first);

String verseAudioUrl(int surah, int verse, {Reciter reciter = const Reciter(defaultReciter, '', '')}) =>
    'https://cdn.islamic.network/quran/audio/${reciter.pathBitrate}/${reciter.id}/${globalVerseNumber(surah, verse)}.mp3';
