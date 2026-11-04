import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/notifications/prayer_notification_service.dart';
import '../../core/prayer/prayer_calculator.dart';
import '../../core/sync/outbox.dart';
import '../../core/utils/dates.dart';
import '../../core/widgets/common.dart';
import '../../shared/providers/providers.dart';
import 'salah_models.dart';
import 'salah_repository.dart';

/// Today's prayers. A prayer with no record just shows a Record button: it is never "missed".
class SalahScreen extends ConsumerWidget {
  const SalahScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final locale = Localizations.localeOf(context).languageCode;
    final settings = ref.watch(prayerSettingsProvider);
    final calc = ref.read(calculatorProvider);
    final today = settings.hasLocation ? calc.nowIn(settings) : DateTime.now();
    final date = ymd(DateTime(today.year, today.month, today.day));
    final records = ref.watch(todaySalahProvider(date)).value ?? const <SalahRecord>[];
    final pending = ref.watch(pendingOutboxProvider).value ?? const [];
    final day = settings.hasLocation ? calc.compute(settings, DateTime(today.year, today.month, today.day)) : null;

    bool isPending(String prayer) => pending.any((p) => p.kind == OutboxKind.salah.name && p.payload.contains('"prayer":"$prayer"') && p.payload.contains('"prayer_date":"$date"'));

    return Scaffold(
      appBar: AppBar(
        title: Text(l.salahTitle),
        actions: [
          IconButton(tooltip: l.prayerHistory, onPressed: () => context.push('/salah/history'), icon: const Icon(Icons.history)),
          IconButton(tooltip: l.prayerTemplates, onPressed: () => context.push('/salah/templates'), icon: const Icon(Icons.tune)),
          IconButton(tooltip: l.prayerSettings, onPressed: () => context.push('/salah/settings'), icon: const Icon(Icons.settings_outlined)),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text(longDate(today, locale), style: context.text.titleSmall),
          Text(l.selfReportedNote, style: context.text.bodySmall?.copyWith(color: context.colors.onSurfaceVariant)),
          const SizedBox(height: 12),
          if (!settings.hasLocation)
            Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: Card(
                child: StateMessage(
                  compact: true,
                  icon: Icons.location_off_outlined,
                  title: l.noLocationTitle,
                  body: l.noLocationBody,
                  action: OutlinedButton(onPressed: () => context.push('/salah/settings'), child: Text(l.chooseLocation)),
                ),
              ),
            ),
          for (final prayer in fardPrayers) ...[
            _PrayerCard(
              prayer: prayer,
              time: day == null ? null : clockTime(calc.inZone(settings, day[prayer]), locale),
              record: records.where((r) => r.prayer == prayer).firstOrNull,
              pending: isPending(prayer),
              date: date,
            ),
            const SizedBox(height: 10),
          ],
        ],
      ),
    );
  }
}

class _PrayerCard extends StatelessWidget {
  const _PrayerCard({required this.prayer, required this.time, required this.record, required this.pending, required this.date});

  final String prayer;
  final String? time;
  final SalahRecord? record;
  final bool pending;
  final String date;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final Widget trailing;
    if (record != null) {
      trailing = StatusChip(label: l.rakatCount(record!.totalRakats), icon: Icons.check, tone: StatusTone.success);
    } else if (pending) {
      trailing = StatusChip(label: l.pendingSync, icon: Icons.sync, tone: StatusTone.pending);
    } else {
      trailing = FilledButton.tonal(
        style: FilledButton.styleFrom(minimumSize: const Size(44, 40)),
        onPressed: () => context.push('/salah/confirm/$prayer?date=$date'),
        child: Text(l.record),
      );
    }
    return Card(
      child: ListTile(
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
        title: Text(prayerName(l, prayer), style: context.text.titleMedium),
        subtitle: time == null ? null : Text(time!),
        trailing: trailing,
      ),
    );
  }
}
