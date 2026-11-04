import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/utils/dates.dart';
import '../../core/widgets/common.dart';
import '../../shared/providers/providers.dart';
import '../../shared/repositories/activity_repository.dart';
import '../tracker/history_screen.dart';
import '../tracker/record_form.dart';

/// Always a manual record: the app can't know, and never assumes, that someone fasted.
class FastingScreen extends ConsumerStatefulWidget {
  const FastingScreen({super.key});

  @override
  ConsumerState<FastingScreen> createState() => _FastingScreenState();
}

class _FastingScreenState extends ConsumerState<FastingScreen> {
  String _type = 'voluntary';
  DateTime _date = DateTime.now();

  Future<void> _pickDate() async {
    final picked = await showDatePicker(context: context, initialDate: _date, firstDate: DateTime(2020), lastDate: DateTime.now());
    if (picked != null) setState(() => _date = picked);
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final locale = Localizations.localeOf(context).languageCode;
    final types = {
      'ramadan': l.fastRamadan,
      'voluntary': l.fastVoluntary,
      'monday_thursday': l.fastMondayThursday,
      'other': l.fastOther,
    };
    final history = ref.watch(activityHistoryProvider('fasting'));
    return Scaffold(
      appBar: AppBar(title: Text(l.fastingTitle)),
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Padding(
            padding: const EdgeInsets.all(16),
            child: Card(
              child: Padding(
                padding: const EdgeInsets.all(14),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(l.recordAFast, style: context.text.titleSmall),
                    const SizedBox(height: 10),
                    Wrap(
                      spacing: 8,
                      children: [
                        for (final e in types.entries) ChoiceChip(label: Text(e.value), selected: _type == e.key, onSelected: (_) => setState(() => _type = e.key)),
                      ],
                    ),
                    ListTile(
                      contentPadding: EdgeInsets.zero,
                      leading: const Icon(Icons.event_outlined),
                      title: Text(shortDate(_date, locale)),
                      trailing: TextButton(onPressed: _pickDate, child: Text(l.change)),
                    ),
                    FilledButton(
                      onPressed: () => submitRecord(context, ref, type: 'fasting', quantity: 1, source: 'manual', date: _date, metadata: {'fast_type': _type}),
                      child: Text(l.confirmRecord),
                    ),
                  ],
                ),
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: Text(l.fastingHistory, style: context.text.titleMedium),
          ),
          Expanded(
            child: !ref.watch(authProvider).signedIn
                ? StateMessage(icon: Icons.history, title: l.guestProgressTitle, body: l.guestProgressBody, compact: true)
                : AsyncView(
                    value: history,
                    builder: (items) => items.isEmpty
                        ? StateMessage(icon: Icons.nights_stay_outlined, title: l.noRecordsTitle, body: l.noRecordsBody, compact: true)
                        : ActivityList(items: items),
                  ),
          ),
        ],
      ),
    );
  }
}
