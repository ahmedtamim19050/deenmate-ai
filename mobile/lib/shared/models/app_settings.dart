import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../providers/providers.dart';
import '../repositories/cached_fetch.dart';

/// Settings an admin marks as public. Each has a safe default when the server is unreachable.
class PublicSettings {
  const PublicSettings(this.aiConfigured, this.values);

  final bool aiConfigured;
  final Map<String, dynamic> values;

  bool _flag(String key, bool fallback) {
    final v = values[key];
    return v == null ? fallback : '$v' == '1';
  }

  bool get homeAdEnabled => _flag('admob_home_native_enabled', true);
  bool get learnAdEnabled => _flag('admob_learn_native_enabled', true);
  bool get learnBannerEnabled => _flag('admob_learn_banner_enabled', false);
  String get supportEmail => '${values['support_email'] ?? ''}';
  String get privacyUrl => '${values['privacy_policy_url'] ?? ''}';
  String get termsUrl => '${values['terms_url'] ?? ''}';

  List<String> get suggestedQuestions => '${values['ai_suggested_questions'] ?? ''}'.split('\n').map((e) => e.trim()).where((e) => e.isNotEmpty).toList();
}

final publicSettingsProvider = FutureProvider<PublicSettings>((ref) async {
  final data = await cachedFetch(ref.read(databaseProvider), 'public_config', () => ref.read(apiClientProvider).get('/config'));
  final inner = data['data'] as Map<String, dynamic>;
  final settings = inner['settings'];
  return PublicSettings(inner['ai_configured'] as bool? ?? false, settings is Map ? settings.cast<String, dynamic>() : const {});
});
