import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_timezone/flutter_timezone.dart';

import '../core/utils/dates.dart';
import '../features/salah/salah_repository.dart';
import '../shared/providers/providers.dart';

/// On return to the app: sync queued records, then reschedule for a new day or a new timezone.
/// Scheduled alarms cover the next 7 days, in case the app is not opened for a while.
class AppLifecycleWatcher extends ConsumerStatefulWidget {
  const AppLifecycleWatcher({super.key, required this.child});

  final Widget child;

  @override
  ConsumerState<AppLifecycleWatcher> createState() => _AppLifecycleWatcherState();
}

class _AppLifecycleWatcherState extends ConsumerState<AppLifecycleWatcher> with WidgetsBindingObserver {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    WidgetsBinding.instance.addPostFrameCallback((_) => _refresh());
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) _refresh();
  }

  Future<void> _refresh() async {
    final prefs = ref.read(prefsProvider);
    if (!prefs.onboardingDone) return;
    await ref.read(authProvider.notifier).refreshTokenIfOld();
    final result = await ref.read(syncServiceProvider).flush();
    if (result.synced > 0) ref.read(dataVersionProvider.notifier).bump();

    final config = ref.read(prayerConfigProvider);
    var settings = config.settings;
    var needsSchedule = prefs.lastScheduledDate != ymd(DateTime.now());
    try {
      final device = (await FlutterTimezone.getLocalTimezone()).identifier;
      if (device != prefs.lastDeviceTimezone) {
        prefs.lastDeviceTimezone = device;
        if (settings.followDeviceTimezone && settings.timezone != device) {
          settings = settings.copyWith(timezone: device);
          await config.saveSettings(settings);
          ref.read(prayerSettingsVersionProvider.notifier).bump();
          needsSchedule = true;
        }
      }
    } catch (_) {}
    if (needsSchedule && settings.hasLocation) {
      await ref.read(notificationSchedulerProvider).run();
    }
  }

  @override
  Widget build(BuildContext context) {
    ref.listen(connectivityProvider, (previous, next) {
      if (next.value == true) {
        unawaited(
          ref.read(syncServiceProvider).flush().then((r) {
            if (r.synced > 0) ref.read(dataVersionProvider.notifier).bump();
          }),
        );
      }
    });
    return widget.child;
  }
}
