import 'package:hijri/hijri_calendar.dart';
import 'package:intl/intl.dart';

String ymd(DateTime d) => DateFormat('yyyy-MM-dd').format(d);

String longDate(DateTime d, String locale) => DateFormat.yMMMMEEEEd(locale).format(d);

String shortDate(DateTime d, String locale) => DateFormat.yMMMd(locale).format(d);

String clockTime(DateTime d, String locale) => DateFormat.jm(locale).format(d);

/// A calculated Umm al-Qura date. Local moon-sighting can differ by a day or two, so the
/// person can apply their own adjustment in settings.
String hijriDate(DateTime d, String locale, {int offsetDays = 0}) {
  HijriCalendar.setLocal(locale == 'ar' ? 'ar' : 'en');
  final h = HijriCalendar.fromDate(d.add(Duration(days: offsetDays)));
  return '${h.hDay} ${h.longMonthName} ${h.hYear} AH';
}

String countdown(Duration d) {
  if (d.isNegative) return '00:00:00';
  String two(int n) => n.toString().padLeft(2, '0');
  return '${two(d.inHours)}:${two(d.inMinutes % 60)}:${two(d.inSeconds % 60)}';
}
