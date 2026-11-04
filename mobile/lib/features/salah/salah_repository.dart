import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:uuid/uuid.dart';

import '../../core/network/api_client.dart';
import '../../core/prayer/prayer_settings.dart';
import '../../core/storage/app_prefs.dart';
import '../../core/sync/outbox.dart';
import '../../core/utils/dates.dart';
import '../../shared/providers/providers.dart';
import '../../shared/repositories/cached_fetch.dart';
import 'salah_models.dart';

enum ConfirmState { synced, pendingSync, rejected }

class ConfirmOutcome {
  const ConfirmOutcome(this.state, {this.points, this.error});

  final ConfirmState state;
  final int? points;
  final String? error;
}

class PrayerConfig {
  PrayerConfig(this._prefs, this._api, this._hasToken);

  final AppPrefs _prefs;
  final ApiClient _api;
  final bool Function() _hasToken;

  PrayerSettings get settings {
    final j = _prefs.readJson('prayer_settings');
    return j == null ? const PrayerSettings() : PrayerSettings.fromJson(j);
  }

  /// Local first, so settings work offline and for guests. The server copy follows if possible.
  Future<void> saveSettings(PrayerSettings s) async {
    await _prefs.writeJson('prayer_settings', s.toJson());
    if (_hasToken()) {
      try {
        await _api.put('/prayer/settings', s.toApiJson());
      } catch (_) {}
    }
  }

  Map<String, PrayerNotificationPref> get notificationPrefs {
    final j = _prefs.readJson('notification_prefs');
    return {
      for (final prayer in const ['fajr', 'dhuhr', 'asr', 'maghrib', 'isha'])
        prayer: j?[prayer] == null ? PrayerNotificationPref(prayer: prayer) : PrayerNotificationPref.fromJson(j![prayer] as Map<String, dynamic>),
    };
  }

  Future<void> saveNotificationPrefs(Map<String, PrayerNotificationPref> prefs) async {
    await _prefs.writeJson('notification_prefs', {for (final e in prefs.entries) e.key: e.value.toJson()});
    if (_hasToken()) {
      try {
        await _api.put('/prayer/settings', {'notifications': prefs.values.map((e) => e.toJson()).toList()});
      } catch (_) {}
    }
  }

  List<SalahTemplate> get templates {
    final j = _prefs.readJson('prayer_templates');
    if (j == null) return defaultTemplates;
    return (j['templates'] as List).map((e) => SalahTemplate.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<void> saveTemplatesLocally(List<SalahTemplate> list) => _prefs.writeJson('prayer_templates', {'templates': list.map((e) => e.toJson()).toList()});

  Future<void> saveTemplates(List<SalahTemplate> list) async {
    await saveTemplatesLocally(list);
    if (_hasToken()) await _api.put('/prayer/templates', {'templates': list.map((e) => e.toJson()).toList()});
  }

  Future<List<SalahTemplate>> restoreDefaults() async {
    await saveTemplatesLocally(defaultTemplates);
    if (_hasToken()) {
      try {
        await _api.post('/prayer/templates/restore');
      } catch (_) {}
    }
    return defaultTemplates;
  }

  /// Pulls saved server settings after signing in on a new device.
  Future<void> pullFromServer() async {
    if (!_hasToken()) return;
    try {
      final remote = (await _api.get('/prayer/settings'))['data'] as Map<String, dynamic>;
      if (remote['latitude'] != null && !settings.hasLocation) {
        await _prefs.writeJson('prayer_settings', PrayerSettings.fromJson(remote).toJson());
      }
      final templates = (await _api.get('/prayer/templates'))['data'] as List;
      await saveTemplatesLocally(templates.map((e) => SalahTemplate.fromJson(e as Map<String, dynamic>)).toList());
    } catch (_) {}
  }
}

final prayerConfigProvider = Provider<PrayerConfig>(
  (ref) => PrayerConfig(ref.watch(prefsProvider), ref.watch(apiClientProvider), () => ref.read(tokenStoreProvider).token != null),
);

/// Bumped when prayer settings change, so times and reminders refresh.
class PrayerSettingsVersion extends Notifier<int> {
  @override
  int build() => 0;

  void bump() => state++;
}

final prayerSettingsVersionProvider = NotifierProvider<PrayerSettingsVersion, int>(PrayerSettingsVersion.new);

final prayerSettingsProvider = Provider<PrayerSettings>((ref) {
  ref.watch(prayerSettingsVersionProvider);
  return ref.watch(prayerConfigProvider).settings;
});

class SalahRepository {
  SalahRepository(this._ref);

  final Ref _ref;

  /// Saves locally first and then tries the server, so it behaves the same offline.
  /// The server alone decides the points. This never runs without an explicit Confirm tap.
  Future<ConfirmOutcome> confirm({required String prayer, required String date, required List<SalahItem> items}) async {
    final key = const Uuid().v4();
    await _ref.read(outboxProvider).enqueue(OutboxKind.salah, key, {
      'prayer': prayer,
      'prayer_date': date,
      'idempotency_key': key,
      'confirmed': true,
      'confirmed_at': DateTime.now().toUtc().toIso8601String(),
      'items': items.map((e) => e.toJson()).toList(),
    });
    await _ref.read(syncServiceProvider).flush();
    _ref.read(dataVersionProvider.notifier).bump();

    final item = await _ref.read(outboxProvider).byKey(key);
    return switch (item?.status) {
      'synced' => ConfirmOutcome(ConfirmState.synced, points: item!.pointsAwarded ?? 0),
      'rejected' => ConfirmOutcome(ConfirmState.rejected, error: item!.error),
      _ => const ConfirmOutcome(ConfirmState.pendingSync),
    };
  }

  Future<List<SalahRecord>> recordsOn(String date) async {
    final data = await cachedFetch(_ref.read(databaseProvider), 'user:salah:$date', () => _ref.read(apiClientProvider).get('/prayer/history', query: {'from': date, 'to': date}));
    return (data['data'] as List).map((e) => SalahRecord.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<List<SalahRecord>> history({int page = 1}) async {
    final data = await _ref.read(apiClientProvider).get('/prayer/history', query: {'page': page});
    return (data['data'] as List).map((e) => SalahRecord.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<void> update(int id, List<SalahItem> items) async {
    await _ref.read(apiClientProvider).put('/salah/$id', {'items': items.map((e) => e.toJson()).toList()});
    _ref.read(dataVersionProvider.notifier).bump();
  }

  Future<void> reverse(int id) async {
    await _ref.read(apiClientProvider).post('/salah/$id/reverse');
    _ref.read(dataVersionProvider.notifier).bump();
  }
}

final salahRepositoryProvider = Provider<SalahRepository>(SalahRepository.new);

final todaySalahProvider = FutureProvider.family<List<SalahRecord>, String>((ref, date) async {
  ref.watch(dataVersionProvider);
  if (!ref.watch(authProvider).signedIn) return const [];
  return ref.read(salahRepositoryProvider).recordsOn(date);
});

/// Rules published by the server, used only to preview points before confirming.
final activityTypesProvider = FutureProvider<Map<String, ActivityRule>>((ref) async {
  if (!ref.watch(authProvider).signedIn) return const {};
  final data = await cachedFetch(ref.read(databaseProvider), 'activity_types', () => ref.read(apiClientProvider).get('/activities/types'));
  return {
    for (final t in (data['data'] as List).cast<Map<String, dynamic>>())
      t['key'] as String: ActivityRule((t['points'] as num).toInt(), (t['per_quantity'] as num).toInt()),
  };
});

class ActivityRule {
  const ActivityRule(this.points, this.perQuantity);

  final int points;
  final int perQuantity;

  int preview(int quantity) => perQuantity < 1 ? 0 : (quantity ~/ perQuantity) * points;
}

/// Reschedules reminders after a settings change, a new day or a timezone change.
class NotificationScheduler {
  NotificationScheduler(this._ref);

  final Ref _ref;

  Future<void> run() async {
    final config = _ref.read(prayerConfigProvider);
    await _ref.read(notificationServiceProvider).reschedule(
      settings: config.settings,
      prefs: config.notificationPrefs,
      localeCode: _ref.read(localeProvider).languageCode,
    );
    _ref.read(prefsProvider).lastScheduledDate = ymd(DateTime.now());
  }
}

final notificationSchedulerProvider = Provider<NotificationScheduler>(NotificationScheduler.new);
