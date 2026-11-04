import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../shared/providers/providers.dart';

class Citation {
  const Citation({required this.title, required this.type, this.author, this.publisher, this.reference, this.language, this.url, this.excerpt, this.reviewedAt});

  final String title;
  final String type;
  final String? author;
  final String? publisher;
  final String? reference;
  final String? language;
  final String? url;
  final String? excerpt;
  final String? reviewedAt;

  factory Citation.fromJson(Map<String, dynamic> j) => Citation(
    title: j['title'] as String? ?? '',
    type: j['type'] as String? ?? '',
    author: j['author'] as String?,
    publisher: j['publisher'] as String?,
    reference: j['reference'] as String?,
    language: j['language'] as String?,
    url: j['url'] as String?,
    excerpt: j['excerpt'] as String?,
    reviewedAt: j['reviewed_at'] as String?,
  );
}

class ChatMessage {
  const ChatMessage({required this.id, required this.role, required this.content, this.status = 'answered', this.citations = const [], this.sending = false, this.failed = false});

  final int? id;
  final String role;
  final String content;
  final String status;
  final List<Citation> citations;
  final bool sending;
  final bool failed;

  bool get isUser => role == 'user';

  factory ChatMessage.fromJson(Map<String, dynamic> j) => ChatMessage(
    id: (j['id'] as num?)?.toInt(),
    role: j['role'] as String,
    content: j['content'] as String,
    status: j['status'] as String? ?? 'answered',
    citations: ((j['citations'] as List?) ?? const []).map((e) => Citation.fromJson(e as Map<String, dynamic>)).toList(),
  );
}

class AiRepository {
  AiRepository(this._ref);

  final Ref _ref;

  Future<({ChatMessage message, String conversationId})> send(String text, String? conversationId) async {
    final data = await _ref.read(apiClientProvider).post('/ai/chat', {'message': text, 'conversation_id': ?conversationId});
    return (message: ChatMessage.fromJson(data['data'] as Map<String, dynamic>), conversationId: data['conversation_id'] as String);
  }

  Future<List<ChatMessage>> messages(String conversationId) async {
    final data = await _ref.read(apiClientProvider).get('/ai/conversations/$conversationId');
    return ((data['data'] as Map)['messages'] as List).map((e) => ChatMessage.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<void> feedback(int messageId, String rating, {String? comment}) =>
      _ref.read(apiClientProvider).post('/ai/feedback', {'message_id': messageId, 'rating': rating, 'comment': ?comment});
}

final aiRepositoryProvider = Provider<AiRepository>(AiRepository.new);

final aiConversationsProvider = FutureProvider<List<Map<String, dynamic>>>((ref) async {
  ref.watch(dataVersionProvider);
  return ((await ref.read(apiClientProvider).get('/ai/conversations'))['data'] as List).cast<Map<String, dynamic>>();
});
