import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

/// Ads must stay away from worship screens, so this fails the build if one is ever added.
void main() {
  const forbidden = [
    'lib/features/quran/quran_screens.dart',
    'lib/features/salah/salah_screen.dart',
    'lib/features/salah/rakat_selection_screen.dart',
    'lib/features/salah/salah_prompt_screens.dart',
    'lib/features/salah/salah_history_screen.dart',
    'lib/features/dhikr/tasbih_screen.dart',
    'lib/features/tracker/sunnah_screen.dart',
    'lib/features/fasting/fasting_screen.dart',
    'lib/features/good_deeds/good_deeds_screen.dart',
    'lib/features/ai_assistant/ai_chat_screen.dart',
    'lib/core/notifications/prayer_notification_service.dart',
  ];

  for (final path in forbidden) {
    test('no ads in $path', () {
      final source = File(path).readAsStringSync();
      expect(source, isNot(contains('NativeAdCard')));
      expect(source, isNot(contains('BannerAdStrip')));
      expect(source, isNot(contains('google_mobile_ads')));
      expect(source, isNot(contains('ad_service')));
    });
  }

  test('ads are only used on Home and Learn', () {
    final users = <String>[];
    for (final f in Directory('lib/features').listSync(recursive: true).whereType<File>()) {
      if (f.path.endsWith('.dart') && f.readAsStringSync().contains('NativeAdCard')) users.add(f.path.replaceAll('\\', '/'));
    }
    expect(users.toSet(), {'lib/features/home/home_screen.dart', 'lib/features/learning/learn_screen.dart'});
  });
}
