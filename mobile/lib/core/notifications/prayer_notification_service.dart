import 'dart:async';

import 'package:flutter/widgets.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:timezone/timezone.dart' as tz;

import '../../l10n/gen/app_localizations.dart';
import '../prayer/prayer_calculator.dart';
import '../prayer/prayer_settings.dart';
import 'notification_routing.dart';

/// Local prayer reminders. Delivery timing is up to the operating system, so nothing here
/// promises exact delivery, and a reminder never implies a prayer was or was not performed.
class PrayerNotificationService {
  PrayerNotificationService([FlutterLocalNotificationsPlugin? plugin]) : _plugin = plugin ?? FlutterLocalNotificationsPlugin();

  static const _categoryId = 'prayer_prompt';
  static const daysAhead = 7;

  final FlutterLocalNotificationsPlugin _plugin;
  final _routes = StreamController<String>.broadcast();

  /// Routes requested by tapping a notification or one of its buttons.
  Stream<String> get routes => _routes.stream;

  String? launchRoute;

  Future<void> init(String localeCode) async {
    final l = lookupAppLocalizations(Locale(localeCode));
    await _plugin.initialize(
      settings: InitializationSettings(
        android: const AndroidInitializationSettings('@drawable/ic_notification'),
        iOS: DarwinInitializationSettings(
          requestAlertPermission: false,
          requestBadgePermission: false,
          requestSoundPermission: false,
          notificationCategories: [
            DarwinNotificationCategory(
              _categoryId,
              actions: [
                DarwinNotificationAction.plain(actionPrayed, l.notifYesPrayed, options: {DarwinNotificationActionOption.foreground}),
                DarwinNotificationAction.plain(actionRemindLater, l.notifRemindLater, options: {DarwinNotificationActionOption.foreground}),
              ],
            ),
          ],
        ),
      ),
      onDidReceiveNotificationResponse: (response) {
        final route = routeForNotification(actionId: response.actionId, payload: response.payload);
        if (route != null) _routes.add(route);
      },
    );

    final launch = await _plugin.getNotificationAppLaunchDetails();
    if (launch?.didNotificationLaunchApp ?? false) {
      final r = launch!.notificationResponse;
      launchRoute = routeForNotification(actionId: r?.actionId, payload: r?.payload);
    }
  }

  /// Asked for at a moment the person chooses. The app works fully if they say no.
  Future<bool> requestPermission() async {
    final android = _plugin.resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>();
    if (android != null) return await android.requestNotificationsPermission() ?? false;
    final ios = _plugin.resolvePlatformSpecificImplementation<IOSFlutterLocalNotificationsPlugin>();
    return await ios?.requestPermissions(alert: true, badge: false, sound: true) ?? false;
  }

  Future<bool> areEnabled() async {
    final android = _plugin.resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>();
    if (android != null) return await android.areNotificationsEnabled() ?? false;
    final ios = _plugin.resolvePlatformSpecificImplementation<IOSFlutterLocalNotificationsPlugin>();
    final options = await ios?.checkPermissions();
    return options?.isEnabled ?? false;
  }

  Future<AndroidScheduleMode> _scheduleMode() async {
    final android = _plugin.resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>();
    final exact = await android?.canScheduleExactNotifications() ?? false;
    return exact ? AndroidScheduleMode.exactAllowWhileIdle : AndroidScheduleMode.inexactAllowWhileIdle;
  }

  NotificationDetails _details(AppLocalizations l, PrayerNotificationPref pref) {
    final silent = pref.sound == 'silent';
    final channel = silent ? 'prayer_silent' : (pref.vibration ? 'prayer_default' : 'prayer_no_vibration');
    return NotificationDetails(
      android: AndroidNotificationDetails(
        channel,
        l.notifChannelName,
        channelDescription: l.notifChannelDescription,
        importance: Importance.high,
        priority: Priority.high,
        playSound: !silent,
        enableVibration: pref.vibration && !silent,
        visibility: pref.privatePreview ? NotificationVisibility.private : NotificationVisibility.public,
        icon: '@drawable/ic_notification',
        actions: [
          AndroidNotificationAction(actionPrayed, l.notifYesPrayed, showsUserInterface: true, cancelNotification: true),
          AndroidNotificationAction(actionRemindLater, l.notifRemindLater, showsUserInterface: true, cancelNotification: true),
        ],
      ),
      iOS: DarwinNotificationDetails(categoryIdentifier: _categoryId, presentSound: !silent),
    );
  }

  String _title(AppLocalizations l, String prayer, PrayerNotificationPref pref) =>
      pref.privatePreview ? l.notifPrivateTitle : l.notifHavePrayed(prayerName(l, prayer));

  /// Replaces all prayer reminders for the coming days. Remind Later entries are kept.
  Future<void> reschedule({
    required PrayerSettings settings,
    required Map<String, PrayerNotificationPref> prefs,
    required String localeCode,
    PrayerCalculator calculator = const PrayerCalculator(),
  }) async {
    final l = lookupAppLocalizations(Locale(localeCode));
    final pending = await _plugin.pendingNotificationRequests();
    for (final request in pending) {
      if (request.id < remindIdBase) await _plugin.cancel(id: request.id);
    }
    if (!settings.hasLocation) return;

    final mode = await _scheduleMode();
    final location = calculator.location(settings);
    final now = tz.TZDateTime.now(location);

    for (var i = 0; i < daysAhead; i++) {
      final date = DateTime(now.year, now.month, now.day + i);
      final day = calculator.compute(settings, date);
      for (final prayer in fardPrayers) {
        final pref = prefs[prayer] ?? PrayerNotificationPref(prayer: prayer);
        if (!pref.enabled) continue;
        final at = tz.TZDateTime.from(day[prayer], location).add(Duration(minutes: pref.offsetMinutes));
        if (at.isBefore(now)) continue;
        try {
          await _plugin.zonedSchedule(
            id: prayerNotificationId(date, prayer, fardPrayers),
            scheduledDate: at,
            title: _title(l, prayer, pref),
            notificationDetails: _details(l, pref),
            androidScheduleMode: mode,
            payload: PrayerPayload(prayer, _ymd(date)).encode(),
          );
        } catch (e) {
          debugPrint('Could not schedule a reminder: ${e.runtimeType}');
        }
      }
    }
  }

  /// One extra reminder for this prayer. It does not touch any other scheduled reminder.
  Future<void> remindLater({
    required String prayer,
    required String date,
    required Duration after,
    required PrayerNotificationPref pref,
    required String localeCode,
  }) async {
    final l = lookupAppLocalizations(Locale(localeCode));
    final parsed = DateTime.parse(date);
    await _plugin.zonedSchedule(
      id: remindNotificationId(parsed, prayer, fardPrayers),
      scheduledDate: tz.TZDateTime.now(tz.UTC).add(after),
      title: _title(l, prayer, pref),
      notificationDetails: _details(l, pref),
      androidScheduleMode: await _scheduleMode(),
      payload: PrayerPayload(prayer, date).encode(),
    );
  }

  Future<void> cancelAll() => _plugin.cancelAll();

  static String _ymd(DateTime d) => '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';
}

String prayerName(AppLocalizations l, String prayer) => switch (prayer) {
  'fajr' => l.prayerFajr,
  'sunrise' => l.prayerSunrise,
  'dhuhr' => l.prayerDhuhr,
  'asr' => l.prayerAsr,
  'maghrib' => l.prayerMaghrib,
  'isha' => l.prayerIsha,
  _ => prayer,
};
