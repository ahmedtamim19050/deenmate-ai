import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/widgets/common.dart';
import '../../shared/providers/providers.dart';
import '../../shared/repositories/activity_repository.dart';
import '../tracker/history_screen.dart';
import '../tracker/record_form.dart';

/// Private records. A category is enough, so nobody has to describe anything sensitive.
class GoodDeedsScreen extends ConsumerStatefulWidget {
  const GoodDeedsScreen({super.key});

  @override
  ConsumerState<GoodDeedsScreen> createState() => _GoodDeedsScreenState();
}

class _GoodDeedsScreenState extends ConsumerState<GoodDeedsScreen> {
  String _category = 'helping';
  final _note = TextEditingController();

  @override
  void dispose() {
    _note.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final categories = {
      'helping': l.deedHelping,
      'charity': l.deedCharity,
      'family': l.deedFamily,
      'visiting': l.deedVisiting,
      'volunteering': l.deedVolunteering,
      'custom': l.deedCustom,
    };
    return Scaffold(
      appBar: AppBar(title: Text(l.goodDeedsTitle)),
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
                    Wrap(
                      spacing: 8,
                      children: [
                        for (final e in categories.entries) ChoiceChip(label: Text(e.value), selected: _category == e.key, onSelected: (_) => setState(() => _category = e.key)),
                      ],
                    ),
                    const SizedBox(height: 10),
                    TextField(
                      controller: _note,
                      maxLength: 200,
                      decoration: InputDecoration(labelText: l.optionalNote, helperText: l.deedNoteHelp),
                    ),
                    const SizedBox(height: 8),
                    FilledButton(
                      onPressed: () async {
                        await submitRecord(context, ref, type: 'good_deed', quantity: 1, source: 'manual', metadata: {
                          'category': _category,
                          if (_note.text.trim().isNotEmpty) 'note': _note.text.trim(),
                        });
                        _note.clear();
                      },
                      child: Text(l.confirmRecord),
                    ),
                  ],
                ),
              ),
            ),
          ),
          Expanded(
            child: !ref.watch(authProvider).signedIn
                ? StateMessage(icon: Icons.history, title: l.guestProgressTitle, body: l.guestProgressBody, compact: true)
                : AsyncView(
                    value: ref.watch(activityHistoryProvider('good_deed')),
                    builder: (items) => items.isEmpty
                        ? StateMessage(icon: Icons.volunteer_activism_outlined, title: l.noRecordsTitle, body: l.deedsPrivate, compact: true)
                        : ActivityList(items: items),
                  ),
          ),
        ],
      ),
    );
  }
}
