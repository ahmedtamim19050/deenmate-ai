import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/notifications/prayer_notification_service.dart';
import '../../core/utils/dates.dart';
import '../../core/widgets/common.dart';
import '../../shared/providers/providers.dart';
import 'salah_models.dart';
import 'salah_repository.dart';
import 'templates_screen.dart';

/// Select categories and rakats, review the summary, then Confirm Prayer.
/// Nothing is sent, and no points move, before that final tap.
class RakatSelectionScreen extends ConsumerStatefulWidget {
  const RakatSelectionScreen({super.key, required this.prayer, this.date, this.existing});

  final String prayer;
  final String? date;
  final SalahRecord? existing;

  @override
  ConsumerState<RakatSelectionScreen> createState() => _RakatSelectionScreenState();
}

class _RakatSelectionScreenState extends ConsumerState<RakatSelectionScreen> {
  late RakatSelection _selection;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    final templates = ref.read(prayerConfigProvider).templates.where((t) => t.prayer == widget.prayer);
    final existing = widget.existing;
    if (existing == null) {
      _selection = RakatSelection.fromTemplates(templates);
    } else {
      final used = <String>{};
      final entries = <RakatEntry>[
        for (final i in existing.items) RakatEntry(i.templateKey ?? i.label, i.label, i.category, i.rakats, true),
      ];
      used.addAll(entries.map((e) => e.key));
      entries.addAll(templates.where((t) => t.enabled && !used.contains(t.key)).map((t) => RakatEntry(t.key, t.label, t.category, t.rakats, false)));
      _selection = RakatSelection(entries);
    }
  }

  Future<void> _confirm() async {
    final l = context.l10n;
    setState(() => _busy = true);
    try {
      if (widget.existing != null) {
        await ref.read(salahRepositoryProvider).update(widget.existing!.id, _selection.toItems());
        if (!mounted) return;
        context.toast(l.recordUpdated);
        context.pop(true);
        return;
      }
      final outcome = await ref.read(salahRepositoryProvider).confirm(
        prayer: widget.prayer,
        date: widget.date ?? ymd(DateTime.now()),
        items: _selection.toItems(),
      );
      if (!mounted) return;
      switch (outcome.state) {
        case ConfirmState.synced:
          context.toast(ref.read(prefsProvider).showPoints ? l.salahSaved(outcome.points ?? 0) : l.salahSavedNoPoints);
        case ConfirmState.pendingSync:
          context.toast(ref.read(authProvider).signedIn ? l.salahPending : l.salahPendingGuest);
        case ConfirmState.rejected:
          context.toast(outcome.error ?? l.errorBody);
      }
      context.canPop() ? context.pop() : context.go('/home');
    } catch (e) {
      if (mounted) context.toast(l.errorBody);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _addOptional() async {
    final l = context.l10n;
    final entry = await showDialog<RakatEntry>(context: context, builder: (_) => _AddCategoryDialog(index: _selection.entries.length));
    if (entry == null) return;
    setState(() => _selection = _selection.added(entry));
    if (mounted) context.toast(l.categoryAdded);
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final prefs = ref.watch(prefsProvider);
    final rule = ref.watch(activityTypesProvider).value?['salah_rakat'];
    return Scaffold(
      appBar: AppBar(title: Text(widget.existing == null ? l.whichDidYouPray(prayerName(l, widget.prayer)) : l.editRecord)),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text(l.rakatInstructions, style: context.text.bodyMedium?.copyWith(color: context.colors.onSurfaceVariant)),
          const SizedBox(height: 12),
          for (var i = 0; i < _selection.entries.length; i++) ...[
            _EntryCard(
              entry: _selection.entries[i],
              onToggle: () => setState(() => _selection = _selection.toggled(i)),
              onRakats: (v) => setState(() => _selection = _selection.withRakats(i, v)),
            ),
            const SizedBox(height: 8),
          ],
          TextButton.icon(onPressed: _addOptional, icon: const Icon(Icons.add), label: Text(l.addOptionalPrayer)),
          const SizedBox(height: 8),
          Card(
            color: context.colors.primaryContainer,
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(l.summary, style: context.text.titleSmall),
                  const SizedBox(height: 6),
                  Text(l.rakatCount(_selection.totalRakats), style: context.text.headlineSmall),
                  if (prefs.showPoints && rule != null) ...[
                    const SizedBox(height: 4),
                    Text(l.pointsPreview(rule.preview(_selection.totalRakats))),
                    Text(l.pointsPreviewNote, style: context.text.bodySmall),
                  ],
                ],
              ),
            ),
          ),
          if (_selection.totalRakats > 40) ...[
            const SizedBox(height: 8),
            Text(l.rakatTooMany, style: TextStyle(color: context.colors.error)),
          ],
        ],
      ),
      bottomNavigationBar: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: FilledButton(
            onPressed: !_selection.isValid || _busy ? null : _confirm,
            child: _busy ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2.5)) : Text(l.confirmPrayer),
          ),
        ),
      ),
    );
  }
}

class _EntryCard extends StatelessWidget {
  const _EntryCard({required this.entry, required this.onToggle, required this.onRakats});

  final RakatEntry entry;
  final VoidCallback onToggle;
  final ValueChanged<int> onRakats;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return Card(
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 4),
        child: Row(
          children: [
            Expanded(
              child: CheckboxListTile(
                value: entry.selected,
                onChanged: (_) => onToggle(),
                controlAffinity: ListTileControlAffinity.leading,
                title: Text(entry.label, style: context.text.titleSmall),
                // Only when it adds something: most labels already name the category.
                subtitle: entry.label == categoryName(l, entry.category) ? null : Text(categoryName(l, entry.category)),
              ),
            ),
            IconButton(tooltip: l.decrease, onPressed: entry.rakats <= 1 ? null : () => onRakats(entry.rakats - 1), icon: const Icon(Icons.remove_circle_outline)),
            Text('${entry.rakats}', style: context.text.titleMedium),
            IconButton(tooltip: l.increase, onPressed: entry.rakats >= 20 ? null : () => onRakats(entry.rakats + 1), icon: const Icon(Icons.add_circle_outline)),
          ],
        ),
      ),
    );
  }
}

class _AddCategoryDialog extends StatefulWidget {
  const _AddCategoryDialog({required this.index});

  final int index;

  @override
  State<_AddCategoryDialog> createState() => _AddCategoryDialogState();
}

class _AddCategoryDialogState extends State<_AddCategoryDialog> {
  String _category = 'nafl';
  int _rakats = 2;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return AlertDialog(
      title: Text(l.addOptionalPrayer),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          DropdownButtonFormField<String>(
            initialValue: _category,
            decoration: InputDecoration(labelText: l.category),
            items: [for (final c in const ['nafl', 'sunnah', 'witr']) DropdownMenuItem(value: c, child: Text(categoryName(l, c)))],
            onChanged: (v) => setState(() => _category = v ?? 'nafl'),
          ),
          const SizedBox(height: 12),
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              IconButton(tooltip: l.decrease, onPressed: _rakats <= 1 ? null : () => setState(() => _rakats--), icon: const Icon(Icons.remove_circle_outline)),
              Text(l.rakatCount(_rakats)),
              IconButton(tooltip: l.increase, onPressed: _rakats >= 20 ? null : () => setState(() => _rakats++), icon: const Icon(Icons.add_circle_outline)),
            ],
          ),
        ],
      ),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context), child: Text(l.cancel)),
        FilledButton(
          onPressed: () => Navigator.pop(context, RakatEntry('extra_${widget.index}', categoryName(l, _category), _category, _rakats, true)),
          child: Text(l.add),
        ),
      ],
    );
  }
}
