enum ApiErrorKind { offline, unauthorized, validation, notFound, rateLimited, server }

class ApiException implements Exception {
  ApiException(this.kind, this.message, {this.errors = const {}});

  final ApiErrorKind kind;
  final String message;
  final Map<String, List<String>> errors;

  /// A retry later could succeed, so queued work should stay queued.
  bool get isRetryable => kind == ApiErrorKind.offline || kind == ApiErrorKind.server || kind == ApiErrorKind.rateLimited;

  String get firstError => errors.values.expand((e) => e).firstOrNull ?? message;

  @override
  String toString() => 'ApiException($kind): $message';
}
