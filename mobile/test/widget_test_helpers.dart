import 'package:deenmate_ai/core/notifications/prayer_notification_service.dart';
import 'package:deenmate_ai/core/security/token_store.dart';
import 'package:deenmate_ai/core/storage/app_database.dart';
import 'package:deenmate_ai/l10n/gen/app_localizations.dart';
import 'package:deenmate_ai/shared/providers/providers.dart';
import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:mocktail/mocktail.dart';
import 'package:shared_preferences/shared_preferences.dart';

class FakeNotifications extends Mock implements PrayerNotificationService {}

class MemoryTokenStore extends TokenStore {
  MemoryTokenStore([this._value]) : super(const FlutterSecureStorage());

  String? _value;

  @override
  String? get token => _value;

  @override
  Future<void> save(String token) async => _value = token;

  @override
  Future<void> clear() async => _value = null;
}

/// A screen with real state management and an in-memory database, and no network or plugins.
Future<(Widget, AppDatabase)> testApp(
  Widget child, {
  Map<String, Object> prefs = const {},
  String? token,
  String locale = 'en',
  List<dynamic> extraOverrides = const [],
}) async {
  SharedPreferences.setMockInitialValues(prefs);
  final shared = await SharedPreferences.getInstance();
  final db = AppDatabase(NativeDatabase.memory());
  final widget = ProviderScope(
    overrides: [
      sharedPrefsProvider.overrideWithValue(shared),
      tokenStoreProvider.overrideWithValue(MemoryTokenStore(token)),
      databaseProvider.overrideWithValue(db),
      notificationServiceProvider.overrideWithValue(FakeNotifications()),
      ...extraOverrides.cast(),
    ],
    child: MaterialApp(
      locale: Locale(locale),
      supportedLocales: AppLocalizations.supportedLocales,
      localizationsDelegates: const [
        AppLocalizations.delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
      home: child,
    ),
  );
  return (widget, db);
}
