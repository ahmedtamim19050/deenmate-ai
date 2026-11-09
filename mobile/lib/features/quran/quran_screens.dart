import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart' show ScrollCacheExtent;
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:share_plus/share_plus.dart';

import '../../app/theme/app_theme.dart';
import '../../core/network/api_exception.dart';
import '../../core/widgets/common.dart';
import '../../shared/providers/providers.dart';
import '../../shared/repositories/activity_repository.dart';
import '../tracker/record_form.dart';
import 'quran_audio_controller.dart';
import 'quran_content_api.dart';
import 'quran_player.dart';
import 'quran_repository.dart';
import 'quran_settings.dart';
import 'word_by_word.dart';

class SurahListScreen extends ConsumerStatefulWidget {
  const SurahListScreen({super.key});

  @override
  ConsumerState<SurahListScreen> createState() => _SurahListScreenState();
}

class _SurahListScreenState extends ConsumerState<SurahListScreen> {
  String _query = '';

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final last = ref.watch(prefsProvider).readJson('last_read');
    return Scaffold(
      appBar: AppBar(
        title: Text(l.quranTitle),
        actions: [
          IconButton(tooltip: l.searchQuran, onPressed: () => context.push('/quran/search'), icon: const Icon(Icons.search)),
          IconButton(tooltip: l.bookmarks, onPressed: () => context.push('/bookmarks'), icon: const Icon(Icons.bookmark_outline)),
          IconButton(tooltip: l.downloads, onPressed: () => context.push('/quran/downloads'), icon: const Icon(Icons.download_outlined)),
          IconButton(tooltip: l.recordOutsideReading, onPressed: () => context.push('/quran/record'), icon: const Icon(Icons.edit_note)),
        ],
      ),
      body: AsyncView(
        value: ref.watch(surahListProvider),
        onRetry: () => ref.invalidate(surahListProvider),
        builder: (surahs) {
          if (surahs.isEmpty) {
            return StateMessage(
              icon: Icons.menu_book_outlined,
              title: l.quranUnavailableTitle,
              body: l.quranUnavailableBody,
              action: FilledButton.tonal(onPressed: () => ref.invalidate(surahListProvider), child: Text(l.retry)),
            );
          }
          final q = _query.toLowerCase();
          final filtered = surahs.where((s) => q.isEmpty || s.name.toLowerCase().contains(q) || s.english.toLowerCase().contains(q) || '${s.id}' == q).toList();
          return ListView.builder(
            padding: const EdgeInsets.fromLTRB(16, 4, 16, 16),
            itemCount: filtered.length + 1,
            itemBuilder: (_, i) {
              if (i == 0) {
                return Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    TextField(
                      decoration: InputDecoration(prefixIcon: const Icon(Icons.search), hintText: l.searchSurah),
                      onChanged: (v) => setState(() => _query = v),
                    ),
                    const SizedBox(height: 10),
                    if (last != null)
                      Padding(
                        padding: const EdgeInsets.only(bottom: 10),
                        child: Card(
                          color: context.colors.primaryContainer,
                          child: NavTile(
                            icon: Icons.bookmark_added_outlined,
                            title: l.continueReading,
                            subtitle: '${last['name']} · ${last['verse']}',
                            onTap: () => context.push('/quran/${last['surah']}?verse=${last['verse']}'),
                          ),
                        ),
                      ),
                  ],
                );
              }
              final s = filtered[i - 1];
              return Card(
                margin: const EdgeInsets.only(bottom: 8),
                child: ListTile(
                  leading: CircleAvatar(backgroundColor: context.colors.primaryContainer, foregroundColor: context.colors.onPrimaryContainer, child: Text('${s.id}', style: context.text.labelMedium)),
                  title: Text(s.name, style: context.text.titleSmall),
                  subtitle: Text('${s.english} · ${l.verseCount(s.verses)}'),
                  trailing: Text(s.arabic, textDirection: TextDirection.rtl, style: const TextStyle(fontFamily: 'ScheherazadeNew', fontSize: 22)),
                  onTap: () => context.push('/quran/${s.id}'),
                ),
              );
            },
          );
        },
      ),
    );
  }
}

/// No ads appear on this screen. Reading progress counts pages actually scrolled through,
/// with a minimum time per page, so opening a surah or leaving it open earns nothing.
class SurahReaderScreen extends ConsumerStatefulWidget {
  const SurahReaderScreen({super.key, required this.surahId, this.scrollToVerse});

  final int surahId;
  final int? scrollToVerse;

  @override
  ConsumerState<SurahReaderScreen> createState() => _SurahReaderScreenState();
}

class _SurahReaderScreenState extends ConsumerState<SurahReaderScreen> {
  static const _minSecondsPerPage = 20;

  String? _edition = defaultTranslationEdition;
  late int _startVerse = widget.scrollToVerse ?? 1;
  final _startedAt = DateTime.now();
  final Set<int> _seenPages = {};
  int _maxVerseSeen = 0;
  String? _surahName;
  late final ActivityRepository _activities;
  int? _selectedWord;

  @override
  void initState() {
    super.initState();
    _activities = ref.read(activityRepositoryProvider);
  }

  @override
  void dispose() {
    _recordProgress();
    super.dispose();
  }

  /// Listening time is recorded by the shared player, which keeps playing after this screen closes.
  void _recordProgress() {
    // The last page may be unfinished, so only pages already passed are counted.
    final finished = _seenPages.length > 1 ? _seenPages.length - 1 : 0;
    final allowed = DateTime.now().difference(_startedAt).inSeconds ~/ _minSecondsPerPage;
    final pages = finished < allowed ? finished : allowed;
    if (pages > 0) {
      unawaited(_activities.record(type: 'quran_page', quantity: pages, source: 'in_app', metadata: {'surah': widget.surahId}));
    }
  }

  void _markSeen(Verse v) {
    if (v.page != null) _seenPages.add(v.page!);
    if (v.number > _maxVerseSeen) {
      _maxVerseSeen = v.number;
      if (_surahName != null) {
        ref.read(prefsProvider).writeJson('last_read', {'surah': widget.surahId, 'name': _surahName, 'verse': v.number});
      }
    }
  }

  QuranAudioController get _audio => ref.read(quranAudioProvider);

  Future<void> _playTop(SurahDetail d) async {
    final a = _audio;
    if (a.surahId == widget.surahId && !a.wordClip && a.queue.isNotEmpty) return a.toggle();
    await a.playSurah(surah: widget.surahId, name: d.surah.name, basmala: d.basmala != null, fromVerse: _startVerse);
  }

  Future<void> _playVerse(SurahDetail d, Verse v) async {
    final a = _audio;
    if (a.isPlayingVerse(widget.surahId, v.number)) return a.toggle();
    await a.playVerse(surah: widget.surahId, name: d.surah.name, basmala: d.basmala != null, verse: v.number);
  }

  Future<void> _repeatVerse(SurahDetail d, Verse v) async {
    await _audio.setRepeat(QuranRepeat.verse);
    await _audio.playVerse(surah: widget.surahId, name: d.surah.name, basmala: d.basmala != null, verse: v.number);
  }

  Future<void> _toggleBookmark(Verse v, Map<int, int> bookmarks) async {
    final l = context.l10n;
    if (!ref.read(authProvider).signedIn) {
      context.toast(l.bookmarkNeedsAccount);
      return;
    }
    final api = ref.read(apiClientProvider);
    try {
      final existing = bookmarks[v.id];
      if (existing != null) {
        await api.delete('/bookmarks/$existing');
      } else {
        await api.post('/bookmarks', {'target_type': 'verse', 'target_id': v.id});
      }
      ref.invalidate(verseBookmarkListProvider);
    } on ApiException catch (e) {
      if (mounted) context.toast(e.kind == ApiErrorKind.offline ? l.offlineBody : e.firstError);
    }
  }

  String _verseText(Verse v) => [
    '${_surahName ?? ''} ${widget.surahId}:${v.number}'.trim(),
    v.arabic,
    if (v.translation != null) v.translation!,
  ].join('\n');

  Future<void> _editNote(Verse v) async {
    final l = context.l10n;
    final key = '${widget.surahId}:${v.number}';
    final controller = TextEditingController(text: ref.read(quranNotesProvider)[key] ?? '');
    final result = await showDialog<String>(
      context: context,
      builder: (c) => AlertDialog(
        title: Text('${l.note} · $key'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            TextField(controller: controller, autofocus: true, minLines: 3, maxLines: 8, maxLength: 2000, decoration: InputDecoration(hintText: l.noteHint)),
            Text(l.notesPrivate, style: c.text.bodySmall),
          ],
        ),
        actions: [
          if (controller.text.isNotEmpty) TextButton(onPressed: () => Navigator.pop(c, ''), child: Text(l.deleteNote)),
          TextButton(onPressed: () => Navigator.pop(c), child: Text(l.cancel)),
          FilledButton(onPressed: () => Navigator.pop(c, controller.text), child: Text(l.save)),
        ],
      ),
    );
    if (result != null) ref.read(quranNotesProvider.notifier).save(key, result);
  }

  void _verseMenu(SurahDetail d, Verse v) {
    final l = context.l10n;
    final hasNote = ref.read(quranNotesProvider).containsKey('${widget.surahId}:${v.number}');
    showModalBottomSheet<void>(
      context: context,
      builder: (c) => SafeArea(
        child: ListView(
          shrinkWrap: true,
          children: [
            ListTile(leading: const Icon(Icons.play_arrow), title: Text(l.playFromHere), onTap: () => _closeThen(c, () => _playVerse(d, v))),
            ListTile(leading: const Icon(Icons.repeat_one), title: Text(l.repeatThisVerse), onTap: () => _closeThen(c, () => _repeatVerse(d, v))),
            ListTile(leading: const Icon(Icons.menu_book_outlined), title: Text(l.tafsir), onTap: () => _closeThen(c, () => context.push('/quran/tafsir/${widget.surahId}/${v.number}'))),
            ListTile(leading: const Icon(Icons.school_outlined), title: Text(l.wordLearning), onTap: () => _closeThen(c, () => context.push('/quran/learn/${widget.surahId}?verse=${v.number}'))),
            ListTile(leading: Icon(hasNote ? Icons.edit_note : Icons.note_add_outlined), title: Text(hasNote ? l.editNote : l.addNote), onTap: () => _closeThen(c, () => _editNote(v))),
            ListTile(
              leading: const Icon(Icons.copy),
              title: Text(l.copy),
              onTap: () => _closeThen(c, () async {
                await Clipboard.setData(ClipboardData(text: _verseText(v)));
                if (mounted) context.toast(l.copied);
              }),
            ),
            ListTile(leading: const Icon(Icons.share_outlined), title: Text(l.share), onTap: () => _closeThen(c, () => SharePlus.instance.share(ShareParams(text: _verseText(v))))),
          ],
        ),
      ),
    );
  }

  void _closeThen(BuildContext sheet, FutureOr<void> Function() action) {
    Navigator.pop(sheet);
    action();
  }

  void _options(SurahDetail d) {
    final editions = ref.read(editionsProvider).value ?? const [];
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (c) => Consumer(
        builder: (c, ref, _) {
          final l = c.l10n;
          final s = ref.watch(quranSettingsProvider);
          final audio = ref.watch(quranAudioProvider);
          void set(QuranSettings Function(QuranSettings) f) => ref.read(quranSettingsProvider.notifier).update(f);
          return SafeArea(
            child: ConstrainedBox(
              constraints: BoxConstraints(maxHeight: MediaQuery.sizeOf(c).height * 0.85),
              child: ListView(
                shrinkWrap: true,
                padding: const EdgeInsets.all(16),
                children: [
                  Text(l.readingOptions, style: c.text.titleMedium),
                  SwitchListTile(title: Text(l.showTranslation), value: s.showTranslation, onChanged: (v) => set((x) => x.copyWith(showTranslation: v))),
                  SwitchListTile(title: Text(l.showTransliteration), value: s.showTransliteration, onChanged: (v) => set((x) => x.copyWith(showTransliteration: v))),
                  SwitchListTile(title: Text(l.wordByWord), subtitle: Text(l.wordByWordHint), value: s.wordByWord, onChanged: (v) => set((x) => x.copyWith(wordByWord: v))),
                  if (s.wordByWord) ...[
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
                      child: SegmentedButton<String>(
                        segments: [
                          ButtonSegment(value: 'bn', label: Text(l.bangla)),
                          ButtonSegment(value: 'en', label: Text(l.english)),
                          ButtonSegment(value: 'both', label: Text(l.both)),
                        ],
                        selected: {s.wordMeaning},
                        onSelectionChanged: (v) => set((x) => x.copyWith(wordMeaning: v.first)),
                      ),
                    ),
                    SwitchListTile(title: Text(l.showWordTransliteration), value: s.wordTransliteration, onChanged: (v) => set((x) => x.copyWith(wordTransliteration: v))),
                  ],
                  ListTile(title: Text(l.arabicTextSize), subtitle: Slider(value: s.arabicSize, min: QuranSettings.minArabic, max: QuranSettings.maxArabic, divisions: 11, label: '${s.arabicSize.round()}', onChanged: (v) => set((x) => x.copyWith(arabicSize: v)))),
                  ListTile(title: Text(l.translationTextSize), subtitle: Slider(value: s.translationSize, min: QuranSettings.minText, max: QuranSettings.maxText, divisions: 12, label: '${s.translationSize.round()}', onChanged: (v) => set((x) => x.copyWith(translationSize: v)))),
                  ListTile(
                    leading: const Icon(Icons.record_voice_over_outlined),
                    title: Text(l.reciter),
                    subtitle: Text(audio.reciter.name),
                    trailing: const Icon(Icons.chevron_right),
                    onTap: () {
                      Navigator.pop(c);
                      showReciterSheet(context, audio);
                    },
                  ),
                  ListTile(
                    leading: const Icon(Icons.download_outlined),
                    title: Text(l.downloads),
                    trailing: const Icon(Icons.chevron_right),
                    onTap: () {
                      Navigator.pop(c);
                      context.push('/quran/downloads');
                    },
                  ),
                  const Divider(),
                  Text(l.translationLabel, style: c.text.titleSmall),
                  for (final e in editions)
                    ListTile(
                      title: Text(e.name),
                      subtitle: Text(e.language),
                      trailing: _edition == e.identifier ? const Icon(Icons.check) : null,
                      onTap: () {
                        setState(() => _edition = e.identifier);
                        Navigator.pop(c);
                      },
                    ),
                  if (editions.length > 1) ...[
                    const SizedBox(height: 8),
                    Text(l.secondTranslation, style: c.text.titleSmall),
                    Wrap(
                      spacing: 8,
                      children: [
                        ChoiceChip(label: Text(l.noneOption), selected: s.secondEdition == null, onSelected: (_) => set((x) => x.copyWith(secondEdition: () => null))),
                        for (final e in editions)
                          if (e.identifier != _edition)
                            ChoiceChip(label: Text(e.name), selected: s.secondEdition == e.identifier, onSelected: (_) => set((x) => x.copyWith(secondEdition: () => e.identifier))),
                      ],
                    ),
                  ],
                  const Divider(),
                  Text(l.textSourceLabel(d.textSource ?? l.unknown), style: c.text.bodySmall),
                  const SizedBox(height: 4),
                  Text(l.translationSourceLabel(d.editionName ?? l.unavailableShort), style: c.text.bodySmall),
                  if (d.editionAttribution != null) ...[
                    const SizedBox(height: 4),
                    SelectableText(d.editionAttribution!, style: c.text.bodySmall),
                  ],
                ],
              ),
            ),
          );
        },
      ),
    );
  }

  void _openWord(Map<int, List<QuranWord>> byVerse, QuranWord w) {
    final all = [
      for (final v in byVerse.keys.toList()..sort()) ...byVerse[v]!,
    ];
    setState(() => _selectedWord = w.id);
    showWordStudy(
      context,
      words: all,
      startIndex: all.indexWhere((x) => x.id == w.id).clamp(0, all.length - 1),
      surah: widget.surahId,
      onSelect: (x) {
        if (mounted) setState(() => _selectedWord = x.id);
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    // Loaded here so the translation picker has its list the first time it opens.
    final editions = ref.watch(editionsProvider).value ?? const [];
    final settings = ref.watch(quranSettingsProvider);
    final notes = ref.watch(quranNotesProvider);
    final audio = ref.watch(quranAudioProvider);
    final detail = ref.watch(surahDetailProvider((id: widget.surahId, edition: _edition)));
    final bookmarks = ref.watch(verseBookmarksProvider).value ?? const {};
    final second = settings.secondEdition;
    final showSecond = settings.showTranslation && second != null && second != _edition && editions.any((e) => e.identifier == second);
    final secondDetail = showSecond ? ref.watch(surahDetailProvider((id: widget.surahId, edition: second))).value : null;
    final secondByVerse = {for (final v in secondDetail?.verses ?? const <Verse>[]) v.number: v.translation};
    final words = settings.wordByWord ? ref.watch(surahWordsProvider(widget.surahId)) : null;

    return ListenableBuilder(
      listenable: audio,
      builder: (context, _) {
        final thisSurah = audio.surahId == widget.surahId && !audio.wordClip;
        return Scaffold(
          appBar: AppBar(
            title: Text(detail.value?.surah.name ?? l.quranTitle),
            actions: [
              IconButton(
                tooltip: thisSurah && audio.playing ? l.pauseRecitation : l.playRecitation,
                onPressed: !detail.hasValue ? null : () => _playTop(detail.value!),
                icon: Icon(thisSurah && audio.playing ? Icons.pause_circle_outline : Icons.play_circle_outline),
              ),
              IconButton(tooltip: l.wordLearning, onPressed: () => context.push('/quran/learn/${widget.surahId}'), icon: const Icon(Icons.school_outlined)),
              if (detail.hasValue) IconButton(tooltip: l.readingOptions, onPressed: () => _options(detail.value!), icon: const Icon(Icons.tune)),
            ],
          ),
          body: AsyncView(
            value: detail,
            onRetry: () => ref.invalidate(surahDetailProvider((id: widget.surahId, edition: _edition))),
            builder: (d) {
              _surahName = d.surah.name;
              if (d.verses.isEmpty) return StateMessage(icon: Icons.menu_book_outlined, title: l.quranUnavailableTitle, body: l.quranUnavailableBody);
              // Opening at a saved verse starts the list there, with a way back to verse 1.
              final verses = d.verses.where((v) => v.number >= _startVerse).toList();
              return ListView.builder(
                // Only verses really on screen get built, which is what reading progress counts.
                scrollCacheExtent: const ScrollCacheExtent.viewport(0.0),
                padding: const EdgeInsets.fromLTRB(16, 4, 16, 24),
                itemCount: verses.length + 1,
                itemBuilder: (_, i) {
                  if (i == 0) {
                    return Column(
                      children: [
                        if (thisSurah && audio.error) Text(l.audioUnavailable, style: TextStyle(color: context.colors.error)),
                        if (_startVerse > 1) TextButton(onPressed: () => setState(() => _startVerse = 1), child: Text(l.showFromStart)),
                        if (d.basmala != null && _startVerse == 1)
                          Padding(
                            padding: const EdgeInsets.symmetric(vertical: 12),
                            child: Text(d.basmala!, textAlign: TextAlign.center, textDirection: TextDirection.rtl, style: quranTextStyle(context, size: settings.arabicSize - 4)),
                          ),
                      ],
                    );
                  }
                  final v = verses[i - 1];
                  _markSeen(v);
                  Widget? wordsView;
                  if (words != null) {
                    wordsView = words.when(
                      data: (byVerse) => VerseWords(
                        words: byVerse[v.number] ?? const [],
                        settings: settings,
                        selectedId: _selectedWord,
                        onTap: (w) => _openWord(byVerse, w),
                      ),
                      loading: () => const LinearProgressIndicator(),
                      error: (_, _) => Text(l.wordByWordUnavailable, style: context.text.bodySmall?.copyWith(color: context.colors.error)),
                    );
                  }
                  return _VerseCard(
                    verse: v,
                    settings: settings,
                    secondTranslation: showSecond ? secondByVerse[v.number] : null,
                    wordsView: words?.hasValue == true ? wordsView : null,
                    wordsStatus: words?.hasValue == true ? null : wordsView,
                    note: notes['${widget.surahId}:${v.number}'],
                    bookmarked: bookmarks.containsKey(v.id),
                    onBookmark: () => _toggleBookmark(v, bookmarks),
                    isCurrent: thisSurah && audio.currentVerse == v.number && !audio.finished,
                    playing: thisSurah && audio.playing && audio.currentVerse == v.number,
                    onPlay: () => _playVerse(d, v),
                    onTafsir: () => context.push('/quran/tafsir/${widget.surahId}/${v.number}'),
                    onMore: () => _verseMenu(d, v),
                    onNote: () => _editNote(v),
                  );
                },
              );
            },
          ),
        );
      },
    );
  }
}

class _VerseCard extends StatelessWidget {
  const _VerseCard({
    required this.verse,
    required this.settings,
    required this.secondTranslation,
    required this.wordsView,
    required this.wordsStatus,
    required this.note,
    required this.bookmarked,
    required this.onBookmark,
    required this.isCurrent,
    required this.playing,
    required this.onPlay,
    required this.onTafsir,
    required this.onMore,
    required this.onNote,
  });

  final Verse verse;
  final QuranSettings settings;
  final String? secondTranslation;

  /// Word-by-word layout, shown in place of the verse line once the words have loaded.
  final Widget? wordsView;

  /// Loading or error state for word-by-word, shown under the verse line.
  final Widget? wordsStatus;
  final String? note;
  final bool bookmarked;
  final VoidCallback onBookmark;
  final bool isCurrent;
  final bool playing;
  final VoidCallback onPlay;
  final VoidCallback onTafsir;
  final VoidCallback onMore;
  final VoidCallback onNote;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return Card(
      color: isCurrent ? context.colors.primaryContainer.withValues(alpha: 0.45) : null,
      margin: const EdgeInsets.only(bottom: 10),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: BorderSide(color: isCurrent ? context.colors.primary : context.colors.outlineVariant.withValues(alpha: 0.4)),
      ),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 6, 6, 14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                StatusChip(label: '${verse.number}'),
                const Spacer(),
                IconButton(
                  tooltip: playing ? l.pauseRecitation : l.playRecitation,
                  onPressed: onPlay,
                  icon: Icon(playing ? Icons.pause_circle_outline : Icons.play_circle_outline, color: isCurrent ? context.colors.primary : null),
                ),
                IconButton(tooltip: l.tafsir, onPressed: onTafsir, icon: const Icon(Icons.menu_book_outlined)),
                IconButton(
                  tooltip: bookmarked ? l.removeBookmark : l.addBookmark,
                  onPressed: onBookmark,
                  icon: Icon(bookmarked ? Icons.bookmark : Icons.bookmark_outline, color: bookmarked ? context.colors.primary : null),
                ),
                IconButton(tooltip: l.moreOptions, onPressed: onMore, icon: const Icon(Icons.more_vert)),
              ],
            ),
            Padding(
              padding: const EdgeInsets.only(right: 10),
              child:
                  wordsView ??
                  Text(verse.arabic, textDirection: TextDirection.rtl, textAlign: TextAlign.right, style: quranTextStyle(context, size: settings.arabicSize)),
            ),
            if (wordsStatus != null) Padding(padding: const EdgeInsets.only(top: 6, right: 10), child: wordsStatus),
            if (settings.showTransliteration && verse.transliteration != null) ...[
              const SizedBox(height: 8),
              Text(verse.transliteration!, style: context.text.bodySmall?.copyWith(fontStyle: FontStyle.italic, fontSize: settings.translationSize - 3)),
            ],
            if (settings.showTranslation) ...[
              const SizedBox(height: 8),
              Text(
                verse.translation ?? l.translationUnavailable,
                style: context.text.bodyLarge?.copyWith(fontFamily: 'HindSiliguri', fontSize: settings.translationSize, height: 1.7),
              ),
              if (secondTranslation != null) ...[
                const SizedBox(height: 6),
                Text(secondTranslation!, style: context.text.bodyMedium?.copyWith(fontSize: settings.translationSize - 2, height: 1.6, color: context.colors.onSurfaceVariant)),
              ],
            ],
            if (note != null) ...[
              const SizedBox(height: 10),
              InkWell(
                onTap: onNote,
                borderRadius: BorderRadius.circular(10),
                child: Container(
                  padding: const EdgeInsets.all(10),
                  margin: const EdgeInsets.only(right: 10),
                  decoration: BoxDecoration(color: context.colors.secondaryContainer.withValues(alpha: 0.6), borderRadius: BorderRadius.circular(10)),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Icon(Icons.sticky_note_2_outlined, size: 18),
                      const SizedBox(width: 8),
                      Expanded(child: Text(note!, style: context.text.bodyMedium)),
                    ],
                  ),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class QuranSearchScreen extends ConsumerStatefulWidget {
  const QuranSearchScreen({super.key});

  @override
  ConsumerState<QuranSearchScreen> createState() => _QuranSearchScreenState();
}

class _QuranSearchScreenState extends ConsumerState<QuranSearchScreen> {
  Future<List<Map<String, dynamic>>>? _results;

  void _search(String q) {
    if (q.trim().length < 2) return;
    setState(() {
      _results = ref.read(apiClientProvider).get('/quran/search', query: {'q': q.trim(), 'edition': defaultTranslationEdition}).then((d) => (d['data'] as List).cast<Map<String, dynamic>>());
    });
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return Scaffold(
      appBar: AppBar(title: Text(l.searchQuran)),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(16),
            child: TextField(
              autofocus: true,
              textInputAction: TextInputAction.search,
              onSubmitted: _search,
              decoration: InputDecoration(prefixIcon: const Icon(Icons.search), hintText: l.searchTranslationHint),
            ),
          ),
          Expanded(
            child: _results == null
                ? StateMessage(icon: Icons.manage_search, title: l.searchQuran, body: l.searchTranslationHint, compact: true)
                : FutureBuilder(
                    future: _results,
                    builder: (context, snap) {
                      if (snap.hasError) return ErrorState(error: snap.error!, compact: true);
                      if (!snap.hasData) return const Center(child: CircularProgressIndicator());
                      final rows = snap.data!;
                      if (rows.isEmpty) return StateMessage(icon: Icons.search_off, title: l.noResults, compact: true);
                      return ListView.builder(
                        padding: const EdgeInsets.symmetric(horizontal: 16),
                        itemCount: rows.length,
                        itemBuilder: (_, i) {
                          final r = rows[i];
                          return Card(
                            margin: const EdgeInsets.only(bottom: 8),
                            child: ListTile(
                              title: Text('${r['surah']} ${r['surah_id']}:${r['verse']}', style: context.text.titleSmall),
                              subtitle: Text(r['translation'] as String, maxLines: 3, overflow: TextOverflow.ellipsis),
                              onTap: () => context.push('/quran/${r['surah_id']}?verse=${r['verse']}'),
                            ),
                          );
                        },
                      );
                    },
                  ),
          ),
        ],
      ),
    );
  }
}

/// Reading done outside the app, recorded only after the person confirms it.
class QuranManualRecordScreen extends ConsumerStatefulWidget {
  const QuranManualRecordScreen({super.key});

  @override
  ConsumerState<QuranManualRecordScreen> createState() => _QuranManualRecordScreenState();
}

class _QuranManualRecordScreenState extends ConsumerState<QuranManualRecordScreen> {
  int _pages = 1;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return Scaffold(
      appBar: AppBar(title: Text(l.recordOutsideReading)),
      body: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(l.outsideReadingBody),
            const SizedBox(height: 24),
            Text(l.pagesRead, textAlign: TextAlign.center, style: context.text.titleSmall),
            const SizedBox(height: 8),
            QuantityStepper(value: _pages, onChanged: (v) => setState(() => _pages = v)),
            const SizedBox(height: 24),
            FilledButton(
              onPressed: () async {
                await submitRecord(context, ref, type: 'quran_page', quantity: _pages, source: 'manual');
                if (context.mounted) context.pop();
              },
              child: Text(l.confirmRecord),
            ),
          ],
        ),
      ),
    );
  }
}

class BookmarksScreen extends ConsumerWidget {
  const BookmarksScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    if (!ref.watch(authProvider).signedIn) {
      return Scaffold(appBar: AppBar(title: Text(l.bookmarks)), body: StateMessage(icon: Icons.bookmark_outline, title: l.accountRequiredTitle, body: l.bookmarkNeedsAccount));
    }
    return Scaffold(
      appBar: AppBar(title: Text(l.bookmarks)),
      body: AsyncView(
        value: ref.watch(verseBookmarkListProvider),
        onRetry: () => ref.invalidate(verseBookmarkListProvider),
        builder: (list) => list.isEmpty
            ? StateMessage(icon: Icons.bookmark_outline, title: l.noBookmarksTitle, body: l.noBookmarksBody)
            : ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  for (final b in list)
                    Card(
                      margin: const EdgeInsets.only(bottom: 8),
                      child: ListTile(
                        title: Text('${b.surahName} ${b.surahId}:${b.verseNumber}', style: context.text.titleSmall),
                        onTap: () => context.push('/quran/${b.surahId}?verse=${b.verseNumber}'),
                      ),
                    ),
                ],
              ),
      ),
    );
  }
}
