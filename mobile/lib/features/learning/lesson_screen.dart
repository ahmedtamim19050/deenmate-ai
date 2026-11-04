import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:uuid/uuid.dart';

import '../../core/network/api_exception.dart';
import '../../core/utils/dates.dart';
import '../../core/widgets/common.dart';
import '../../shared/providers/providers.dart';
import 'learning_repository.dart';
import 'review_badge.dart';

class LessonScreen extends ConsumerStatefulWidget {
  const LessonScreen({super.key, required this.lessonId});

  final int lessonId;

  @override
  ConsumerState<LessonScreen> createState() => _LessonScreenState();
}

class _LessonScreenState extends ConsumerState<LessonScreen> {
  bool _busy = false;

  Future<void> _complete() async {
    final l = context.l10n;
    if (!ref.read(authProvider).signedIn) {
      context.toast(l.lessonNeedsAccount);
      return;
    }
    setState(() => _busy = true);
    try {
      final r = await ref.read(apiClientProvider).post('/lessons/${widget.lessonId}/complete', {'local_date': ymd(DateTime.now())});
      ref.read(dataVersionProvider.notifier).bump();
      if (mounted) context.toast(ref.read(prefsProvider).showPoints ? l.lessonCompletedPoints(r['points_awarded'] as int) : l.lessonCompleted);
    } on ApiException catch (e) {
      if (mounted) context.toast(e.kind == ApiErrorKind.offline ? l.offlineBody : e.firstError);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final lesson = ref.watch(lessonProvider(widget.lessonId));
    return Scaffold(
      appBar: AppBar(title: Text(lesson.value?['course_title'] as String? ?? l.navLearn)),
      body: AsyncView(
        value: lesson,
        onRetry: () => ref.invalidate(lessonProvider(widget.lessonId)),
        builder: (d) {
          final references = ((d['references'] as List?) ?? const []).cast<String>();
          final quiz = d['quiz'] as Map<String, dynamic>?;
          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Text(d['title'] as String, style: context.text.headlineSmall),
              const SizedBox(height: 8),
              Row(children: [ReviewBadge(status: d['review_status'] as String? ?? 'pending')]),
              if (d['review_status'] != 'approved') ...[
                const SizedBox(height: 6),
                Text(l.awaitingReviewBody, style: context.text.bodySmall),
              ],
              const SizedBox(height: 14),
              SelectableText(d['body'] as String, style: context.text.bodyLarge),
              if (references.isNotEmpty) ...[
                SectionHeader(l.references),
                for (final r in references) Text('• $r', style: context.text.bodyMedium),
              ],
              const SizedBox(height: 20),
              if (d['completed'] == true)
                Row(
                  children: [
                    Icon(Icons.check_circle, color: context.colors.primary),
                    const SizedBox(width: 8),
                    Text(l.lessonCompleted),
                  ],
                )
              else
                FilledButton(onPressed: _busy ? null : _complete, child: Text(l.markLessonComplete)),
              if (quiz != null) ...[
                SectionHeader(quiz['title'] as String),
                _QuizCard(quiz: quiz),
              ],
            ],
          );
        },
      ),
    );
  }
}

class _QuizCard extends ConsumerStatefulWidget {
  const _QuizCard({required this.quiz});

  final Map<String, dynamic> quiz;

  @override
  ConsumerState<_QuizCard> createState() => _QuizCardState();
}

class _QuizCardState extends ConsumerState<_QuizCard> {
  final Map<int, int> _answers = {};
  Map<String, dynamic>? _result;
  bool _busy = false;
  String _attemptKey = const Uuid().v4();

  List<Map<String, dynamic>> get _questions => (widget.quiz['questions'] as List).cast<Map<String, dynamic>>();

  Future<void> _submit() async {
    final l = context.l10n;
    if (!ref.read(authProvider).signedIn) {
      context.push('/login');
      return;
    }
    setState(() => _busy = true);
    try {
      final r = await ref.read(apiClientProvider).post('/quizzes/${widget.quiz['id']}/attempts', {
        'idempotency_key': _attemptKey,
        'local_date': ymd(DateTime.now()),
        'answers': {for (final e in _answers.entries) '${e.key}': e.value},
      });
      ref.read(dataVersionProvider.notifier).bump();
      if (mounted) setState(() => _result = r['data'] as Map<String, dynamic>);
    } on ApiException catch (e) {
      if (mounted) context.toast(e.kind == ApiErrorKind.offline ? l.offlineBody : e.firstError);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final review = {for (final r in (_result?['review'] as List? ?? const []).cast<Map<String, dynamic>>()) r['question_id'] as int: r};
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        for (final q in _questions)
          Card(
            margin: const EdgeInsets.only(bottom: 10),
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 10),
              child: RadioGroup<int>(
                groupValue: _answers[q['id'] as int],
                onChanged: (v) {
                  if (_result == null && v != null) setState(() => _answers[q['id'] as int] = v);
                },
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 16),
                      child: Text(q['question'] as String, style: context.text.titleSmall),
                    ),
                    for (final (i, option) in (q['options'] as List).cast<String>().indexed)
                      RadioListTile<int>(
                        value: i,
                        title: Text(option),
                        secondary: review[q['id']] == null ? null : (review[q['id']]!['correct_index'] == i ? Icon(Icons.check_circle, color: context.colors.primary) : null),
                      ),
                    if (review[q['id']]?['explanation'] != null)
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 16),
                        child: Text(review[q['id']]!['explanation'] as String, style: context.text.bodySmall),
                      ),
                  ],
                ),
              ),
            ),
          ),
        if (_result != null) ...[
          Text(
            _result!['passed'] == true ? l.quizPassed(_result!['score'] as int, _result!['total'] as int) : l.quizKeepLearning(_result!['score'] as int, _result!['total'] as int),
            style: context.text.titleSmall,
          ),
          const SizedBox(height: 8),
          OutlinedButton(
            onPressed: () => setState(() {
              _result = null;
              _answers.clear();
              _attemptKey = const Uuid().v4();
            }),
            child: Text(l.tryAgain),
          ),
        ] else
          FilledButton(onPressed: _busy || _answers.length < _questions.length ? null : _submit, child: Text(l.submitAnswers)),
      ],
    );
  }
}
