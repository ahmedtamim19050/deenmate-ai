import 'package:dio/dio.dart';

import '../config.dart';
import 'api_exception.dart';

/// Attaches the token and turns every failure into an [ApiException].
class ApiClient {
  ApiClient({
    required String? Function() tokenReader,
    required void Function() onUnauthorized,
    Dio? dio,
    String Function()? localeReader,
  }) : _dio = dio ?? Dio(BaseOptions(baseUrl: AppConfig.apiBaseUrl)) {
    _dio.options
      ..connectTimeout = const Duration(seconds: 12)
      ..receiveTimeout = const Duration(seconds: 150)
      ..headers['Accept'] = 'application/json';
    _dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) {
          final token = tokenReader();
          if (token != null) options.headers['Authorization'] = 'Bearer $token';
          final locale = localeReader?.call();
          if (locale != null) options.headers['Accept-Language'] = locale;
          handler.next(options);
        },
        onError: (error, handler) {
          if (error.response?.statusCode == 401) onUnauthorized();
          handler.next(error);
        },
      ),
    );
  }

  final Dio _dio;

  Future<Map<String, dynamic>> get(String path, {Map<String, dynamic>? query}) =>
      _send(() => _dio.get<dynamic>(path, queryParameters: query));

  Future<Map<String, dynamic>> post(String path, [Object? body]) => _send(() => _dio.post<dynamic>(path, data: body));

  Future<Map<String, dynamic>> put(String path, [Object? body]) => _send(() => _dio.put<dynamic>(path, data: body));

  Future<Map<String, dynamic>> delete(String path) => _send(() => _dio.delete<dynamic>(path));

  /// Downloads a private file, such as the personal data export, as text.
  Future<String> getText(String path) async {
    try {
      final response = await _dio.get<String>(path, options: Options(responseType: ResponseType.plain));
      return response.data ?? '';
    } on DioException catch (e) {
      throw _map(e);
    }
  }

  Future<Map<String, dynamic>> _send(Future<Response<dynamic>> Function() call) async {
    try {
      final response = await call();
      final data = response.data;
      return data is Map<String, dynamic> ? data : <String, dynamic>{'data': data};
    } on DioException catch (e) {
      throw _map(e);
    }
  }

  ApiException _map(DioException e) {
    final status = e.response?.statusCode;
    if (status == null) return ApiException(ApiErrorKind.offline, 'No connection');
    final body = e.response?.data;
    final message = body is Map && body['message'] is String ? body['message'] as String : 'Request failed';
    switch (status) {
      case 401:
        return ApiException(ApiErrorKind.unauthorized, message);
      case 404:
        return ApiException(ApiErrorKind.notFound, message);
      case 422:
        final raw = body is Map ? body['errors'] : null;
        final errors = <String, List<String>>{};
        if (raw is Map) {
          raw.forEach((k, v) => errors['$k'] = v is List ? v.map((x) => '$x').toList() : ['$v']);
        }
        return ApiException(ApiErrorKind.validation, message, errors: errors);
      case 429:
        return ApiException(ApiErrorKind.rateLimited, message);
      default:
        return ApiException(ApiErrorKind.server, message);
    }
  }
}
