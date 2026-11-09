import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../app/theme/app_theme.dart';
import '../../core/widgets/common.dart';
import 'quran_audio.dart';
import 'quran_audio_controller.dart';
import 'quran_repository.dart';

/// Wraps every screen so the mini player stays visible across the app while a surah plays.
/// It sits above the Navigator, so it navigates through [router] and avoids tooltips,
/// which need an Overlay.
class QuranMiniPlayerHost extends ConsumerWidget {
  const QuranMiniPlayerHost({super.key, required this.router, required this.child});

  final GoRouter router;
  final Widget child;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final audio = ref.watch(quranAudioProvider);
    return ListenableBuilder(
      listenable: audio,
      builder: (context, _) {
        final media = MediaQuery.of(context);
        final keyboardOpen = media.viewInsets.bottom > 0;
        final show = audio.active && !keyboardOpen;
        // The tree keeps the same shape whether or not the bar shows, so the Navigator keeps its state.
        return Column(
          children: [
            Expanded(
              child: MediaQuery(
                data: show ? media.removePadding(removeBottom: true).removeViewPadding(removeBottom: true) : media,
                child: child,
              ),
            ),
            if (show) _MiniPlayer(audio: audio, onOpen: () => router.push('/quran/player')) else const SizedBox.shrink(),
          ],
        );
      },
    );
  }
}

class _MiniPlayer extends StatelessWidget {
  const _MiniPlayer({required this.audio, required this.onOpen});

  final QuranAudioController audio;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final colors = Theme.of(context).colorScheme;
    final verse = audio.currentVerse;
    final subtitle = audio.wordClip
        ? (audio.wordLabel ?? l.wordByWord)
        : audio.onBasmala
        ? l.bismillah
        : verse != null
        ? l.verseNumber(verse)
        : (audio.lastVerse != null ? l.verseNumber(audio.lastVerse!) : '');
    return Material(
      color: colors.surfaceContainerHigh,
      elevation: 6,
      child: SafeArea(
        top: false,
        child: InkWell(
          onTap: onOpen,
          child: Padding(
            padding: const EdgeInsets.fromLTRB(12, 6, 4, 6),
            child: Row(
              children: [
                Icon(Icons.graphic_eq, color: colors.primary),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('${audio.surahName} · $subtitle', maxLines: 1, overflow: TextOverflow.ellipsis, style: Theme.of(context).textTheme.titleSmall),
                      Text(
                        audio.error ? l.audioUnavailable : audio.reciter.name,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: Theme.of(context).textTheme.bodySmall?.copyWith(color: audio.error ? colors.error : null),
                      ),
                    ],
                  ),
                ),
                Semantics(
                  label: l.previousVerse,
                  button: true,
                  child: IconButton(onPressed: audio.wordClip ? null : audio.previous, icon: const Icon(Icons.skip_previous)),
                ),
                Semantics(
                  label: audio.playing ? l.pauseRecitation : l.playRecitation,
                  button: true,
                  child: audio.loading
                      ? const Padding(padding: EdgeInsets.all(12), child: SizedBox.square(dimension: 24, child: CircularProgressIndicator(strokeWidth: 2.5)))
                      : IconButton(onPressed: audio.toggle, icon: Icon(audio.playing ? Icons.pause_circle_filled : Icons.play_circle_fill, size: 36, color: colors.primary)),
                ),
                Semantics(
                  label: l.nextVerse,
                  button: true,
                  child: IconButton(onPressed: audio.wordClip ? null : audio.next, icon: const Icon(Icons.skip_next)),
                ),
                Semantics(
                  label: l.stopPlayback,
                  button: true,
                  child: IconButton(onPressed: audio.stop, icon: const Icon(Icons.close)),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// Lists all reciters and switches the shared player to the one chosen.
void showReciterSheet(BuildContext context, QuranAudioController audio) {
  final l = context.l10n;
  showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    builder: (c) => SafeArea(
      child: ConstrainedBox(
        constraints: BoxConstraints(maxHeight: MediaQuery.sizeOf(c).height * 0.75),
        child: ListView(
          shrinkWrap: true,
          padding: const EdgeInsets.symmetric(vertical: 12),
          children: [
            Padding(padding: const EdgeInsets.fromLTRB(16, 0, 16, 8), child: Text(l.reciter, style: c.text.titleMedium)),
            for (final r in reciters)
              ListTile(
                title: Text(r.name),
                subtitle: Text(
                  [r.arabicName, if (r.lowQuality) l.reciterLowQuality, if (r.longOpenings) l.reciterOpeningsWarning].join(' · '),
                ),
                trailing: audio.reciter.id == r.id ? const Icon(Icons.check) : null,
                onTap: () {
                  Navigator.pop(c);
                  audio.setReciter(r);
                },
              ),
          ],
        ),
      ),
    ),
  );
}

class QuranPlayerScreen extends ConsumerWidget {
  const QuranPlayerScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final audio = ref.watch(quranAudioProvider);
    final l = context.l10n;
    return ListenableBuilder(
      listenable: audio,
      builder: (context, _) {
        if (!audio.active) {
          return Scaffold(appBar: AppBar(title: Text(l.nowPlaying)), body: StateMessage(icon: Icons.headphones_outlined, title: l.nothingPlaying));
        }
        final surah = audio.surahId!;
        final verse = audio.currentVerse ?? audio.lastVerse;
        final detail = ref.watch(surahDetailProvider((id: surah, edition: defaultTranslationEdition))).value;
        final verseData = verse == null ? null : detail?.verses.where((v) => v.number == verse).firstOrNull;
        return Scaffold(
          appBar: AppBar(
            title: Text(l.nowPlaying),
            actions: [
              IconButton(tooltip: l.openInReader, onPressed: () => context.push('/quran/$surah?verse=${verse ?? 1}'), icon: const Icon(Icons.menu_book_outlined)),
            ],
          ),
          body: ListView(
            padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
            children: [
              Text(audio.surahName, textAlign: TextAlign.center, style: context.text.headlineSmall),
              const SizedBox(height: 4),
              Text(
                audio.wordClip ? (audio.wordLabel ?? l.wordByWord) : (audio.onBasmala ? l.bismillah : (verse != null ? '$surah:$verse' : '')),
                textAlign: TextAlign.center,
                style: context.text.titleMedium?.copyWith(color: context.colors.primary),
              ),
              const SizedBox(height: 4),
              ActionChip(
                avatar: const Icon(Icons.record_voice_over_outlined, size: 18),
                label: Text(audio.reciter.name),
                onPressed: () => showReciterSheet(context, audio),
              ),
              if (verseData != null && !audio.wordClip && !audio.onBasmala) ...[
                const SizedBox(height: 16),
                Text(verseData.arabic, textAlign: TextAlign.center, textDirection: TextDirection.rtl, style: quranTextStyle(context, size: 28)),
                if (verseData.translation != null) ...[
                  const SizedBox(height: 8),
                  Text(verseData.translation!, textAlign: TextAlign.center, style: context.text.bodyLarge?.copyWith(fontFamily: 'HindSiliguri', height: 1.6)),
                ],
              ],
              if (audio.error) Padding(padding: const EdgeInsets.only(top: 12), child: Text(l.audioUnavailable, textAlign: TextAlign.center, style: TextStyle(color: context.colors.error))),
              const SizedBox(height: 20),
              _Progress(audio: audio),
              Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  IconButton(tooltip: l.previousVerse, iconSize: 36, onPressed: audio.wordClip ? null : audio.previous, icon: const Icon(Icons.skip_previous)),
                  const SizedBox(width: 12),
                  audio.loading
                      ? const SizedBox.square(dimension: 72, child: Padding(padding: EdgeInsets.all(18), child: CircularProgressIndicator()))
                      : IconButton(
                          tooltip: audio.playing ? l.pauseRecitation : l.playRecitation,
                          iconSize: 72,
                          color: context.colors.primary,
                          onPressed: audio.toggle,
                          icon: Icon(audio.playing ? Icons.pause_circle_filled : Icons.play_circle_fill),
                        ),
                  const SizedBox(width: 12),
                  IconButton(tooltip: l.nextVerse, iconSize: 36, onPressed: audio.wordClip ? null : audio.next, icon: const Icon(Icons.skip_next)),
                ],
              ),
              const SizedBox(height: 16),
              SectionHeader(l.repeat),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  for (final (mode, label) in [
                    (QuranRepeat.off, l.repeatOff),
                    (QuranRepeat.verse, l.repeatVerse),
                    (QuranRepeat.range, audio.repeat == QuranRepeat.range && audio.rangeStart != null ? l.repeatRangeValue(audio.rangeStart!, audio.rangeEnd!) : l.repeatRange),
                    (QuranRepeat.surah, l.repeatSurah),
                  ])
                    ChoiceChip(
                      label: Text(label),
                      selected: audio.repeat == mode,
                      onSelected: (_) async {
                        if (mode == QuranRepeat.range) {
                          final range = await pickVerseRange(context, audio.verseCount, initialFrom: verse ?? 1);
                          if (range != null) await audio.setRepeat(QuranRepeat.range, from: range.$1, to: range.$2);
                        } else {
                          await audio.setRepeat(mode);
                        }
                      },
                    ),
                ],
              ),
              SectionHeader(l.playbackSpeed),
              Wrap(
                spacing: 8,
                children: [
                  for (final s in const [0.75, 1.0, 1.25, 1.5])
                    ChoiceChip(label: Text('${s}x'), selected: audio.speed == s, onSelected: (_) => audio.setSpeed(s)),
                ],
              ),
              SectionHeader(l.sleepTimer),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  ChoiceChip(label: Text(l.sleepOff), selected: audio.sleepAt == null && !audio.sleepAtVerseEnd, onSelected: (_) => audio.setSleep()),
                  for (final m in const [5, 10, 15, 30, 60])
                    ChoiceChip(label: Text(l.sleepMinutes(m)), selected: false, onSelected: (_) => audio.setSleep(minutes: m)),
                  ChoiceChip(label: Text(l.sleepEndOfVerse), selected: audio.sleepAtVerseEnd, onSelected: (_) => audio.setSleep(endOfVerse: true)),
                ],
              ),
              if (audio.sleepAt != null)
                Padding(
                  padding: const EdgeInsets.only(top: 8),
                  child: Text(l.sleepActive(TimeOfDay.fromDateTime(audio.sleepAt!).format(context)), style: context.text.bodySmall),
                ),
              if (!audio.wordClip && audio.queue.isNotEmpty) ...[
                SectionHeader(l.queue),
                Card(
                  child: Column(
                    children: [
                      for (var i = 0; i < audio.queue.length && i < 400; i++)
                        ListTile(
                          dense: true,
                          selected: i == audio.index,
                          leading: Icon(i == audio.index ? Icons.graphic_eq : Icons.music_note_outlined, size: 20),
                          title: Text(audio.queue[i].verse == null ? l.bismillah : '${audio.surahName} ${audio.queue[i].surah}:${audio.queue[i].verse}'),
                          onTap: () => audio.player.seek(Duration.zero, index: i),
                        ),
                    ],
                  ),
                ),
              ],
              const SizedBox(height: 12),
              Text(l.backgroundPlaybackNote, style: context.text.bodySmall, textAlign: TextAlign.center),
            ],
          ),
        );
      },
    );
  }
}

class _Progress extends StatelessWidget {
  const _Progress({required this.audio});

  final QuranAudioController audio;

  String _fmt(Duration d) => '${d.inMinutes}:${(d.inSeconds % 60).toString().padLeft(2, '0')}';

  @override
  Widget build(BuildContext context) {
    return StreamBuilder<Duration>(
      stream: audio.player.positionStream,
      builder: (context, snap) {
        final total = audio.player.duration ?? Duration.zero;
        final pos = snap.data ?? Duration.zero;
        final max = total.inMilliseconds.toDouble();
        return Column(
          children: [
            Slider(
              value: max <= 0 ? 0 : pos.inMilliseconds.clamp(0, total.inMilliseconds).toDouble(),
              max: max <= 0 ? 1 : max,
              onChanged: max <= 0 ? null : (v) => audio.player.seek(Duration(milliseconds: v.round())),
            ),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 24),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [Text(_fmt(pos), style: context.text.bodySmall), Text(_fmt(total), style: context.text.bodySmall)],
              ),
            ),
          ],
        );
      },
    );
  }
}

/// Asks for a verse range within a surah of [verseCount] verses.
Future<(int, int)?> pickVerseRange(BuildContext context, int verseCount, {int initialFrom = 1}) {
  final l = context.l10n;
  final from = TextEditingController(text: '$initialFrom');
  final to = TextEditingController(text: '${(initialFrom + 4).clamp(1, verseCount)}');
  final form = GlobalKey<FormState>();
  String? check(String? v) {
    final n = int.tryParse(v ?? '');
    return n == null || n < 1 || n > verseCount ? l.verseRangeInvalid(verseCount) : null;
  }

  return showDialog<(int, int)>(
    context: context,
    builder: (c) => AlertDialog(
      title: Text(l.repeatRange),
      content: Form(
        key: form,
        child: Row(
          children: [
            Expanded(child: TextFormField(controller: from, keyboardType: TextInputType.number, decoration: InputDecoration(labelText: l.rangeFrom), validator: check)),
            const SizedBox(width: 12),
            Expanded(
              child: TextFormField(
                controller: to,
                keyboardType: TextInputType.number,
                decoration: InputDecoration(labelText: l.rangeTo),
                validator: (v) => check(v) ?? ((int.tryParse(v!) ?? 0) < (int.tryParse(from.text) ?? 0) ? l.verseRangeInvalid(verseCount) : null),
              ),
            ),
          ],
        ),
      ),
      actions: [
        TextButton(onPressed: () => Navigator.pop(c), child: Text(l.cancel)),
        FilledButton(
          onPressed: () {
            if (form.currentState!.validate()) Navigator.pop(c, (int.parse(from.text), int.parse(to.text)));
          },
          child: Text(l.save),
        ),
      ],
    ),
  );
}
