import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/notifications/prayer_notification_service.dart';
import '../../core/prayer/prayer_settings.dart';
import '../../core/widgets/common.dart';
import 'prayer_settings_widgets.dart';
import 'salah_repository.dart';

class PrayerSettingsScreen extends ConsumerWidget {
  const PrayerSettingsScreen({super.key});

  Future<void> _save(WidgetRef ref, PrayerSettings s) async {
    await ref.read(prayerConfigProvider).saveSettings(s);
    ref.read(prayerSettingsVersionProvider.notifier).bump();
    await ref.read(notificationSchedulerProvider).run();
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final s = ref.watch(prayerSettingsProvider);
    return Scaffold(
      appBar: AppBar(title: Text(l.prayerSettings)),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text(l.location, style: context.text.titleMedium),
          const SizedBox(height: 10),
          LocationPicker(settings: s, onChanged: (v) => _save(ref, v)),
          SectionHeader(l.calculationMethod),
          Text(l.onboardMethodBody, style: context.text.bodySmall),
          const SizedBox(height: 8),
          MethodPicker(settings: s, onChanged: (v) => _save(ref, v)),
          SectionHeader(l.highLatitudeRule),
          Text(l.highLatitudeHelp, style: context.text.bodySmall),
          const SizedBox(height: 8),
          RadioGroup<String>(
            groupValue: s.highLatitudeRule,
            onChanged: (v) {
              if (v != null) _save(ref, s.copyWith(highLatitudeRule: v));
            },
            child: Card(
              child: Column(
                children: [
                  RadioListTile<String>(value: 'angle_based', title: Text(l.highLatAngle)),
                  RadioListTile<String>(value: 'one_seventh', title: Text(l.highLatSeventh)),
                  RadioListTile<String>(value: 'middle_of_night', title: Text(l.highLatMiddle)),
                ],
              ),
            ),
          ),
          SectionHeader(l.manualOffsets),
          Text(l.manualOffsetsHelp, style: context.text.bodySmall),
          const SizedBox(height: 8),
          Card(
            child: Column(
              children: [
                for (final p in PrayerSettings.prayerOffsetKeys)
                  _OffsetRow(
                    label: prayerName(l, p),
                    value: s.offset(p),
                    onChanged: (v) => _save(ref, s.copyWith(offsets: {...s.offsets, p: v})),
                  ),
              ],
            ),
          ),
          const SizedBox(height: 20),
          OutlinedButton.icon(onPressed: () => context.push('/salah/templates'), icon: const Icon(Icons.tune), label: Text(l.prayerTemplates)),
        ],
      ),
    );
  }
}

class _OffsetRow extends StatelessWidget {
  const _OffsetRow({required this.label, required this.value, required this.onChanged});

  final String label;
  final int value;
  final ValueChanged<int> onChanged;

  @override
  Widget build(BuildContext context) => ListTile(
    title: Text(label),
    trailing: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        IconButton(tooltip: context.l10n.decrease, onPressed: value <= -120 ? null : () => onChanged(value - 1), icon: const Icon(Icons.remove_circle_outline)),
        SizedBox(
          width: 44,
          child: Text(value > 0 ? '+$value' : '$value', textAlign: TextAlign.center, style: context.text.titleSmall),
        ),
        IconButton(tooltip: context.l10n.increase, onPressed: value >= 120 ? null : () => onChanged(value + 1), icon: const Icon(Icons.add_circle_outline)),
      ],
    ),
  );
}
