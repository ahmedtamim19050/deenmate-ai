import 'dart:async';

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../core/ads/ad_service.dart';
import '../../core/network/api_client.dart';
import '../../core/notifications/prayer_notification_service.dart';
import '../../core/prayer/prayer_calculator.dart';
import '../../core/security/token_store.dart';
import '../../core/storage/app_database.dart';
import '../../core/storage/app_prefs.dart';
import '../../core/sync/outbox.dart';
import '../models/user.dart';

// Filled in by main() once the asynchronous services are ready.
final sharedPrefsProvider = Provider<SharedPreferences>((_) => throw UnimplementedError());
final tokenStoreProvider = Provider<TokenStore>((_) => throw UnimplementedError());
final databaseProvider = Provider<AppDatabase>((_) => throw UnimplementedError());
final notificationServiceProvider = Provider<PrayerNotificationService>((_) => throw UnimplementedError());
final adServiceProvider = Provider<AdService>((_) => AdService());

final prefsProvider = Provider<AppPrefs>((ref) => AppPrefs(ref.watch(sharedPrefsProvider)));

final calculatorProvider = Provider<PrayerCalculator>((_) => const PrayerCalculator());

final apiClientProvider = Provider<ApiClient>((ref) {
  final tokens = ref.watch(tokenStoreProvider);
  return ApiClient(
    tokenReader: () => tokens.token,
    localeReader: () => ref.read(localeProvider).languageCode,
    onUnauthorized: () => ref.read(authProvider.notifier).onTokenRejected(),
  );
});

final outboxProvider = Provider<OutboxRepository>((ref) => OutboxRepository(ref.watch(databaseProvider)));

final syncServiceProvider = Provider<SyncService>(
  (ref) => SyncService(outbox: ref.watch(outboxProvider), api: ref.watch(apiClientProvider), hasToken: () => ref.read(tokenStoreProvider).token != null),
);

/// Bumped when server data changes, so personal screens refetch.
class DataVersion extends Notifier<int> {
  @override
  int build() => 0;

  void bump() => state++;
}

final dataVersionProvider = NotifierProvider<DataVersion, int>(DataVersion.new);

final pendingOutboxProvider = StreamProvider((ref) => ref.watch(outboxProvider).watchPending());

final connectivityProvider = StreamProvider<bool>((ref) async* {
  final c = Connectivity();
  bool online(List<ConnectivityResult> r) => r.any((x) => x != ConnectivityResult.none);
  yield online(await c.checkConnectivity());
  yield* c.onConnectivityChanged.map(online);
});

class LocaleController extends Notifier<Locale> {
  @override
  Locale build() => Locale(ref.watch(prefsProvider).locale);

  void set(String code) {
    ref.read(prefsProvider).locale = code;
    state = Locale(code);
  }
}

final localeProvider = NotifierProvider<LocaleController, Locale>(LocaleController.new);

class ThemeController extends Notifier<ThemeMode> {
  @override
  ThemeMode build() => _mode(ref.watch(prefsProvider).theme);

  ThemeMode _mode(String v) => switch (v) {
    'light' => ThemeMode.light,
    'dark' => ThemeMode.dark,
    _ => ThemeMode.system,
  };

  void set(String v) {
    ref.read(prefsProvider).theme = v;
    state = _mode(v);
  }
}

final themeProvider = NotifierProvider<ThemeController, ThemeMode>(ThemeController.new);

class AuthState {
  const AuthState({this.user, this.guest = false});

  final AppUser? user;
  final bool guest;

  bool get signedIn => user != null;
}

class AuthController extends Notifier<AuthState> {
  @override
  AuthState build() {
    final prefs = ref.watch(prefsProvider);
    final hasToken = ref.watch(tokenStoreProvider).token != null;
    final stored = prefs.readJson('user');
    return AuthState(user: hasToken && stored != null ? AppUser.fromJson(stored) : null, guest: prefs.guestMode);
  }

  AppPrefs get _prefs => ref.read(prefsProvider);
  ApiClient get _api => ref.read(apiClientProvider);

  Future<void> _adopt(Map<String, dynamic> response) async {
    final tokens = ref.read(tokenStoreProvider);
    await tokens.save(response['token'] as String);
    _prefs.tokenIssuedAt = DateTime.now().toIso8601String();
    _prefs.guestMode = false;
    final json = response['data'] as Map<String, dynamic>;
    await _prefs.writeJson('user', json);
    state = AuthState(user: AppUser.fromJson(json));
    ref.read(dataVersionProvider.notifier).bump();
    unawaited(ref.read(syncServiceProvider).flush());
  }

  Future<void> login(String email, String password) async {
    await _adopt(await _api.post('/auth/login', {'email': email, 'password': password, 'device_name': 'mobile'}));
  }

  Future<void> register(String name, String email, String password) async {
    await _adopt(
      await _api.post('/auth/register', {
        'name': name,
        'email': email,
        'password': password,
        'password_confirmation': password,
        'locale': ref.read(localeProvider).languageCode,
        'device_name': 'mobile',
      }),
    );
  }

  void continueAsGuest() {
    _prefs.guestMode = true;
    state = const AuthState(guest: true);
  }

  Future<void> updateUser(Map<String, dynamic> json) async {
    await _prefs.writeJson('user', json);
    state = AuthState(user: AppUser.fromJson(json), guest: state.guest);
  }

  /// Keeps long-lived sessions fresh, without blocking startup when offline.
  Future<void> refreshTokenIfOld() async {
    final issued = DateTime.tryParse(_prefs.tokenIssuedAt ?? '');
    if (!state.signedIn || issued == null || DateTime.now().difference(issued).inDays < 30) return;
    try {
      final response = await _api.post('/auth/refresh');
      await ref.read(tokenStoreProvider).save(response['token'] as String);
      _prefs.tokenIssuedAt = DateTime.now().toIso8601String();
    } catch (_) {
      // The existing token still works, so this simply tries again next launch.
    }
  }

  Future<void> onTokenRejected() async {
    if (!state.signedIn) return;
    await ref.read(tokenStoreProvider).clear();
    await ref.read(sharedPrefsProvider).remove('user');
    state = const AuthState();
  }

  /// Queued records are removed too, so the next person on this device never inherits them.
  Future<void> signOut({bool callServer = true}) async {
    if (callServer) {
      try {
        await _api.post('/auth/logout');
      } catch (_) {}
    }
    await ref.read(tokenStoreProvider).clear();
    await ref.read(databaseProvider).clearPersonalData();
    await _prefs.clearPersonal();
    await ref.read(notificationServiceProvider).cancelAll();
    state = const AuthState();
    ref.read(dataVersionProvider.notifier).bump();
  }
}

final authProvider = NotifierProvider<AuthController, AuthState>(AuthController.new);
