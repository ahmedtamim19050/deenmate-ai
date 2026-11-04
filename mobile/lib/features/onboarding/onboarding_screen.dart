import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../app/theme/app_theme.dart';
import '../../core/prayer/prayer_settings.dart';
import '../../core/widgets/common.dart';
import '../../core/widgets/geometric_pattern.dart';
import '../../shared/providers/providers.dart';
import '../salah/prayer_settings_widgets.dart';
import '../salah/salah_repository.dart';
import '../salah/templates_screen.dart';

/// Nothing here is forced: GPS, notifications and an account can all be skipped and changed later.
class OnboardingScreen extends ConsumerStatefulWidget {
  const OnboardingScreen({super.key});

  @override
  ConsumerState<OnboardingScreen> createState() => _OnboardingScreenState();
}

class _OnboardingScreenState extends ConsumerState<OnboardingScreen> {
  final _pages = PageController();
  int _page = 0;
  late PrayerSettings _settings = ref.read(prayerConfigProvider).settings;
  bool? _notificationsGranted;

  static const _pageCount = 10;

  void _next() {
    if (_page == _pageCount - 1) {
      _finish();
      return;
    }
    _pages.nextPage(duration: const Duration(milliseconds: 280), curve: Curves.easeOutCubic);
  }

  void _back() => _pages.previousPage(duration: const Duration(milliseconds: 280), curve: Curves.easeOutCubic);

  Future<void> _saveSettings(PrayerSettings s) async {
    setState(() => _settings = s);
    await ref.read(prayerConfigProvider).saveSettings(s);
    ref.read(prayerSettingsVersionProvider.notifier).bump();
  }

  Future<void> _finish() async {
    final prefs = ref.read(prefsProvider);
    prefs.onboardingDone = true;
    if (!ref.read(authProvider).signedIn) ref.read(authProvider.notifier).continueAsGuest();
    if (_settings.hasLocation && (_notificationsGranted ?? false)) {
      await ref.read(notificationSchedulerProvider).run();
    }
    if (mounted) context.go('/home');
  }

  Future<void> _account(String route) async {
    await context.push(route);
    if (mounted && ref.read(authProvider).signedIn) _next();
  }

  @override
  void dispose() {
    _pages.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    ref.watch(authProvider);
    return Scaffold(
      body: SafeArea(
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(8, 6, 20, 0),
              child: Row(
                children: [
                  _page == 0
                      ? const SizedBox(width: 48)
                      : IconButton(tooltip: l.back, onPressed: _back, icon: const BackButtonIcon()),
                  Expanded(
                    child: ClipRRect(
                      borderRadius: BorderRadius.circular(4),
                      child: LinearProgressIndicator(value: (_page + 1) / _pageCount, minHeight: 6),
                    ),
                  ),
                ],
              ),
            ),
            Expanded(
              child: PageView(
                controller: _pages,
                physics: const NeverScrollableScrollPhysics(),
                onPageChanged: (i) => setState(() => _page = i),
                children: [
                  _Welcome(onNext: _next),
                  _Step(
                    title: l.onboardLanguageTitle,
                    onNext: _next,
                    child: const LanguagePicker(),
                  ),
                  _Step(
                    title: l.onboardAccountTitle,
                    body: l.onboardAccountBody,
                    onNext: _next,
                    nextLabel: l.continueAsGuest,
                    child: Column(
                      children: [
                        FilledButton(onPressed: () => _account('/register'), style: FilledButton.styleFrom(minimumSize: const Size.fromHeight(52)), child: Text(l.createAccount)),
                        const SizedBox(height: 10),
                        OutlinedButton(onPressed: () => _account('/login'), style: OutlinedButton.styleFrom(minimumSize: const Size.fromHeight(52)), child: Text(l.signIn)),
                        const SizedBox(height: 14),
                        Text(l.guestExplain, style: context.text.bodySmall),
                      ],
                    ),
                  ),
                  _Step(
                    title: l.onboardLocationTitle,
                    body: l.onboardLocationBody,
                    onNext: _next,
                    nextLabel: _settings.hasLocation ? l.continueLabel : l.skipForNow,
                    child: LocationPicker(settings: _settings, onChanged: _saveSettings),
                  ),
                  _Step(
                    title: l.onboardMethodTitle,
                    body: l.onboardMethodBody,
                    onNext: _next,
                    child: MethodPicker(settings: _settings, onChanged: _saveSettings),
                  ),
                  _Step(
                    title: l.onboardAsrTitle,
                    body: l.onboardAsrBody,
                    onNext: _next,
                    child: AsrPicker(settings: _settings, onChanged: _saveSettings),
                  ),
                  _Step(
                    title: l.onboardNotificationsTitle,
                    body: l.onboardNotificationsBody,
                    onNext: _next,
                    nextLabel: _notificationsGranted == true ? l.continueLabel : l.skipForNow,
                    child: _NotificationsStep(
                      granted: _notificationsGranted,
                      onAsk: () async {
                        final granted = await ref.read(notificationServiceProvider).requestPermission();
                        setState(() => _notificationsGranted = granted);
                      },
                    ),
                  ),
                  _Step(
                    title: l.onboardTemplatesTitle,
                    body: l.onboardTemplatesBody,
                    onNext: _next,
                    child: const TemplateSummary(),
                  ),
                  _Step(
                    title: l.onboardPointsTitle,
                    onNext: _next,
                    child: _PointsExplainer(),
                  ),
                  _Step(
                    title: l.onboardDoneTitle,
                    body: l.onboardDoneBody,
                    onNext: _next,
                    nextLabel: l.onboardFinish,
                    child: Icon(Icons.check_circle_outline, size: 92, color: context.colors.primary),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Welcome extends StatelessWidget {
  const _Welcome({required this.onNext});

  final VoidCallback onNext;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return Padding(
      padding: const EdgeInsets.all(20),
      child: Column(
        children: [
          Expanded(
            child: Container(
              width: double.infinity,
              clipBehavior: Clip.antiAlias,
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(28),
                gradient: const LinearGradient(begin: Alignment.topLeft, end: Alignment.bottomRight, colors: [AppColors.emerald, AppColors.green]),
              ),
              child: Stack(
                children: [
                  const Positioned.fill(child: GeometricPattern(opacity: 0.09)),
                  Padding(
                    padding: const EdgeInsets.all(28),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.end,
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Image.asset('assets/images/logo_mark.png', width: 72, height: 72),
                        const SizedBox(height: 20),
                        Text(l.onboardWelcomeTitle, style: context.text.headlineMedium?.copyWith(color: Colors.white)),
                        const SizedBox(height: 10),
                        Text(l.tagline, style: context.text.titleMedium?.copyWith(color: AppColors.gold)),
                        const SizedBox(height: 14),
                        Text(l.onboardWelcomeBody, style: context.text.bodyMedium?.copyWith(color: Colors.white.withValues(alpha: 0.88))),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 16),
          FilledButton(onPressed: onNext, style: FilledButton.styleFrom(minimumSize: const Size.fromHeight(52)), child: Text(l.getStarted)),
        ],
      ),
    );
  }
}

class _Step extends StatelessWidget {
  const _Step({required this.title, this.body, required this.child, required this.onNext, this.nextLabel});

  final String title;
  final String? body;
  final Widget child;
  final VoidCallback onNext;
  final String? nextLabel;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(20, 16, 20, 20),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Expanded(
          child: ListView(
            children: [
              Text(title, style: context.text.headlineSmall),
              if (body != null) ...[
                const SizedBox(height: 8),
                Text(body!, style: context.text.bodyMedium?.copyWith(color: context.colors.onSurfaceVariant)),
              ],
              const SizedBox(height: 20),
              child,
            ],
          ),
        ),
        const SizedBox(height: 12),
        FilledButton(onPressed: onNext, child: Text(nextLabel ?? context.l10n.continueLabel)),
      ],
    ),
  );
}

class _NotificationsStep extends StatelessWidget {
  const _NotificationsStep({required this.granted, required this.onAsk});

  final bool? granted;
  final VoidCallback onAsk;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(l.notifHavePrayed(l.prayerFajr), style: context.text.titleSmall),
            const SizedBox(height: 8),
            Row(
              children: [
                StatusChip(label: l.notifYesPrayed),
                const SizedBox(width: 8),
                StatusChip(label: l.notifRemindLater),
              ],
            ),
            const SizedBox(height: 16),
            if (granted == null)
              FilledButton.tonal(onPressed: onAsk, child: Text(l.enableNotifications))
            else
              Text(granted! ? l.notificationsAllowed : l.notificationsDenied, style: context.text.bodyMedium),
            const SizedBox(height: 10),
            Text(l.notificationLimits, style: context.text.bodySmall),
          ],
        ),
      ),
    );
  }
}

class _PointsExplainer extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    Widget row(IconData i, String t) => Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(i, color: context.colors.primary),
          const SizedBox(width: 12),
          Expanded(child: Text(t)),
        ],
      ),
    );
    return Column(
      children: [
        row(Icons.emoji_events_outlined, l.pointsExplain1),
        row(Icons.favorite_border, l.pointsExplain2),
        row(Icons.lock_outline, l.pointsExplain3),
        row(Icons.sentiment_satisfied_outlined, l.pointsExplain4),
      ],
    );
  }
}

/// Language choices, used in onboarding and settings.
class LanguagePicker extends ConsumerWidget {
  const LanguagePicker({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final current = ref.watch(localeProvider).languageCode;
    const options = {'en': 'English', 'ar': 'العربية', 'bn': 'বাংলা'};
    return RadioGroup<String>(
      groupValue: current,
      onChanged: (v) async {
        if (v == null) return;
        ref.read(localeProvider.notifier).set(v);
        if (ref.read(authProvider).signedIn) {
          try {
            await ref.read(apiClientProvider).put('/profile/preferences', {'locale': v});
          } catch (_) {}
        }
      },
      child: Column(
        children: [
          for (final e in options.entries)
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: Card(
                child: RadioListTile<String>(value: e.key, title: Text(e.value)),
              ),
            ),
        ],
      ),
    );
  }
}
