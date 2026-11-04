import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/notifications/prayer_notification_service.dart';
import '../../core/utils/dates.dart';
import '../../core/widgets/account_gate.dart';
import '../../core/widgets/common.dart';
import '../../shared/providers/providers.dart';
import 'rakat_selection_screen.dart';
import 'salah_models.dart';
import 'salah_repository.dart';

final salahHistoryProvider = FutureProvider<List<SalahRecord>>((ref) async {
  ref.watch(dataVersionProvider);
  return ref.read(salahRepositoryProvider).history();
});

class SalahHistoryScreen extends ConsumerWidget {
  const SalahHistoryScreen({super.key});

  Future<void> _remove(BuildContext context, WidgetRef ref, SalahRecord r) async {
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
      await ref.read(salahRepositoryProvider).reverse(r.id);
      if (context.mounted) context.toast(l.recordRemoved);
    } catch (e) {
      if (context.mounted) context.toast(l.errorBody);
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final locale = Localizations.localeOf(context).languageCode;
    final showPoints = ref.watch(prefsProvider).showPoints;
    if (!ref.watch(authProvider).signedIn) {
      return Scaffold(appBar: AppBar(title: Text(l.prayerHistory)), body: AccountRequired(reason: l.historyNeedsAccount));
    }
    final history = ref.watch(salahHistoryProvider);
    return Scaffold(
      appBar: AppBar(title: Text(l.prayerHistory)),
      body: AsyncView(
        value: history,
        onRetry: () => ref.invalidate(salahHistoryProvider),
        builder: (items) => items.isEmpty
            ? StateMessage(icon: Icons.mosque_outlined, title: l.noRecordsTitle, body: l.noRecordsBody)
            : ListView.separated(
                padding: const EdgeInsets.all(16),
                itemCount: items.length,
                separatorBuilder: (_, _) => const SizedBox(height: 10),
                itemBuilder: (_, i) {
                  final r = items[i];
                  return Card(
                    child: Padding(
                      padding: const EdgeInsets.fromLTRB(16, 12, 6, 8),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Expanded(child: Text('${prayerName(l, r.prayer)} · ${shortDate(DateTime.parse(r.date), locale)}', style: context.text.titleSmall)),
                              IconButton(
                                tooltip: l.editRecord,
                                onPressed: () => Navigator.of(context).push(MaterialPageRoute<bool>(builder: (_) => RakatSelectionScreen(prayer: r.prayer, date: r.date, existing: r))),
                                icon: const Icon(Icons.edit_outlined),
                              ),
                              IconButton(tooltip: l.remove, onPressed: () => _remove(context, ref, r), icon: const Icon(Icons.delete_outline)),
                            ],
                          ),
                          Text(r.items.map((e) => '${e.label} ${e.rakats}').join(' · ')),
                          const SizedBox(height: 4),
                          Text(
                            [
                              l.rakatCount(r.totalRakats),
                              if (r.confirmedAt != null) l.confirmedAt(clockTime(r.confirmedAt!.toLocal(), locale)),
                              if (showPoints) l.pointsAwarded(r.points),
                            ].join(' · '),
                            style: context.text.bodySmall,
                          ),
                        ],
                      ),
                    ),
                  );
                },
              ),
      ),
    );
  }
}
