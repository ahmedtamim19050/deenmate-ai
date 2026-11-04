import 'package:deenmate_ai/core/notifications/notification_routing.dart';
import 'package:deenmate_ai/core/prayer/prayer_calculator.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  final payload = const PrayerPayload('fajr', '2026-03-20').encode();

  test('Yes, I Prayed opens the rakat screen and records nothing itself', () {
    expect(routeForNotification(actionId: actionPrayed, payload: payload), '/salah/confirm/fajr?date=2026-03-20');
  });

  test('Remind Later opens the reminder choices', () {
    expect(routeForNotification(actionId: actionRemindLater, payload: payload), '/salah/remind/fajr?date=2026-03-20');
  });

  test('tapping the notification body only asks the question', () {
    expect(routeForNotification(payload: payload), '/salah/prompt/fajr?date=2026-03-20');
  });

  test('a bad or unrelated payload goes nowhere', () {
    expect(routeForNotification(actionId: actionPrayed, payload: null), isNull);
    expect(routeForNotification(actionId: actionPrayed, payload: 'not json'), isNull);
    expect(routeForNotification(actionId: actionPrayed, payload: '{"type":"other"}'), isNull);
    expect(routeForNotification(actionId: actionPrayed, payload: '{"type":"prayer"}'), isNull);
  });

  test('payload round trips', () {
    final decoded = PrayerPayload.decode(payload)!;
    expect(decoded.prayer, 'fajr');
    expect(decoded.date, '2026-03-20');
  });

  test('ids are stable, so rescheduling replaces rather than duplicates', () {
    final a = prayerNotificationId(DateTime(2026, 3, 20), 'asr', fardPrayers);
    final b = prayerNotificationId(DateTime(2026, 3, 20, 23, 59), 'asr', fardPrayers);
    expect(a, b);
  });

  test('ids are unique across prayers and days', () {
    final ids = <int>{};
    for (var day = 0; day < 400; day++) {
      for (final p in fardPrayers) {
        ids.add(prayerNotificationId(DateTime(2026, 1, 1).add(Duration(days: day)), p, fardPrayers));
      }
    }
    expect(ids.length, 400 * fardPrayers.length);
  });

  test('Remind Later ids can never collide with scheduled prayer ids', () {
    final date = DateTime(2026, 3, 20);
    final scheduled = prayerNotificationId(date, 'fajr', fardPrayers);
    final remind = remindNotificationId(date, 'fajr', fardPrayers);
    expect(remind, isNot(scheduled));
    expect(remind, greaterThanOrEqualTo(remindIdBase));
    expect(scheduled, lessThan(remindIdBase));
    // Must fit the signed 32-bit range that Android notification ids use.
    expect(remind, lessThan(1 << 31));
  });
}
