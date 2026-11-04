import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/widgets/account_gate.dart';
import '../../core/widgets/common.dart';
import '../../core/widgets/week_chart.dart';
import '../../shared/providers/providers.dart';
import '../home/home_repository.dart';

final progressProvider = FutureProvider<Map<String, dynamic>>((ref) async {
  ref.watch(dataVersionProvider);
  return (await ref.read(apiClientProvider).get('/progress', query: {'days': 7}))['data'] as Map<String, dynamic>;
});

/// A private view of recorded activity. It compares with nobody and judges nothing.
class ProgressScreen extends ConsumerWidget {
  const ProgressScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final showPoints = ref.watch(prefsProvider).showPoints;
    if (!ref.watch(authProvider).signedIn) {
      return Scaffold(appBar: AppBar(title: Text(l.yourProgress)), body: AccountRequired(reason: l.historyNeedsAccount));
    }
    return Scaffold(
      appBar: AppBar(title: Text(l.yourProgress)),
      body: AsyncView(
        value: ref.watch(progressProvider),
        onRetry: () => ref.invalidate(progressProvider),
        builder: (data) {
          final series = (data['series'] as List).map((e) => DaySummary.fromJson(e as Map<String, dynamic>)).toList();
          final totals = data['totals'] as Map<String, dynamic>;
          Widget chart(String title, int Function(DaySummary) read) => Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: Card(
              child: Padding(
                padding: const EdgeInsets.all(14),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(title, style: context.text.titleSmall),
                    const SizedBox(height: 10),
                    WeekChart(days: series, value: read),
                  ],
                ),
              ),
            ),
          );
          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Text(l.keepBuilding, style: context.text.titleMedium),
              Text(l.oneStepAtATime, style: context.text.bodyMedium?.copyWith(color: context.colors.onSurfaceVariant)),
              const SizedBox(height: 14),
              chart(l.dailyActivities, (d) => d.activityCount),
              chart(l.quranPages, (d) => d.quranPages),
              chart(l.dhikrCount, (d) => d.dhikrCount),
              Card(
                child: Column(
                  children: [
                    ListTile(title: Text(l.salahRecorded), trailing: Text('${totals['salah_records']}')),
                    ListTile(title: Text(l.fastingDays), trailing: Text('${totals['fasting_days']}')),
                    ListTile(title: Text(l.lessonsCompleted), trailing: Text('${totals['lessons_completed']}')),
                    ListTile(title: Text(l.goodDeedsTitle), trailing: Text('${totals['good_deeds']}')),
                    if (showPoints) ListTile(title: Text(l.deenPoints), trailing: Text('${totals['points_earned']}')),
                  ],
                ),
              ),
              const SizedBox(height: 10),
              Text(l.progressNote, style: context.text.bodySmall),
            ],
          );
        },
      ),
    );
  }
}
