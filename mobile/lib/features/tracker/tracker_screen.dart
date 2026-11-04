import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../core/widgets/common.dart';

class TrackerScreen extends StatelessWidget {
  const TrackerScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final sections = <(IconData, String, String, String)>[
      (Icons.mosque_outlined, l.salahTitle, l.trackerSalahBody, '/salah'),
      (Icons.menu_book_outlined, l.quranTitle, l.trackerQuranBody, '/quran'),
      (Icons.radio_button_checked, l.dhikrTitle, l.trackerDhikrBody, '/dhikr'),
      (Icons.nights_stay_outlined, l.fastingTitle, l.trackerFastingBody, '/fasting'),
      (Icons.volunteer_activism_outlined, l.goodDeedsTitle, l.trackerDeedsBody, '/deeds'),
      (Icons.wb_twilight_outlined, l.sunnahTitle, l.trackerSunnahBody, '/sunnah'),
      (Icons.flag_outlined, l.personalGoals, l.trackerGoalsBody, '/goals'),
      (Icons.history, l.activityHistory, l.trackerHistoryBody, '/history'),
      (Icons.insights_outlined, l.yourProgress, l.trackerProgressBody, '/progress'),
    ];
    return Scaffold(
      appBar: AppBar(title: Text(l.navTracker)),
      body: ListView.separated(
        padding: const EdgeInsets.all(16),
        itemCount: sections.length + 1,
        separatorBuilder: (_, _) => const SizedBox(height: 10),
        itemBuilder: (_, i) {
          if (i == 0) return Text(l.trackerNote, style: context.text.bodyMedium?.copyWith(color: context.colors.onSurfaceVariant));
          final (icon, title, body, route) = sections[i - 1];
          return Card(child: NavTile(icon: icon, title: title, subtitle: body, onTap: () => context.push(route)));
        },
      ),
    );
  }
}
