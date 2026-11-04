import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../app/theme/app_theme.dart';
import '../../core/utils/dates.dart';
import '../../core/widgets/account_gate.dart';
import '../../core/widgets/common.dart';
import '../../shared/providers/providers.dart';

final pointsBalanceProvider = FutureProvider<int>((ref) async {
  ref.watch(dataVersionProvider);
  return ((await ref.read(apiClientProvider).get('/points/balance'))['data']['balance'] as num).toInt();
});

final pointsHistoryProvider = FutureProvider<List<Map<String, dynamic>>>((ref) async {
  ref.watch(dataVersionProvider);
  return ((await ref.read(apiClientProvider).get('/points/history'))['data'] as List).cast<Map<String, dynamic>>();
});

class PointsScreen extends ConsumerWidget {
  const PointsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final locale = Localizations.localeOf(context).languageCode;
    if (!ref.watch(authProvider).signedIn) {
      return Scaffold(appBar: AppBar(title: Text(l.deenPoints)), body: AccountRequired(reason: l.pointsNeedAccount));
    }
    return Scaffold(
      appBar: AppBar(title: Text(l.deenPoints)),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(18),
              child: Column(
                children: [
                  const Icon(Icons.stars_rounded, color: AppColors.gold, size: 42),
                  const SizedBox(height: 6),
                  Text(ref.watch(pointsBalanceProvider).when(data: (b) => '$b', loading: () => '…', error: (_, _) => '-'), style: context.text.displaySmall),
                  const SizedBox(height: 10),
                  Text(l.pointsDisclaimerLong, textAlign: TextAlign.center, style: context.text.bodySmall),
                ],
              ),
            ),
          ),
          SectionHeader(l.pointsHistory),
          ref.watch(pointsHistoryProvider).when(
            loading: () => const Padding(padding: EdgeInsets.all(24), child: Center(child: CircularProgressIndicator())),
            error: (e, _) => ErrorState(error: e, compact: true, onRetry: () => ref.invalidate(pointsHistoryProvider)),
            data: (rows) => rows.isEmpty
                ? StateMessage(compact: true, icon: Icons.stars_outlined, title: l.noPointsTitle, body: l.noPointsBody)
                : Card(
                    child: Column(
                      children: [
                        for (final r in rows)
                          ListTile(
                            dense: true,
                            title: Text(r['activity'] as String? ?? _typeLabel(context, r['type'] as String)),
                            subtitle: Text([
                              _typeLabel(context, r['type'] as String),
                              shortDate(DateTime.parse(r['created_at'] as String).toLocal(), locale),
                            ].join(' · ')),
                            trailing: Text('${(r['points'] as num) > 0 ? '+' : ''}${r['points']}', style: context.text.titleSmall),
                          ),
                      ],
                    ),
                  ),
          ),
        ],
      ),
    );
  }

  String _typeLabel(BuildContext context, String type) => switch (type) {
    'earn' => context.l10n.txEarn,
    'reverse' => context.l10n.txReverse,
    _ => context.l10n.txAdjust,
  };
}
