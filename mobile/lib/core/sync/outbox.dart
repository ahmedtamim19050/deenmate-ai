import 'dart:convert';

import 'package:drift/drift.dart';

import '../network/api_client.dart';
import '../network/api_exception.dart';
import '../storage/app_database.dart';

enum OutboxKind { activity, salah }

class SyncResult {
  const SyncResult({this.synced = 0, this.rejected = 0, this.stoppedEarly = false});

  final int synced;
  final int rejected;
  final bool stoppedEarly;
}

/// Offline-first queue. A record is saved here first, shown as pending, then sent.
/// The idempotency key means a retry returns the first result and never awards again.
class OutboxRepository {
  OutboxRepository(this._db);

  final AppDatabase _db;

  Future<void> enqueue(OutboxKind kind, String key, Map<String, dynamic> payload) async {
    await _db
        .into(_db.outboxItems)
        .insert(
          OutboxItemsCompanion.insert(idempotencyKey: key, kind: kind.name, payload: jsonEncode(payload), createdAt: DateTime.now()),
          mode: InsertMode.insertOrIgnore,
        );
  }

  Future<List<OutboxItem>> pending() =>
      (_db.select(_db.outboxItems)
            ..where((t) => t.status.equals('pending'))
            ..orderBy([(t) => OrderingTerm.asc(t.id)]))
          .get();

  Stream<List<OutboxItem>> watchPending() =>
      (_db.select(_db.outboxItems)
            ..where((t) => t.status.equals('pending'))
            ..orderBy([(t) => OrderingTerm.asc(t.id)]))
          .watch();

  Future<OutboxItem?> byKey(String key) => (_db.select(_db.outboxItems)..where((t) => t.idempotencyKey.equals(key))).getSingleOrNull();

  Future<void> markSynced(String key, int points) =>
      (_db.update(_db.outboxItems)..where((t) => t.idempotencyKey.equals(key))).write(
        OutboxItemsCompanion(status: const Value('synced'), pointsAwarded: Value(points), syncedAt: Value(DateTime.now()), error: const Value(null)),
      );

  Future<void> markRejected(String key, String message) => (_db.update(_db.outboxItems)..where((t) => t.idempotencyKey.equals(key))).write(
    OutboxItemsCompanion(status: const Value('rejected'), error: Value(message)),
  );

  Future<void> bumpAttempts(OutboxItem item) => (_db.update(_db.outboxItems)..where((t) => t.id.equals(item.id))).write(
    OutboxItemsCompanion(attempts: Value(item.attempts + 1)),
  );

  /// Removes one record the person cancelled before it reached the server.
  Future<void> discard(String key) => (_db.delete(_db.outboxItems)..where((t) => t.idempotencyKey.equals(key))).go();

  Future<void> purgeSyncedOlderThan(Duration age) =>
      (_db.delete(_db.outboxItems)
            ..where((t) => t.status.equals('synced') & t.syncedAt.isSmallerThanValue(DateTime.now().subtract(age))))
          .go();
}

/// Sends queued work. Stops at the first retryable failure, so nothing is lost or reordered.
class SyncService {
  SyncService({required this.outbox, required this.api, required this.hasToken});

  final OutboxRepository outbox;
  final ApiClient api;
  final bool Function() hasToken;

  bool _running = false;

  Future<SyncResult> flush() async {
    if (_running || !hasToken()) return const SyncResult();
    _running = true;
    var synced = 0;
    var rejected = 0;
    try {
      for (final item in await outbox.pending()) {
        try {
          final payload = jsonDecode(item.payload) as Map<String, dynamic>;
          final path = item.kind == OutboxKind.salah.name ? '/salah/confirm' : '/activities';
          final response = await api.post(path, payload);
          await outbox.markSynced(item.idempotencyKey, (response['points_awarded'] as num?)?.toInt() ?? 0);
          synced++;
        } on ApiException catch (e) {
          if (e.isRetryable || e.kind == ApiErrorKind.unauthorized) {
            await outbox.bumpAttempts(item);
            return SyncResult(synced: synced, rejected: rejected, stoppedEarly: true);
          }
          await outbox.markRejected(item.idempotencyKey, e.firstError);
          rejected++;
        }
      }
      return SyncResult(synced: synced, rejected: rejected);
    } finally {
      _running = false;
    }
  }
}
