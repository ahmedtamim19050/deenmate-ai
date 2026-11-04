import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../shared/providers/providers.dart';
import '../../shared/repositories/cached_fetch.dart';

class CourseSummary {
  const CourseSummary(this.id, this.title, this.description, this.category, this.reviewStatus, this.lessonCount, this.completed, this.lessons);

  final int id;
  final String title;
  final String? description;
  final String category;
  final String reviewStatus;
  final int lessonCount;
  final int completed;
  final List<Map<String, dynamic>> lessons;

  factory CourseSummary.fromJson(Map<String, dynamic> j) => CourseSummary(
    (j['id'] as num).toInt(),
    j['title'] as String,
    j['description'] as String?,
    j['category'] as String,
    j['review_status'] as String? ?? 'pending',
    (j['lesson_count'] as num? ?? 0).toInt(),
    (j['completed_lessons'] as num? ?? 0).toInt(),
    ((j['lessons'] as List?) ?? const []).cast<Map<String, dynamic>>(),
  );
}

/// Public content, cached so it stays readable offline.
final coursesProvider = FutureProvider<List<CourseSummary>>((ref) async {
  ref.watch(dataVersionProvider);
  final data = await cachedFetch(ref.read(databaseProvider), 'courses', () => ref.read(apiClientProvider).get('/courses'));
  return (data['data'] as List).map((e) => CourseSummary.fromJson(e as Map<String, dynamic>)).toList();
});

final courseProvider = FutureProvider.family<CourseSummary, int>((ref, id) async {
  ref.watch(dataVersionProvider);
  final data = await cachedFetch(ref.read(databaseProvider), 'course:$id', () => ref.read(apiClientProvider).get('/courses/$id'));
  return CourseSummary.fromJson(data['data'] as Map<String, dynamic>);
});

final lessonProvider = FutureProvider.family<Map<String, dynamic>, int>((ref, id) async {
  ref.watch(dataVersionProvider);
  final data = await cachedFetch(ref.read(databaseProvider), 'lesson:$id', () => ref.read(apiClientProvider).get('/lessons/$id'));
  return data['data'] as Map<String, dynamic>;
});
