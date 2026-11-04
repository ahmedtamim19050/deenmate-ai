import 'package:flutter/foundation.dart';

/// Build-time configuration. Nothing here is a secret, and database or AI
/// credentials never reach the app: they stay on the server.
class AppConfig {
  /// Set with --dart-define-from-file. See env/README.md.
  static const apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: kReleaseMode ? 'https://deen.sohojware.dev/api/v1' : 'http://deenmate-ai.test/api/v1',
  );

  // Google's public sample ad units, used until production ids are supplied.
  static const _testBanner = 'ca-app-pub-3940256099942544/6300978111';
  static const _testNative = 'ca-app-pub-3940256099942544/2247696110';
  static const _testBannerIos = 'ca-app-pub-3940256099942544/2934735716';
  static const _testNativeIos = 'ca-app-pub-3940256099942544/3986624511';

  static const _bannerUnit = String.fromEnvironment('ADMOB_BANNER_UNIT');
  static const _nativeUnit = String.fromEnvironment('ADMOB_NATIVE_UNIT');

  static String get bannerAdUnit {
    if (_bannerUnit.isNotEmpty) return _bannerUnit;
    return defaultTargetPlatform == TargetPlatform.iOS ? _testBannerIos : _testBanner;
  }

  static String get nativeAdUnit {
    if (_nativeUnit.isNotEmpty) return _nativeUnit;
    return defaultTargetPlatform == TargetPlatform.iOS ? _testNativeIos : _testNative;
  }

  static const appVersionLabel = '1.0.0';
}
