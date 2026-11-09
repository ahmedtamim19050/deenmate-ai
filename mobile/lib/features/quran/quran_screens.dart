import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart' show ScrollCacheExtent;
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:just_audio/just_audio.dart';

import '../../app/theme/app_theme.dart';
import '../../core/network/api_exception.dart';
import '../../core/widgets/common.dart';
import '../../shared/providers/providers.dart';
import '../../shared/repositories/activity_repository.dart';
import '../tracker/record_form.dart';
import 'quran_audio.dart';
import 'quran_repository.dart';

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
  bool _showTransliteration = false;
  bool _showTranslation = true;
  final _startedAt = DateTime.now();
  final Set<int> _seenPages = {};
  int _maxVerseSeen = 0;
  String? _surahName;
  late final ActivityRepository _activities;

  final _player = AudioPlayer();
  bool _audioLoading = false;
  bool _playing = false;
  String? _audioError;
  Timer? _audioTick;
  int _audioSeconds = 0;

  /// Verse numbers in play order. Null is the basmala recited before a surah's first verse.
  List<int?> _queue = const [];
  int? _currentVerse;

  @override
  void initState() {
    super.initState();
    _activities = ref.read(activityRepositoryProvider);
    _player.playerStateStream.listen((s) {
      if (!mounted) return;
      setState(() {
        final finished = s.processingState == ProcessingState.completed;
        _playing = s.playing && !finished;
        if (finished) {
          _queue = const [];
          _currentVerse = null;
        }
      });
    });
    _player.currentIndexStream.listen((i) {
      if (mounted && i != null && i < _queue.length) setState(() => _currentVerse = _queue[i]);
    });
    _player.playbackEventStream.listen(
      (_) {},
      onError: (Object _, StackTrace _) {
        if (!mounted) return;
        setState(() {
          _audioError = context.l10n.audioUnavailable;
          _audioLoading = false;
          _queue = const [];
          _currentVerse = null;
        });
      },
    );
    _audioTick = Timer.periodic(const Duration(seconds: 1), (_) {
      if (_player.playing) _audioSeconds++;
    });
  }

  @override
  void dispose() {
    _audioTick?.cancel();
    _player.dispose();
    _recordProgress();
    super.dispose();
  }

  void _recordProgress() {
    // The last page may be unfinished, so only pages already passed are counted.
    final finished = _seenPages.length > 1 ? _seenPages.length - 1 : 0;
    final allowed = DateTime.now().difference(_startedAt).inSeconds ~/ _minSecondsPerPage;
    final pages = finished < allowed ? finished : allowed;
    if (pages > 0) {
      unawaited(_activities.record(type: 'quran_page', quantity: pages, source: 'in_app', metadata: {'surah': widget.surahId}));
    }
    final minutes = _audioSeconds ~/ 60;
    if (minutes >= 5) {
      unawaited(_activities.record(type: 'audio_learning', quantity: minutes, source: 'in_app', metadata: {'surah': widget.surahId}));
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

  /// Starts at [verse] and carries on through the rest of the surah.
  Future<void> _playFrom(SurahDetail d, int verse, {bool withBasmala = false}) async {
    final l = context.l10n;
    final queue = <int?>[
      if (withBasmala) null,
      for (final v in d.verses)
        if (v.number >= verse) v.number,
    ];
    setState(() {
      _audioError = null;
      _audioLoading = true;
      _queue = queue;
      _currentVerse = queue.first;
    });
    try {
      await _player.setAudioSources([
        // Al-Fatihah's first verse is the basmala recitation.
        for (final n in queue) AudioSource.uri(Uri.parse(n == null ? verseAudioUrl(1, 1) : verseAudioUrl(widget.surahId, n))),
      ]);
      unawaited(_player.play());
    } catch (_) {
      if (mounted) {
        setState(() {
          _audioError = l.audioUnavailable;
          _queue = const [];
          _currentVerse = null;
        });
      }
    } finally {
      if (mounted) setState(() => _audioLoading = false);
    }
  }

  Future<void> _togglePlayback(SurahDetail d) async {
    if (_playing) {
      await _player.pause();
    } else if (_queue.isNotEmpty) {
      unawaited(_player.play());
    } else {
      await _playFrom(d, _startVerse, withBasmala: d.basmala != null && _startVerse == 1);
    }
  }

  Future<void> _toggleVerse(SurahDetail d, Verse v) async {
    if (_queue.isNotEmpty && _currentVerse == v.number) {
      await _togglePlayback(d);
    } else {
      await _playFrom(d, v.number);
    }
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

  void _options(SurahDetail d) {
    final l = context.l10n;
    final editions = ref.read(editionsProvider).value ?? const [];
    showModalBottomSheet<void>(
      context: context,
      builder: (c) => StatefulBuilder(
        builder: (c, setSheet) => SafeArea(
          child: ListView(
            shrinkWrap: true,
            padding: const EdgeInsets.all(16),
            children: [
              SwitchListTile(
                title: Text(l.showTranslation),
                value: _showTranslation,
                onChanged: (v) {
                  setState(() => _showTranslation = v);
                  setSheet(() {});
                },
              ),
              SwitchListTile(
                title: Text(l.showTransliteration),
                value: _showTransliteration,
                onChanged: (v) {
                  setState(() => _showTransliteration = v);
                  setSheet(() {});
                },
              ),
              const Divider(),
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
              const Divider(),
              Text(l.textSourceLabel(d.textSource ?? l.unknown), style: context.text.bodySmall),
              const SizedBox(height: 4),
              Text(l.translationSourceLabel(d.editionName ?? l.unavailableShort), style: context.text.bodySmall),
            ],
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    // Loaded here so the translation picker has its list the first time it opens.
    ref.watch(editionsProvider);
    final detail = ref.watch(surahDetailProvider((id: widget.surahId, edition: _edition)));
    final bookmarks = ref.watch(verseBookmarksProvider).value ?? const {};
    return Scaffold(
      appBar: AppBar(
        title: Text(detail.value?.surah.name ?? l.quranTitle),
        actions: [
          IconButton(
            tooltip: _playing ? l.pauseRecitation : l.playRecitation,
            onPressed: _audioLoading || !detail.hasValue ? null : () => _togglePlayback(detail.value!),
            icon: Icon(_playing ? Icons.pause_circle_outline : Icons.play_circle_outline),
          ),
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
                    if (_audioError != null) Text(_audioError!, style: TextStyle(color: context.colors.error)),
                    if (_startVerse > 1) TextButton(onPressed: () => setState(() => _startVerse = 1), child: Text(l.showFromStart)),
                    if (d.basmala != null && _startVerse == 1)
                      Padding(
                        padding: const EdgeInsets.symmetric(vertical: 12),
                        child: Text(d.basmala!, textAlign: TextAlign.center, textDirection: TextDirection.rtl, style: quranTextStyle(context, size: 24)),
                      ),
                  ],
                );
              }
              final v = verses[i - 1];
              _markSeen(v);
              return _VerseCard(
                verse: v,
                showTranslation: _showTranslation,
                showTransliteration: _showTransliteration,
                bookmarked: bookmarks.containsKey(v.id),
                onBookmark: () => _toggleBookmark(v, bookmarks),
                isCurrent: _queue.isNotEmpty && _currentVerse == v.number,
                playing: _playing && _currentVerse == v.number,
                onPlay: _audioLoading ? null : () => _toggleVerse(d, v),
              );
            },
          );
        },
      ),
    );
  }
}

class _VerseCard extends StatelessWidget {
  const _VerseCard({
    required this.verse,
    required this.showTranslation,
    required this.showTransliteration,
    required this.bookmarked,
    required this.onBookmark,
    required this.isCurrent,
    required this.playing,
    required this.onPlay,
  });

  final Verse verse;
  final bool showTranslation;
  final bool showTransliteration;
  final bool bookmarked;
  final VoidCallback onBookmark;
  final bool isCurrent;
  final bool playing;
  final VoidCallback? onPlay;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return Card(
      color: isCurrent ? context.colors.primaryContainer.withValues(alpha: 0.45) : null,
      margin: const EdgeInsets.only(bottom: 10),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 8, 8, 14),
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
                IconButton(
                  tooltip: bookmarked ? l.removeBookmark : l.addBookmark,
                  onPressed: onBookmark,
                  icon: Icon(bookmarked ? Icons.bookmark : Icons.bookmark_outline, color: bookmarked ? context.colors.primary : null),
                ),
              ],
            ),
            Padding(
              padding: const EdgeInsets.only(right: 8),
              child: Text(verse.arabic, textDirection: TextDirection.rtl, textAlign: TextAlign.right, style: quranTextStyle(context)),
            ),
            if (showTransliteration && verse.transliteration != null) ...[
              const SizedBox(height: 8),
              Text(verse.transliteration!, style: context.text.bodySmall?.copyWith(fontStyle: FontStyle.italic)),
            ],
            if (showTranslation) ...[
              const SizedBox(height: 8),
              Text(verse.translation ?? l.translationUnavailable, style: context.text.bodyLarge?.copyWith(fontFamily: 'HindSiliguri', height: 1.7)),
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
