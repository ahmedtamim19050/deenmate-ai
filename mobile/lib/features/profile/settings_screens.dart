import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:package_info_plus/package_info_plus.dart';

import '../../core/network/api_exception.dart';
import '../../core/notifications/prayer_notification_service.dart';
import '../../core/prayer/prayer_calculator.dart';
import '../../core/prayer/prayer_settings.dart';
import '../../core/widgets/common.dart';
import '../../shared/models/app_settings.dart';
import '../../shared/providers/providers.dart';
import '../onboarding/onboarding_screen.dart';
import '../salah/salah_repository.dart';

class AccountScreen extends ConsumerStatefulWidget {
  const AccountScreen({super.key});

  @override
  ConsumerState<AccountScreen> createState() => _AccountScreenState();
}

class _AccountScreenState extends ConsumerState<AccountScreen> {
  late final _name = TextEditingController(text: ref.read(authProvider).user?.name ?? '');
  late final _country = TextEditingController(text: ref.read(authProvider).user?.country ?? '');
  bool _busy = false;

  @override
  void dispose() {
    _name.dispose();
    _country.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    final l = context.l10n;
    setState(() => _busy = true);
    try {
      final country = _country.text.trim().toUpperCase();
      final r = await ref.read(apiClientProvider).put('/profile', {'name': _name.text.trim(), 'country': country.isEmpty ? null : country});
      await ref.read(authProvider.notifier).updateUser(r['data'] as Map<String, dynamic>);
      if (mounted) context.toast(l.saved);
    } on ApiException catch (e) {
      if (mounted) context.toast(e.kind == ApiErrorKind.offline ? l.offlineBody : e.firstError);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return Scaffold(
      appBar: AppBar(title: Text(l.personalInformation)),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          TextField(controller: _name, decoration: InputDecoration(labelText: l.name)),
          const SizedBox(height: 12),
          TextField(controller: _country, maxLength: 2, decoration: InputDecoration(labelText: l.countryCode, helperText: l.optional)),
          const SizedBox(height: 12),
          ListTile(contentPadding: EdgeInsets.zero, title: Text(l.email), subtitle: Text(ref.watch(authProvider).user?.email ?? '')),
          const SizedBox(height: 12),
          FilledButton(onPressed: _busy ? null : _save, child: Text(l.save)),
        ],
      ),
    );
  }
}

class AppearanceScreen extends ConsumerStatefulWidget {
  const AppearanceScreen({super.key});

  @override
  ConsumerState<AppearanceScreen> createState() => _AppearanceScreenState();
}

class _AppearanceScreenState extends ConsumerState<AppearanceScreen> {
  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final prefs = ref.watch(prefsProvider);
    final theme = prefs.theme;
    return Scaffold(
      appBar: AppBar(title: Text(l.languageAndTheme)),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text(l.language, style: context.text.titleMedium),
          const SizedBox(height: 8),
          const LanguagePicker(),
          SectionHeader(l.theme),
          SegmentedButton<String>(
            segments: [
              ButtonSegment(value: 'system', label: Text(l.themeSystem)),
              ButtonSegment(value: 'light', label: Text(l.themeLight)),
              ButtonSegment(value: 'dark', label: Text(l.themeDark)),
            ],
            selected: {theme},
            onSelectionChanged: (v) {
              ref.read(themeProvider.notifier).set(v.first);
              setState(() {});
            },
          ),
          SectionHeader(l.hijriAdjustment),
          Text(l.hijriAdjustmentHelp, style: context.text.bodySmall),
          const SizedBox(height: 8),
          SegmentedButton<int>(
            segments: const [
              ButtonSegment(value: -2, label: Text('-2')),
              ButtonSegment(value: -1, label: Text('-1')),
              ButtonSegment(value: 0, label: Text('0')),
              ButtonSegment(value: 1, label: Text('+1')),
              ButtonSegment(value: 2, label: Text('+2')),
            ],
            selected: {prefs.hijriOffset},
            onSelectionChanged: (v) {
              prefs.hijriOffset = v.first;
              setState(() {});
            },
          ),
          SectionHeader(l.tasbihHaptics),
          SwitchListTile(
            contentPadding: EdgeInsets.zero,
            title: Text(l.hapticFeedback),
            value: prefs.haptics,
            onChanged: (v) {
              prefs.haptics = v;
              setState(() {});
            },
          ),
        ],
      ),
    );
  }
}

class NotificationSettingsScreen extends ConsumerStatefulWidget {
  const NotificationSettingsScreen({super.key});

  @override
  ConsumerState<NotificationSettingsScreen> createState() => _NotificationSettingsScreenState();
}

class _NotificationSettingsScreenState extends ConsumerState<NotificationSettingsScreen> {
  late Map<String, PrayerNotificationPref> _prefs = ref.read(prayerConfigProvider).notificationPrefs;
  bool? _enabled;

  @override
  void initState() {
    super.initState();
    ref.read(notificationServiceProvider).areEnabled().then((v) {
      if (mounted) setState(() => _enabled = v);
    });
  }

  Future<void> _update(String prayer, PrayerNotificationPref pref) async {
    setState(() => _prefs = {..._prefs, prayer: pref});
    await ref.read(prayerConfigProvider).saveNotificationPrefs(_prefs);
    await ref.read(notificationSchedulerProvider).run();
  }

  Future<void> _applyAll(PrayerNotificationPref Function(PrayerNotificationPref) f) async {
    setState(() => _prefs = {for (final e in _prefs.entries) e.key: f(e.value)});
    await ref.read(prayerConfigProvider).saveNotificationPrefs(_prefs);
    await ref.read(notificationSchedulerProvider).run();
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final first = _prefs.values.first;
    return Scaffold(
      appBar: AppBar(title: Text(l.notificationPreferences)),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          if (_enabled == false)
            Card(
              color: context.colors.secondaryContainer,
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(l.notificationsOffTitle, style: context.text.titleSmall),
                    Text(l.notificationsOffBody),
                    const SizedBox(height: 8),
                    FilledButton.tonal(
                      onPressed: () async {
                        final granted = await ref.read(notificationServiceProvider).requestPermission();
                        setState(() => _enabled = granted);
                        if (granted) await ref.read(notificationSchedulerProvider).run();
                      },
                      child: Text(l.enableNotifications),
                    ),
                  ],
                ),
              ),
            ),
          SectionHeader(l.perPrayerReminders),
          Card(
            child: Column(
              children: [
                for (final p in fardPrayers)
                  Column(
                    children: [
                      SwitchListTile(
                        title: Text(prayerName(l, p)),
                        value: _prefs[p]!.enabled,
                        onChanged: (v) => _update(p, _prefs[p]!.copyWith(enabled: v)),
                      ),
                      if (_prefs[p]!.enabled)
                        ListTile(
                          dense: true,
                          title: Text(l.reminderOffset),
                          trailing: DropdownButton<int>(
                            value: _prefs[p]!.offsetMinutes,
                            underline: const SizedBox.shrink(),
                            items: [
                              for (final m in const [0, 5, 10, 15, 30, 60])
                                DropdownMenuItem(value: m, child: Text(m == 0 ? l.atPrayerTime : l.minutesAfter(m))),
                            ],
                            onChanged: (v) => _update(p, _prefs[p]!.copyWith(offsetMinutes: v ?? 0)),
                          ),
                        ),
                    ],
                  ),
              ],
            ),
          ),
          SectionHeader(l.soundAndPrivacy),
          Card(
            child: Column(
              children: [
                SwitchListTile(title: Text(l.sound), value: first.sound != 'silent', onChanged: (v) => _applyAll((x) => x.copyWith(sound: v ? 'default' : 'silent'))),
                SwitchListTile(title: Text(l.vibration), value: first.vibration, onChanged: (v) => _applyAll((x) => x.copyWith(vibration: v))),
                SwitchListTile(title: Text(l.privatePreview), subtitle: Text(l.privatePreviewBody), value: first.privatePreview, onChanged: (v) => _applyAll((x) => x.copyWith(privatePreview: v))),
              ],
            ),
          ),
          const SizedBox(height: 12),
          Text(l.notificationLimits, style: context.text.bodySmall),
        ],
      ),
    );
  }
}

class PointPreferencesScreen extends ConsumerStatefulWidget {
  const PointPreferencesScreen({super.key});

  @override
  ConsumerState<PointPreferencesScreen> createState() => _PointPreferencesScreenState();
}

class _PointPreferencesScreenState extends ConsumerState<PointPreferencesScreen> {
  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final prefs = ref.watch(prefsProvider);
    return Scaffold(
      appBar: AppBar(title: Text(l.pointPreferences)),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Card(
            child: SwitchListTile(
              title: Text(l.showPoints),
              subtitle: Text(l.showPointsBody),
              value: prefs.showPoints,
              onChanged: (v) async {
                prefs.showPoints = v;
                setState(() {});
                if (ref.read(authProvider).signedIn) {
                  try {
                    await ref.read(apiClientProvider).put('/profile/preferences', {'show_points': v});
                  } catch (_) {}
                }
              },
            ),
          ),
          const SizedBox(height: 14),
          Text(l.pointsDisclaimerLong, style: context.text.bodyMedium),
        ],
      ),
    );
  }
}

class AboutScreen extends StatelessWidget {
  const AboutScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return Scaffold(
      appBar: AppBar(title: Text(l.about)),
      body: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          Image.asset(
            Theme.of(context).brightness == Brightness.dark ? 'assets/images/logo_mark_dark.png' : 'assets/images/logo_mark.png',
            width: 72,
            height: 72,
          ),
          const SizedBox(height: 14),
          Text(l.appName, textAlign: TextAlign.center, style: context.text.headlineSmall),
          Text(l.tagline, textAlign: TextAlign.center, style: context.text.bodyMedium),
          FutureBuilder(
            future: PackageInfo.fromPlatform(),
            builder: (_, s) => Text(s.hasData ? l.versionLabel(s.data!.version) : '', textAlign: TextAlign.center, style: context.text.bodySmall),
          ),
          const SizedBox(height: 20),
          Text(l.aboutBody),
          const SizedBox(height: 14),
          Text(l.aboutSources, style: context.text.bodySmall),
          const SizedBox(height: 14),
          OutlinedButton(onPressed: () => showLicensePage(context: context, applicationName: l.appName), child: Text(l.openSourceLicenses)),
        ],
      ),
    );
  }
}

class HelpScreen extends ConsumerWidget {
  const HelpScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final remote = ref.watch(publicSettingsProvider).value;
    Widget faq(String q, String a) => Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: ExpansionTile(title: Text(q, style: context.text.titleSmall), childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 14), expandedAlignment: Alignment.centerLeft, children: [Text(a)]),
    );
    return Scaffold(
      appBar: AppBar(title: Text(l.helpAndSupport)),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          faq(l.faqNotificationsQ, l.notificationLimits),
          faq(l.faqTimesQ, l.faqTimesA),
          faq(l.faqPointsQ, l.pointsDisclaimerLong),
          faq(l.faqAiQ, l.faqAiA),
          faq(l.faqSyncQ, l.faqSyncA),
          faq(l.faqDataQ, l.faqDataA),
          const SizedBox(height: 10),
          ListTile(
            leading: const Icon(Icons.mail_outline),
            title: Text(l.contactSupport),
            subtitle: SelectableText(remote?.supportEmail.isNotEmpty == true ? remote!.supportEmail : l.supportNotConfigured),
          ),
        ],
      ),
    );
  }
}
