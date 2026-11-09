import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/widgets/common.dart';
import 'quran_audio.dart';
import 'quran_audio_controller.dart';
import 'quran_downloads.dart';
import 'quran_player.dart';
import 'quran_repository.dart';

/// Saves recitation audio and Quran text per surah for offline use, for the current reciter.
class QuranDownloadsScreen extends ConsumerWidget {
  const QuranDownloadsScreen({super.key});

  Future<void> _start(BuildContext context, WidgetRef ref, Reciter r, Surah s, {int from = 1, int? to}) async {
    final l = context.l10n;
    // Opening the surah through the normal provider also stores its text and translation offline.
    ref.read(surahDetailProvider((id: s.id, edition: defaultTranslationEdition)).future).ignore();
    final ok = await ref.read(quranDownloadsProvider).download(r, s.id, from: from, to: to);
    if (context.mounted) context.toast(ok ? l.downloadDone(s.name) : l.downloadStopped);
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final downloads = ref.watch(quranDownloadsProvider);
    final audio = ref.watch(quranAudioProvider);
    return Scaffold(
      appBar: AppBar(title: Text(l.downloads)),
      body: ListenableBuilder(
        listenable: Listenable.merge([downloads, audio]),
        builder: (context, _) {
          final reciter = audio.reciter;
          return AsyncView(
            value: ref.watch(surahListProvider),
            onRetry: () => ref.invalidate(surahListProvider),
            builder: (surahs) => ListView.builder(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
              itemCount: surahs.length + 1,
              itemBuilder: (_, i) {
                if (i == 0) {
                  return Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Card(
                        child: ListTile(
                          leading: const Icon(Icons.record_voice_over_outlined),
                          title: Text(reciter.name),
                          subtitle: Text(l.reciter),
                          trailing: const Icon(Icons.chevron_right),
                          onTap: () => showReciterSheet(context, audio),
                        ),
                      ),
                      Padding(padding: const EdgeInsets.symmetric(vertical: 8), child: Text(l.downloadsHint, style: context.text.bodySmall)),
                    ],
                  );
                }
                final s = surahs[i - 1];
                final job = downloads.job(reciter, s.id);
                final saved = downloads.savedCount(reciter, s.id);
                return Card(
                  margin: const EdgeInsets.only(bottom: 8),
                  child: ListTile(
                    title: Text('${s.id}. ${s.name}'),
                    subtitle: job != null
                        ? Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(l.downloadingProgress(job.done, job.total)),
                              const SizedBox(height: 4),
                              LinearProgressIndicator(value: job.total == 0 ? null : job.done / job.total),
                            ],
                          )
                        : Text(l.downloadedCount(saved, s.verses)),
                    trailing: job != null
                        ? IconButton(tooltip: l.cancel, onPressed: () => downloads.cancel(reciter, s.id), icon: const Icon(Icons.close))
                        : PopupMenuButton<String>(
                            onSelected: (v) async {
                              if (v == 'all') {
                                await _start(context, ref, reciter, s);
                              } else if (v == 'range') {
                                final range = await pickVerseRange(context, s.verses);
                                if (range != null && context.mounted) await _start(context, ref, reciter, s, from: range.$1, to: range.$2);
                              } else if (v == 'delete') {
                                await downloads.delete(reciter, s.id);
                              }
                            },
                            itemBuilder: (_) => [
                              PopupMenuItem(value: 'all', child: Text(l.downloadSurah)),
                              PopupMenuItem(value: 'range', child: Text(l.downloadRange)),
                              if (saved > 0) PopupMenuItem(value: 'delete', child: Text(l.deleteDownload)),
                            ],
                            icon: Icon(saved >= s.verses ? Icons.download_done : Icons.download_outlined),
                          ),
                  ),
                );
              },
            ),
          );
        },
      ),
    );
  }
}
