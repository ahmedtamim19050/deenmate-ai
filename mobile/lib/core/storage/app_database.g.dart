// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'app_database.dart';

// ignore_for_file: type=lint
class $OutboxItemsTable extends OutboxItems
    with TableInfo<$OutboxItemsTable, OutboxItem> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $OutboxItemsTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _idMeta = const VerificationMeta('id');
  @override
  late final GeneratedColumn<int> id = GeneratedColumn<int>(
    'id',
    aliasedName,
    false,
    hasAutoIncrement: true,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
    defaultConstraints: GeneratedColumn.constraintIsAlways(
      'PRIMARY KEY AUTOINCREMENT',
    ),
  );
  static const VerificationMeta _idempotencyKeyMeta = const VerificationMeta(
    'idempotencyKey',
  );
  @override
  late final GeneratedColumn<String> idempotencyKey = GeneratedColumn<String>(
    'idempotency_key',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
    defaultConstraints: GeneratedColumn.constraintIsAlways('UNIQUE'),
  );
  static const VerificationMeta _kindMeta = const VerificationMeta('kind');
  @override
  late final GeneratedColumn<String> kind = GeneratedColumn<String>(
    'kind',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _payloadMeta = const VerificationMeta(
    'payload',
  );
  @override
  late final GeneratedColumn<String> payload = GeneratedColumn<String>(
    'payload',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _statusMeta = const VerificationMeta('status');
  @override
  late final GeneratedColumn<String> status = GeneratedColumn<String>(
    'status',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant('pending'),
  );
  static const VerificationMeta _pointsAwardedMeta = const VerificationMeta(
    'pointsAwarded',
  );
  @override
  late final GeneratedColumn<int> pointsAwarded = GeneratedColumn<int>(
    'points_awarded',
    aliasedName,
    true,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _errorMeta = const VerificationMeta('error');
  @override
  late final GeneratedColumn<String> error = GeneratedColumn<String>(
    'error',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _attemptsMeta = const VerificationMeta(
    'attempts',
  );
  @override
  late final GeneratedColumn<int> attempts = GeneratedColumn<int>(
    'attempts',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
    defaultValue: const Constant(0),
  );
  static const VerificationMeta _createdAtMeta = const VerificationMeta(
    'createdAt',
  );
  @override
  late final GeneratedColumn<DateTime> createdAt = GeneratedColumn<DateTime>(
    'created_at',
    aliasedName,
    false,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _syncedAtMeta = const VerificationMeta(
    'syncedAt',
  );
  @override
  late final GeneratedColumn<DateTime> syncedAt = GeneratedColumn<DateTime>(
    'synced_at',
    aliasedName,
    true,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: false,
  );
  @override
  List<GeneratedColumn> get $columns => [
    id,
    idempotencyKey,
    kind,
    payload,
    status,
    pointsAwarded,
    error,
    attempts,
    createdAt,
    syncedAt,
  ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'outbox_items';
  @override
  VerificationContext validateIntegrity(
    Insertable<OutboxItem> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('id')) {
      context.handle(_idMeta, id.isAcceptableOrUnknown(data['id']!, _idMeta));
    }
    if (data.containsKey('idempotency_key')) {
      context.handle(
        _idempotencyKeyMeta,
        idempotencyKey.isAcceptableOrUnknown(
          data['idempotency_key']!,
          _idempotencyKeyMeta,
        ),
      );
    } else if (isInserting) {
      context.missing(_idempotencyKeyMeta);
    }
    if (data.containsKey('kind')) {
      context.handle(
        _kindMeta,
        kind.isAcceptableOrUnknown(data['kind']!, _kindMeta),
      );
    } else if (isInserting) {
      context.missing(_kindMeta);
    }
    if (data.containsKey('payload')) {
      context.handle(
        _payloadMeta,
        payload.isAcceptableOrUnknown(data['payload']!, _payloadMeta),
      );
    } else if (isInserting) {
      context.missing(_payloadMeta);
    }
    if (data.containsKey('status')) {
      context.handle(
        _statusMeta,
        status.isAcceptableOrUnknown(data['status']!, _statusMeta),
      );
    }
    if (data.containsKey('points_awarded')) {
      context.handle(
        _pointsAwardedMeta,
        pointsAwarded.isAcceptableOrUnknown(
          data['points_awarded']!,
          _pointsAwardedMeta,
        ),
      );
    }
    if (data.containsKey('error')) {
      context.handle(
        _errorMeta,
        error.isAcceptableOrUnknown(data['error']!, _errorMeta),
      );
    }
    if (data.containsKey('attempts')) {
      context.handle(
        _attemptsMeta,
        attempts.isAcceptableOrUnknown(data['attempts']!, _attemptsMeta),
      );
    }
    if (data.containsKey('created_at')) {
      context.handle(
        _createdAtMeta,
        createdAt.isAcceptableOrUnknown(data['created_at']!, _createdAtMeta),
      );
    } else if (isInserting) {
      context.missing(_createdAtMeta);
    }
    if (data.containsKey('synced_at')) {
      context.handle(
        _syncedAtMeta,
        syncedAt.isAcceptableOrUnknown(data['synced_at']!, _syncedAtMeta),
      );
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {id};
  @override
  OutboxItem map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return OutboxItem(
      id: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}id'],
      )!,
      idempotencyKey: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}idempotency_key'],
      )!,
      kind: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}kind'],
      )!,
      payload: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}payload'],
      )!,
      status: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}status'],
      )!,
      pointsAwarded: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}points_awarded'],
      ),
      error: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}error'],
      ),
      attempts: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}attempts'],
      )!,
      createdAt: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}created_at'],
      )!,
      syncedAt: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}synced_at'],
      ),
    );
  }

  @override
  $OutboxItemsTable createAlias(String alias) {
    return $OutboxItemsTable(attachedDatabase, alias);
  }
}

class OutboxItem extends DataClass implements Insertable<OutboxItem> {
  final int id;
  final String idempotencyKey;
  final String kind;
  final String payload;
  final String status;
  final int? pointsAwarded;
  final String? error;
  final int attempts;
  final DateTime createdAt;
  final DateTime? syncedAt;
  const OutboxItem({
    required this.id,
    required this.idempotencyKey,
    required this.kind,
    required this.payload,
    required this.status,
    this.pointsAwarded,
    this.error,
    required this.attempts,
    required this.createdAt,
    this.syncedAt,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['id'] = Variable<int>(id);
    map['idempotency_key'] = Variable<String>(idempotencyKey);
    map['kind'] = Variable<String>(kind);
    map['payload'] = Variable<String>(payload);
    map['status'] = Variable<String>(status);
    if (!nullToAbsent || pointsAwarded != null) {
      map['points_awarded'] = Variable<int>(pointsAwarded);
    }
    if (!nullToAbsent || error != null) {
      map['error'] = Variable<String>(error);
    }
    map['attempts'] = Variable<int>(attempts);
    map['created_at'] = Variable<DateTime>(createdAt);
    if (!nullToAbsent || syncedAt != null) {
      map['synced_at'] = Variable<DateTime>(syncedAt);
    }
    return map;
  }

  OutboxItemsCompanion toCompanion(bool nullToAbsent) {
    return OutboxItemsCompanion(
      id: Value(id),
      idempotencyKey: Value(idempotencyKey),
      kind: Value(kind),
      payload: Value(payload),
      status: Value(status),
      pointsAwarded: pointsAwarded == null && nullToAbsent
          ? const Value.absent()
          : Value(pointsAwarded),
      error: error == null && nullToAbsent
          ? const Value.absent()
          : Value(error),
      attempts: Value(attempts),
      createdAt: Value(createdAt),
      syncedAt: syncedAt == null && nullToAbsent
          ? const Value.absent()
          : Value(syncedAt),
    );
  }

  factory OutboxItem.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return OutboxItem(
      id: serializer.fromJson<int>(json['id']),
      idempotencyKey: serializer.fromJson<String>(json['idempotencyKey']),
      kind: serializer.fromJson<String>(json['kind']),
      payload: serializer.fromJson<String>(json['payload']),
      status: serializer.fromJson<String>(json['status']),
      pointsAwarded: serializer.fromJson<int?>(json['pointsAwarded']),
      error: serializer.fromJson<String?>(json['error']),
      attempts: serializer.fromJson<int>(json['attempts']),
      createdAt: serializer.fromJson<DateTime>(json['createdAt']),
      syncedAt: serializer.fromJson<DateTime?>(json['syncedAt']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'id': serializer.toJson<int>(id),
      'idempotencyKey': serializer.toJson<String>(idempotencyKey),
      'kind': serializer.toJson<String>(kind),
      'payload': serializer.toJson<String>(payload),
      'status': serializer.toJson<String>(status),
      'pointsAwarded': serializer.toJson<int?>(pointsAwarded),
      'error': serializer.toJson<String?>(error),
      'attempts': serializer.toJson<int>(attempts),
      'createdAt': serializer.toJson<DateTime>(createdAt),
      'syncedAt': serializer.toJson<DateTime?>(syncedAt),
    };
  }

  OutboxItem copyWith({
    int? id,
    String? idempotencyKey,
    String? kind,
    String? payload,
    String? status,
    Value<int?> pointsAwarded = const Value.absent(),
    Value<String?> error = const Value.absent(),
    int? attempts,
    DateTime? createdAt,
    Value<DateTime?> syncedAt = const Value.absent(),
  }) => OutboxItem(
    id: id ?? this.id,
    idempotencyKey: idempotencyKey ?? this.idempotencyKey,
    kind: kind ?? this.kind,
    payload: payload ?? this.payload,
    status: status ?? this.status,
    pointsAwarded: pointsAwarded.present
        ? pointsAwarded.value
        : this.pointsAwarded,
    error: error.present ? error.value : this.error,
    attempts: attempts ?? this.attempts,
    createdAt: createdAt ?? this.createdAt,
    syncedAt: syncedAt.present ? syncedAt.value : this.syncedAt,
  );
  OutboxItem copyWithCompanion(OutboxItemsCompanion data) {
    return OutboxItem(
      id: data.id.present ? data.id.value : this.id,
      idempotencyKey: data.idempotencyKey.present
          ? data.idempotencyKey.value
          : this.idempotencyKey,
      kind: data.kind.present ? data.kind.value : this.kind,
      payload: data.payload.present ? data.payload.value : this.payload,
      status: data.status.present ? data.status.value : this.status,
      pointsAwarded: data.pointsAwarded.present
          ? data.pointsAwarded.value
          : this.pointsAwarded,
      error: data.error.present ? data.error.value : this.error,
      attempts: data.attempts.present ? data.attempts.value : this.attempts,
      createdAt: data.createdAt.present ? data.createdAt.value : this.createdAt,
      syncedAt: data.syncedAt.present ? data.syncedAt.value : this.syncedAt,
    );
  }

  @override
  String toString() {
    return (StringBuffer('OutboxItem(')
          ..write('id: $id, ')
          ..write('idempotencyKey: $idempotencyKey, ')
          ..write('kind: $kind, ')
          ..write('payload: $payload, ')
          ..write('status: $status, ')
          ..write('pointsAwarded: $pointsAwarded, ')
          ..write('error: $error, ')
          ..write('attempts: $attempts, ')
          ..write('createdAt: $createdAt, ')
          ..write('syncedAt: $syncedAt')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(
    id,
    idempotencyKey,
    kind,
    payload,
    status,
    pointsAwarded,
    error,
    attempts,
    createdAt,
    syncedAt,
  );
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is OutboxItem &&
          other.id == this.id &&
          other.idempotencyKey == this.idempotencyKey &&
          other.kind == this.kind &&
          other.payload == this.payload &&
          other.status == this.status &&
          other.pointsAwarded == this.pointsAwarded &&
          other.error == this.error &&
          other.attempts == this.attempts &&
          other.createdAt == this.createdAt &&
          other.syncedAt == this.syncedAt);
}

class OutboxItemsCompanion extends UpdateCompanion<OutboxItem> {
  final Value<int> id;
  final Value<String> idempotencyKey;
  final Value<String> kind;
  final Value<String> payload;
  final Value<String> status;
  final Value<int?> pointsAwarded;
  final Value<String?> error;
  final Value<int> attempts;
  final Value<DateTime> createdAt;
  final Value<DateTime?> syncedAt;
  const OutboxItemsCompanion({
    this.id = const Value.absent(),
    this.idempotencyKey = const Value.absent(),
    this.kind = const Value.absent(),
    this.payload = const Value.absent(),
    this.status = const Value.absent(),
    this.pointsAwarded = const Value.absent(),
    this.error = const Value.absent(),
    this.attempts = const Value.absent(),
    this.createdAt = const Value.absent(),
    this.syncedAt = const Value.absent(),
  });
  OutboxItemsCompanion.insert({
    this.id = const Value.absent(),
    required String idempotencyKey,
    required String kind,
    required String payload,
    this.status = const Value.absent(),
    this.pointsAwarded = const Value.absent(),
    this.error = const Value.absent(),
    this.attempts = const Value.absent(),
    required DateTime createdAt,
    this.syncedAt = const Value.absent(),
  }) : idempotencyKey = Value(idempotencyKey),
       kind = Value(kind),
       payload = Value(payload),
       createdAt = Value(createdAt);
  static Insertable<OutboxItem> custom({
    Expression<int>? id,
    Expression<String>? idempotencyKey,
    Expression<String>? kind,
    Expression<String>? payload,
    Expression<String>? status,
    Expression<int>? pointsAwarded,
    Expression<String>? error,
    Expression<int>? attempts,
    Expression<DateTime>? createdAt,
    Expression<DateTime>? syncedAt,
  }) {
    return RawValuesInsertable({
      if (id != null) 'id': id,
      if (idempotencyKey != null) 'idempotency_key': idempotencyKey,
      if (kind != null) 'kind': kind,
      if (payload != null) 'payload': payload,
      if (status != null) 'status': status,
      if (pointsAwarded != null) 'points_awarded': pointsAwarded,
      if (error != null) 'error': error,
      if (attempts != null) 'attempts': attempts,
      if (createdAt != null) 'created_at': createdAt,
      if (syncedAt != null) 'synced_at': syncedAt,
    });
  }

  OutboxItemsCompanion copyWith({
    Value<int>? id,
    Value<String>? idempotencyKey,
    Value<String>? kind,
    Value<String>? payload,
    Value<String>? status,
    Value<int?>? pointsAwarded,
    Value<String?>? error,
    Value<int>? attempts,
    Value<DateTime>? createdAt,
    Value<DateTime?>? syncedAt,
  }) {
    return OutboxItemsCompanion(
      id: id ?? this.id,
      idempotencyKey: idempotencyKey ?? this.idempotencyKey,
      kind: kind ?? this.kind,
      payload: payload ?? this.payload,
      status: status ?? this.status,
      pointsAwarded: pointsAwarded ?? this.pointsAwarded,
      error: error ?? this.error,
      attempts: attempts ?? this.attempts,
      createdAt: createdAt ?? this.createdAt,
      syncedAt: syncedAt ?? this.syncedAt,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (id.present) {
      map['id'] = Variable<int>(id.value);
    }
    if (idempotencyKey.present) {
      map['idempotency_key'] = Variable<String>(idempotencyKey.value);
    }
    if (kind.present) {
      map['kind'] = Variable<String>(kind.value);
    }
    if (payload.present) {
      map['payload'] = Variable<String>(payload.value);
    }
    if (status.present) {
      map['status'] = Variable<String>(status.value);
    }
    if (pointsAwarded.present) {
      map['points_awarded'] = Variable<int>(pointsAwarded.value);
    }
    if (error.present) {
      map['error'] = Variable<String>(error.value);
    }
    if (attempts.present) {
      map['attempts'] = Variable<int>(attempts.value);
    }
    if (createdAt.present) {
      map['created_at'] = Variable<DateTime>(createdAt.value);
    }
    if (syncedAt.present) {
      map['synced_at'] = Variable<DateTime>(syncedAt.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('OutboxItemsCompanion(')
          ..write('id: $id, ')
          ..write('idempotencyKey: $idempotencyKey, ')
          ..write('kind: $kind, ')
          ..write('payload: $payload, ')
          ..write('status: $status, ')
          ..write('pointsAwarded: $pointsAwarded, ')
          ..write('error: $error, ')
          ..write('attempts: $attempts, ')
          ..write('createdAt: $createdAt, ')
          ..write('syncedAt: $syncedAt')
          ..write(')'))
        .toString();
  }
}

class $CachedResponsesTable extends CachedResponses
    with TableInfo<$CachedResponsesTable, CachedResponse> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $CachedResponsesTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _cacheKeyMeta = const VerificationMeta(
    'cacheKey',
  );
  @override
  late final GeneratedColumn<String> cacheKey = GeneratedColumn<String>(
    'cache_key',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _bodyMeta = const VerificationMeta('body');
  @override
  late final GeneratedColumn<String> body = GeneratedColumn<String>(
    'body',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _updatedAtMeta = const VerificationMeta(
    'updatedAt',
  );
  @override
  late final GeneratedColumn<DateTime> updatedAt = GeneratedColumn<DateTime>(
    'updated_at',
    aliasedName,
    false,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: true,
  );
  @override
  List<GeneratedColumn> get $columns => [cacheKey, body, updatedAt];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'cached_responses';
  @override
  VerificationContext validateIntegrity(
    Insertable<CachedResponse> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('cache_key')) {
      context.handle(
        _cacheKeyMeta,
        cacheKey.isAcceptableOrUnknown(data['cache_key']!, _cacheKeyMeta),
      );
    } else if (isInserting) {
      context.missing(_cacheKeyMeta);
    }
    if (data.containsKey('body')) {
      context.handle(
        _bodyMeta,
        body.isAcceptableOrUnknown(data['body']!, _bodyMeta),
      );
    } else if (isInserting) {
      context.missing(_bodyMeta);
    }
    if (data.containsKey('updated_at')) {
      context.handle(
        _updatedAtMeta,
        updatedAt.isAcceptableOrUnknown(data['updated_at']!, _updatedAtMeta),
      );
    } else if (isInserting) {
      context.missing(_updatedAtMeta);
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {cacheKey};
  @override
  CachedResponse map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return CachedResponse(
      cacheKey: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}cache_key'],
      )!,
      body: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}body'],
      )!,
      updatedAt: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}updated_at'],
      )!,
    );
  }

  @override
  $CachedResponsesTable createAlias(String alias) {
    return $CachedResponsesTable(attachedDatabase, alias);
  }
}

class CachedResponse extends DataClass implements Insertable<CachedResponse> {
  final String cacheKey;
  final String body;
  final DateTime updatedAt;
  const CachedResponse({
    required this.cacheKey,
    required this.body,
    required this.updatedAt,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['cache_key'] = Variable<String>(cacheKey);
    map['body'] = Variable<String>(body);
    map['updated_at'] = Variable<DateTime>(updatedAt);
    return map;
  }

  CachedResponsesCompanion toCompanion(bool nullToAbsent) {
    return CachedResponsesCompanion(
      cacheKey: Value(cacheKey),
      body: Value(body),
      updatedAt: Value(updatedAt),
    );
  }

  factory CachedResponse.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return CachedResponse(
      cacheKey: serializer.fromJson<String>(json['cacheKey']),
      body: serializer.fromJson<String>(json['body']),
      updatedAt: serializer.fromJson<DateTime>(json['updatedAt']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'cacheKey': serializer.toJson<String>(cacheKey),
      'body': serializer.toJson<String>(body),
      'updatedAt': serializer.toJson<DateTime>(updatedAt),
    };
  }

  CachedResponse copyWith({
    String? cacheKey,
    String? body,
    DateTime? updatedAt,
  }) => CachedResponse(
    cacheKey: cacheKey ?? this.cacheKey,
    body: body ?? this.body,
    updatedAt: updatedAt ?? this.updatedAt,
  );
  CachedResponse copyWithCompanion(CachedResponsesCompanion data) {
    return CachedResponse(
      cacheKey: data.cacheKey.present ? data.cacheKey.value : this.cacheKey,
      body: data.body.present ? data.body.value : this.body,
      updatedAt: data.updatedAt.present ? data.updatedAt.value : this.updatedAt,
    );
  }

  @override
  String toString() {
    return (StringBuffer('CachedResponse(')
          ..write('cacheKey: $cacheKey, ')
          ..write('body: $body, ')
          ..write('updatedAt: $updatedAt')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(cacheKey, body, updatedAt);
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is CachedResponse &&
          other.cacheKey == this.cacheKey &&
          other.body == this.body &&
          other.updatedAt == this.updatedAt);
}

class CachedResponsesCompanion extends UpdateCompanion<CachedResponse> {
  final Value<String> cacheKey;
  final Value<String> body;
  final Value<DateTime> updatedAt;
  final Value<int> rowid;
  const CachedResponsesCompanion({
    this.cacheKey = const Value.absent(),
    this.body = const Value.absent(),
    this.updatedAt = const Value.absent(),
    this.rowid = const Value.absent(),
  });
  CachedResponsesCompanion.insert({
    required String cacheKey,
    required String body,
    required DateTime updatedAt,
    this.rowid = const Value.absent(),
  }) : cacheKey = Value(cacheKey),
       body = Value(body),
       updatedAt = Value(updatedAt);
  static Insertable<CachedResponse> custom({
    Expression<String>? cacheKey,
    Expression<String>? body,
    Expression<DateTime>? updatedAt,
    Expression<int>? rowid,
  }) {
    return RawValuesInsertable({
      if (cacheKey != null) 'cache_key': cacheKey,
      if (body != null) 'body': body,
      if (updatedAt != null) 'updated_at': updatedAt,
      if (rowid != null) 'rowid': rowid,
    });
  }

  CachedResponsesCompanion copyWith({
    Value<String>? cacheKey,
    Value<String>? body,
    Value<DateTime>? updatedAt,
    Value<int>? rowid,
  }) {
    return CachedResponsesCompanion(
      cacheKey: cacheKey ?? this.cacheKey,
      body: body ?? this.body,
      updatedAt: updatedAt ?? this.updatedAt,
      rowid: rowid ?? this.rowid,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (cacheKey.present) {
      map['cache_key'] = Variable<String>(cacheKey.value);
    }
    if (body.present) {
      map['body'] = Variable<String>(body.value);
    }
    if (updatedAt.present) {
      map['updated_at'] = Variable<DateTime>(updatedAt.value);
    }
    if (rowid.present) {
      map['rowid'] = Variable<int>(rowid.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('CachedResponsesCompanion(')
          ..write('cacheKey: $cacheKey, ')
          ..write('body: $body, ')
          ..write('updatedAt: $updatedAt, ')
          ..write('rowid: $rowid')
          ..write(')'))
        .toString();
  }
}

class $TasbihSessionsTable extends TasbihSessions
    with TableInfo<$TasbihSessionsTable, TasbihSession> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $TasbihSessionsTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _idMeta = const VerificationMeta('id');
  @override
  late final GeneratedColumn<int> id = GeneratedColumn<int>(
    'id',
    aliasedName,
    false,
    hasAutoIncrement: true,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
    defaultConstraints: GeneratedColumn.constraintIsAlways(
      'PRIMARY KEY AUTOINCREMENT',
    ),
  );
  static const VerificationMeta _presetMeta = const VerificationMeta('preset');
  @override
  late final GeneratedColumn<String> preset = GeneratedColumn<String>(
    'preset',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _countMeta = const VerificationMeta('count');
  @override
  late final GeneratedColumn<int> count = GeneratedColumn<int>(
    'count',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _targetMeta = const VerificationMeta('target');
  @override
  late final GeneratedColumn<int> target = GeneratedColumn<int>(
    'target',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _startedAtMeta = const VerificationMeta(
    'startedAt',
  );
  @override
  late final GeneratedColumn<DateTime> startedAt = GeneratedColumn<DateTime>(
    'started_at',
    aliasedName,
    false,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _endedAtMeta = const VerificationMeta(
    'endedAt',
  );
  @override
  late final GeneratedColumn<DateTime> endedAt = GeneratedColumn<DateTime>(
    'ended_at',
    aliasedName,
    false,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: true,
  );
  @override
  List<GeneratedColumn> get $columns => [
    id,
    preset,
    count,
    target,
    startedAt,
    endedAt,
  ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'tasbih_sessions';
  @override
  VerificationContext validateIntegrity(
    Insertable<TasbihSession> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('id')) {
      context.handle(_idMeta, id.isAcceptableOrUnknown(data['id']!, _idMeta));
    }
    if (data.containsKey('preset')) {
      context.handle(
        _presetMeta,
        preset.isAcceptableOrUnknown(data['preset']!, _presetMeta),
      );
    } else if (isInserting) {
      context.missing(_presetMeta);
    }
    if (data.containsKey('count')) {
      context.handle(
        _countMeta,
        count.isAcceptableOrUnknown(data['count']!, _countMeta),
      );
    } else if (isInserting) {
      context.missing(_countMeta);
    }
    if (data.containsKey('target')) {
      context.handle(
        _targetMeta,
        target.isAcceptableOrUnknown(data['target']!, _targetMeta),
      );
    } else if (isInserting) {
      context.missing(_targetMeta);
    }
    if (data.containsKey('started_at')) {
      context.handle(
        _startedAtMeta,
        startedAt.isAcceptableOrUnknown(data['started_at']!, _startedAtMeta),
      );
    } else if (isInserting) {
      context.missing(_startedAtMeta);
    }
    if (data.containsKey('ended_at')) {
      context.handle(
        _endedAtMeta,
        endedAt.isAcceptableOrUnknown(data['ended_at']!, _endedAtMeta),
      );
    } else if (isInserting) {
      context.missing(_endedAtMeta);
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {id};
  @override
  TasbihSession map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return TasbihSession(
      id: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}id'],
      )!,
      preset: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}preset'],
      )!,
      count: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}count'],
      )!,
      target: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}target'],
      )!,
      startedAt: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}started_at'],
      )!,
      endedAt: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}ended_at'],
      )!,
    );
  }

  @override
  $TasbihSessionsTable createAlias(String alias) {
    return $TasbihSessionsTable(attachedDatabase, alias);
  }
}

class TasbihSession extends DataClass implements Insertable<TasbihSession> {
  final int id;
  final String preset;
  final int count;
  final int target;
  final DateTime startedAt;
  final DateTime endedAt;
  const TasbihSession({
    required this.id,
    required this.preset,
    required this.count,
    required this.target,
    required this.startedAt,
    required this.endedAt,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['id'] = Variable<int>(id);
    map['preset'] = Variable<String>(preset);
    map['count'] = Variable<int>(count);
    map['target'] = Variable<int>(target);
    map['started_at'] = Variable<DateTime>(startedAt);
    map['ended_at'] = Variable<DateTime>(endedAt);
    return map;
  }

  TasbihSessionsCompanion toCompanion(bool nullToAbsent) {
    return TasbihSessionsCompanion(
      id: Value(id),
      preset: Value(preset),
      count: Value(count),
      target: Value(target),
      startedAt: Value(startedAt),
      endedAt: Value(endedAt),
    );
  }

  factory TasbihSession.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return TasbihSession(
      id: serializer.fromJson<int>(json['id']),
      preset: serializer.fromJson<String>(json['preset']),
      count: serializer.fromJson<int>(json['count']),
      target: serializer.fromJson<int>(json['target']),
      startedAt: serializer.fromJson<DateTime>(json['startedAt']),
      endedAt: serializer.fromJson<DateTime>(json['endedAt']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'id': serializer.toJson<int>(id),
      'preset': serializer.toJson<String>(preset),
      'count': serializer.toJson<int>(count),
      'target': serializer.toJson<int>(target),
      'startedAt': serializer.toJson<DateTime>(startedAt),
      'endedAt': serializer.toJson<DateTime>(endedAt),
    };
  }

  TasbihSession copyWith({
    int? id,
    String? preset,
    int? count,
    int? target,
    DateTime? startedAt,
    DateTime? endedAt,
  }) => TasbihSession(
    id: id ?? this.id,
    preset: preset ?? this.preset,
    count: count ?? this.count,
    target: target ?? this.target,
    startedAt: startedAt ?? this.startedAt,
    endedAt: endedAt ?? this.endedAt,
  );
  TasbihSession copyWithCompanion(TasbihSessionsCompanion data) {
    return TasbihSession(
      id: data.id.present ? data.id.value : this.id,
      preset: data.preset.present ? data.preset.value : this.preset,
      count: data.count.present ? data.count.value : this.count,
      target: data.target.present ? data.target.value : this.target,
      startedAt: data.startedAt.present ? data.startedAt.value : this.startedAt,
      endedAt: data.endedAt.present ? data.endedAt.value : this.endedAt,
    );
  }

  @override
  String toString() {
    return (StringBuffer('TasbihSession(')
          ..write('id: $id, ')
          ..write('preset: $preset, ')
          ..write('count: $count, ')
          ..write('target: $target, ')
          ..write('startedAt: $startedAt, ')
          ..write('endedAt: $endedAt')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode =>
      Object.hash(id, preset, count, target, startedAt, endedAt);
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is TasbihSession &&
          other.id == this.id &&
          other.preset == this.preset &&
          other.count == this.count &&
          other.target == this.target &&
          other.startedAt == this.startedAt &&
          other.endedAt == this.endedAt);
}

class TasbihSessionsCompanion extends UpdateCompanion<TasbihSession> {
  final Value<int> id;
  final Value<String> preset;
  final Value<int> count;
  final Value<int> target;
  final Value<DateTime> startedAt;
  final Value<DateTime> endedAt;
  const TasbihSessionsCompanion({
    this.id = const Value.absent(),
    this.preset = const Value.absent(),
    this.count = const Value.absent(),
    this.target = const Value.absent(),
    this.startedAt = const Value.absent(),
    this.endedAt = const Value.absent(),
  });
  TasbihSessionsCompanion.insert({
    this.id = const Value.absent(),
    required String preset,
    required int count,
    required int target,
    required DateTime startedAt,
    required DateTime endedAt,
  }) : preset = Value(preset),
       count = Value(count),
       target = Value(target),
       startedAt = Value(startedAt),
       endedAt = Value(endedAt);
  static Insertable<TasbihSession> custom({
    Expression<int>? id,
    Expression<String>? preset,
    Expression<int>? count,
    Expression<int>? target,
    Expression<DateTime>? startedAt,
    Expression<DateTime>? endedAt,
  }) {
    return RawValuesInsertable({
      if (id != null) 'id': id,
      if (preset != null) 'preset': preset,
      if (count != null) 'count': count,
      if (target != null) 'target': target,
      if (startedAt != null) 'started_at': startedAt,
      if (endedAt != null) 'ended_at': endedAt,
    });
  }

  TasbihSessionsCompanion copyWith({
    Value<int>? id,
    Value<String>? preset,
    Value<int>? count,
    Value<int>? target,
    Value<DateTime>? startedAt,
    Value<DateTime>? endedAt,
  }) {
    return TasbihSessionsCompanion(
      id: id ?? this.id,
      preset: preset ?? this.preset,
      count: count ?? this.count,
      target: target ?? this.target,
      startedAt: startedAt ?? this.startedAt,
      endedAt: endedAt ?? this.endedAt,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (id.present) {
      map['id'] = Variable<int>(id.value);
    }
    if (preset.present) {
      map['preset'] = Variable<String>(preset.value);
    }
    if (count.present) {
      map['count'] = Variable<int>(count.value);
    }
    if (target.present) {
      map['target'] = Variable<int>(target.value);
    }
    if (startedAt.present) {
      map['started_at'] = Variable<DateTime>(startedAt.value);
    }
    if (endedAt.present) {
      map['ended_at'] = Variable<DateTime>(endedAt.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('TasbihSessionsCompanion(')
          ..write('id: $id, ')
          ..write('preset: $preset, ')
          ..write('count: $count, ')
          ..write('target: $target, ')
          ..write('startedAt: $startedAt, ')
          ..write('endedAt: $endedAt')
          ..write(')'))
        .toString();
  }
}

abstract class _$AppDatabase extends GeneratedDatabase {
  _$AppDatabase(QueryExecutor e) : super(e);
  $AppDatabaseManager get managers => $AppDatabaseManager(this);
  late final $OutboxItemsTable outboxItems = $OutboxItemsTable(this);
  late final $CachedResponsesTable cachedResponses = $CachedResponsesTable(
    this,
  );
  late final $TasbihSessionsTable tasbihSessions = $TasbihSessionsTable(this);
  @override
  Iterable<TableInfo<Table, Object?>> get allTables =>
      allSchemaEntities.whereType<TableInfo<Table, Object?>>();
  @override
  List<DatabaseSchemaEntity> get allSchemaEntities => [
    outboxItems,
    cachedResponses,
    tasbihSessions,
  ];
}

typedef $$OutboxItemsTableCreateCompanionBuilder =
    OutboxItemsCompanion Function({
      Value<int> id,
      required String idempotencyKey,
      required String kind,
      required String payload,
      Value<String> status,
      Value<int?> pointsAwarded,
      Value<String?> error,
      Value<int> attempts,
      required DateTime createdAt,
      Value<DateTime?> syncedAt,
    });
typedef $$OutboxItemsTableUpdateCompanionBuilder =
    OutboxItemsCompanion Function({
      Value<int> id,
      Value<String> idempotencyKey,
      Value<String> kind,
      Value<String> payload,
      Value<String> status,
      Value<int?> pointsAwarded,
      Value<String?> error,
      Value<int> attempts,
      Value<DateTime> createdAt,
      Value<DateTime?> syncedAt,
    });

class $$OutboxItemsTableFilterComposer
    extends Composer<_$AppDatabase, $OutboxItemsTable> {
  $$OutboxItemsTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get idempotencyKey => $composableBuilder(
    column: $table.idempotencyKey,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get kind => $composableBuilder(
    column: $table.kind,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get payload => $composableBuilder(
    column: $table.payload,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get status => $composableBuilder(
    column: $table.status,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get pointsAwarded => $composableBuilder(
    column: $table.pointsAwarded,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get error => $composableBuilder(
    column: $table.error,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get attempts => $composableBuilder(
    column: $table.attempts,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<DateTime> get createdAt => $composableBuilder(
    column: $table.createdAt,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<DateTime> get syncedAt => $composableBuilder(
    column: $table.syncedAt,
    builder: (column) => ColumnFilters(column),
  );
}

class $$OutboxItemsTableOrderingComposer
    extends Composer<_$AppDatabase, $OutboxItemsTable> {
  $$OutboxItemsTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get idempotencyKey => $composableBuilder(
    column: $table.idempotencyKey,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get kind => $composableBuilder(
    column: $table.kind,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get payload => $composableBuilder(
    column: $table.payload,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get status => $composableBuilder(
    column: $table.status,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get pointsAwarded => $composableBuilder(
    column: $table.pointsAwarded,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get error => $composableBuilder(
    column: $table.error,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get attempts => $composableBuilder(
    column: $table.attempts,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<DateTime> get createdAt => $composableBuilder(
    column: $table.createdAt,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<DateTime> get syncedAt => $composableBuilder(
    column: $table.syncedAt,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$OutboxItemsTableAnnotationComposer
    extends Composer<_$AppDatabase, $OutboxItemsTable> {
  $$OutboxItemsTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<int> get id =>
      $composableBuilder(column: $table.id, builder: (column) => column);

  GeneratedColumn<String> get idempotencyKey => $composableBuilder(
    column: $table.idempotencyKey,
    builder: (column) => column,
  );

  GeneratedColumn<String> get kind =>
      $composableBuilder(column: $table.kind, builder: (column) => column);

  GeneratedColumn<String> get payload =>
      $composableBuilder(column: $table.payload, builder: (column) => column);

  GeneratedColumn<String> get status =>
      $composableBuilder(column: $table.status, builder: (column) => column);

  GeneratedColumn<int> get pointsAwarded => $composableBuilder(
    column: $table.pointsAwarded,
    builder: (column) => column,
  );

  GeneratedColumn<String> get error =>
      $composableBuilder(column: $table.error, builder: (column) => column);

  GeneratedColumn<int> get attempts =>
      $composableBuilder(column: $table.attempts, builder: (column) => column);

  GeneratedColumn<DateTime> get createdAt =>
      $composableBuilder(column: $table.createdAt, builder: (column) => column);

  GeneratedColumn<DateTime> get syncedAt =>
      $composableBuilder(column: $table.syncedAt, builder: (column) => column);
}

class $$OutboxItemsTableTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          $OutboxItemsTable,
          OutboxItem,
          $$OutboxItemsTableFilterComposer,
          $$OutboxItemsTableOrderingComposer,
          $$OutboxItemsTableAnnotationComposer,
          $$OutboxItemsTableCreateCompanionBuilder,
          $$OutboxItemsTableUpdateCompanionBuilder,
          (
            OutboxItem,
            BaseReferences<_$AppDatabase, $OutboxItemsTable, OutboxItem>,
          ),
          OutboxItem,
          PrefetchHooks Function()
        > {
  $$OutboxItemsTableTableManager(_$AppDatabase db, $OutboxItemsTable table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$OutboxItemsTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$OutboxItemsTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$OutboxItemsTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                Value<String> idempotencyKey = const Value.absent(),
                Value<String> kind = const Value.absent(),
                Value<String> payload = const Value.absent(),
                Value<String> status = const Value.absent(),
                Value<int?> pointsAwarded = const Value.absent(),
                Value<String?> error = const Value.absent(),
                Value<int> attempts = const Value.absent(),
                Value<DateTime> createdAt = const Value.absent(),
                Value<DateTime?> syncedAt = const Value.absent(),
              }) => OutboxItemsCompanion(
                id: id,
                idempotencyKey: idempotencyKey,
                kind: kind,
                payload: payload,
                status: status,
                pointsAwarded: pointsAwarded,
                error: error,
                attempts: attempts,
                createdAt: createdAt,
                syncedAt: syncedAt,
              ),
          createCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                required String idempotencyKey,
                required String kind,
                required String payload,
                Value<String> status = const Value.absent(),
                Value<int?> pointsAwarded = const Value.absent(),
                Value<String?> error = const Value.absent(),
                Value<int> attempts = const Value.absent(),
                required DateTime createdAt,
                Value<DateTime?> syncedAt = const Value.absent(),
              }) => OutboxItemsCompanion.insert(
                id: id,
                idempotencyKey: idempotencyKey,
                kind: kind,
                payload: payload,
                status: status,
                pointsAwarded: pointsAwarded,
                error: error,
                attempts: attempts,
                createdAt: createdAt,
                syncedAt: syncedAt,
              ),
          withReferenceMapper: (p0) => p0
              .map(
                (e) => (
                  e.readTable<$OutboxItemsTable, OutboxItem>(table),
                  BaseReferences<_$AppDatabase, $OutboxItemsTable, OutboxItem>(
                    db,
                    table,
                    e,
                  ),
                ),
              )
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$OutboxItemsTableProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      $OutboxItemsTable,
      OutboxItem,
      $$OutboxItemsTableFilterComposer,
      $$OutboxItemsTableOrderingComposer,
      $$OutboxItemsTableAnnotationComposer,
      $$OutboxItemsTableCreateCompanionBuilder,
      $$OutboxItemsTableUpdateCompanionBuilder,
      (
        OutboxItem,
        BaseReferences<_$AppDatabase, $OutboxItemsTable, OutboxItem>,
      ),
      OutboxItem,
      PrefetchHooks Function()
    >;
typedef $$CachedResponsesTableCreateCompanionBuilder =
    CachedResponsesCompanion Function({
      required String cacheKey,
      required String body,
      required DateTime updatedAt,
      Value<int> rowid,
    });
typedef $$CachedResponsesTableUpdateCompanionBuilder =
    CachedResponsesCompanion Function({
      Value<String> cacheKey,
      Value<String> body,
      Value<DateTime> updatedAt,
      Value<int> rowid,
    });

class $$CachedResponsesTableFilterComposer
    extends Composer<_$AppDatabase, $CachedResponsesTable> {
  $$CachedResponsesTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<String> get cacheKey => $composableBuilder(
    column: $table.cacheKey,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get body => $composableBuilder(
    column: $table.body,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<DateTime> get updatedAt => $composableBuilder(
    column: $table.updatedAt,
    builder: (column) => ColumnFilters(column),
  );
}

class $$CachedResponsesTableOrderingComposer
    extends Composer<_$AppDatabase, $CachedResponsesTable> {
  $$CachedResponsesTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<String> get cacheKey => $composableBuilder(
    column: $table.cacheKey,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get body => $composableBuilder(
    column: $table.body,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<DateTime> get updatedAt => $composableBuilder(
    column: $table.updatedAt,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$CachedResponsesTableAnnotationComposer
    extends Composer<_$AppDatabase, $CachedResponsesTable> {
  $$CachedResponsesTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<String> get cacheKey =>
      $composableBuilder(column: $table.cacheKey, builder: (column) => column);

  GeneratedColumn<String> get body =>
      $composableBuilder(column: $table.body, builder: (column) => column);

  GeneratedColumn<DateTime> get updatedAt =>
      $composableBuilder(column: $table.updatedAt, builder: (column) => column);
}

class $$CachedResponsesTableTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          $CachedResponsesTable,
          CachedResponse,
          $$CachedResponsesTableFilterComposer,
          $$CachedResponsesTableOrderingComposer,
          $$CachedResponsesTableAnnotationComposer,
          $$CachedResponsesTableCreateCompanionBuilder,
          $$CachedResponsesTableUpdateCompanionBuilder,
          (
            CachedResponse,
            BaseReferences<
              _$AppDatabase,
              $CachedResponsesTable,
              CachedResponse
            >,
          ),
          CachedResponse,
          PrefetchHooks Function()
        > {
  $$CachedResponsesTableTableManager(
    _$AppDatabase db,
    $CachedResponsesTable table,
  ) : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$CachedResponsesTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$CachedResponsesTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$CachedResponsesTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<String> cacheKey = const Value.absent(),
                Value<String> body = const Value.absent(),
                Value<DateTime> updatedAt = const Value.absent(),
                Value<int> rowid = const Value.absent(),
              }) => CachedResponsesCompanion(
                cacheKey: cacheKey,
                body: body,
                updatedAt: updatedAt,
                rowid: rowid,
              ),
          createCompanionCallback:
              ({
                required String cacheKey,
                required String body,
                required DateTime updatedAt,
                Value<int> rowid = const Value.absent(),
              }) => CachedResponsesCompanion.insert(
                cacheKey: cacheKey,
                body: body,
                updatedAt: updatedAt,
                rowid: rowid,
              ),
          withReferenceMapper: (p0) => p0
              .map(
                (e) => (
                  e.readTable<$CachedResponsesTable, CachedResponse>(table),
                  BaseReferences<
                    _$AppDatabase,
                    $CachedResponsesTable,
                    CachedResponse
                  >(db, table, e),
                ),
              )
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$CachedResponsesTableProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      $CachedResponsesTable,
      CachedResponse,
      $$CachedResponsesTableFilterComposer,
      $$CachedResponsesTableOrderingComposer,
      $$CachedResponsesTableAnnotationComposer,
      $$CachedResponsesTableCreateCompanionBuilder,
      $$CachedResponsesTableUpdateCompanionBuilder,
      (
        CachedResponse,
        BaseReferences<_$AppDatabase, $CachedResponsesTable, CachedResponse>,
      ),
      CachedResponse,
      PrefetchHooks Function()
    >;
typedef $$TasbihSessionsTableCreateCompanionBuilder =
    TasbihSessionsCompanion Function({
      Value<int> id,
      required String preset,
      required int count,
      required int target,
      required DateTime startedAt,
      required DateTime endedAt,
    });
typedef $$TasbihSessionsTableUpdateCompanionBuilder =
    TasbihSessionsCompanion Function({
      Value<int> id,
      Value<String> preset,
      Value<int> count,
      Value<int> target,
      Value<DateTime> startedAt,
      Value<DateTime> endedAt,
    });

class $$TasbihSessionsTableFilterComposer
    extends Composer<_$AppDatabase, $TasbihSessionsTable> {
  $$TasbihSessionsTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get preset => $composableBuilder(
    column: $table.preset,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get count => $composableBuilder(
    column: $table.count,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get target => $composableBuilder(
    column: $table.target,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<DateTime> get startedAt => $composableBuilder(
    column: $table.startedAt,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<DateTime> get endedAt => $composableBuilder(
    column: $table.endedAt,
    builder: (column) => ColumnFilters(column),
  );
}

class $$TasbihSessionsTableOrderingComposer
    extends Composer<_$AppDatabase, $TasbihSessionsTable> {
  $$TasbihSessionsTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get preset => $composableBuilder(
    column: $table.preset,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get count => $composableBuilder(
    column: $table.count,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get target => $composableBuilder(
    column: $table.target,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<DateTime> get startedAt => $composableBuilder(
    column: $table.startedAt,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<DateTime> get endedAt => $composableBuilder(
    column: $table.endedAt,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$TasbihSessionsTableAnnotationComposer
    extends Composer<_$AppDatabase, $TasbihSessionsTable> {
  $$TasbihSessionsTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<int> get id =>
      $composableBuilder(column: $table.id, builder: (column) => column);

  GeneratedColumn<String> get preset =>
      $composableBuilder(column: $table.preset, builder: (column) => column);

  GeneratedColumn<int> get count =>
      $composableBuilder(column: $table.count, builder: (column) => column);

  GeneratedColumn<int> get target =>
      $composableBuilder(column: $table.target, builder: (column) => column);

  GeneratedColumn<DateTime> get startedAt =>
      $composableBuilder(column: $table.startedAt, builder: (column) => column);

  GeneratedColumn<DateTime> get endedAt =>
      $composableBuilder(column: $table.endedAt, builder: (column) => column);
}

class $$TasbihSessionsTableTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          $TasbihSessionsTable,
          TasbihSession,
          $$TasbihSessionsTableFilterComposer,
          $$TasbihSessionsTableOrderingComposer,
          $$TasbihSessionsTableAnnotationComposer,
          $$TasbihSessionsTableCreateCompanionBuilder,
          $$TasbihSessionsTableUpdateCompanionBuilder,
          (
            TasbihSession,
            BaseReferences<_$AppDatabase, $TasbihSessionsTable, TasbihSession>,
          ),
          TasbihSession,
          PrefetchHooks Function()
        > {
  $$TasbihSessionsTableTableManager(
    _$AppDatabase db,
    $TasbihSessionsTable table,
  ) : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$TasbihSessionsTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$TasbihSessionsTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$TasbihSessionsTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                Value<String> preset = const Value.absent(),
                Value<int> count = const Value.absent(),
                Value<int> target = const Value.absent(),
                Value<DateTime> startedAt = const Value.absent(),
                Value<DateTime> endedAt = const Value.absent(),
              }) => TasbihSessionsCompanion(
                id: id,
                preset: preset,
                count: count,
                target: target,
                startedAt: startedAt,
                endedAt: endedAt,
              ),
          createCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                required String preset,
                required int count,
                required int target,
                required DateTime startedAt,
                required DateTime endedAt,
              }) => TasbihSessionsCompanion.insert(
                id: id,
                preset: preset,
                count: count,
                target: target,
                startedAt: startedAt,
                endedAt: endedAt,
              ),
          withReferenceMapper: (p0) => p0
              .map(
                (e) => (
                  e.readTable<$TasbihSessionsTable, TasbihSession>(table),
                  BaseReferences<
                    _$AppDatabase,
                    $TasbihSessionsTable,
                    TasbihSession
                  >(db, table, e),
                ),
              )
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$TasbihSessionsTableProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      $TasbihSessionsTable,
      TasbihSession,
      $$TasbihSessionsTableFilterComposer,
      $$TasbihSessionsTableOrderingComposer,
      $$TasbihSessionsTableAnnotationComposer,
      $$TasbihSessionsTableCreateCompanionBuilder,
      $$TasbihSessionsTableUpdateCompanionBuilder,
      (
        TasbihSession,
        BaseReferences<_$AppDatabase, $TasbihSessionsTable, TasbihSession>,
      ),
      TasbihSession,
      PrefetchHooks Function()
    >;

class $AppDatabaseManager {
  final _$AppDatabase _db;
  $AppDatabaseManager(this._db);
  $$OutboxItemsTableTableManager get outboxItems =>
      $$OutboxItemsTableTableManager(_db, _db.outboxItems);
  $$CachedResponsesTableTableManager get cachedResponses =>
      $$CachedResponsesTableTableManager(_db, _db.cachedResponses);
  $$TasbihSessionsTableTableManager get tasbihSessions =>
      $$TasbihSessionsTableTableManager(_db, _db.tasbihSessions);
}
