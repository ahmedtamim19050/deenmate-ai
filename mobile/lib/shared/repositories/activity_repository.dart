import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:uuid/uuid.dart';

import '../../core/sync/outbox.dart';
import '../../core/utils/dates.dart';
import '../../features/salah/salah_repository.dart';
import '../providers/providers.dart';

class ActivityEntry {
  const ActivityEntry({
    required this.id,
    required this.type,
    required this.typeName,
    required this.category,
    required this.quantity,
    required this.unit,
    required this.source,
    required this.occurredAt,
    required this.localDate,
    required this.points,
    this.metadata,
  });

  final int id;
  final String type;
  final String typeName;
  final String category;
  final int quantity;
  final String unit;
  final String source;
  final DateTime occurredAt;
  final String localDate;
  final int points;
  final Map<String, dynamic>? metadata;

  factory ActivityEntry.fromJson(Map<String, dynamic> j) => ActivityEntry(
    id: (j['id'] as num).toInt(),
    type: j['type'] as String? ?? '',
    typeName: j['type_name'] as String? ?? '',
    category: j['category'] as String? ?? '',
    quantity: (j['quantity'] as num).toInt(),
    unit: j['unit'] as String? ?? '',
    source: j['source'] as String? ?? 'manual',
    occurredAt: DateTime.parse(j['occurred_at'] as String).toLocal(),
    localDate: j['local_date'] as String,
    points: (j['points'] as num?)?.toInt() ?? 0,
    metadata: j['metadata'] is Map ? (j['metadata'] as Map).cast<String, dynamic>() : null,
  );
}

class ActivityRepository {
  ActivityRepository(this._ref);

  final Ref _ref;

  /// Queues first, so a record made offline is safe and is only final once the server replies.
  /// Returns whether it has already reached the server, and the points the server awarded.
  Future<({bool synced, int? points, String? error})> record({
    required String type,
    required int quantity,
    required String source,
    DateTime? date,
    Map<String, dynamic>? metadata,
  }) async {
    final key = const Uuid().v4();
    final when = date ?? DateTime.now();
    final settings = _ref.read(prayerSettingsProvider);
    final local = settings.hasLocation ? _ref.read(calculatorProvider).nowIn(settings) : DateTime.now();
    await _ref.read(outboxProvider).enqueue(OutboxKind.activity, key, {
      'type': type,
      'quantity': quantity,
      'source': source,
      'idempotency_key': key,
      'occurred_at': DateTime.now().toUtc().toIso8601String(),
      'local_date': date != null ? ymd(when) : ymd(DateTime(local.year, local.month, local.day)),
      'confirmed': source == 'manual',
      'metadata': ?metadata,
    });
    await _ref.read(syncServiceProvider).flush();
    _ref.read(dataVersionProvider.notifier).bump();
    final item = await _ref.read(outboxProvider).byKey(key);
    return (synced: item?.status == 'synced', points: item?.pointsAwarded, error: item?.status == 'rejected' ? item?.error : null);
  }

  Future<List<ActivityEntry>> history({String? category, int page = 1}) async {
    final data = await _ref.read(apiClientProvider).get('/activities/history', query: {'category': ?category, 'page': page});
    return (data['data'] as List).map((e) => ActivityEntry.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<void> update(int id, int quantity) async {
    await _ref.read(apiClientProvider).put('/activities/$id', {'quantity': quantity});
    _ref.read(dataVersionProvider.notifier).bump();
  }

  Future<void> remove(int id) async {
    await _ref.read(apiClientProvider).delete('/activities/$id');
    _ref.read(dataVersionProvider.notifier).bump();
  }
}

final activityRepositoryProvider = Provider<ActivityRepository>(ActivityRepository.new);

final activityHistoryProvider = FutureProvider.family<List<ActivityEntry>, String?>((ref, category) async {
  ref.watch(dataVersionProvider);
  if (!ref.watch(authProvider).signedIn) return const [];
  return ref.read(activityRepositoryProvider).history(category: category);
});
