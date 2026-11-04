import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/network/api_exception.dart';
import '../../core/widgets/account_gate.dart';
import '../../core/widgets/common.dart';
import '../../shared/providers/providers.dart';

class Goal {
  const Goal(this.id, this.type, this.title, this.description, this.activityKey, this.target, this.period, this.status, this.current);

  final int id;
  final String type;
  final String title;
  final String? description;
  final String? activityKey;
  final int target;
  final String period;
  final String status;
  final int? current;

  factory Goal.fromJson(Map<String, dynamic> j) => Goal(
    (j['id'] as num).toInt(),
    j['type'] as String,
    j['title'] as String,
    j['description'] as String?,
    j['activity_type_key'] as String?,
    (j['target_value'] as num).toInt(),
    j['period'] as String,
    j['status'] as String,
    (j['current_value'] as num?)?.toInt(),
  );
}

final goalsProvider = FutureProvider<List<Goal>>((ref) async {
  ref.watch(dataVersionProvider);
  final data = await ref.read(apiClientProvider).get('/goals');
  return (data['data'] as List).map((e) => Goal.fromJson(e as Map<String, dynamic>)).toList();
});

class GoalsScreen extends ConsumerWidget {
  const GoalsScreen({super.key});

  Future<void> _setStatus(BuildContext context, WidgetRef ref, Goal g, String status) async {
    try {
      await ref.read(apiClientProvider).put('/goals/${g.id}', {'status': status});
      ref.read(dataVersionProvider.notifier).bump();
    } on ApiException catch (e) {
      if (context.mounted) context.toast(e.firstError);
    }
  }

  Future<void> _editor(BuildContext context, WidgetRef ref, [Goal? g]) async {
    await showModalBottomSheet<void>(context: context, isScrollControlled: true, useSafeArea: true, builder: (_) => _GoalEditor(goal: g));
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    if (!ref.watch(authProvider).signedIn) {
      return Scaffold(appBar: AppBar(title: Text(l.personalGoals)), body: AccountRequired(reason: l.goalsNeedAccount));
    }
    return Scaffold(
      appBar: AppBar(title: Text(l.personalGoals)),
      floatingActionButton: FloatingActionButton.extended(onPressed: () => _editor(context, ref), icon: const Icon(Icons.add), label: Text(l.newGoal)),
      body: AsyncView(
        value: ref.watch(goalsProvider),
        onRetry: () => ref.invalidate(goalsProvider),
        builder: (goals) {
          final visible = goals.where((g) => g.status != 'archived').toList();
          if (visible.isEmpty) return StateMessage(icon: Icons.flag_outlined, title: l.noGoalsTitle, body: l.noGoalsBody);
          return ListView.separated(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 90),
            itemCount: visible.length,
            separatorBuilder: (_, _) => const SizedBox(height: 10),
            itemBuilder: (_, i) {
              final g = visible[i];
              return Card(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(16, 10, 6, 12),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(child: Text(g.title, style: context.text.titleSmall)),
                          if (g.status == 'paused') StatusChip(label: l.goalPaused, tone: StatusTone.pending),
                          if (g.status == 'completed') StatusChip(label: l.goalCompleted, tone: StatusTone.success, icon: Icons.check),
                          PopupMenuButton<String>(
                            onSelected: (v) => switch (v) {
                              'edit' => _editor(context, ref, g),
                              _ => _setStatus(context, ref, g, v),
                            },
                            itemBuilder: (_) => [
                              PopupMenuItem(value: 'edit', child: Text(l.edit)),
                              if (g.status == 'active') PopupMenuItem(value: 'paused', child: Text(l.pause)),
                              if (g.status == 'paused') PopupMenuItem(value: 'active', child: Text(l.resume)),
                              if (g.status != 'completed') PopupMenuItem(value: 'completed', child: Text(l.markComplete)),
                              PopupMenuItem(value: 'archived', child: Text(l.archive)),
                            ],
                          ),
                        ],
                      ),
                      if (g.description != null) Text(g.description!, style: context.text.bodySmall),
                      if (g.current != null) ...[
                        const SizedBox(height: 8),
                        LinearProgressIndicator(value: (g.current! / g.target).clamp(0.0, 1.0), minHeight: 7, borderRadius: BorderRadius.circular(4)),
                        const SizedBox(height: 4),
                        Text('${g.current} / ${g.target}', style: context.text.bodySmall),
                      ] else
                        Text(l.goalTarget(g.target), style: context.text.bodySmall),
                    ],
                  ),
                ),
              );
            },
          );
        },
      ),
    );
  }
}

class _GoalEditor extends ConsumerStatefulWidget {
  const _GoalEditor({this.goal});

  final Goal? goal;

  @override
  ConsumerState<_GoalEditor> createState() => _GoalEditorState();
}

class _GoalEditorState extends ConsumerState<_GoalEditor> {
  late final _title = TextEditingController(text: widget.goal?.title ?? '');
  late final _target = TextEditingController(text: '${widget.goal?.target ?? 1}');
  late String _type = widget.goal?.type ?? 'quran';
  late String _period = widget.goal?.period ?? 'daily';
  bool _busy = false;

  static const _keyFor = {'quran': 'quran_page', 'dhikr': 'dhikr_count', 'learning': 'lesson_complete'};

  @override
  void dispose() {
    _title.dispose();
    _target.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    final l = context.l10n;
    final target = int.tryParse(_target.text);
    if (_title.text.trim().isEmpty || target == null || target < 1) {
      context.toast(l.goalInvalid);
      return;
    }
    setState(() => _busy = true);
    final body = {'type': _type, 'title': _title.text.trim(), 'target_value': target, 'period': _period, 'activity_type_key': _keyFor[_type]};
    try {
      final api = ref.read(apiClientProvider);
      widget.goal == null ? await api.post('/goals', body) : await api.put('/goals/${widget.goal!.id}', body);
      ref.read(dataVersionProvider.notifier).bump();
      if (mounted) Navigator.pop(context);
    } on ApiException catch (e) {
      if (mounted) context.toast(e.firstError);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final types = {'quran': l.goalTypeQuran, 'dhikr': l.goalTypeDhikr, 'learning': l.goalTypeLearning, 'habit': l.goalTypeHabit, 'custom': l.goalTypeCustom};
    final periods = {'daily': l.periodDaily, 'weekly': l.periodWeekly, 'total': l.periodTotal};
    return Padding(
      padding: EdgeInsets.fromLTRB(20, 20, 20, 20 + MediaQuery.viewInsetsOf(context).bottom),
      child: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(widget.goal == null ? l.newGoal : l.edit, style: context.text.titleLarge),
            const SizedBox(height: 14),
            TextField(controller: _title, decoration: InputDecoration(labelText: l.goalTitle)),
            const SizedBox(height: 10),
            Wrap(
              spacing: 8,
              children: [for (final e in types.entries) ChoiceChip(label: Text(e.value), selected: _type == e.key, onSelected: (_) => setState(() => _type = e.key))],
            ),
            const SizedBox(height: 10),
            TextField(controller: _target, keyboardType: TextInputType.number, decoration: InputDecoration(labelText: l.target)),
            const SizedBox(height: 10),
            Wrap(
              spacing: 8,
              children: [for (final e in periods.entries) ChoiceChip(label: Text(e.value), selected: _period == e.key, onSelected: (_) => setState(() => _period = e.key))],
            ),
            const SizedBox(height: 16),
            FilledButton(onPressed: _busy ? null : _save, child: Text(l.save)),
          ],
        ),
      ),
    );
  }
}
