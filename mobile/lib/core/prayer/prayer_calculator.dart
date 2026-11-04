import 'package:adhan/adhan.dart' as adhan;
import 'package:timezone/timezone.dart' as tz;

import 'prayer_settings.dart';

const fardPrayers = ['fajr', 'dhuhr', 'asr', 'maghrib', 'isha'];

class CalculationMethodInfo {
  const CalculationMethodInfo(this.key, this.name, this.fajr, {this.isha, this.ishaInterval, this.maghrib});

  final String key;
  final String name;
  final double fajr;
  final double? isha;
  final int? ishaInterval;
  final double? maghrib;
}

/// Same parameters as the server, so device and API schedules agree. Karachi is the default,
/// and it can be changed in prayer settings.
const calculationMethods = <CalculationMethodInfo>[
  CalculationMethodInfo('MWL', 'Muslim World League', 18, isha: 17),
  CalculationMethodInfo('ISNA', 'Islamic Society of North America', 15, isha: 15),
  CalculationMethodInfo('MAKKAH', 'Umm Al-Qura, Makkah', 18.5, ishaInterval: 90),
  CalculationMethodInfo('KARACHI', 'University of Islamic Sciences, Karachi', 18, isha: 18),
  CalculationMethodInfo('EGYPT', 'Egyptian General Authority of Survey', 19.5, isha: 17.5),
  CalculationMethodInfo('TEHRAN', 'Institute of Geophysics, Tehran', 17.7, isha: 14, maghrib: 4.5),
  CalculationMethodInfo('GULF', 'Gulf Region', 19.5, ishaInterval: 90),
  CalculationMethodInfo('KUWAIT', 'Kuwait', 18, isha: 17.5),
  CalculationMethodInfo('QATAR', 'Qatar', 18, ishaInterval: 90),
  CalculationMethodInfo('SINGAPORE', 'Singapore', 20, isha: 18),
  CalculationMethodInfo('FRANCE', 'Union of Islamic Organisations of France', 12, isha: 12),
  CalculationMethodInfo('TURKEY', 'Diyanet, Turkey', 18, isha: 17),
  CalculationMethodInfo('RUSSIA', 'Spiritual Administration of Muslims of Russia', 16, isha: 15),
];

class PrayerDay {
  const PrayerDay({required this.date, required this.times});

  final DateTime date;

  /// Prayer name to instant. Convert with [PrayerCalculator.inZone] for display.
  final Map<String, DateTime> times;

  DateTime operator [](String prayer) => times[prayer]!;
}

class PrayerCalculator {
  const PrayerCalculator();

  tz.Location location(PrayerSettings s) {
    try {
      return tz.getLocation(s.timezone);
    } catch (_) {
      return tz.UTC;
    }
  }

  tz.TZDateTime nowIn(PrayerSettings s) => tz.TZDateTime.now(location(s));

  tz.TZDateTime inZone(PrayerSettings s, DateTime instant) => tz.TZDateTime.from(instant, location(s));

  /// [date] is the calendar date at the prayer location, not on the device.
  PrayerDay compute(PrayerSettings s, DateTime date) {
    if (!s.hasLocation) throw StateError('A location is required to calculate prayer times.');

    final method = calculationMethods.firstWhere((m) => m.key == s.calculationMethod, orElse: () => calculationMethods.first);
    final params =
        adhan.CalculationParameters(
            fajrAngle: method.fajr,
            // The library requires an angle even for interval methods, where it goes unused.
            ishaAngle: method.isha ?? 0,
            ishaInterval: method.ishaInterval ?? 0,
            maghribAngle: method.maghrib,
          )
          ..madhab = s.asrMethod == 'hanafi' ? adhan.Madhab.hanafi : adhan.Madhab.shafi
          ..highLatitudeRule = switch (s.highLatitudeRule) {
            'one_seventh' => adhan.HighLatitudeRule.seventh_of_the_night,
            'middle_of_night' => adhan.HighLatitudeRule.middle_of_the_night,
            _ => adhan.HighLatitudeRule.twilight_angle,
          };

    params.adjustments
      ..fajr = s.offset('fajr')
      ..sunrise = s.offset('sunrise')
      ..dhuhr = s.offset('dhuhr')
      ..asr = s.offset('asr')
      ..maghrib = s.offset('maghrib')
      ..isha = s.offset('isha');

    final p = adhan.PrayerTimes.utc(
      adhan.Coordinates(s.latitude!, s.longitude!),
      adhan.DateComponents(date.year, date.month, date.day),
      params,
    );

    return PrayerDay(
      date: DateTime(date.year, date.month, date.day),
      times: {
        'fajr': p.fajr.toUtc(),
        'sunrise': p.sunrise.toUtc(),
        'dhuhr': p.dhuhr.toUtc(),
        'asr': p.asr.toUtc(),
        'maghrib': p.maghrib.toUtc(),
        'isha': p.isha.toUtc(),
      },
    );
  }

  /// The next prayer after [now], rolling into tomorrow after Isha.
  ({String prayer, DateTime at}) nextPrayer(PrayerSettings s, DateTime now) {
    final local = inZone(s, now);
    final today = compute(s, DateTime(local.year, local.month, local.day));
    for (final name in fardPrayers) {
      if (today[name].isAfter(now)) return (prayer: name, at: today[name]);
    }
    final tomorrow = compute(s, DateTime(local.year, local.month, local.day + 1));
    return (prayer: 'fajr', at: tomorrow['fajr']);
  }
}
