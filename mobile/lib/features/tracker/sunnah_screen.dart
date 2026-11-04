import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/widgets/common.dart';
import '../../shared/providers/providers.dart';
import '../../shared/repositories/activity_repository.dart';
import 'history_screen.dart';
import 'record_form.dart';

/// Sunnah practices and dua the person chooses to note. These are always self-reported.
class SunnahScreen extends ConsumerStatefulWidget {
  const SunnahScreen({super.key});

  @override
  ConsumerState<SunnahScreen> createState() => _SunnahScreenState();
}

class _SunnahScreenState extends ConsumerState<SunnahScreen> {
  final _note = TextEditingController();

  @override
  void dispose() {
    _note.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final presets = [l.sunnahSmile, l.sunnahSiwak, l.sunnahBismillah, l.sunnahSalam, l.sunnahVisitSick];
    final history = ref.watch(activityHistoryProvider('sunnah'));
    return Scaffold(
      appBar: AppBar(title: Text(l.sunnahTitle)),
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(l.sunnahIntro, style: context.text.bodyMedium),
                const SizedBox(height: 10),
                Wrap(
                  spacing: 8,
                  runSpacing: 4,
                  children: [
                    for (final p in presets)
                      ActionChip(
                        label: Text(p),
                        onPressed: () => submitRecord(context, ref, type: 'sunnah_act', quantity: 1, source: 'manual', metadata: {'practice': p}),
                      ),
                  ],
                ),
                const SizedBox(height: 10),
                FilledButton.tonalIcon(
                  onPressed: () => submitRecord(context, ref, type: 'dua', quantity: 1, source: 'manual'),
                  icon: const Icon(Icons.front_hand_outlined),
                  label: Text(l.recordDua),
                ),
              ],
            ),
          ),
          const Divider(),
          Expanded(
            child: !ref.watch(authProvider).signedIn
                ? StateMessage(icon: Icons.history, title: l.guestProgressTitle, body: l.guestProgressBody, compact: true)
                : AsyncView(
                    value: history,
                    builder: (items) => items.isEmpty
                        ? StateMessage(icon: Icons.wb_twilight_outlined, title: l.noRecordsTitle, body: l.noRecordsBody, compact: true)
                        : ActivityList(items: items),
                  ),
          ),
        ],
      ),
    );
  }
}
