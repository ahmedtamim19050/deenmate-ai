import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/widgets/common.dart';
import 'learning_repository.dart';

class CourseScreen extends ConsumerWidget {
  const CourseScreen({super.key, required this.courseId});

  final int courseId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    return Scaffold(
      appBar: AppBar(title: Text(ref.watch(courseProvider(courseId)).value?.title ?? l.courses)),
      body: AsyncView(
        value: ref.watch(courseProvider(courseId)),
        onRetry: () => ref.invalidate(courseProvider(courseId)),
        builder: (c) => ListView(
          padding: const EdgeInsets.all(16),
          children: [
            if (c.description != null) Text(c.description!, style: context.text.bodyMedium),
            const SizedBox(height: 12),
            if (c.lessons.isEmpty)
              StateMessage(icon: Icons.school_outlined, title: l.noLessonsTitle, compact: true)
            else
              for (final lesson in c.lessons)
                Card(
                  margin: const EdgeInsets.only(bottom: 8),
                  child: ListTile(
                    leading: Icon(lesson['completed'] == true ? Icons.check_circle : Icons.play_circle_outline, color: context.colors.primary),
                    title: Text(lesson['title'] as String, style: context.text.titleSmall),
                    subtitle: Text(l.minutesShort(lesson['duration_minutes'] as int)),
                    onTap: () => context.push('/lessons/${lesson['id']}'),
                  ),
                ),
          ],
        ),
      ),
    );
  }
}
