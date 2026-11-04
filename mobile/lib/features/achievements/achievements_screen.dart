import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/widgets/account_gate.dart';
import '../../core/widgets/common.dart';
import '../../shared/providers/providers.dart';

final achievementsProvider = FutureProvider<List<Map<String, dynamic>>>((ref) async {
  ref.watch(dataVersionProvider);
  return ((await ref.read(apiClientProvider).get('/achievements'))['data'] as List).cast<Map<String, dynamic>>();
});

const _icons = <String, IconData>{
  'menu_book': Icons.menu_book,
  'volunteer_activism': Icons.volunteer_activism,
  'school': Icons.school,
  'flag': Icons.flag,
  'radio_button_checked': Icons.radio_button_checked,
  'nights_stay': Icons.nights_stay,
  'mosque': Icons.mosque,
  'auto_stories': Icons.auto_stories,
};

class AchievementsScreen extends ConsumerWidget {
  const AchievementsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    if (!ref.watch(authProvider).signedIn) {
      return Scaffold(appBar: AppBar(title: Text(l.achievements)), body: AccountRequired(reason: l.pointsNeedAccount));
    }
    return Scaffold(
      appBar: AppBar(title: Text(l.achievements)),
      body: AsyncView(
        value: ref.watch(achievementsProvider),
        onRetry: () => ref.invalidate(achievementsProvider),
        builder: (rows) => rows.isEmpty
            ? StateMessage(icon: Icons.emoji_events_outlined, title: l.noRecordsTitle)
            : ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  Text(l.achievementsNote, style: context.text.bodySmall),
                  const SizedBox(height: 10),
                  for (final a in rows) ...[
                    Card(
                      child: ListTile(
                        leading: Icon(_icons[a['icon']] ?? Icons.star, color: a['earned'] == true ? context.colors.primary : context.colors.outlineVariant, size: 30),
                        title: Text(a['name'] as String, style: context.text.titleSmall),
                        subtitle: Text(a['description'] as String),
                        trailing: a['earned'] == true ? Icon(Icons.check_circle, color: context.colors.primary) : null,
                      ),
                    ),
                    const SizedBox(height: 8),
                  ],
                ],
              ),
      ),
    );
  }
}
