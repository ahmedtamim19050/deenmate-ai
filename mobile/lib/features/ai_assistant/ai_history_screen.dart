import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/widgets/common.dart';
import '../../shared/providers/providers.dart';
import 'ai_repository.dart';

class AiHistoryScreen extends ConsumerWidget {
  const AiHistoryScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    return Scaffold(
      appBar: AppBar(title: Text(l.aiHistory)),
      body: AsyncView(
        value: ref.watch(aiConversationsProvider),
        onRetry: () => ref.invalidate(aiConversationsProvider),
        builder: (rows) => rows.isEmpty
            ? StateMessage(icon: Icons.chat_bubble_outline, title: l.noConversationsTitle, body: l.noConversationsBody)
            : ListView.separated(
                padding: const EdgeInsets.all(16),
                itemCount: rows.length,
                separatorBuilder: (_, _) => const SizedBox(height: 8),
                itemBuilder: (_, i) {
                  final c = rows[i];
                  return Card(
                    child: ListTile(
                      title: Text(c['title'] as String? ?? l.aiTitle, maxLines: 2, overflow: TextOverflow.ellipsis),
                      onTap: () => context.go('/ai?c=${c['id']}'),
                      trailing: IconButton(
                        tooltip: l.remove,
                        icon: const Icon(Icons.delete_outline),
                        onPressed: () async {
                          try {
                            await ref.read(apiClientProvider).delete('/ai/conversations/${c['id']}');
                            ref.invalidate(aiConversationsProvider);
                          } catch (_) {
                            if (context.mounted) context.toast(l.errorBody);
                          }
                        },
                      ),
                    ),
                  );
                },
              ),
      ),
    );
  }
}
