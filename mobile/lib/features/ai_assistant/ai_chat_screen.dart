import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/network/api_exception.dart';
import '../../core/widgets/account_gate.dart';
import '../../core/widgets/common.dart';
import '../../shared/models/app_settings.dart';
import '../../shared/providers/providers.dart';
import 'ai_repository.dart';

class AiChatScreen extends ConsumerStatefulWidget {
  const AiChatScreen({super.key, this.conversationId});

  final String? conversationId;

  @override
  ConsumerState<AiChatScreen> createState() => _AiChatScreenState();
}

class _AiChatScreenState extends ConsumerState<AiChatScreen> {
  final _input = TextEditingController();
  final _scroll = ScrollController();
  final List<ChatMessage> _messages = [];
  String? _conversationId;
  bool _sending = false;
  bool _loadingHistory = false;

  @override
  void initState() {
    super.initState();
    if (widget.conversationId != null) _open(widget.conversationId!);
  }

  @override
  void didUpdateWidget(AiChatScreen old) {
    super.didUpdateWidget(old);
    if (widget.conversationId != old.conversationId && widget.conversationId != null) _open(widget.conversationId!);
  }

  @override
  void dispose() {
    _input.dispose();
    _scroll.dispose();
    super.dispose();
  }

  Future<void> _open(String id) async {
    setState(() {
      _loadingHistory = true;
      _conversationId = id;
      _messages.clear();
    });
    try {
      final rows = await ref.read(aiRepositoryProvider).messages(id);
      if (mounted) setState(() => _messages.addAll(rows));
    } catch (e) {
      if (mounted) context.toast(context.l10n.errorBody);
    } finally {
      if (mounted) setState(() => _loadingHistory = false);
    }
    _toEnd();
  }

  void _newChat() => setState(() {
    _messages.clear();
    _conversationId = null;
  });

  void _toEnd() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scroll.hasClients) _scroll.animateTo(_scroll.position.maxScrollExtent, duration: const Duration(milliseconds: 240), curve: Curves.easeOut);
    });
  }

  Future<void> _send([String? preset]) async {
    final text = (preset ?? _input.text).trim();
    if (text.length < 2 || _sending) return;
    _input.clear();
    setState(() {
      _sending = true;
      _messages.add(ChatMessage(id: null, role: 'user', content: text));
    });
    _toEnd();
    try {
      final result = await ref.read(aiRepositoryProvider).send(text, _conversationId);
      if (!mounted) return;
      setState(() {
        _conversationId = result.conversationId;
        _messages.add(result.message);
      });
      ref.read(dataVersionProvider.notifier).bump();
    } on ApiException catch (e) {
      if (!mounted) return;
      final l = context.l10n;
      final message = switch (e.kind) {
        ApiErrorKind.offline => l.aiOffline,
        ApiErrorKind.rateLimited => l.aiRateLimited,
        _ => l.errorBody,
      };
      setState(() => _messages.add(ChatMessage(id: null, role: 'assistant', content: message, status: 'local_error', failed: true)));
    } finally {
      if (mounted) setState(() => _sending = false);
    }
    _toEnd();
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final auth = ref.watch(authProvider);
    final config = ref.watch(publicSettingsProvider).value;

    return Scaffold(
      appBar: AppBar(
        title: Text(l.aiTitle),
        actions: [
          if (auth.signedIn) ...[
            IconButton(tooltip: l.newChat, onPressed: _newChat, icon: const Icon(Icons.add_comment_outlined)),
            IconButton(tooltip: l.aiHistory, onPressed: () => context.push('/ai-history'), icon: const Icon(Icons.history)),
          ],
        ],
      ),
      body: !auth.signedIn
          ? AccountRequired(reason: l.aiNeedsAccount)
          : Column(
              children: [
                if (config != null && !config.aiConfigured)
                  MaterialBanner(
                    leading: const Icon(Icons.settings_suggest_outlined),
                    content: Text(l.aiNotConfiguredBanner),
                    actions: const [SizedBox.shrink()],
                  ),
                Expanded(
                  child: _loadingHistory
                      ? const Center(child: CircularProgressIndicator())
                      : _messages.isEmpty
                      ? _Intro(suggestions: config?.suggestedQuestions ?? const [], onAsk: _send)
                      : ListView.builder(
                          controller: _scroll,
                          padding: const EdgeInsets.all(16),
                          itemCount: _messages.length + (_sending ? 1 : 0),
                          itemBuilder: (_, i) {
                            if (i == _messages.length) return const _Thinking();
                            return _Bubble(message: _messages[i]);
                          },
                        ),
                ),
                Padding(
                  padding: const EdgeInsets.fromLTRB(16, 0, 16, 2),
                  child: Text(
                    l.aiDisclaimer,
                    textAlign: TextAlign.center,
                    style: context.text.labelSmall?.copyWith(color: context.colors.onSurfaceVariant),
                  ),
                ),
                SafeArea(
                  top: false,
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(12, 6, 12, 10),
                    child: Row(
                      children: [
                        Expanded(
                          child: TextField(
                            controller: _input,
                            minLines: 1,
                            maxLines: 4,
                            maxLength: 1500,
                            textInputAction: TextInputAction.newline,
                            decoration: InputDecoration(
                              hintText: l.aiInputHint,
                              counterText: '',
                              contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                            ),
                          ),
                        ),
                        const SizedBox(width: 8),
                        IconButton.filled(tooltip: l.send, onPressed: _sending ? null : _send, icon: const Icon(Icons.arrow_upward)),
                      ],
                    ),
                  ),
                ),
              ],
            ),
    );
  }
}

class _Intro extends StatelessWidget {
  const _Intro({required this.suggestions, required this.onAsk});

  final List<String> suggestions;
  final ValueChanged<String> onAsk;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return ListView(
      padding: const EdgeInsets.all(20),
      children: [
        Icon(Icons.auto_awesome, size: 40, color: context.colors.primary),
        const SizedBox(height: 12),
        Text(l.aiIntroTitle, style: context.text.titleLarge),
        const SizedBox(height: 6),
        Text(l.aiIntroBody, style: context.text.bodyMedium?.copyWith(color: context.colors.onSurfaceVariant)),
        const SizedBox(height: 18),
        Text(l.tryAsking, style: context.text.titleSmall),
        const SizedBox(height: 8),
        for (final s in suggestions)
          Padding(
            padding: const EdgeInsets.only(bottom: 8),
            child: Card(
              child: ListTile(dense: true, title: Text(s), trailing: const Icon(Icons.north_east, size: 16), onTap: () => onAsk(s)),
            ),
          ),
      ],
    );
  }
}

class _Thinking extends StatelessWidget {
  const _Thinking();

  @override
  Widget build(BuildContext context) => Align(
    alignment: AlignmentDirectional.centerStart,
    child: Padding(
      padding: const EdgeInsets.all(10),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2)),
          const SizedBox(width: 10),
          Text(context.l10n.aiSearching, style: context.text.bodySmall),
        ],
      ),
    ),
  );
}

class _Bubble extends ConsumerWidget {
  const _Bubble({required this.message});

  final ChatMessage message;

  Future<void> _rate(BuildContext context, WidgetRef ref, String rating) async {
    final l = context.l10n;
    try {
      await ref.read(aiRepositoryProvider).feedback(message.id!, rating);
      if (context.mounted) context.toast(l.feedbackThanks);
    } catch (_) {
      if (context.mounted) context.toast(l.errorBody);
    }
  }

  Future<void> _report(BuildContext context, WidgetRef ref) async {
    final l = context.l10n;
    final controller = TextEditingController();
    final send = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        title: Text(l.reportIncorrect),
        content: TextField(
          controller: controller,
          maxLines: 3,
          maxLength: 1000,
          decoration: InputDecoration(labelText: l.reportWhat),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(c, false), child: Text(l.cancel)),
          FilledButton(onPressed: () => Navigator.pop(c, true), child: Text(l.send)),
        ],
      ),
    );
    if (send != true) return;
    try {
      await ref.read(aiRepositoryProvider).feedback(message.id!, 'incorrect', comment: controller.text.trim());
      if (context.mounted) context.toast(l.reportThanks);
    } catch (_) {
      if (context.mounted) context.toast(l.errorBody);
    }
  }

  void _showSources(BuildContext context) {
    final l = context.l10n;
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (_) => DraggableScrollableSheet(
        expand: false,
        initialChildSize: 0.6,
        builder: (_, controller) => ListView(
          controller: controller,
          padding: const EdgeInsets.all(18),
          children: [
            Text(l.sources, style: context.text.titleLarge),
            const SizedBox(height: 10),
            for (final c in message.citations) ...[
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(14),
                  child: _EvidenceCard(citation: c, detailed: true),
                ),
              ),
              const SizedBox(height: 8),
            ],
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final isUser = message.isUser;
    final bg = isUser ? context.colors.primary : context.colors.surface;
    final fg = isUser ? context.colors.onPrimary : context.colors.onSurface;
    return Align(
      alignment: isUser ? AlignmentDirectional.centerEnd : AlignmentDirectional.centerStart,
      child: ConstrainedBox(
        constraints: BoxConstraints(maxWidth: MediaQuery.sizeOf(context).width * (isUser ? 0.8 : 0.92)),
        child: Container(
          margin: const EdgeInsets.only(bottom: 10),
          padding: const EdgeInsets.fromLTRB(14, 12, 14, 8),
          decoration: BoxDecoration(
            color: bg,
            borderRadius: BorderRadius.circular(16),
            border: isUser ? null : Border.all(color: context.colors.outlineVariant.withValues(alpha: 0.5)),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (isUser || !message.isStructured)
                SelectableText(message.content, style: context.text.bodyMedium?.copyWith(color: fg))
              else
                _StructuredReply(message: message),
              if (!isUser) ...[
                if (message.citations.isNotEmpty) ...[
                  const SizedBox(height: 8),
                  ActionChip(
                    avatar: const Icon(Icons.format_quote, size: 16),
                    label: Text(l.viewSources(message.citations.length)),
                    onPressed: () => _showSources(context),
                  ),
                ],
                Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    IconButton(
                      tooltip: l.copy,
                      visualDensity: VisualDensity.compact,
                      onPressed: () {
                        Clipboard.setData(ClipboardData(text: message.content));
                        context.toast(l.copied);
                      },
                      icon: const Icon(Icons.copy, size: 18),
                    ),
                    if (message.id != null && !message.failed) ...[
                      IconButton(
                        tooltip: l.helpful,
                        visualDensity: VisualDensity.compact,
                        onPressed: () => _rate(context, ref, 'helpful'),
                        icon: const Icon(Icons.thumb_up_outlined, size: 18),
                      ),
                      IconButton(
                        tooltip: l.notHelpful,
                        visualDensity: VisualDensity.compact,
                        onPressed: () => _rate(context, ref, 'not_helpful'),
                        icon: const Icon(Icons.thumb_down_outlined, size: 18),
                      ),
                      IconButton(
                        tooltip: l.reportIncorrect,
                        visualDensity: VisualDensity.compact,
                        onPressed: () => _report(context, ref),
                        icon: const Icon(Icons.flag_outlined, size: 18),
                      ),
                    ],
                  ],
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

/// An assistant reply shown in parts: the AI explanation, clearly labelled, then the evidence
/// exactly as published (Arabic, approved translation, credit), then any limitations.
class _StructuredReply extends StatelessWidget {
  const _StructuredReply({required this.message});

  final ChatMessage message;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final quran = message.citations.where((c) => c.isQuran).take(3).toList();
    final others = message.citations.where((c) => !c.isQuran).toList();
    final muted = context.text.bodySmall?.copyWith(color: context.colors.onSurfaceVariant);
    final bangla = message.answerLanguage == 'bn';

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (message.citations.isNotEmpty && message.status == 'answered') ...[
          Text(l.aiExplanation, style: context.text.labelLarge?.copyWith(color: context.colors.primary)),
          const SizedBox(height: 4),
        ],
        SelectableText(
          message.answerText!,
          style: context.text.bodyMedium?.copyWith(fontFamily: bangla ? 'HindSiliguri' : null, height: bangla ? 1.6 : null),
        ),
        if (message.scholarlyDifferences != null) ...[const SizedBox(height: 8), SelectableText(message.scholarlyDifferences!, style: context.text.bodyMedium)],
        if (message.citations.isNotEmpty && message.status == 'answered') ...[const SizedBox(height: 4), Text(l.aiExplanationNote, style: muted)],
        if (quran.isNotEmpty) ...[
          const SizedBox(height: 12),
          Text(l.aiEvidenceQuran, style: context.text.labelLarge?.copyWith(color: context.colors.primary)),
          for (final c in quran) ...[const SizedBox(height: 8), _EvidenceCard(citation: c)],
        ],
        for (final c in others) ...[const SizedBox(height: 8), _EvidenceCard(citation: c)],
        if (message.limitations.isNotEmpty) ...[
          const SizedBox(height: 12),
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(color: context.colors.surfaceContainerHighest.withValues(alpha: 0.6), borderRadius: BorderRadius.circular(10)),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Icon(Icons.info_outline, size: 16, color: context.colors.onSurfaceVariant),
                    const SizedBox(width: 6),
                    Text(l.aiPleaseNote, style: context.text.labelMedium),
                  ],
                ),
                for (final limitation in message.limitations) ...[const SizedBox(height: 4), Text(limitation.message, style: muted)],
              ],
            ),
          ),
        ],
      ],
    );
  }
}

/// One source passage, quoted exactly and kept visually apart from the AI explanation.
class _EvidenceCard extends StatelessWidget {
  const _EvidenceCard({required this.citation, this.detailed = false});

  final Citation citation;
  final bool detailed;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = citation;
    final muted = context.text.bodySmall?.copyWith(color: context.colors.onSurfaceVariant);
    final heading = switch (c.type) {
      'tafsir' => '${l.aiEvidenceTafsir} · ${c.label}',
      'hadith' => c.collection != null && c.hadithNumber != null ? l.aiHadithNumber(c.collection!, c.hadithNumber!) : '${l.aiEvidenceHadith} · ${c.label}',
      _ => c.label,
    };
    final quotation = c.quotation;

    return Container(
      width: double.infinity,
      padding: const EdgeInsetsDirectional.fromSTEB(12, 8, 8, 8),
      decoration: BoxDecoration(
        border: BorderDirectional(start: BorderSide(color: context.colors.primary, width: 3)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(heading, style: context.text.titleSmall),
          if (c.arabic != null) ...[
            const SizedBox(height: 6),
            SizedBox(
              width: double.infinity,
              child: SelectableText(
                c.arabic!,
                textDirection: TextDirection.rtl,
                textAlign: TextAlign.right,
                style: const TextStyle(fontFamily: 'ScheherazadeNew', fontSize: 21, height: 1.8),
              ),
            ),
          ],
          if (quotation != null) ...[
            const SizedBox(height: 6),
            SelectableText('“$quotation”', style: context.text.bodyMedium?.copyWith(fontFamily: c.language == 'bn' ? 'HindSiliguri' : null, height: 1.5)),
          ],
          if (c.isQuran && c.translation != null) ...[const SizedBox(height: 2), Text(l.aiExactTranslation, style: muted)],
          if (c.type == 'hadith' && c.grade != null) ...[
            const SizedBox(height: 4),
            Text(c.gradeSource != null ? l.aiHadithGradeBy(c.grade!, c.gradeSource!) : l.aiHadithGrade(c.grade!), style: muted),
          ],
          const SizedBox(height: 6),
          Text('— ${c.credit}', style: muted),
          if (detailed) ...[
            if (c.book != null) Text(c.book!, style: muted),
            if (c.url != null) ...[const SizedBox(height: 4), SelectableText(c.url!, style: muted)],
            if (c.licenseUrl != null) SelectableText(c.licenseUrl!, style: muted),
          ],
        ],
      ),
    );
  }
}
