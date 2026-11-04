import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/network/api_exception.dart';
import '../../core/utils/dates.dart';
import '../../core/widgets/account_gate.dart';
import '../../core/widgets/common.dart';
import '../../shared/providers/providers.dart';
import '../../shared/repositories/activity_repository.dart';

class HistoryScreen extends ConsumerWidget {
  const HistoryScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    if (!ref.watch(authProvider).signedIn) {
      return Scaffold(appBar: AppBar(title: Text(l.activityHistory)), body: AccountRequired(reason: l.historyNeedsAccount));
    }
    return Scaffold(
      appBar: AppBar(title: Text(l.activityHistory)),
      body: AsyncView(
        value: ref.watch(activityHistoryProvider(null)),
        onRetry: () => ref.invalidate(activityHistoryProvider(null)),
        builder: (items) => items.isEmpty
            ? StateMessage(icon: Icons.history, title: l.noRecordsTitle, body: l.noRecordsBody)
            : ActivityList(items: items),
      ),
    );
  }
}

/// Shared list with edit and remove. Salah has its own screen, which keeps its rakat detail.
class ActivityList extends ConsumerWidget {
  const ActivityList({super.key, required this.items});

  final List<ActivityEntry> items;

  Future<void> _edit(BuildContext context, WidgetRef ref, ActivityEntry e) async {
    final l = context.l10n;
    final controller = TextEditingController(text: '${e.quantity}');
    final value = await showDialog<int>(
      context: context,
      builder: (c) => AlertDialog(
        title: Text(l.editRecord),
        content: TextField(controller: controller, autofocus: true, keyboardType: TextInputType.number, decoration: InputDecoration(labelText: e.unit)),
        actions: [
          TextButton(onPressed: () => Navigator.pop(c), child: Text(l.cancel)),
          FilledButton(onPressed: () => Navigator.pop(c, int.tryParse(controller.text)), child: Text(l.save)),
        ],
      ),
    );
    if (value == null || value < 1) return;
    try {
      await ref.read(activityRepositoryProvider).update(e.id, value);
      if (context.mounted) context.toast(l.recordUpdated);
    } on ApiException catch (err) {
      if (context.mounted) context.toast(err.firstError);
    }
  }

  Future<void> _remove(BuildContext context, WidgetRef ref, ActivityEntry e) async {
    final l = context.l10n;
    final yes = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        title: Text(l.removeRecordTitle),
        content: Text(l.removeRecordBody),
        actions: [
          TextButton(onPressed: () => Navigator.pop(c, false), child: Text(l.cancel)),
          FilledButton(onPressed: () => Navigator.pop(c, true), child: Text(l.remove)),
        ],
      ),
    );
    if (yes != true) return;
    try {
      await ref.read(activityRepositoryProvider).remove(e.id);
      if (context.mounted) context.toast(l.recordRemoved);
    } on ApiException catch (err) {
      if (context.mounted) context.toast(err.firstError);
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final locale = Localizations.localeOf(context).languageCode;
    final showPoints = ref.watch(prefsProvider).showPoints;
    return ListView.separated(
      padding: const EdgeInsets.all(16),
      itemCount: items.length,
      separatorBuilder: (_, _) => const SizedBox(height: 8),
      itemBuilder: (_, i) {
        final e = items[i];
        final editable = e.category != 'salah' && e.category != 'learning' && e.category != 'quiz';
        return Card(
          child: ListTile(
            title: Text(e.typeName, style: context.text.titleSmall),
            subtitle: Text(
              [
                '${e.quantity} ${e.unit}',
                shortDate(DateTime.parse(e.localDate), locale),
                if (showPoints) l.pointsAwarded(e.points),
              ].join(' · '),
            ),
            trailing: !editable
                ? null
                : PopupMenuButton<String>(
                    onSelected: (v) => v == 'edit' ? _edit(context, ref, e) : _remove(context, ref, e),
                    itemBuilder: (_) => [
                      PopupMenuItem(value: 'edit', child: Text(l.editRecord)),
                      PopupMenuItem(value: 'remove', child: Text(l.remove)),
                    ],
                  ),
          ),
        );
      },
    );
  }
}
