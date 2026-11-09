import 'package:flutter/foundation.dart';

/// Build-time configuration. Nothing here is a secret, and database or AI
/// credentials never reach the app: they stay on the server.
class AppConfig {
  /// Set with --dart-define-from-file. See env/README.md.
  static const apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: kReleaseMode ? 'https://deen.sohojware.dev/api/v1' : 'http://deenmate-ai.test/api/v1',
  );

  static const appVersionLabel = '1.0.0';
}
