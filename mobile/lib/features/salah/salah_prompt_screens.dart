import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/notifications/prayer_notification_service.dart';
import '../../core/utils/dates.dart';
import '../../core/widgets/common.dart';
import '../../shared/providers/providers.dart';
import 'salah_repository.dart';

/// Opened by tapping the reminder. It asks a question and records nothing.
class SalahPromptScreen extends ConsumerWidget {
  const SalahPromptScreen({super.key, required this.prayer, this.date});

  final String prayer;
  final String? date;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final d = date ?? ymd(DateTime.now());
    return Scaffold(
      appBar: AppBar(),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const Spacer(),
              Icon(Icons.mosque_outlined, size: 64, color: context.colors.primary),
              const SizedBox(height: 20),
              Text(l.notifHavePrayed(prayerName(l, prayer)), textAlign: TextAlign.center, style: context.text.headlineSmall),
              const Spacer(),
              FilledButton(onPressed: () => context.pushReplacement('/salah/confirm/$prayer?date=$d'), child: Text(l.notifYesPrayed)),
              const SizedBox(height: 10),
              OutlinedButton(onPressed: () => context.pushReplacement('/salah/remind/$prayer?date=$d'), child: Text(l.notifRemindLater)),
            ],
          ),
        ),
      ),
    );
  }
}

/// Adds one more reminder for this prayer. The rest of the schedule is left exactly as it is.
class RemindLaterScreen extends ConsumerWidget {
  const RemindLaterScreen({super.key, required this.prayer, this.date});

  final String prayer;
  final String? date;

  Future<void> _set(BuildContext context, WidgetRef ref, int minutes) async {
    final l = context.l10n;
    final config = ref.read(prayerConfigProvider);
    await ref.read(notificationServiceProvider).remindLater(
      prayer: prayer,
      date: date ?? ymd(DateTime.now()),
      after: Duration(minutes: minutes),
      pref: config.notificationPrefs[prayer]!,
      localeCode: ref.read(localeProvider).languageCode,
    );
    if (!context.mounted) return;
    context.toast(l.remindSet(minutes));
    context.canPop() ? context.pop() : context.go('/home');
  }

  Future<void> _custom(BuildContext context, WidgetRef ref) async {
    final l = context.l10n;
    final controller = TextEditingController();
    final minutes = await showDialog<int>(
      context: context,
      builder: (c) => AlertDialog(
        title: Text(l.remindCustom),
        content: TextField(
          controller: controller,
          autofocus: true,
          keyboardType: TextInputType.number,
          decoration: InputDecoration(labelText: l.minutes, helperText: l.remindCustomHelp),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(c), child: Text(l.cancel)),
          FilledButton(
            onPressed: () {
              final v = int.tryParse(controller.text);
              if (v != null && v >= 1 && v <= 720) Navigator.pop(c, v);
            },
            child: Text(l.save),
          ),
        ],
      ),
    );
    if (minutes != null && context.mounted) await _set(context, ref, minutes);
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    return Scaffold(
      appBar: AppBar(title: Text(l.notifRemindLater)),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text(l.remindQuestion(prayerName(l, prayer)), style: context.text.titleMedium),
          const SizedBox(height: 12),
          Card(
            child: Column(
              children: [
                for (final m in const [5, 10, 15]) NavTile(icon: Icons.schedule, title: l.inMinutes(m), onTap: () => _set(context, ref, m)),
                NavTile(icon: Icons.more_time, title: l.remindCustom, onTap: () => _custom(context, ref)),
              ],
            ),
          ),
          const SizedBox(height: 12),
          Text(l.remindNote, style: context.text.bodySmall),
        ],
      ),
    );
  }
}
