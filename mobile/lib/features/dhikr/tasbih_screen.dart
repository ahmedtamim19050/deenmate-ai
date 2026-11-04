import 'package:drift/drift.dart' show OrderingTerm;
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/storage/app_database.dart';
import '../../core/utils/dates.dart';
import '../../core/widgets/common.dart';
import '../../shared/providers/providers.dart';
import '../tracker/record_form.dart';

class DhikrPreset {
  const DhikrPreset(this.key, this.arabic, this.latin);

  final String key;
  final String arabic;
  final String latin;
}

const dhikrPresets = [
  DhikrPreset('subhanallah', 'سُبْحَانَ ٱللَّٰهِ', 'SubhanAllah'),
  DhikrPreset('alhamdulillah', 'ٱلْحَمْدُ لِلَّٰهِ', 'Alhamdulillah'),
  DhikrPreset('allahuakbar', 'ٱللَّٰهُ أَكْبَرُ', 'Allahu Akbar'),
  DhikrPreset('lailaha', 'لَا إِلَٰهَ إِلَّا ٱللَّٰهُ', 'La ilaha illa Allah'),
  DhikrPreset('astaghfirullah', 'أَسْتَغْفِرُ ٱللَّٰهَ', 'Astaghfirullah'),
];

final tasbihHistoryProvider = StreamProvider<List<TasbihSession>>((ref) {
  final db = ref.watch(databaseProvider);
  return (db.select(db.tasbihSessions)
        ..orderBy([(t) => OrderingTerm.desc(t.endedAt)])
        ..limit(30))
      .watch();
});

/// Only taps in this screen count. It works fully offline. There are no ads on this screen.
class TasbihScreen extends ConsumerStatefulWidget {
  const TasbihScreen({super.key});

  @override
  ConsumerState<TasbihScreen> createState() => _TasbihScreenState();
}

class _TasbihScreenState extends ConsumerState<TasbihScreen> {
  DhikrPreset _preset = dhikrPresets.first;
  int _count = 0;
  int _target = 33;
  DateTime? _startedAt;

  void _tap() {
    if (ref.read(prefsProvider).haptics) HapticFeedback.lightImpact();
    setState(() {
      _startedAt ??= DateTime.now();
      _count++;
    });
  }

  Future<void> _reset() async {
    if (_count == 0) return;
    final l = context.l10n;
    final yes = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        title: Text(l.resetCounterTitle),
        content: Text(l.resetCounterBody),
        actions: [
          TextButton(onPressed: () => Navigator.pop(c, false), child: Text(l.cancel)),
          FilledButton(onPressed: () => Navigator.pop(c, true), child: Text(l.reset)),
        ],
      ),
    );
    if (yes == true) {
      setState(() {
        _count = 0;
        _startedAt = null;
      });
    }
  }

  Future<void> _finish() async {
    if (_count == 0) return;
    final count = _count;
    final db = ref.read(databaseProvider);
    await db.into(db.tasbihSessions).insert(
      TasbihSessionsCompanion.insert(preset: _preset.key, count: count, target: _target, startedAt: _startedAt ?? DateTime.now(), endedAt: DateTime.now()),
    );
    setState(() {
      _count = 0;
      _startedAt = null;
    });
    if (!mounted) return;
    await submitRecord(context, ref, type: 'dhikr_count', quantity: count, source: 'in_app', metadata: {'preset': _preset.key});
  }

  Future<void> _customTarget() async {
    final l = context.l10n;
    final controller = TextEditingController(text: '$_target');
    final value = await showDialog<int>(
      context: context,
      builder: (c) => AlertDialog(
        title: Text(l.customTarget),
        content: TextField(controller: controller, autofocus: true, keyboardType: TextInputType.number, decoration: InputDecoration(labelText: l.target)),
        actions: [
          TextButton(onPressed: () => Navigator.pop(c), child: Text(l.cancel)),
          FilledButton(onPressed: () => Navigator.pop(c, int.tryParse(controller.text)), child: Text(l.save)),
        ],
      ),
    );
    if (value != null && value >= 1 && value <= 100000) setState(() => _target = value);
  }

  void _manualRecord() {
    final l = context.l10n;
    var quantity = 33;
    showModalBottomSheet<void>(
      context: context,
      builder: (c) => StatefulBuilder(
        builder: (c, setSheet) => SafeArea(
          child: Padding(
            padding: const EdgeInsets.all(20),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(l.outsideAppDhikr, style: context.text.titleMedium),
                const SizedBox(height: 6),
                Text(l.outsideAppDhikrBody, style: context.text.bodySmall),
                const SizedBox(height: 14),
                QuantityStepper(value: quantity, max: 100000, onChanged: (v) => setSheet(() => quantity = v)),
                const SizedBox(height: 14),
                FilledButton(
                  onPressed: () {
                    Navigator.pop(c);
                    submitRecord(context, ref, type: 'dhikr_count', quantity: quantity, source: 'manual');
                  },
                  child: Text(l.confirmRecord),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final locale = Localizations.localeOf(context).languageCode;
    final progress = (_count / _target).clamp(0.0, 1.0);
    final history = ref.watch(tasbihHistoryProvider).value ?? const [];
    return Scaffold(
      appBar: AppBar(
        title: Text(l.dhikrTitle),
        actions: [
          IconButton(tooltip: l.outsideAppDhikr, onPressed: _manualRecord, icon: const Icon(Icons.edit_note)),
          IconButton(tooltip: l.reset, onPressed: _reset, icon: const Icon(Icons.restart_alt)),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          SizedBox(
            height: 44,
            child: ListView(
              scrollDirection: Axis.horizontal,
              children: [
                for (final p in dhikrPresets)
                  Padding(
                    padding: const EdgeInsetsDirectional.only(end: 8),
                    child: ChoiceChip(label: Text(p.latin), selected: p.key == _preset.key, onSelected: (_) => setState(() => _preset = p)),
                  ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          Directionality(
            textDirection: TextDirection.rtl,
            child: Text(_preset.arabic, textAlign: TextAlign.center, style: const TextStyle(fontFamily: 'ScheherazadeNew', fontSize: 34, height: 1.6)),
          ),
          const SizedBox(height: 16),
          Center(
            child: Semantics(
              button: true,
              label: l.tasbihTapLabel(_count),
              child: GestureDetector(
                onTap: _tap,
                behavior: HitTestBehavior.opaque,
                child: SizedBox(
                  width: 250,
                  height: 250,
                  child: Stack(
                    alignment: Alignment.center,
                    children: [
                      SizedBox.expand(child: CircularProgressIndicator(value: progress, strokeWidth: 10, backgroundColor: context.colors.primaryContainer)),
                      Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Text('$_count', style: context.text.displayLarge?.copyWith(fontWeight: FontWeight.w700, color: context.colors.primary, fontFeatures: const [FontFeature.tabularFigures()])),
                          Text(l.targetOf(_target), style: context.text.bodyMedium),
                          if (_count >= _target) Text(l.targetReached, style: context.text.labelLarge?.copyWith(color: context.colors.primary)),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
          const SizedBox(height: 14),
          Wrap(
            alignment: WrapAlignment.center,
            spacing: 8,
            children: [
              for (final t in const [33, 99, 100]) ChoiceChip(label: Text('$t'), selected: _target == t, onSelected: (_) => setState(() => _target = t)),
              ActionChip(label: Text(l.customTarget), onPressed: _customTarget),
            ],
          ),
          const SizedBox(height: 14),
          FilledButton(onPressed: _count == 0 ? null : _finish, child: Text(l.saveSession)),
          SectionHeader(l.sessionHistory),
          if (history.isEmpty)
            Text(l.noSessionsYet, style: context.text.bodySmall)
          else
            Card(
              child: Column(
                children: [
                  for (final s in history.take(10))
                    ListTile(
                      dense: true,
                      title: Text(dhikrPresets.where((p) => p.key == s.preset).firstOrNull?.latin ?? s.preset),
                      subtitle: Text('${shortDate(s.endedAt, locale)} ${clockTime(s.endedAt, locale)}'),
                      trailing: Text('${s.count}', style: context.text.titleSmall),
                    ),
                ],
              ),
            ),
        ],
      ),
    );
  }
}
