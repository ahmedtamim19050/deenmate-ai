import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';
import 'package:uuid/uuid.dart';

/// Device preferences. Tokens are kept in secure storage instead, never here.
class AppPrefs {
  AppPrefs(this._p);

  final SharedPreferences _p;

  bool get onboardingDone => _p.getBool('onboarding_done') ?? false;
  set onboardingDone(bool v) => _p.setBool('onboarding_done', v);

  bool get guestMode => _p.getBool('guest_mode') ?? false;
  set guestMode(bool v) => _p.setBool('guest_mode', v);

  String get locale => _p.getString('locale') ?? 'en';
  set locale(String v) => _p.setString('locale', v);

  String get theme => _p.getString('theme') ?? 'system';
  set theme(String v) => _p.setString('theme', v);

  bool get haptics => _p.getBool('haptics') ?? true;
  set haptics(bool v) => _p.setBool('haptics', v);

  bool get showPoints => _p.getBool('show_points') ?? true;
  set showPoints(bool v) => _p.setBool('show_points', v);

  bool get aiPersonalization => _p.getBool('ai_personalization') ?? false;
  set aiPersonalization(bool v) => _p.setBool('ai_personalization', v);

  bool get adsPersonalized => _p.getBool('ads_personalized') ?? false;
  set adsPersonalized(bool v) => _p.setBool('ads_personalized', v);

  int get hijriOffset => _p.getInt('hijri_offset') ?? 0;
  set hijriOffset(int v) => _p.setInt('hijri_offset', v);

  String? get lastScheduledDate => _p.getString('last_scheduled_date');
  set lastScheduledDate(String? v) => v == null ? _p.remove('last_scheduled_date') : _p.setString('last_scheduled_date', v);

  String? get lastDeviceTimezone => _p.getString('last_device_tz');
  set lastDeviceTimezone(String? v) => v == null ? _p.remove('last_device_tz') : _p.setString('last_device_tz', v);

  String? get tokenIssuedAt => _p.getString('token_issued_at');
  set tokenIssuedAt(String? v) => v == null ? _p.remove('token_issued_at') : _p.setString('token_issued_at', v);

  Map<String, dynamic>? readJson(String key) {
    final raw = _p.getString(key);
    if (raw == null) return null;
    return jsonDecode(raw) as Map<String, dynamic>;
  }

  Future<void> writeJson(String key, Map<String, dynamic> value) => _p.setString(key, jsonEncode(value));

  String get deviceUuid {
    final existing = _p.getString('device_uuid');
    if (existing != null) return existing;
    final created = const Uuid().v4();
    _p.setString('device_uuid', created);
    return created;
  }

  /// Personal settings go, while language and theme stay for the next person who signs in.
  Future<void> clearPersonal() async {
    for (final key in ['prayer_settings', 'notification_prefs', 'prayer_templates', 'user', 'token_issued_at', 'last_scheduled_date', 'guest_mode']) {
      await _p.remove(key);
    }
  }
}
