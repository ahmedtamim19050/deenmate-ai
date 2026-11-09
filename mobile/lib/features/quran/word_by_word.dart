import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/widgets/common.dart';
import 'quran_audio_controller.dart';
import 'quran_content_api.dart';
import 'quran_settings.dart';

/// The meaning line under a word, in the language chosen in Quran settings.
String? wordMeaning(QuranWord w, String mode) => switch (mode) {
  'en' => w.english,
  'both' => [w.bangla, w.english].whereType<String>().join(' · '),
  _ => w.bangla ?? w.english,
};

/// Arabic words of one verse, right to left, each with its meaning underneath.
class VerseWords extends StatelessWidget {
  const VerseWords({super.key, required this.words, required this.settings, required this.selectedId, required this.onTap});

  final List<QuranWord> words;
  final QuranSettings settings;
  final int? selectedId;
  final ValueChanged<QuranWord> onTap;

  @override
  Widget build(BuildContext context) {
    return Directionality(
      textDirection: TextDirection.rtl,
      child: Wrap(
        spacing: 6,
        runSpacing: 8,
        children: [
          for (final w in words)
            InkWell(
              borderRadius: BorderRadius.circular(10),
              onTap: () => onTap(w),
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
                decoration: BoxDecoration(
                  color: w.id == selectedId ? context.colors.primaryContainer : context.colors.surfaceContainerHighest.withValues(alpha: 0.5),
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(color: w.id == selectedId ? context.colors.primary : Colors.transparent),
                ),
                child: ConstrainedBox(
                  constraints: const BoxConstraints(maxWidth: 150),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(w.arabic, style: TextStyle(fontFamily: 'ScheherazadeNew', fontSize: settings.arabicSize - 6, height: 1.5)),
                      if (settings.wordTransliteration && w.transliteration != null)
                        Text(w.transliteration!, textDirection: TextDirection.ltr, style: context.text.labelSmall?.copyWith(fontStyle: FontStyle.italic)),
                      if (wordMeaning(w, settings.wordMeaning) case final m? when m.isNotEmpty)
                        Text(m, textAlign: TextAlign.center, textDirection: TextDirection.ltr, style: context.text.bodySmall?.copyWith(fontFamily: 'HindSiliguri')),
                    ],
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

/// One word in detail, with audio, repetition and previous/next across the whole surah.
Future<void> showWordStudy(
  BuildContext context, {
  required List<QuranWord> words,
  required int startIndex,
  required int surah,
  ValueChanged<QuranWord>? onSelect,
}) {
  return showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    builder: (_) => _WordStudySheet(words: words, startIndex: startIndex, surah: surah, onSelect: onSelect),
  );
}

class _WordStudySheet extends ConsumerStatefulWidget {
  const _WordStudySheet({required this.words, required this.startIndex, required this.surah, this.onSelect});

  final List<QuranWord> words;
  final int startIndex;
  final int surah;
  final ValueChanged<QuranWord>? onSelect;

  @override
  ConsumerState<_WordStudySheet> createState() => _WordStudySheetState();
}

class _WordStudySheetState extends ConsumerState<_WordStudySheet> {
  late int _i = widget.startIndex;
  int _times = 1;

  void _go(int delta) {
    final next = (_i + delta).clamp(0, widget.words.length - 1);
    setState(() => _i = next);
    widget.onSelect?.call(widget.words[next]);
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final w = widget.words[_i];
    final settings = ref.watch(quranSettingsProvider);
    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(20, 12, 20, 16),
        child: _WordCard(
          word: w,
          surah: widget.surah,
          settings: settings,
          reveal: true,
          times: _times,
          onTimes: (t) => setState(() => _times = t),
          progress: l.wordProgress(_i + 1, widget.words.length),
          onPrevious: _i > 0 ? () => _go(-1) : null,
          onNext: _i < widget.words.length - 1 ? () => _go(1) : null,
        ),
      ),
    );
  }
}

class _WordCard extends ConsumerWidget {
  const _WordCard({
    required this.word,
    required this.surah,
    required this.settings,
    required this.reveal,
    required this.times,
    required this.onTimes,
    required this.progress,
    this.onPrevious,
    this.onNext,
    this.onReveal,
  });

  final QuranWord word;
  final int surah;
  final QuranSettings settings;
  final bool reveal;
  final int times;
  final ValueChanged<int> onTimes;
  final String progress;
  final VoidCallback? onPrevious;
  final VoidCallback? onNext;
  final VoidCallback? onReveal;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final audio = ref.read(quranAudioProvider);
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text('$progress · $surah:${word.verse}', textAlign: TextAlign.center, style: context.text.labelMedium),
        const SizedBox(height: 12),
        Text(word.arabic, textAlign: TextAlign.center, textDirection: TextDirection.rtl, style: const TextStyle(fontFamily: 'ScheherazadeNew', fontSize: 52, height: 1.6)),
        if (word.transliteration != null) Text(word.transliteration!, textAlign: TextAlign.center, style: context.text.titleMedium?.copyWith(fontStyle: FontStyle.italic)),
        const SizedBox(height: 12),
        if (reveal) ...[
          if (word.bangla != null) _Meaning(label: l.bangla, text: word.bangla!, bangla: true),
          if (word.english != null) _Meaning(label: l.english, text: word.english!),
        ] else
          Center(child: FilledButton.tonalIcon(onPressed: onReveal, icon: const Icon(Icons.visibility_outlined), label: Text(l.showMeaning))),
        const SizedBox(height: 12),
        Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            for (final t in const [1, 3, 5])
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 4),
                child: ChoiceChip(label: Text(l.repeatTimes(t)), selected: times == t, onSelected: (_) => onTimes(t)),
              ),
          ],
        ),
        const SizedBox(height: 8),
        Row(
          children: [
            IconButton(tooltip: l.previousWord, onPressed: onPrevious, icon: const Icon(Icons.chevron_left)),
            Expanded(
              child: FilledButton.icon(
                onPressed: word.audioUrl == null ? null : () => audio.playWord(word.audioUrl!, times: times, label: '${word.arabic} · $surah:${word.verse}'),
                icon: const Icon(Icons.volume_up_outlined),
                label: Text(l.playWord),
              ),
            ),
            IconButton(tooltip: l.nextWord, onPressed: onNext, icon: const Icon(Icons.chevron_right)),
          ],
        ),
        const SizedBox(height: 8),
        Text(l.wordDataSource, textAlign: TextAlign.center, style: context.text.bodySmall),
      ],
    );
  }
}

class _Meaning extends StatelessWidget {
  const _Meaning({required this.label, required this.text, this.bangla = false});

  final String label;
  final String text;
  final bool bangla;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 6),
    child: Column(
      children: [
        Text(label, style: context.text.labelSmall),
        Text(text, textAlign: TextAlign.center, style: context.text.titleMedium?.copyWith(fontFamily: bangla ? 'HindSiliguri' : null)),
      ],
    ),
  );
}

/// Steps through a surah word by word. Meanings stay hidden until asked for, and each word
/// can play automatically when it is reached.
class WordLearningScreen extends ConsumerStatefulWidget {
  const WordLearningScreen({super.key, required this.surah, this.startVerse});

  final int surah;
  final int? startVerse;

  @override
  ConsumerState<WordLearningScreen> createState() => _WordLearningScreenState();
}

class _WordLearningScreenState extends ConsumerState<WordLearningScreen> {
  int? _i;
  bool _reveal = false;
  bool _autoPlay = true;
  int _times = 1;

  void _go(List<QuranWord> words, int next) {
    setState(() {
      _i = next.clamp(0, words.length - 1);
      _reveal = false;
    });
    final w = words[_i!];
    if (_autoPlay && w.audioUrl != null) {
      ref.read(quranAudioProvider).playWord(w.audioUrl!, times: _times, label: '${w.arabic} · ${widget.surah}:${w.verse}');
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final settings = ref.watch(quranSettingsProvider);
    return Scaffold(
      appBar: AppBar(
        title: Text(l.wordLearning),
        actions: [
          Row(
            children: [
              Text(l.autoPlayShort, style: context.text.labelMedium),
              Switch(value: _autoPlay, onChanged: (v) => setState(() => _autoPlay = v)),
            ],
          ),
        ],
      ),
      body: AsyncView(
        value: ref.watch(surahWordsProvider(widget.surah)),
        onRetry: () => ref.invalidate(surahWordsProvider(widget.surah)),
        builder: (byVerse) {
          final words = [
            for (final v in byVerse.keys.toList()..sort()) ...byVerse[v]!,
          ];
          if (words.isEmpty) return StateMessage(icon: Icons.translate, title: l.wordByWordUnavailable);
          final i = _i ??= (words.indexWhere((w) => w.verse == (widget.startVerse ?? 1))).clamp(0, words.length - 1);
          return ListView(
            padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
            children: [
              LinearProgressIndicator(value: (i + 1) / words.length),
              const SizedBox(height: 20),
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: _WordCard(
                    word: words[i],
                    surah: widget.surah,
                    settings: settings,
                    reveal: _reveal,
                    onReveal: () => setState(() => _reveal = true),
                    times: _times,
                    onTimes: (t) => setState(() => _times = t),
                    progress: l.wordProgress(i + 1, words.length),
                    onPrevious: i > 0 ? () => _go(words, i - 1) : null,
                    onNext: i < words.length - 1 ? () => _go(words, i + 1) : null,
                  ),
                ),
              ),
            ],
          );
        },
      ),
    );
  }
}
