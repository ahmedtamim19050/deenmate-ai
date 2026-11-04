import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/widgets/common.dart';
import '../../shared/providers/providers.dart';
import '../../shared/repositories/activity_repository.dart';

/// Records something the person did, after an explicit tap, and reports the honest result.
Future<void> submitRecord(
  BuildContext context,
  WidgetRef ref, {
  required String type,
  required int quantity,
  required String source,
  DateTime? date,
  Map<String, dynamic>? metadata,
}) async {
  final l = context.l10n;
  final result = await ref.read(activityRepositoryProvider).record(type: type, quantity: quantity, source: source, date: date, metadata: metadata);
  if (!context.mounted) return;
  if (result.error != null) {
    context.toast(result.error!);
  } else if (result.synced) {
    context.toast(ref.read(prefsProvider).showPoints ? l.recordSavedPoints(result.points ?? 0) : l.recordSaved);
  } else {
    context.toast(ref.read(authProvider).signedIn ? l.recordPending : l.salahPendingGuest);
  }
}

class QuantityStepper extends StatelessWidget {
  const QuantityStepper({super.key, required this.value, required this.onChanged, this.min = 1, this.max = 604});

  final int value;
  final ValueChanged<int> onChanged;
  final int min;
  final int max;

  @override
  Widget build(BuildContext context) => Row(
    mainAxisAlignment: MainAxisAlignment.center,
    children: [
      IconButton.filledTonal(tooltip: context.l10n.decrease, onPressed: value <= min ? null : () => onChanged(value - 1), icon: const Icon(Icons.remove)),
      SizedBox(
        width: 72,
        child: Text('$value', textAlign: TextAlign.center, style: context.text.headlineSmall),
      ),
      IconButton.filledTonal(tooltip: context.l10n.increase, onPressed: value >= max ? null : () => onChanged(value + 1), icon: const Icon(Icons.add)),
    ],
  );
}
