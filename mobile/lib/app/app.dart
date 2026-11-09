import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../features/quran/quran_player.dart';
import '../features/salah/salah_repository.dart';
import '../l10n/gen/app_localizations.dart';
import '../shared/providers/providers.dart';
import 'lifecycle.dart';
import 'router.dart';
import 'theme/app_theme.dart';

class DeenMateApp extends ConsumerStatefulWidget {
  const DeenMateApp({super.key});

  @override
  ConsumerState<DeenMateApp> createState() => _DeenMateAppState();
}

class _DeenMateAppState extends ConsumerState<DeenMateApp> {
  StreamSubscription<String>? _routes;
  late final GoRouter _router = buildRouter(ref);

  @override
  void initState() {
    super.initState();
    final notifications = ref.read(notificationServiceProvider);
    _routes = notifications.routes.listen(_router.push);
    final launch = notifications.launchRoute;
    if (launch != null && ref.read(prefsProvider).onboardingDone) {
      notifications.launchRoute = null;
      WidgetsBinding.instance.addPostFrameCallback((_) => _router.push(launch));
    }
  }

  @override
  void dispose() {
    _routes?.cancel();
    _router.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    ref.watch(prayerConfigProvider);
    return AppLifecycleWatcher(
      child: MaterialApp.router(
        onGenerateTitle: (c) => AppLocalizations.of(c).appName,
        debugShowCheckedModeBanner: false,
        theme: AppTheme.light(),
        darkTheme: AppTheme.dark(),
        themeMode: ref.watch(themeProvider),
        locale: ref.watch(localeProvider),
        supportedLocales: AppLocalizations.supportedLocales,
        localizationsDelegates: const [
          AppLocalizations.delegate,
          GlobalMaterialLocalizations.delegate,
          GlobalWidgetsLocalizations.delegate,
          GlobalCupertinoLocalizations.delegate,
        ],
        routerConfig: _router,
        builder: (context, child) => QuranMiniPlayerHost(router: _router, child: child ?? const SizedBox.shrink()),
      ),
    );
  }
}
