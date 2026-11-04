import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/network/api_exception.dart';
import '../../core/notifications/prayer_notification_service.dart';
import '../../core/prayer/prayer_calculator.dart';
import '../../core/widgets/common.dart';
import '../../l10n/gen/app_localizations.dart';
import 'salah_models.dart';
import 'salah_repository.dart';

String categoryName(AppLocalizations l, String c) => switch (c) {
  'fard' => l.categoryFard,
  'sunnah' => l.categorySunnah,
  'nafl' => l.categoryNafl,
  'witr' => l.categoryWitr,
  _ => c,
};

/// Short read-only summary, used in onboarding.
class TemplateSummary extends ConsumerWidget {
  const TemplateSummary({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final templates = ref.watch(prayerConfigProvider).templates;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Card(
          child: Column(
            children: [
              for (final p in fardPrayers)
                ListTile(
                  dense: true,
                  title: Text(prayerName(l, p), style: context.text.titleSmall),
                  subtitle: Text(templates.where((t) => t.prayer == p && t.enabled).map((t) => '${t.rakats} ${t.label}').join(' · ')),
                ),
            ],
          ),
        ),
        const SizedBox(height: 10),
        OutlinedButton(onPressed: () => context.push('/salah/templates'), child: Text(l.customizeTemplates)),
      ],
    );
  }
}

class TemplatesScreen extends ConsumerStatefulWidget {
  const TemplatesScreen({super.key});

  @override
  ConsumerState<TemplatesScreen> createState() => _TemplatesScreenState();
}

class _TemplatesScreenState extends ConsumerState<TemplatesScreen> {
  late List<SalahTemplate> _items = [...ref.read(prayerConfigProvider).templates];
  bool _dirty = false;
  bool _busy = false;

  void _update(SalahTemplate before, SalahTemplate after) {
    setState(() {
      _items = [for (final t in _items) identical(t, before) ? after : t];
      _dirty = true;
    });
  }

  Future<void> _add(String prayer) async {
    final l = context.l10n;
    final label = TextEditingController(text: l.categoryNafl);
    var category = 'nafl';
    final added = await showDialog<bool>(
      context: context,
      builder: (c) => StatefulBuilder(
        builder: (c, setDialog) => AlertDialog(
          title: Text(l.addCategory),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(controller: label, decoration: InputDecoration(labelText: l.label)),
              const SizedBox(height: 10),
              DropdownButtonFormField<String>(
                initialValue: category,
                decoration: InputDecoration(labelText: l.category),
                items: [for (final x in const ['fard', 'sunnah', 'nafl', 'witr']) DropdownMenuItem(value: x, child: Text(categoryName(l, x)))],
                onChanged: (v) => setDialog(() => category = v ?? 'nafl'),
              ),
            ],
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(c, false), child: Text(l.cancel)),
            FilledButton(onPressed: () => Navigator.pop(c, true), child: Text(l.add)),
          ],
        ),
      ),
    );
    if (added != true || label.text.trim().isEmpty) return;
    setState(() {
      _items = [
        ..._items,
        SalahTemplate(prayer: prayer, key: 'custom_${DateTime.now().millisecondsSinceEpoch}', label: label.text.trim(), category: category, rakats: 2),
      ];
      _dirty = true;
    });
  }

  Future<void> _save() async {
    final l = context.l10n;
    setState(() => _busy = true);
    try {
      await ref.read(prayerConfigProvider).saveTemplates(_items);
      if (!mounted) return;
      context.toast(l.templatesSaved);
      setState(() => _dirty = false);
    } on ApiException catch (e) {
      if (!mounted) return;
      context.toast(e.kind == ApiErrorKind.offline ? l.savedOnDevice : e.firstError);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _restore() async {
    final l = context.l10n;
    final yes = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        title: Text(l.restoreDefaults),
        content: Text(l.restoreDefaultsBody),
        actions: [
          TextButton(onPressed: () => Navigator.pop(c, false), child: Text(l.cancel)),
          FilledButton(onPressed: () => Navigator.pop(c, true), child: Text(l.restore)),
        ],
      ),
    );
    if (yes != true) return;
    final restored = await ref.read(prayerConfigProvider).restoreDefaults();
    setState(() {
      _items = [...restored];
      _dirty = false;
    });
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return Scaffold(
      appBar: AppBar(
        title: Text(l.prayerTemplates),
        actions: [TextButton(onPressed: _restore, child: Text(l.restore))],
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 90),
        children: [
          Text(l.templatesNote, style: context.text.bodySmall),
          for (final p in fardPrayers) ...[
            SectionHeader(prayerName(l, p), trailing: IconButton(tooltip: l.addCategory, onPressed: () => _add(p), icon: const Icon(Icons.add))),
            Card(
              child: Column(
                children: [
                  for (final t in _items.where((t) => t.prayer == p))
                    Row(
                      children: [
                        Expanded(
                          child: SwitchListTile(
                            value: t.enabled,
                            onChanged: (v) => _update(t, t.copyWith(enabled: v)),
                            title: Text(t.label),
                            subtitle: Text(categoryName(l, t.category)),
                          ),
                        ),
                        IconButton(tooltip: l.decrease, onPressed: t.rakats <= 1 ? null : () => _update(t, t.copyWith(rakats: t.rakats - 1)), icon: const Icon(Icons.remove_circle_outline)),
                        Text('${t.rakats}', style: context.text.titleSmall),
                        IconButton(tooltip: l.increase, onPressed: t.rakats >= 20 ? null : () => _update(t, t.copyWith(rakats: t.rakats + 1)), icon: const Icon(Icons.add_circle_outline)),
                      ],
                    ),
                ],
              ),
            ),
          ],
        ],
      ),
      floatingActionButton: _dirty
          ? FloatingActionButton.extended(onPressed: _busy ? null : _save, icon: const Icon(Icons.save_outlined), label: Text(l.save))
          : null,
    );
  }
}
