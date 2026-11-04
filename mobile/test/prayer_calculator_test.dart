import 'package:deenmate_ai/core/prayer/prayer_calculator.dart';
import 'package:deenmate_ai/core/prayer/prayer_settings.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:timezone/data/latest.dart' as tzdata;

/// Reference values produced by the server's prayer library for the same inputs, so a change
/// on either side that made device and server disagree would fail here.
const _reference = [
  ['Dhaka', 23.8103, 90.4125, 'Asia/Dhaka', 'KARACHI', 'hanafi', '2026-03-20', '04:47', '06:03', '12:06', '16:28', '18:09', '19:25'],
  ['Dhaka', 23.8103, 90.4125, 'Asia/Dhaka', 'KARACHI', 'hanafi', '2026-06-21', '03:44', '05:12', '12:00', '16:41', '18:48', '20:16'],
  ['Makkah', 21.4225, 39.8262, 'Asia/Riyadh', 'MAKKAH', 'standard', '2026-03-20', '05:09', '06:25', '12:28', '15:52', '18:32', '20:02'],
  ['Makkah', 21.4225, 39.8262, 'Asia/Riyadh', 'MAKKAH', 'standard', '2026-06-21', '04:11', '05:39', '12:22', '15:42', '19:06', '20:36'],
  ['London', 51.5074, -0.1278, 'Europe/London', 'MWL', 'standard', '2026-03-20', '04:10', '06:03', '12:08', '15:24', '18:14', '20:00'],
  ['New York', 40.7128, -74.006, 'America/New_York', 'ISNA', 'standard', '2026-03-20', '05:44', '06:59', '13:03', '16:28', '19:08', '20:24'],
  ['New York', 40.7128, -74.006, 'America/New_York', 'ISNA', 'standard', '2026-06-21', '03:45', '05:25', '12:58', '16:58', '20:31', '22:11'],
  ['Cairo', 30.0444, 31.2357, 'Africa/Cairo', 'EGYPT', 'standard', '2026-03-20', '04:32', '05:59', '12:03', '15:29', '18:06', '19:24'],
  ['Cairo', 30.0444, 31.2357, 'Africa/Cairo', 'EGYPT', 'standard', '2026-06-21', '04:08', '05:54', '12:57', '16:32', '19:59', '21:33'],
];

const _calc = PrayerCalculator();

PrayerSettings _settings({double lat = 23.8103, double lng = 90.4125, String tz = 'Asia/Dhaka', String method = 'KARACHI', String asr = 'standard', Map<String, int> offsets = const {}}) =>
    PrayerSettings(latitude: lat, longitude: lng, timezone: tz, calculationMethod: method, asrMethod: asr, offsets: offsets);

void main() {
  setUpAll(tzdata.initializeTimeZones);

  group('agreement with the server library', () {
    for (final r in _reference) {
      test('${r[0]} ${r[6]} (${r[4]})', () {
        final s = _settings(lat: r[1] as double, lng: r[2] as double, tz: r[3] as String, method: r[4] as String, asr: r[5] as String);
        final parts = (r[6] as String).split('-').map(int.parse).toList();
        final day = _calc.compute(s, DateTime(parts[0], parts[1], parts[2]));
        const names = ['fajr', 'sunrise', 'dhuhr', 'asr', 'maghrib', 'isha'];
        for (var i = 0; i < names.length; i++) {
          final local = _calc.inZone(s, day[names[i]]);
          final expected = (r[7 + i] as String).split(':').map(int.parse).toList();
          final delta = (local.hour * 60 + local.minute - (expected[0] * 60 + expected[1])).abs();
          expect(delta, lessThanOrEqualTo(3), reason: '${names[i]} was ${local.hour}:${local.minute}, expected ${r[7 + i]}');
        }
      });
    }
  });

  test('times stay in order and on the requested local date', () {
    final s = _settings();
    final day = _calc.compute(s, DateTime(2026, 3, 20));
    const order = ['fajr', 'sunrise', 'dhuhr', 'asr', 'maghrib', 'isha'];
    for (var i = 1; i < order.length; i++) {
      expect(day[order[i - 1]].isBefore(day[order[i]]), isTrue);
    }
    expect(_calc.inZone(s, day['fajr']).day, 20);
    expect(_calc.inZone(s, day['isha']).day, 20);
  });

  test('the same instant is shown correctly across a timezone far from UTC', () {
    final s = _settings(lat: -36.8509, lng: 174.7645, tz: 'Pacific/Auckland', method: 'MWL');
    final day = _calc.compute(s, DateTime(2026, 1, 10));
    final local = _calc.inZone(s, day['dhuhr']);
    expect(local.day, 10);
    expect(local.hour, inInclusiveRange(12, 14));
  });

  test('hanafi Asr is later than standard Asr', () {
    final standard = _calc.compute(_settings(), DateTime(2026, 3, 20))['asr'];
    final hanafi = _calc.compute(_settings(asr: 'hanafi'), DateTime(2026, 3, 20))['asr'];
    expect(hanafi.isAfter(standard), isTrue);
  });

  test('a manual offset moves only that prayer', () {
    final base = _calc.compute(_settings(), DateTime(2026, 3, 20));
    final moved = _calc.compute(_settings(offsets: {'fajr': 7}), DateTime(2026, 3, 20));
    expect(moved['fajr'].difference(base['fajr']).inMinutes, 7);
    expect(moved['dhuhr'], base['dhuhr']);
  });

  test('every method calculates a valid day', () {
    for (final m in calculationMethods) {
      final day = _calc.compute(_settings(method: m.key), DateTime(2026, 3, 20));
      expect(day['fajr'].isBefore(day['isha']), isTrue, reason: m.key);
    }
  });

  test('high latitude rules still give a usable schedule in midsummer', () {
    for (final rule in const ['angle_based', 'one_seventh', 'middle_of_night']) {
      final s = PrayerSettings(latitude: 59.3293, longitude: 18.0686, timezone: 'Europe/Stockholm', calculationMethod: 'MWL', highLatitudeRule: rule);
      final day = _calc.compute(s, DateTime(2026, 6, 21));
      expect(day['fajr'].isBefore(day['sunrise']), isTrue, reason: rule);
      expect(day['maghrib'].isBefore(day['isha']), isTrue, reason: rule);
    }
  });

  test('computing without a location is refused rather than guessed', () {
    expect(() => _calc.compute(const PrayerSettings(), DateTime(2026, 3, 20)), throwsStateError);
  });

  test('next prayer rolls into tomorrow after Isha', () {
    final s = _settings();
    final day = _calc.compute(s, DateTime(2026, 3, 20));
    final afterIsha = day['isha'].add(const Duration(minutes: 5));
    final next = _calc.nextPrayer(s, afterIsha);
    expect(next.prayer, 'fajr');
    expect(next.at.isAfter(afterIsha), isTrue);

    final beforeDhuhr = day['dhuhr'].subtract(const Duration(minutes: 1));
    expect(_calc.nextPrayer(s, beforeDhuhr).prayer, 'dhuhr');
  });

  test('settings survive a round trip and only send fields the API accepts', () {
    final s = _settings(offsets: {'isha': -3});
    final back = PrayerSettings.fromJson(s.toJson());
    expect(back.offset('isha'), -3);
    expect(back.calculationMethod, 'KARACHI');
    expect(s.toApiJson().containsKey('follow_device_timezone'), isFalse);
  });
}
