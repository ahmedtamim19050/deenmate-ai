import 'dart:convert';

import '../../core/network/api_exception.dart';
import '../../core/storage/app_database.dart';

/// Network first, then the last good copy when offline. Keys for personal data start with
/// "user:" so sign-out can remove them.
Future<Map<String, dynamic>> cachedFetch(AppDatabase db, String key, Future<Map<String, dynamic>> Function() fetch) async {
  try {
    final fresh = await fetch();
    await db.putCache(key, jsonEncode(fresh));
    return fresh;
  } on ApiException catch (e) {
    if (e.kind == ApiErrorKind.offline || e.kind == ApiErrorKind.server) {
      final cached = await db.readCache(key);
      if (cached != null) return jsonDecode(cached) as Map<String, dynamic>;
    }
    rethrow;
  }
}
