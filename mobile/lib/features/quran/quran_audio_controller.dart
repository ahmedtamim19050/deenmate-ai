import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:just_audio/just_audio.dart';
import 'package:just_audio_background/just_audio_background.dart';

import '../../shared/providers/providers.dart';
import '../../shared/repositories/activity_repository.dart';
import 'quran_audio.dart';
import 'quran_downloads.dart';

enum QuranRepeat { off, verse, range, surah }

/// One item in the play queue. A null verse is the basmala recited before a surah.
class QueueEntry {
  const QueueEntry(this.surah, this.verse);

  final int surah;
  final int? verse;
}

/// The app's only audio player. It outlives the reader so the mini player, the full player,
/// the notification and the lock screen all control the same recitation.
class QuranAudioController extends ChangeNotifier {
  QuranAudioController(this._ref) {
    _subs.add(player.playerStateStream.listen(_onState));
    _subs.add(player.currentIndexStream.listen((i) {
      if (i != null && i != index) {
        index = i;
        if (!wordClip && currentVerse != null) lastVerse = currentVerse;
        notifyListeners();
      }
    }));
    _subs.add(player.positionDiscontinuityStream.listen((d) {
      if (d.reason == PositionDiscontinuityReason.autoAdvance && sleepAtVerseEnd) {
        sleepAtVerseEnd = false;
        player.pause();
        player.seek(Duration.zero);
        notifyListeners();
      }
    }));
    _subs.add(player.playbackEventStream.listen((_) {}, onError: (Object _, StackTrace _) => _fail()));
    _tick = Timer.periodic(const Duration(seconds: 1), (_) {
      if (player.playing && !wordClip) _listenedSeconds++;
    });
  }

  final Ref _ref;
  final AudioPlayer player = AudioPlayer();
  final List<StreamSubscription<Object?>> _subs = [];
  late final Timer _tick;

  late Reciter reciter = reciterById(_ref.read(prefsProvider).quranReciter);
  int? surahId;
  String surahName = '';
  bool hasBasmala = false;
  List<QueueEntry> queue = const [];
  int? index;
  bool playing = false;
  bool loading = false;
  bool finished = false;
  bool error = false;

  /// The last verse heard, so playback can resume there after a word clip or the end.
  int? lastVerse;

  QuranRepeat repeat = QuranRepeat.off;
  int? rangeStart;
  int? rangeEnd;
  double speed = 1;
  DateTime? sleepAt;
  bool sleepAtVerseEnd = false;
  Timer? _sleepTimer;

  /// A single word being practised from the word-by-word screens.
  bool wordClip = false;
  String? wordLabel;

  int _listenedSeconds = 0;
  int? _listenedSurah;

  bool get active => surahId != null;
  QueueEntry? get current => index != null && index! < queue.length ? queue[index!] : null;
  int? get currentVerse => wordClip ? null : current?.verse;
  bool get onBasmala => !wordClip && current != null && current!.verse == null;
  int get verseCount => surahId == null ? 0 : surahVerseCounts[surahId! - 1];

  bool isPlayingVerse(int surah, int verse) => surahId == surah && currentVerse == verse && !finished;

  void _onState(PlayerState s) {
    final done = s.processingState == ProcessingState.completed;
    playing = s.playing && !done;
    loading = s.processingState == ProcessingState.loading;
    if (done && !finished) {
      finished = true;
      if (wordClip) {
        _afterWordClip();
      } else {
        _flushListening();
      }
    }
    notifyListeners();
  }

  void _fail() {
    error = true;
    loading = false;
    playing = false;
    notifyListeners();
  }

  AudioSource _source(QueueEntry e) {
    final verse = e.verse ?? 1;
    final surah = e.verse == null ? 1 : e.surah;
    final local = _ref.read(quranDownloadsProvider).localFile(reciter, surah, verse);
    final item = MediaItem(
      id: '${reciter.id}/${globalVerseNumber(surah, verse)}/${e.verse == null ? 'b' : 'v'}',
      title: e.verse == null ? '$surahName · بِسْمِ ٱللَّٰهِ' : '$surahName · ${e.surah}:${e.verse}',
      album: surahName,
      artist: reciter.name,
    );
    return local != null
        ? AudioSource.file(local.path, tag: item)
        : AudioSource.uri(Uri.parse(verseAudioUrl(surah, verse, reciter: reciter)), tag: item);
  }

  List<QueueEntry> _entries(int from, int to, {required bool basmala}) => [
    if (basmala) QueueEntry(surahId!, null),
    for (var v = from; v <= to; v++) QueueEntry(surahId!, v),
  ];

  Future<void> _load(List<QueueEntry> entries, {int initialIndex = 0, bool autoplay = true}) async {
    queue = entries;
    index = initialIndex;
    error = false;
    finished = false;
    wordClip = false;
    wordLabel = null;
    loading = true;
    notifyListeners();
    try {
      await player.setAudioSources([for (final e in entries) _source(e)], initialIndex: initialIndex);
      await player.setLoopMode(switch (repeat) {
        QuranRepeat.verse => LoopMode.one,
        QuranRepeat.range || QuranRepeat.surah => LoopMode.all,
        QuranRepeat.off => LoopMode.off,
      });
      await player.setSpeed(speed);
      if (autoplay) unawaited(player.play());
    } catch (_) {
      _fail();
    } finally {
      loading = false;
      notifyListeners();
    }
  }

  /// Plays a surah from [fromVerse]. The basmala is recited first when starting at verse 1.
  Future<void> playSurah({required int surah, required String name, required bool basmala, int fromVerse = 1, bool autoplay = true}) async {
    if (_listenedSurah != null && _listenedSurah != surah) _flushListening();
    _listenedSurah = surah;
    if (surahId != surah) {
      rangeStart = null;
      rangeEnd = null;
      if (repeat == QuranRepeat.range) repeat = QuranRepeat.off;
    }
    surahId = surah;
    surahName = name;
    hasBasmala = basmala;
    lastVerse = fromVerse;
    final q = _queueFrom(fromVerse);
    // Starting at verse 1 keeps the basmala at the front of the queue.
    await _load(q, initialIndex: fromVerse == 1 ? 0 : _indexOf(q, fromVerse), autoplay: autoplay);
  }

  /// The queue for the current repeat mode, starting at [verse].
  List<QueueEntry> _queueFrom(int verse) {
    if (repeat == QuranRepeat.range && rangeStart != null && rangeEnd != null) {
      final start = verse >= rangeStart! && verse <= rangeEnd! ? verse : rangeStart!;
      return _entries(start, rangeEnd!, basmala: false);
    }
    if (repeat == QuranRepeat.surah) {
      // The whole surah is queued so the loop goes back to verse 1, with playback starting at [verse].
      return _entries(1, verseCount, basmala: _withBasmala);
    }
    return _entries(verse, verseCount, basmala: _withBasmala && verse == 1);
  }

  bool get _withBasmala => hasBasmala && !reciter.longOpenings;

  int _indexOf(List<QueueEntry> q, int verse) {
    final i = q.indexWhere((e) => e.verse == verse);
    return i < 0 ? 0 : i;
  }

  Future<void> _rebuild({required bool autoplay}) async {
    if (surahId == null) return;
    final verse = currentVerse ?? lastVerse ?? 1;
    final q = _queueFrom(verse);
    await _load(q, initialIndex: _indexOf(q, verse), autoplay: autoplay);
  }

  Future<void> toggle() async {
    if (playing) {
      await player.pause();
    } else if (finished || error || queue.isEmpty) {
      await _rebuild(autoplay: true);
    } else {
      unawaited(player.play());
    }
  }

  Future<void> pause() => player.pause();

  Future<void> next() async {
    if (player.hasNext) await player.seekToNext();
  }

  Future<void> previous() async {
    if (player.position > const Duration(seconds: 3) || !player.hasPrevious) {
      await player.seek(Duration.zero);
    } else {
      await player.seekToPrevious();
    }
  }

  /// Plays [verse] of [surah], reusing the queue when the verse is already in it.
  Future<void> playVerse({required int surah, required String name, required bool basmala, required int verse}) async {
    if (surahId == surah && !wordClip && !finished && !error) {
      final i = queue.indexWhere((e) => e.verse == verse);
      if (i >= 0) {
        await player.seek(Duration.zero, index: i);
        unawaited(player.play());
        return;
      }
    }
    await playSurah(surah: surah, name: name, basmala: basmala, fromVerse: verse);
  }

  /// Switching reciter keeps the surah and verse, and keeps playing if it was playing.
  Future<void> setReciter(Reciter r) async {
    if (r.id == reciter.id) return;
    final wasPlaying = playing;
    reciter = r;
    _ref.read(prefsProvider).quranReciter = r.id;
    if (surahId != null && queue.isNotEmpty && !wordClip) {
      await _rebuild(autoplay: wasPlaying);
    } else {
      notifyListeners();
    }
  }

  Future<void> setRepeat(QuranRepeat mode, {int? from, int? to}) async {
    repeat = mode;
    if (mode == QuranRepeat.range) {
      rangeStart = from;
      rangeEnd = to;
    }
    if (surahId != null && !wordClip && queue.isNotEmpty) {
      await _rebuild(autoplay: playing || mode == QuranRepeat.range);
    } else {
      notifyListeners();
    }
  }

  Future<void> setSpeed(double value) async {
    speed = value;
    await player.setSpeed(value);
    notifyListeners();
  }

  /// [minutes] null turns the timer off. [endOfVerse] stops when the current verse ends.
  void setSleep({int? minutes, bool endOfVerse = false}) {
    _sleepTimer?.cancel();
    sleepAt = null;
    sleepAtVerseEnd = endOfVerse;
    if (minutes != null) {
      sleepAt = DateTime.now().add(Duration(minutes: minutes));
      _sleepTimer = Timer(Duration(minutes: minutes), () {
        sleepAt = null;
        player.pause();
        notifyListeners();
      });
    }
    notifyListeners();
  }

  Future<void> seekToVerse(int verse) async {
    final i = queue.indexWhere((e) => e.verse == verse);
    if (i >= 0) await player.seek(Duration.zero, index: i);
  }

  /// Plays one word [times] times. Recitation resumes, paused, at the same verse afterwards.
  Future<void> playWord(String url, {int times = 1, String? label}) async {
    if (surahId != null && !wordClip) lastVerse = currentVerse ?? lastVerse;
    wordClip = true;
    wordLabel = label;
    finished = false;
    error = false;
    notifyListeners();
    try {
      await player.setLoopMode(LoopMode.off);
      await player.setAudioSources([
        for (var i = 0; i < times; i++) AudioSource.uri(Uri.parse(url), tag: MediaItem(id: '$url#$i', title: label ?? 'Word', album: surahName)),
      ]);
      await player.setSpeed(speed);
      unawaited(player.play());
    } catch (_) {
      wordClip = false;
      _fail();
    }
  }

  void _afterWordClip() {
    wordClip = false;
    wordLabel = null;
    if (surahId != null) {
      unawaited(_rebuild(autoplay: false));
    } else {
      queue = const [];
      index = null;
    }
  }

  /// Stops playback and hides the mini player.
  Future<void> stop() async {
    _flushListening();
    _sleepTimer?.cancel();
    sleepAt = null;
    sleepAtVerseEnd = false;
    await player.stop();
    surahId = null;
    queue = const [];
    index = null;
    wordClip = false;
    finished = false;
    error = false;
    notifyListeners();
  }

  /// Listening of five minutes or more counts as audio learning, as before.
  void _flushListening() {
    final surah = _listenedSurah;
    final minutes = _listenedSeconds ~/ 60;
    _listenedSeconds = 0;
    if (surah != null && minutes >= 5) {
      unawaited(_ref.read(activityRepositoryProvider).record(type: 'audio_learning', quantity: minutes, source: 'in_app', metadata: {'surah': surah}));
    }
  }

  @override
  void dispose() {
    _flushListening();
    _tick.cancel();
    _sleepTimer?.cancel();
    for (final s in _subs) {
      s.cancel();
    }
    player.dispose();
    super.dispose();
  }
}

final quranAudioProvider = Provider<QuranAudioController>((ref) {
  final controller = QuranAudioController(ref);
  ref.onDispose(controller.dispose);
  return controller;
});
