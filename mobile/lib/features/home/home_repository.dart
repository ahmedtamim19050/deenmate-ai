import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../shared/providers/providers.dart';
import '../../shared/repositories/cached_fetch.dart';

class DaySummary {
  const DaySummary({
    required this.date,
    required this.activityCount,
    required this.salahCount,
    required this.quranPages,
    required this.dhikrCount,
    required this.goodDeeds,
    required this.lessons,
    required this.fasted,
    required this.points,
  });

  final String date;
  final int activityCount;
  final int salahCount;
  final int quranPages;
  final int dhikrCount;
  final int goodDeeds;
  final int lessons;
  final bool fasted;
  final int points;

  factory DaySummary.fromJson(Map<String, dynamic> j) => DaySummary(
    date: j['date'] as String? ?? '',
    activityCount: (j['activity_count'] as num? ?? 0).toInt(),
    salahCount: (j['salah_count'] as num? ?? 0).toInt(),
    quranPages: (j['quran_pages'] as num? ?? 0).toInt(),
    dhikrCount: (j['dhikr_count'] as num? ?? 0).toInt(),
    goodDeeds: (j['good_deeds'] as num? ?? 0).toInt(),
    lessons: (j['lessons_completed'] as num? ?? 0).toInt(),
    fasted: j['fasted'] as bool? ?? false,
    points: (j['points_earned'] as num? ?? 0).toInt(),
  );
}

class Dashboard {
  const Dashboard(this.raw);

  final Map<String, dynamic> raw;

  int get balance => (raw['points']['balance'] as num).toInt();
  int get earnedToday => (raw['points']['earned_today'] as num).toInt();
  DaySummary get today => DaySummary.fromJson(raw['today'] as Map<String, dynamic>);
  List<DaySummary> get weekly => (raw['weekly'] as List).map((e) => DaySummary.fromJson(e as Map<String, dynamic>)).toList();
  List<Map<String, dynamic>> get goals => (raw['goals'] as List).cast<Map<String, dynamic>>();
  List<Map<String, dynamic>> get recent => (raw['recent'] as List).cast<Map<String, dynamic>>();
  Map<String, dynamic>? get nextLesson => raw['next_lesson'] as Map<String, dynamic>?;
  Set<String> get salahToday => (raw['salah_today'] as List).cast<String>().toSet();
}

final dashboardProvider = FutureProvider<Dashboard?>((ref) async {
  ref.watch(dataVersionProvider);
  if (!ref.watch(authProvider).signedIn) return null;
  final data = await cachedFetch(ref.read(databaseProvider), 'user:dashboard', () => ref.read(apiClientProvider).get('/dashboard'));
  return Dashboard(data['data'] as Map<String, dynamic>);
});
