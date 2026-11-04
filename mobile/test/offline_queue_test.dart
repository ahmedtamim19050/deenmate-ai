import 'dart:convert';

import 'package:deenmate_ai/core/network/api_client.dart';
import 'package:deenmate_ai/core/network/api_exception.dart';
import 'package:deenmate_ai/core/storage/app_database.dart';
import 'package:deenmate_ai/core/sync/outbox.dart';
import 'package:drift/native.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';

class _MockApi extends Mock implements ApiClient {}

void main() {
  late AppDatabase db;
  late OutboxRepository outbox;
  late _MockApi api;
  var signedIn = true;

  SyncService service() => SyncService(outbox: outbox, api: api, hasToken: () => signedIn);

  setUpAll(() => registerFallbackValue(<String, dynamic>{}));

  setUp(() {
    db = AppDatabase(NativeDatabase.memory());
    outbox = OutboxRepository(db);
    api = _MockApi();
    signedIn = true;
  });

  tearDown(() => db.close());

  Map<String, dynamic> salah(String key) => {'prayer': 'fajr', 'prayer_date': '2026-03-20', 'idempotency_key': key, 'confirmed': true, 'items': []};

  test('a queued record stays pending, with no points, until the server replies', () async {
    await outbox.enqueue(OutboxKind.salah, 'k1', salah('k1'));
    final item = await outbox.byKey('k1');
    expect(item!.status, 'pending');
    expect(item.pointsAwarded, isNull);
  });

  test('enqueueing the same key twice keeps one record', () async {
    await outbox.enqueue(OutboxKind.salah, 'k1', salah('k1'));
    await outbox.enqueue(OutboxKind.salah, 'k1', salah('k1'));
    expect(await outbox.pending(), hasLength(1));
  });

  test('sync sends to the right endpoint and stores the server result', () async {
    await outbox.enqueue(OutboxKind.salah, 'k1', salah('k1'));
    await outbox.enqueue(OutboxKind.activity, 'k2', {'type': 'quran_page', 'idempotency_key': 'k2'});
    when(() => api.post('/salah/confirm', any())).thenAnswer((_) async => {'points_awarded': 4});
    when(() => api.post('/activities', any())).thenAnswer((_) async => {'points_awarded': 2});

    final result = await service().flush();

    expect(result.synced, 2);
    expect((await outbox.byKey('k1'))!.pointsAwarded, 4);
    expect((await outbox.byKey('k2'))!.pointsAwarded, 2);
    expect(await outbox.pending(), isEmpty);
  });

  test('the same idempotency key is sent on every retry', () async {
    await outbox.enqueue(OutboxKind.salah, 'k1', salah('k1'));
    final sent = <String>[];
    var calls = 0;
    when(() => api.post('/salah/confirm', any())).thenAnswer((inv) async {
      sent.add((inv.positionalArguments[1] as Map)['idempotency_key'] as String);
      if (calls++ == 0) throw ApiException(ApiErrorKind.offline, 'offline');
      return {'points_awarded': 4, 'duplicate': true};
    });

    await service().flush();
    expect((await outbox.byKey('k1'))!.status, 'pending');

    await service().flush();
    expect(sent, ['k1', 'k1']);
    expect((await outbox.byKey('k1'))!.status, 'synced');
  });

  test('a network failure stops the run and keeps everything for later', () async {
    await outbox.enqueue(OutboxKind.salah, 'k1', salah('k1'));
    await outbox.enqueue(OutboxKind.salah, 'k2', salah('k2'));
    when(() => api.post(any(), any())).thenThrow(ApiException(ApiErrorKind.offline, 'offline'));

    final result = await service().flush();

    expect(result.stoppedEarly, isTrue);
    expect(await outbox.pending(), hasLength(2));
    verify(() => api.post(any(), any())).called(1);
  });

  test('a server error keeps the record queued', () async {
    await outbox.enqueue(OutboxKind.salah, 'k1', salah('k1'));
    when(() => api.post(any(), any())).thenThrow(ApiException(ApiErrorKind.server, 'down'));

    await service().flush();

    expect((await outbox.byKey('k1'))!.status, 'pending');
    expect((await outbox.byKey('k1'))!.attempts, 1);
  });

  test('a validation rejection is recorded and does not block the rest', () async {
    await outbox.enqueue(OutboxKind.salah, 'k1', salah('k1'));
    await outbox.enqueue(OutboxKind.activity, 'k2', {'idempotency_key': 'k2'});
    when(() => api.post('/salah/confirm', any())).thenThrow(ApiException(ApiErrorKind.validation, 'bad', errors: {'prayer': ['Already recorded']}));
    when(() => api.post('/activities', any())).thenAnswer((_) async => {'points_awarded': 2});

    final result = await service().flush();

    expect(result.rejected, 1);
    expect(result.synced, 1);
    final rejected = await outbox.byKey('k1');
    expect(rejected!.status, 'rejected');
    expect(rejected.error, 'Already recorded');
  });

  test('guests keep records queued and nothing is sent', () async {
    signedIn = false;
    await outbox.enqueue(OutboxKind.salah, 'k1', salah('k1'));

    await service().flush();

    verifyNever(() => api.post(any(), any()));
    expect(await outbox.pending(), hasLength(1));
  });

  test('payload is stored exactly', () async {
    await outbox.enqueue(OutboxKind.activity, 'k1', {'type': 'dhikr_count', 'quantity': 33});
    expect(jsonDecode((await outbox.byKey('k1'))!.payload), {'type': 'dhikr_count', 'quantity': 33});
  });

  test('sign-out data clearing removes queued and personal cached data but keeps public cache', () async {
    await outbox.enqueue(OutboxKind.salah, 'k1', salah('k1'));
    await db.putCache('user:dashboard', '{}');
    await db.putCache('quran:surahs', '{}');

    await db.clearPersonalData();

    expect(await outbox.pending(), isEmpty);
    expect(await db.readCache('user:dashboard'), isNull);
    expect(await db.readCache('quran:surahs'), isNotNull);
  });
}
