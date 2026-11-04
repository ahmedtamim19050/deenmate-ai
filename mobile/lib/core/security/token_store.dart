import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class TokenStore {
  TokenStore([FlutterSecureStorage? storage]) : _storage = storage ?? const FlutterSecureStorage();

  static const _key = 'auth_token';
  final FlutterSecureStorage _storage;

  String? _cached;

  String? get token => _cached;

  Future<void> load() async {
    try {
      _cached = await _storage.read(key: _key);
    } catch (_) {
      // Keystore entries can become unreadable after a backup restore. Signing in again fixes it.
      _cached = null;
    }
  }

  Future<void> save(String token) async {
    _cached = token;
    await _storage.write(key: _key, value: token);
  }

  Future<void> clear() async {
    _cached = null;
    await _storage.delete(key: _key);
  }
}
