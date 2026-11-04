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
                  child: Text(l.aiDisclaimer, textAlign: TextAlign.center, style: context.text.labelSmall?.copyWith(color: context.colors.onSurfaceVariant)),
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
                            decoration: InputDecoration(hintText: l.aiInputHint, counterText: '', contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12)),
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
        content: TextField(controller: controller, maxLines: 3, maxLength: 1000, decoration: InputDecoration(labelText: l.reportWhat)),
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
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(c.reference ?? c.title, style: context.text.titleSmall),
                      const SizedBox(height: 4),
                      Text(c.title, style: context.text.bodySmall),
                      if (c.author != null) Text(c.author!, style: context.text.bodySmall),
                      if (c.publisher != null) Text(c.publisher!, style: context.text.bodySmall),
                      if (c.excerpt != null) ...[const SizedBox(height: 8), Text(c.excerpt!)],
                      if (c.url != null) ...[const SizedBox(height: 8), SelectableText(c.url!, style: context.text.bodySmall)],
                    ],
                  ),
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
              SelectableText(message.content, style: context.text.bodyMedium?.copyWith(color: fg)),
              if (!isUser) ...[
                if (message.citations.isNotEmpty) ...[
                  const SizedBox(height: 8),
                  ActionChip(avatar: const Icon(Icons.format_quote, size: 16), label: Text(l.viewSources(message.citations.length)), onPressed: () => _showSources(context)),
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
                      IconButton(tooltip: l.helpful, visualDensity: VisualDensity.compact, onPressed: () => _rate(context, ref, 'helpful'), icon: const Icon(Icons.thumb_up_outlined, size: 18)),
                      IconButton(tooltip: l.notHelpful, visualDensity: VisualDensity.compact, onPressed: () => _rate(context, ref, 'not_helpful'), icon: const Icon(Icons.thumb_down_outlined, size: 18)),
                      IconButton(tooltip: l.reportIncorrect, visualDensity: VisualDensity.compact, onPressed: () => _report(context, ref), icon: const Icon(Icons.flag_outlined, size: 18)),
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
