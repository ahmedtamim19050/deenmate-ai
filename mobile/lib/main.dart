import 'dart:async';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_timezone/flutter_timezone.dart';
import 'package:audio_session/audio_session.dart';
import 'package:just_audio_background/just_audio_background.dart';
import 'package:path_provider/path_provider.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:timezone/data/latest_10y.dart' as tzdata;
import 'package:timezone/timezone.dart' as tz;

import 'app/app.dart';
import 'core/notifications/prayer_notification_service.dart';
import 'core/security/token_store.dart';
import 'core/storage/app_database.dart';
import 'core/storage/app_prefs.dart';
import 'features/quran/quran_downloads.dart';
import 'shared/providers/providers.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  tzdata.initializeTimeZones();
  try {
    tz.setLocalLocation(tz.getLocation((await FlutterTimezone.getLocalTimezone()).identifier));
  } catch (_) {
    // Prayer times use the zone chosen in settings, so this only affects the UTC fallback.
  }

  final shared = await SharedPreferences.getInstance();
  final prefs = AppPrefs(shared);
  final tokens = TokenStore();
  await tokens.load();
  final notifications = PrayerNotificationService();
  await notifications.init(prefs.locale);

  // Recitation keeps playing in the background, with notification and lock-screen controls.
  await JustAudioBackground.init(
    androidNotificationChannelId: 'com.deenmate.ai.recitation',
    androidNotificationChannelName: 'Quran recitation',
    androidNotificationOngoing: true,
  );
  // Pauses for calls and other apps, and ducks for short sounds such as navigation prompts.
  await (await AudioSession.instance).configure(const AudioSessionConfiguration.music());
  final downloads = QuranDownloads(await getApplicationDocumentsDirectory());

  final firebaseReady = await _initFirebase();

  final container = ProviderContainer(
    overrides: [
      sharedPrefsProvider.overrideWithValue(shared),
      tokenStoreProvider.overrideWithValue(tokens),
      databaseProvider.overrideWithValue(AppDatabase()),
      notificationServiceProvider.overrideWithValue(notifications),
      quranDownloadsProvider.overrideWithValue(downloads),
    ],
  );

  runApp(UncontrolledProviderScope(container: container, child: const DeenMateApp()));

  // Started after the first frame, as it is not needed to use the app.
  if (firebaseReady) unawaited(_registerPush(container));
}

/// Firebase is optional: without google-services config the app runs with local reminders only.
Future<bool> _initFirebase() async {
  try {
    await Firebase.initializeApp();
    return true;
  } catch (e) {
    debugPrint('Firebase is not configured: ${e.runtimeType}');
    return false;
  }
}

Future<void> _registerPush(ProviderContainer container) async {
  final tokens = container.read(tokenStoreProvider);
  if (tokens.token == null) return;
  try {
    final push = await FirebaseMessaging.instance.getToken();
    await container.read(apiClientProvider).post('/auth/devices', {
      'device_uuid': container.read(prefsProvider).deviceUuid,
      'platform': defaultTargetPlatform == TargetPlatform.iOS ? 'ios' : 'android',
      'push_token': push,
    });
    await FirebaseMessaging.instance.subscribeToTopic('announcements');
  } catch (e) {
    debugPrint('Push registration skipped: ${e.runtimeType}');
  }
}
