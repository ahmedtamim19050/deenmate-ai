import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:path/path.dart' as p;

import 'quran_audio.dart';

class DownloadJob {
  DownloadJob({required this.reciterId, required this.surah, required this.total});

  final String reciterId;
  final int surah;
  final int total;
  int done = 0;
  bool failed = false;
  final CancelToken cancel = CancelToken();

  String get key => jobKey(reciterId, surah);

  static String jobKey(String reciterId, int surah) => '$reciterId:$surah';
}

/// Recitation audio saved for offline listening. AlQuran Cloud's terms allow downloading
/// recitations for personal use and ask apps to cache. Files live in the app's own storage.
class QuranDownloads extends ChangeNotifier {
  QuranDownloads(this.root, {Dio? dio}) : _dio = dio ?? Dio();

  final Directory root;
  final Dio _dio;
  final Map<String, DownloadJob> jobs = {};

  File fileFor(Reciter r, int surah, int verse) => File(p.join(root.path, 'quran_audio', r.id, '${globalVerseNumber(surah, verse)}.mp3'));

  /// The saved file, or null when the verse has not been downloaded.
  File? localFile(Reciter r, int surah, int verse) {
    final f = fileFor(r, surah, verse);
    return f.existsSync() ? f : null;
  }

  int savedCount(Reciter r, int surah) {
    var n = 0;
    for (var v = 1; v <= surahVerseCounts[surah - 1]; v++) {
      if (fileFor(r, surah, v).existsSync()) n++;
    }
    return n;
  }

  DownloadJob? job(Reciter r, int surah) => jobs[DownloadJob.jobKey(r.id, surah)];

  /// Downloads verses [from] to [to]. Surahs that open with the basmala also get its recitation.
  Future<bool> download(Reciter r, int surah, {int from = 1, int? to}) async {
    final last = to ?? surahVerseCounts[surah - 1];
    final targets = <(int, int)>[
      if (surah != 1 && surah != 9) (1, 1),
      for (var v = from; v <= last; v++) (surah, v),
    ];
    final job = DownloadJob(reciterId: r.id, surah: surah, total: targets.length);
    jobs[job.key]?.cancel.cancel();
    jobs[job.key] = job;
    notifyListeners();

    for (final (s, v) in targets) {
      if (job.cancel.isCancelled) break;
      final file = fileFor(r, s, v);
      if (!file.existsSync()) {
        final part = File('${file.path}.part');
        try {
          await file.parent.create(recursive: true);
          await _dio.download(verseAudioUrl(s, v, reciter: r), part.path, cancelToken: job.cancel);
          await part.rename(file.path);
        } catch (_) {
          if (part.existsSync()) await part.delete();
          if (!job.cancel.isCancelled) job.failed = true;
          break;
        }
      }
      job.done++;
      notifyListeners();
    }
    final ok = !job.failed && !job.cancel.isCancelled;
    jobs.remove(job.key);
    notifyListeners();
    return ok;
  }

  void cancel(Reciter r, int surah) {
    job(r, surah)?.cancel.cancel();
  }

  /// Removes the surah's verses. The shared basmala file is kept, as other surahs use it.
  Future<void> delete(Reciter r, int surah) async {
    for (var v = 1; v <= surahVerseCounts[surah - 1]; v++) {
      final f = fileFor(r, surah, v);
      if (f.existsSync()) await f.delete();
    }
    notifyListeners();
  }
}

final quranDownloadsProvider = Provider<QuranDownloads>((_) => throw UnimplementedError());
