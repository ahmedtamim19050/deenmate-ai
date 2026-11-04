import 'dart:convert';

const actionPrayed = 'yes_prayed';
const actionRemindLater = 'remind_later';

class PrayerPayload {
  const PrayerPayload(this.prayer, this.date);

  final String prayer;
  final String date;

  String encode() => jsonEncode({'type': 'prayer', 'prayer': prayer, 'date': date});

  static PrayerPayload? decode(String? raw) {
    if (raw == null) return null;
    try {
      final j = jsonDecode(raw);
      if (j is Map && j['type'] == 'prayer' && j['prayer'] is String && j['date'] is String) {
        return PrayerPayload(j['prayer'] as String, j['date'] as String);
      }
    } catch (_) {}
    return null;
  }
}

/// Where a notification interaction leads. No interaction ever writes a record or awards
/// points: "Yes, I Prayed" only opens the rakat screen, and a dismissal does nothing at all.
String? routeForNotification({String? actionId, String? payload}) {
  final p = PrayerPayload.decode(payload);
  if (p == null) return null;
  final query = 'date=${p.date}';
  return switch (actionId) {
    actionPrayed => '/salah/confirm/${p.prayer}?$query',
    actionRemindLater => '/salah/remind/${p.prayer}?$query',
    _ => '/salah/prompt/${p.prayer}?$query',
  };
}

/// Stable ids mean rescheduling replaces a notification and can never duplicate it.
int prayerNotificationId(DateTime date, String prayer, List<String> prayers) {
  final day = DateTime.utc(date.year, date.month, date.day).millisecondsSinceEpoch ~/ Duration.millisecondsPerDay;
  return day * 10 + prayers.indexOf(prayer);
}

/// Remind Later uses its own id range, so it never touches the next prayer's schedule.
const remindIdBase = 1000000000;

int remindNotificationId(DateTime date, String prayer, List<String> prayers) => remindIdBase + prayerNotificationId(date, prayer, prayers);
