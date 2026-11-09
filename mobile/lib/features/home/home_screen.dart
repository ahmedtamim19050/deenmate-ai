import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../app/theme/app_theme.dart';
import '../../core/notifications/prayer_notification_service.dart';
import '../../core/prayer/prayer_calculator.dart';
import '../../core/prayer/prayer_settings.dart';
import '../../core/utils/dates.dart';
import '../../core/widgets/common.dart';
import '../../core/widgets/geometric_pattern.dart';
import '../../core/widgets/week_chart.dart';
import '../../shared/models/app_settings.dart';
import '../../shared/providers/providers.dart';
import '../salah/salah_repository.dart';
import 'home_repository.dart';

class HomeScreen extends ConsumerWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final dashboard = ref.watch(dashboardProvider);
    final auth = ref.watch(authProvider);
    final prefs = ref.watch(prefsProvider);
    final settings = ref.watch(prayerSettingsProvider);

    return Scaffold(
      body: RefreshIndicator(
        onRefresh: () async {
          await ref.read(syncServiceProvider).flush();
          ref.read(dataVersionProvider.notifier).bump();
          ref.invalidate(publicSettingsProvider);
        },
        child: ListView(
          padding: EdgeInsets.zero,
          children: [
            _Hero(name: auth.user?.name, settings: settings),
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 4, 16, 24),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const _PendingBanner(),
                  SectionHeader(l.todayPrayers),
                  _PrayerStrip(settings: settings, recorded: dashboard.value?.salahToday ?? const {}),
                  if (auth.signedIn)
                    ...dashboard.when(
                      data: (d) => d == null ? const <Widget>[] : _personal(context, ref, d, prefs.showPoints),
                      loading: () => const [Padding(padding: EdgeInsets.all(32), child: Center(child: CircularProgressIndicator()))],
                      error: (e, _) => [ErrorState(error: e, compact: true, onRetry: () => ref.invalidate(dashboardProvider))],
                    )
                  else ...[
                    SectionHeader(l.yourProgress),
                    Card(
                      child: StateMessage(
                        compact: true,
                        icon: Icons.insights_outlined,
                        title: l.guestProgressTitle,
                        body: l.guestProgressBody,
                        action: FilledButton.tonal(onPressed: () => context.push('/register'), child: Text(l.createAccount)),
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  List<Widget> _personal(BuildContext context, WidgetRef ref, Dashboard d, bool showPoints) {
    final l = context.l10n;
    final today = d.today;
    return [
      if (showPoints) ...[
        const SizedBox(height: 14),
        Card(
          clipBehavior: Clip.antiAlias,
          child: InkWell(
            onTap: () => context.push('/points'),
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Row(
                children: [
                  const Icon(Icons.stars_rounded, color: AppColors.gold, size: 34),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(l.deenPoints, style: context.text.labelMedium),
                        Text('${d.balance}', style: context.text.headlineSmall),
                        Text(l.pointsDisclaimerShort, style: context.text.bodySmall?.copyWith(color: context.colors.onSurfaceVariant)),
                      ],
                    ),
                  ),
                  Text(l.pointsToday(d.earnedToday), style: context.text.labelMedium),
                ],
              ),
            ),
          ),
        ),
      ],
      SectionHeader(l.todaySummary),
      GridView.count(
        crossAxisCount: 2,
        shrinkWrap: true,
        physics: const NeverScrollableScrollPhysics(),
        mainAxisSpacing: 10,
        crossAxisSpacing: 10,
        childAspectRatio: 2.2,
        children: [
          _Metric(icon: Icons.mosque_outlined, label: l.salahRecorded, value: '${today.salahCount}', onTap: () => context.push('/salah')),
          _Metric(icon: Icons.menu_book_outlined, label: l.quranPages, value: '${today.quranPages}', onTap: () => context.push('/quran')),
          _Metric(icon: Icons.radio_button_checked, label: l.dhikrCount, value: '${today.dhikrCount}', onTap: () => context.push('/dhikr')),
          _Metric(icon: Icons.nights_stay_outlined, label: l.fastingStatus, value: today.fasted ? l.fastRecorded : l.fastNotRecorded, onTap: () => context.push('/fasting')),
        ],
      ),
      SectionHeader(l.thisWeek, trailing: TextButton(onPressed: () => context.push('/progress'), child: Text(l.seeMore))),
      Card(
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: WeekChart(days: d.weekly),
        ),
      ),
      if (d.goals.isNotEmpty) ...[
        SectionHeader(l.personalGoals, trailing: TextButton(onPressed: () => context.push('/goals'), child: Text(l.seeMore))),
        for (final g in d.goals) ...[
          Card(
            child: ListTile(
              title: Text(g['title'] as String),
              subtitle: g['current_value'] == null
                  ? null
                  : Padding(
                      padding: const EdgeInsets.only(top: 6),
                      child: LinearProgressIndicator(value: ((g['current_value'] as num) / (g['target_value'] as num)).clamp(0.0, 1.0).toDouble(), minHeight: 6, borderRadius: BorderRadius.circular(4)),
                    ),
              onTap: () => context.push('/goals'),
            ),
          ),
          const SizedBox(height: 8),
        ],
      ],
      if (d.nextLesson != null) ...[
        SectionHeader(l.suggestedForYou),
        Card(
          child: NavTile(
            icon: Icons.school_outlined,
            title: d.nextLesson!['title'] as String,
            subtitle: d.nextLesson!['course'] as String,
            onTap: () => context.push('/lessons/${d.nextLesson!['id']}'),
          ),
        ),
        const SizedBox(height: 8),
        Card(
          child: NavTile(
            icon: Icons.auto_awesome_outlined,
            title: l.askAssistant,
            subtitle: l.askAssistantBody,
            onTap: () => context.go('/ai'),
          ),
        ),
      ],
      if (d.recent.isNotEmpty) ...[
        SectionHeader(l.recentActivity, trailing: TextButton(onPressed: () => context.push('/history'), child: Text(l.seeMore))),
        Card(
          child: Column(
            children: [
              for (final r in d.recent)
                ListTile(
                  dense: true,
                  title: Text(r['type_name'] as String? ?? ''),
                  subtitle: Text('${r['quantity']} ${r['unit']}'),
                  trailing: showPoints ? Text('+${r['points']}', style: context.text.labelLarge) : null,
                ),
            ],
          ),
        ),
      ],
      if (d.recent.isEmpty)
        Padding(
          padding: const EdgeInsets.only(top: 16),
          child: Card(
            child: StateMessage(compact: true, icon: Icons.spa_outlined, title: l.startSmallTitle, body: l.startSmallBody),
          ),
        ),
    ];
  }
}

class _Hero extends ConsumerStatefulWidget {
  const _Hero({required this.name, required this.settings});

  final String? name;
  final PrayerSettings settings;

  @override
  ConsumerState<_Hero> createState() => _HeroState();
}

class _HeroState extends ConsumerState<_Hero> {
  Timer? _timer;

  @override
  void initState() {
    super.initState();
    _timer = Timer.periodic(const Duration(seconds: 1), (_) {
      if (mounted) setState(() {});
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final locale = Localizations.localeOf(context).languageCode;
    final calc = ref.read(calculatorProvider);
    final s = widget.settings;
    final now = s.hasLocation ? calc.nowIn(s) : DateTime.now();
    final hour = now.hour;
    final greeting = hour < 12 ? l.greetingMorning : (hour < 17 ? l.greetingAfternoon : l.greetingEvening);
    final next = s.hasLocation ? calc.nextPrayer(s, DateTime.now().toUtc()) : null;
    final hijriOffset = ref.read(prefsProvider).hijriOffset;

    return Container(
      clipBehavior: Clip.antiAlias,
      decoration: const BoxDecoration(
        gradient: LinearGradient(begin: Alignment.topLeft, end: Alignment.bottomRight, colors: [AppColors.emerald, AppColors.green]),
        borderRadius: BorderRadius.vertical(bottom: Radius.circular(26)),
      ),
      child: Stack(
        children: [
          const Positioned.fill(child: GeometricPattern()),
          SafeArea(
            bottom: false,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(20, 16, 20, 22),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(widget.name == null ? greeting : '$greeting, ${widget.name}', style: context.text.titleLarge?.copyWith(color: Colors.white)),
                  const SizedBox(height: 4),
                  Text(longDate(now, locale), style: context.text.bodyMedium?.copyWith(color: Colors.white.withValues(alpha: 0.85))),
                  Text(hijriDate(now, locale, offsetDays: hijriOffset), style: context.text.bodyMedium?.copyWith(color: AppColors.gold)),
                  const SizedBox(height: 18),
                  if (next != null)
                    Row(
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(l.nextPrayer, style: context.text.labelMedium?.copyWith(color: Colors.white.withValues(alpha: 0.8))),
                              Text(prayerName(l, next.prayer), style: context.text.headlineSmall?.copyWith(color: Colors.white)),
                              Text(clockTime(calc.inZone(s, next.at), locale), style: context.text.bodyMedium?.copyWith(color: Colors.white)),
                            ],
                          ),
                        ),
                        Semantics(
                          label: l.timeRemaining,
                          child: Text(
                            countdown(next.at.difference(DateTime.now().toUtc())),
                            style: context.text.headlineMedium?.copyWith(color: AppColors.gold, fontFeatures: const [FontFeature.tabularFigures()]),
                          ),
                        ),
                      ],
                    )
                  else
                    FilledButton.tonal(onPressed: () => context.push('/salah/settings'), child: Text(l.setLocationForTimes)),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _PrayerStrip extends ConsumerWidget {
  const _PrayerStrip({required this.settings, required this.recorded});

  final PrayerSettings settings;
  final Set<String> recorded;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    if (!settings.hasLocation) {
      return Card(
        child: StateMessage(
          compact: true,
          icon: Icons.location_off_outlined,
          title: l.noLocationTitle,
          body: l.noLocationBody,
          action: OutlinedButton(onPressed: () => context.push('/salah/settings'), child: Text(l.chooseLocation)),
        ),
      );
    }
    final locale = Localizations.localeOf(context).languageCode;
    final calc = ref.read(calculatorProvider);
    final now = calc.nowIn(settings);
    final day = calc.compute(settings, DateTime(now.year, now.month, now.day));
    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () => context.push('/salah'),
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 6),
          child: Row(
            children: [
              for (final p in fardPrayers)
                Expanded(
                  child: Column(
                    children: [
                      Text(prayerName(l, p), style: context.text.labelMedium, maxLines: 1, overflow: TextOverflow.fade, softWrap: false),
                      const SizedBox(height: 4),
                      Text(clockTime(calc.inZone(settings, day[p]), locale).replaceAll(' ', ' '), style: context.text.bodySmall, maxLines: 1, overflow: TextOverflow.fade, softWrap: false),
                      const SizedBox(height: 6),
                      Icon(recorded.contains(p) ? Icons.check_circle : Icons.circle_outlined, size: 18, color: recorded.contains(p) ? context.colors.primary : context.colors.outlineVariant),
                    ],
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Metric extends StatelessWidget {
  const _Metric({required this.icon, required this.label, required this.value, this.onTap});

  final IconData icon;
  final String label;
  final String value;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) => Card(
    clipBehavior: Clip.antiAlias,
    child: InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Row(
          children: [
            Icon(icon, color: context.colors.primary),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(value, style: context.text.titleSmall, maxLines: 1, overflow: TextOverflow.ellipsis),
                  Text(label, style: context.text.bodySmall, maxLines: 1, overflow: TextOverflow.ellipsis),
                ],
              ),
            ),
          ],
        ),
      ),
    ),
  );
}

class _PendingBanner extends ConsumerWidget {
  const _PendingBanner();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final pending = ref.watch(pendingOutboxProvider).value ?? const [];
    if (pending.isEmpty) return const SizedBox.shrink();
    final l = context.l10n;
    final signedIn = ref.watch(authProvider).signedIn;
    return Padding(
      padding: const EdgeInsets.only(top: 12),
      child: Card(
        color: context.colors.secondaryContainer,
        child: ListTile(
          leading: const Icon(Icons.sync),
          title: Text(l.pendingSyncCount(pending.length)),
          subtitle: Text(signedIn ? l.pendingSyncBody : l.pendingSyncGuest),
        ),
      ),
    );
  }
}
