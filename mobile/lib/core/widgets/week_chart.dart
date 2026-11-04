import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../features/home/home_repository.dart';
import 'common.dart';

/// Seven bars of recorded activity. A low day is simply a short bar, with no judgement attached.
class WeekChart extends StatelessWidget {
  const WeekChart({super.key, required this.days, this.value});

  final List<DaySummary> days;
  final int Function(DaySummary)? value;

  @override
  Widget build(BuildContext context) {
    final locale = Localizations.localeOf(context).languageCode;
    final read = value ?? (DaySummary d) => d.activityCount;
    final max = days.map(read).fold<int>(0, (a, b) => a > b ? a : b);
    final total = days.map(read).fold<int>(0, (a, b) => a + b);
    return Semantics(
      label: context.l10n.chartSummary(total),
      child: ExcludeSemantics(
        child: SizedBox(
          height: 120,
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              for (final d in days)
                Expanded(
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 4),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.end,
                      children: [
                        Text(read(d) == 0 ? '' : '${read(d)}', style: context.text.labelSmall),
                        const SizedBox(height: 4),
                        Flexible(
                          child: FractionallySizedBox(
                            heightFactor: max == 0 ? 0.03 : (read(d) / max).clamp(0.03, 1.0),
                            child: Container(
                              decoration: BoxDecoration(
                                color: read(d) == 0 ? context.colors.outlineVariant : context.colors.primary,
                                borderRadius: BorderRadius.circular(6),
                              ),
                            ),
                          ),
                        ),
                        const SizedBox(height: 6),
                        Text(DateFormat.E(locale).format(DateTime.parse(d.date)), style: context.text.labelSmall, maxLines: 1, overflow: TextOverflow.clip),
                      ],
                    ),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}
