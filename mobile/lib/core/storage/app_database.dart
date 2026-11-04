import 'package:drift/drift.dart';
import 'package:drift_flutter/drift_flutter.dart';

part 'app_database.g.dart';

/// Records waiting to reach the server. Points are only final once the server replies.
class OutboxItems extends Table {
  IntColumn get id => integer().autoIncrement()();
  TextColumn get idempotencyKey => text().unique()();
  TextColumn get kind => text()();
  TextColumn get payload => text()();
  TextColumn get status => text().withDefault(const Constant('pending'))();
  IntColumn get pointsAwarded => integer().nullable()();
  TextColumn get error => text().nullable()();
  IntColumn get attempts => integer().withDefault(const Constant(0))();
  DateTimeColumn get createdAt => dateTime()();
  DateTimeColumn get syncedAt => dateTime().nullable()();
}

/// Last good response for each cached screen and Quran surah.
class CachedResponses extends Table {
  TextColumn get cacheKey => text()();
  TextColumn get body => text()();
  DateTimeColumn get updatedAt => dateTime()();

  @override
  Set<Column<Object>> get primaryKey => {cacheKey};
}

class TasbihSessions extends Table {
  IntColumn get id => integer().autoIncrement()();
  TextColumn get preset => text()();
  IntColumn get count => integer()();
  IntColumn get target => integer()();
  DateTimeColumn get startedAt => dateTime()();
  DateTimeColumn get endedAt => dateTime()();
}

@DriftDatabase(tables: [OutboxItems, CachedResponses, TasbihSessions])
class AppDatabase extends _$AppDatabase {
  AppDatabase([QueryExecutor? executor]) : super(executor ?? driftDatabase(name: 'deenmate'));

  @override
  int get schemaVersion => 1;

  Future<void> putCache(String key, String body) => into(cachedResponses).insertOnConflictUpdate(
    CachedResponsesCompanion.insert(cacheKey: key, body: body, updatedAt: DateTime.now()),
  );

  Future<String?> readCache(String key) async {
    final row = await (select(cachedResponses)..where((t) => t.cacheKey.equals(key))).getSingleOrNull();
    return row?.body;
  }

  /// Removes everything personal, for sign-out and account deletion.
  Future<void> clearPersonalData() async {
    await delete(outboxItems).go();
    await delete(tasbihSessions).go();
    await (delete(cachedResponses)..where((t) => t.cacheKey.like('user:%'))).go();
  }
}
