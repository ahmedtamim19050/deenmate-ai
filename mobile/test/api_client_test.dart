import 'package:deenmate_ai/core/network/api_client.dart';
import 'package:deenmate_ai/core/network/api_exception.dart';
import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';

class _Adapter implements HttpClientAdapter {
  _Adapter(this.status, this.body, {this.failureType});

  final int status;
  final String body;
  final DioExceptionType? failureType;
  RequestOptions? last;

  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<List<int>>? requestStream, Future<void>? cancelFuture) async {
    last = options;
    final failureType = this.failureType;
    if (failureType != null) {
      throw DioException(requestOptions: options, type: failureType);
    }
    return ResponseBody.fromString(body, status, headers: {
      Headers.contentTypeHeader: ['application/json'],
    });
  }

  @override
  void close({bool force = false}) {}
}

void main() {
  (ApiClient, _Adapter) client(
    int status,
    String body, {
    String? token,
    void Function()? onUnauthorized,
    DioExceptionType? failureType,
  }) {
    final adapter = _Adapter(status, body, failureType: failureType);
    final dio = Dio(BaseOptions(baseUrl: 'https://example.test/api/v1'))..httpClientAdapter = adapter;
    return (ApiClient(tokenReader: () => token, onUnauthorized: onUnauthorized ?? () {}, dio: dio), adapter);
  }

  test('sends the bearer token when signed in', () async {
    final (api, adapter) = client(200, '{"data":{}}', token: 'abc');
    await api.get('/dashboard');
    expect(adapter.last!.headers['Authorization'], 'Bearer abc');
  });

  test('sends no token for guests', () async {
    final (api, adapter) = client(200, '{"data":[]}');
    await api.get('/courses');
    expect(adapter.last!.headers.containsKey('Authorization'), isFalse);
  });

  test('401 calls the sign-out callback and reports unauthorized', () async {
    var rejected = false;
    final (api, _) = client(401, '{"message":"Unauthenticated."}', onUnauthorized: () => rejected = true);
    await expectLater(api.get('/dashboard'), throwsA(isA<ApiException>().having((e) => e.kind, 'kind', ApiErrorKind.unauthorized)));
    expect(rejected, isTrue);
  });

  test('422 exposes the first validation message', () async {
    final (api, _) = client(422, '{"message":"Invalid","errors":{"prayer":["Already recorded"]}}');
    await expectLater(
      api.post('/salah/confirm', {}),
      throwsA(isA<ApiException>().having((e) => e.kind, 'kind', ApiErrorKind.validation).having((e) => e.firstError, 'first', 'Already recorded').having((e) => e.isRetryable, 'retry', isFalse)),
    );
  });

  test('429 and 500 are retryable, 404 is not', () async {
    final (rate, _) = client(429, '{"message":"Slow down"}');
    await expectLater(rate.get('/x'), throwsA(isA<ApiException>().having((e) => e.isRetryable, 'retry', isTrue)));
    final (server, _) = client(500, '{"message":"Oops"}');
    await expectLater(server.get('/x'), throwsA(isA<ApiException>().having((e) => e.isRetryable, 'retry', isTrue)));
    final (missing, _) = client(404, '{"message":"Nope"}');
    await expectLater(missing.get('/x'), throwsA(isA<ApiException>().having((e) => e.isRetryable, 'retry', isFalse)));
  });

  test('timeouts and connection failures are reported distinctly', () async {
    final (timeoutApi, _) = client(
      200,
      '',
      failureType: DioExceptionType.connectionTimeout,
    );
    await expectLater(
      timeoutApi.get('/dashboard'),
      throwsA(
        isA<ApiException>()
            .having((e) => e.kind, 'kind', ApiErrorKind.offline)
            .having((e) => e.message, 'message', 'Request timed out'),
      ),
    );

    final (connectionApi, _) = client(
      200,
      '',
      failureType: DioExceptionType.connectionError,
    );
    await expectLater(
      connectionApi.get('/dashboard'),
      throwsA(
        isA<ApiException>()
            .having((e) => e.kind, 'kind', ApiErrorKind.offline)
            .having((e) => e.message, 'message', 'No connection'),
      ),
    );
  });
}
