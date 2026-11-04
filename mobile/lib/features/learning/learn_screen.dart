import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/ads/ad_service.dart';
import '../../core/widgets/common.dart';
import '../../shared/models/app_settings.dart';
import '../../shared/providers/providers.dart';
import 'learning_repository.dart';
import 'review_badge.dart';

class LearnScreen extends ConsumerStatefulWidget {
  const LearnScreen({super.key});

  @override
  ConsumerState<LearnScreen> createState() => _LearnScreenState();
}

class _LearnScreenState extends ConsumerState<LearnScreen> {
  String? _category;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final categories = {'beginner': l.catBeginner, 'basics': l.catBasics, 'quran': l.catQuran, 'seerah': l.catSeerah, 'history': l.catHistory};
    final remote = ref.watch(publicSettingsProvider).value;
    final ads = ref.watch(adServiceProvider);
    return Scaffold(
      appBar: AppBar(
        title: Text(l.navLearn),
        actions: [IconButton(tooltip: l.bookmarks, onPressed: () => context.push('/bookmarks'), icon: const Icon(Icons.bookmark_outline))],
      ),
      bottomNavigationBar: (remote?.learnBannerEnabled ?? false) ? BannerAdStrip(service: ads) : null,
      body: RefreshIndicator(
        onRefresh: () async => ref.invalidate(coursesProvider),
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Row(
              children: [
                Expanded(
                  child: Card(child: _LibraryTile(icon: Icons.menu_book, title: l.quranTitle, onTap: () => context.push('/quran'))),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Card(child: _LibraryTile(icon: Icons.library_books_outlined, title: l.hadithTitle, onTap: () => context.push('/hadith'))),
                ),
              ],
            ),
            SectionHeader(l.courses),
            SizedBox(
              height: 42,
              child: ListView(
                scrollDirection: Axis.horizontal,
                children: [
                  Padding(
                    padding: const EdgeInsetsDirectional.only(end: 8),
                    child: ChoiceChip(label: Text(l.catAll), selected: _category == null, onSelected: (_) => setState(() => _category = null)),
                  ),
                  for (final e in categories.entries)
                    Padding(
                      padding: const EdgeInsetsDirectional.only(end: 8),
                      child: ChoiceChip(label: Text(e.value), selected: _category == e.key, onSelected: (_) => setState(() => _category = e.key)),
                    ),
                ],
              ),
            ),
            const SizedBox(height: 10),
            ref.watch(coursesProvider).when(
              loading: () => const Padding(padding: EdgeInsets.all(32), child: Center(child: CircularProgressIndicator())),
              error: (e, _) => ErrorState(error: e, onRetry: () => ref.invalidate(coursesProvider)),
              data: (all) {
                final courses = all.where((c) => _category == null || c.category == _category).toList();
                if (courses.isEmpty) return StateMessage(icon: Icons.school_outlined, title: l.noCoursesTitle, body: l.noCoursesBody, compact: true);
                return Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    for (var i = 0; i < courses.length; i++) ...[
                      _CourseCard(course: courses[i]),
                      const SizedBox(height: 10),
                      if (i == 1 && (remote?.learnAdEnabled ?? true)) NativeAdCard(service: ads, label: l.sponsored),
                    ],
                  ],
                );
              },
            ),
          ],
        ),
      ),
    );
  }
}

class _LibraryTile extends StatelessWidget {
  const _LibraryTile({required this.icon, required this.title, required this.onTap});

  final IconData icon;
  final String title;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => InkWell(
    borderRadius: BorderRadius.circular(18),
    onTap: onTap,
    child: Padding(
      padding: const EdgeInsets.symmetric(vertical: 18, horizontal: 8),
      child: Column(
        children: [
          Icon(icon, color: context.colors.primary, size: 30),
          const SizedBox(height: 8),
          Text(title, style: context.text.titleSmall),
        ],
      ),
    ),
  );
}

class _CourseCard extends StatelessWidget {
  const _CourseCard({required this.course});

  final CourseSummary course;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () => context.push('/courses/${course.id}'),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(course.title, style: context.text.titleSmall),
              if (course.description != null) ...[
                const SizedBox(height: 4),
                Text(course.description!, style: context.text.bodySmall),
              ],
              const SizedBox(height: 10),
              if (course.lessonCount > 0) LinearProgressIndicator(value: course.completed / course.lessonCount, minHeight: 5, borderRadius: BorderRadius.circular(3)),
              const SizedBox(height: 8),
              Row(
                children: [
                  Expanded(child: Text(l.lessonsProgress(course.completed, course.lessonCount), style: context.text.bodySmall)),
                  ReviewBadge(status: course.reviewStatus),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
