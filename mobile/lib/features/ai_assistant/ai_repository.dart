import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../shared/providers/providers.dart';

/// One piece of evidence under an answer. Every field comes from an approved source on the
/// server (never from AI text): the Arabic, the exact translation and its credit.
class Citation {
  const Citation({
    required this.title,
    required this.type,
    this.author,
    this.publisher,
    this.reference,
    this.language,
    this.url,
    this.excerpt,
    this.reviewedAt,
    this.edition,
    this.version,
    this.attribution,
    this.licenseUrl,
    this.surahNumber,
    this.surahName,
    this.ayahNumber,
    this.ayahFrom,
    this.ayahTo,
    this.arabic,
    this.translation,
    this.translator,
    this.text,
    this.collection,
    this.book,
    this.hadithNumber,
    this.grade,
    this.gradeSource,
  });

  final String title;
  final String type;
  final String? author;
  final String? publisher;
  final String? reference;
  final String? language;
  final String? url;
  final String? excerpt;
  final String? reviewedAt;
  final String? edition;
  final String? version;
  final String? attribution;
  final String? licenseUrl;
  final int? surahNumber;
  final String? surahName;
  final int? ayahNumber;
  final int? ayahFrom;
  final int? ayahTo;
  final String? arabic;
  final String? translation;
  final String? translator;
  final String? text;
  final String? collection;
  final String? book;
  final String? hadithNumber;
  final String? grade;
  final String? gradeSource;

  bool get isQuran => type == 'quran';

  /// The source's own words: the translation for a verse, the text for anything else.
  String? get quotation => translation ?? text ?? excerpt;

  /// "Al-Baqara 2:255" when the surah is known, otherwise the stored reference.
  String get label {
    if (isQuran && surahNumber != null && ayahNumber != null) {
      return surahName != null ? '$surahName $surahNumber:$ayahNumber' : '$surahNumber:$ayahNumber';
    }
    if (type == 'tafsir' && surahNumber != null && ayahFrom != null) {
      return ayahTo != null && ayahTo != ayahFrom ? '$surahNumber:$ayahFrom–$ayahTo' : '$surahNumber:$ayahFrom';
    }
    return reference ?? title;
  }

  /// Translator or author, edition and version, with the required attribution.
  String get credit => [attribution ?? edition ?? title, if (version != null && !(attribution ?? '').contains(version!)) version!].join(' · ');

  factory Citation.fromJson(Map<String, dynamic> j) => Citation(
    title: j['title'] as String? ?? '',
    type: j['type'] as String? ?? '',
    author: j['author'] as String?,
    publisher: j['publisher'] as String?,
    reference: j['reference'] as String?,
    language: j['language'] as String?,
    url: (j['source_url'] ?? j['url']) as String?,
    excerpt: j['excerpt'] as String?,
    reviewedAt: j['reviewed_at'] as String?,
    edition: j['edition'] as String?,
    version: j['version'] as String?,
    attribution: j['attribution'] as String?,
    licenseUrl: j['license_url'] as String?,
    surahNumber: (j['surah_number'] as num?)?.toInt(),
    surahName: j['surah_name'] as String?,
    ayahNumber: (j['ayah_number'] as num?)?.toInt(),
    ayahFrom: (j['ayah_from'] as num?)?.toInt(),
    ayahTo: (j['ayah_to'] as num?)?.toInt(),
    arabic: j['arabic'] as String?,
    translation: j['translation'] as String?,
    translator: j['translator'] as String?,
    text: j['text'] as String?,
    collection: j['collection'] as String?,
    book: j['book'] as String?,
    hadithNumber: j['hadith_number']?.toString(),
    grade: j['grade'] as String?,
    gradeSource: j['grade_source'] as String?,
  );
}

class Limitation {
  const Limitation(this.code, this.message);

  final String code;
  final String message;

  factory Limitation.fromJson(Map<String, dynamic> j) => Limitation(j['code'] as String? ?? '', j['message'] as String? ?? '');
}

class ChatMessage {
  const ChatMessage({
    required this.id,
    required this.role,
    required this.content,
    this.status = 'answered',
    this.citations = const [],
    this.answerText,
    this.answerLanguage,
    this.scholarlyDifferences,
    this.limitations = const [],
    this.confidence,
    this.sending = false,
    this.failed = false,
  });

  final int? id;
  final String role;

  /// The whole reply as one text; used for copying and by replies stored before evidence existed.
  final String content;
  final String status;
  final List<Citation> citations;

  /// The AI explanation alone, without the quoted sources.
  final String? answerText;
  final String? answerLanguage;
  final String? scholarlyDifferences;
  final List<Limitation> limitations;
  final double? confidence;
  final bool sending;
  final bool failed;

  bool get isUser => role == 'user';

  /// Whether this reply can be shown as explanation plus evidence cards.
  bool get isStructured => answerText != null;

  factory ChatMessage.fromJson(Map<String, dynamic> j) {
    final answer = j['answer'] as Map<String, dynamic>?;
    return ChatMessage(
      id: (j['id'] as num?)?.toInt(),
      role: j['role'] as String,
      content: j['content'] as String,
      status: j['status'] as String? ?? 'answered',
      citations: ((j['evidence'] ?? j['citations']) as List? ?? const []).map((e) => Citation.fromJson(e as Map<String, dynamic>)).toList(),
      answerText: answer?['text'] as String?,
      answerLanguage: answer?['language'] as String?,
      scholarlyDifferences: answer?['scholarly_differences'] as String?,
      limitations: ((j['limitations'] as List?) ?? const []).map((e) => Limitation.fromJson(e as Map<String, dynamic>)).toList(),
      confidence: (j['confidence'] as num?)?.toDouble(),
    );
  }
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
