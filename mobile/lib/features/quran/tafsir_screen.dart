import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:share_plus/share_plus.dart';

import '../../app/theme/app_theme.dart';
import '../../core/widgets/common.dart';
import 'quran_audio.dart';
import 'quran_content_api.dart';
import 'quran_repository.dart';
import 'quran_settings.dart';

/// Scholarly tafsir from published sources only, attributed to its author. Nothing here is
/// generated. It needs a connection and is not saved, because of the provider's terms.
class TafsirScreen extends ConsumerStatefulWidget {
  const TafsirScreen({super.key, required this.surah, required this.verse});

  final int surah;
  final int verse;

  @override
  ConsumerState<TafsirScreen> createState() => _TafsirScreenState();
}

class _TafsirScreenState extends ConsumerState<TafsirScreen> {
  late int _surah = widget.surah;
  late int _verse = widget.verse;

  bool get _hasPrevious => _surah > 1 || _verse > 1;
  bool get _hasNext => _surah < 114 || _verse < surahVerseCounts[113];

  void _step(int delta) {
    setState(() {
      if (delta < 0) {
        if (_verse > 1) {
          _verse--;
        } else if (_surah > 1) {
          _surah--;
          _verse = surahVerseCounts[_surah - 1];
        }
      } else {
        if (_verse < surahVerseCounts[_surah - 1]) {
          _verse++;
        } else if (_surah < 114) {
          _surah++;
          _verse = 1;
        }
      }
    });
  }

  TafsirSource? _chosen(List<TafsirSource> sources, QuranSettings s) {
    if (sources.isEmpty) return null;
    final saved = sources.where((t) => t.id == s.tafsirId).firstOrNull;
    if (saved != null) return saved;
    final wantBangla = Localizations.localeOf(context).languageCode != 'en';
    // Ahsanul Bayaan (Bangla) and Ibn Kathir (English) are concise, well-known defaults.
    return sources.where((t) => t.id == (wantBangla ? 165 : 169)).firstOrNull ?? sources.first;
  }

  String _shareText(Verse? v, TafsirSource source, TafsirEntry entry) {
    final key = '$_surah:$_verse';
    return [
      'Quran $key',
      if (v != null) v.arabic,
      if (v?.translation != null) v!.translation!,
      '',
      entry.text,
      '',
      '— ${source.name}${source.author.isEmpty ? '' : ', ${source.author}'} (Quran.com)',
    ].join('\n');
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final settings = ref.watch(quranSettingsProvider);
    final sources = ref.watch(tafsirSourcesProvider);
    final detail = ref.watch(surahDetailProvider((id: _surah, edition: defaultTranslationEdition))).value;
    final verse = detail?.verses.where((v) => v.number == _verse).firstOrNull;
    final source = sources.hasValue ? _chosen(sources.value!, settings) : null;
    final entry = source == null ? null : ref.watch(tafsirProvider((tafsirId: source.id, verseKey: '$_surah:$_verse')));

    void setSize(double delta) => ref
        .read(quranSettingsProvider.notifier)
        .update((s) => s.copyWith(tafsirSize: (s.tafsirSize + delta).clamp(QuranSettings.minText, QuranSettings.maxText)));

    return Scaffold(
      appBar: AppBar(
        title: Text('${l.tafsir} · $_surah:$_verse'),
        actions: [
          IconButton(tooltip: l.smallerText, onPressed: () => setSize(-1), icon: const Icon(Icons.text_decrease)),
          IconButton(tooltip: l.largerText, onPressed: () => setSize(1), icon: const Icon(Icons.text_increase)),
          if (source != null && entry?.value != null && entry!.value!.text.isNotEmpty) ...[
            IconButton(
              tooltip: l.copy,
              onPressed: () async {
                await Clipboard.setData(ClipboardData(text: _shareText(verse, source, entry.value!)));
                if (context.mounted) context.toast(l.copied);
              },
              icon: const Icon(Icons.copy),
            ),
            IconButton(
              tooltip: l.share,
              onPressed: () => SharePlus.instance.share(ShareParams(text: _shareText(verse, source, entry.value!))),
              icon: const Icon(Icons.share_outlined),
            ),
          ],
        ],
      ),
      body: AsyncView(
        value: sources,
        onRetry: () => ref.invalidate(tafsirSourcesProvider),
        builder: (list) {
          if (list.isEmpty || source == null) return StateMessage(icon: Icons.menu_book_outlined, title: l.tafsirUnavailable);
          return ListView(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
            children: [
              SizedBox(
                height: 44,
                child: ListView(
                  scrollDirection: Axis.horizontal,
                  children: [
                    for (final t in list)
                      Padding(
                        padding: const EdgeInsets.only(right: 8),
                        child: ChoiceChip(
                          label: Text('${t.name} · ${t.language == 'bengali' ? l.bangla : l.english}'),
                          selected: t.id == source.id,
                          onSelected: (_) => ref.read(quranSettingsProvider.notifier).update((s) => s.copyWith(tafsirId: t.id)),
                        ),
                      ),
                  ],
                ),
              ),
              const SizedBox(height: 12),
              if (verse != null)
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(14),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Text(verse.arabic, textDirection: TextDirection.rtl, textAlign: TextAlign.right, style: quranTextStyle(context, size: settings.arabicSize - 4)),
                        if (verse.translation != null) ...[
                          const SizedBox(height: 8),
                          Text(verse.translation!, style: context.text.bodyLarge?.copyWith(fontFamily: 'HindSiliguri', height: 1.6)),
                        ],
                      ],
                    ),
                  ),
                ),
              const SizedBox(height: 12),
              AsyncView(
                value: entry!,
                onRetry: () => ref.invalidate(tafsirProvider((tafsirId: source.id, verseKey: '$_surah:$_verse'))),
                builder: (e) => Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    if (e.text.isEmpty)
                      StateMessage(icon: Icons.link, title: l.tafsirGrouped, compact: true)
                    else
                      SelectableText(
                        e.text,
                        style: context.text.bodyLarge?.copyWith(fontSize: settings.tafsirSize, height: 1.75, fontFamily: source.language == 'bengali' ? 'HindSiliguri' : null),
                      ),
                    const SizedBox(height: 16),
                    Text(l.tafsirAttribution(source.name, source.author.isEmpty ? '-' : source.author), style: context.text.bodySmall),
                    const SizedBox(height: 4),
                    Text(l.tafsirOnlineOnly, style: context.text.bodySmall),
                  ],
                ),
              ),
            ],
          );
        },
      ),
      bottomNavigationBar: SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(12, 4, 12, 8),
          child: Row(
            children: [
              Expanded(child: OutlinedButton.icon(onPressed: _hasPrevious ? () => _step(-1) : null, icon: const Icon(Icons.chevron_left), label: Text(l.previousVerse))),
              const SizedBox(width: 8),
              IconButton(tooltip: l.openInReader, onPressed: () => context.push('/quran/$_surah?verse=$_verse'), icon: const Icon(Icons.menu_book_outlined)),
              const SizedBox(width: 8),
              Expanded(child: OutlinedButton.icon(onPressed: _hasNext ? () => _step(1) : null, icon: const Icon(Icons.chevron_right), label: Text(l.nextVerse))),
            ],
          ),
        ),
      ),
    );
  }
}
