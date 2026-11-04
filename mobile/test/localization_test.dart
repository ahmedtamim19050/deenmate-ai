import 'dart:convert';
import 'dart:io';

import 'package:deenmate_ai/core/notifications/prayer_notification_service.dart';
import 'package:deenmate_ai/l10n/gen/app_localizations.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  Map<String, dynamic> arb(String loc) => jsonDecode(File('lib/l10n/app_$loc.arb').readAsStringSync()) as Map<String, dynamic>;

  test('every language has every string, and none is left empty', () {
    final en = arb('en').keys.toSet();
    for (final loc in const ['ar', 'bn']) {
      final other = arb(loc);
      expect(other.keys.toSet(), en, reason: loc);
      expect(other.values.where((v) => '$v'.trim().isEmpty), isEmpty, reason: loc);
    }
  });

  test('required notification and confirmation wording', () {
    final l = lookupAppLocalizations(const Locale('en'));
    expect(l.notifHavePrayed(prayerName(l, 'maghrib')), 'Have you prayed Maghrib?');
    expect(l.notifYesPrayed, 'Yes, I Prayed');
    expect(l.notifRemindLater, 'Remind Later');
    expect(l.confirmPrayer, 'Confirm Prayer');
  });

  test('no string shames, ranks or promises spiritual reward', () {
    // Short, specific phrases, so a supportive line that mentions a disclaimer stays allowed.
    const banned = ['missed prayer', 'you missed', 'bad muslim', 'good muslim', 'leaderboard rank', 'hasanat', 'you failed', 'shame', 'your rank', 'streak lost', 'broke your streak'];
    for (final loc in const ['en']) {
      for (final entry in arb(loc).entries) {
        for (final phrase in banned) {
          expect('${entry.value}'.toLowerCase().contains(phrase), isFalse, reason: '${entry.key} contains "$phrase"');
        }
      }
    }
  });

  test('the points disclaimer stays in place', () {
    final l = lookupAppLocalizations(const Locale('en'));
    expect(l.pointsDisclaimerLong, contains('do not measure faith'));
    expect(l.pointsExplain3, contains('no leaderboards'));
  });
}
