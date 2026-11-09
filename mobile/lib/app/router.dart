import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../core/widgets/common.dart';
import '../features/achievements/achievements_screen.dart';
import '../features/ai_assistant/ai_chat_screen.dart';
import '../features/ai_assistant/ai_history_screen.dart';
import '../features/auth/auth_screens.dart';
import '../features/dhikr/tasbih_screen.dart';
import '../features/fasting/fasting_screen.dart';
import '../features/goals/goals_screen.dart';
import '../features/good_deeds/good_deeds_screen.dart';
import '../features/home/home_screen.dart';
import '../features/learning/course_screen.dart';
import '../features/learning/hadith_screens.dart';
import '../features/learning/learn_screen.dart';
import '../features/learning/lesson_screen.dart';
import '../features/onboarding/onboarding_screen.dart';
import '../features/points/points_screen.dart';
import '../features/privacy/privacy_screen.dart';
import '../features/profile/profile_screen.dart';
import '../features/profile/settings_screens.dart';
import '../features/quran/quran_downloads_screen.dart';
import '../features/quran/quran_player.dart';
import '../features/quran/quran_screens.dart';
import '../features/quran/tafsir_screen.dart';
import '../features/quran/word_by_word.dart';
import '../features/salah/prayer_settings_screen.dart';
import '../features/salah/rakat_selection_screen.dart';
import '../features/salah/salah_history_screen.dart';
import '../features/salah/salah_prompt_screens.dart';
import '../features/salah/salah_screen.dart';
import '../features/salah/templates_screen.dart';
import '../features/tracker/history_screen.dart';
import '../features/tracker/progress_screen.dart';
import '../features/tracker/sunnah_screen.dart';
import '../features/tracker/tracker_screen.dart';
import '../shared/providers/providers.dart';

/// Screens that onboarding links to, so its redirect must let them through.
const _openDuringOnboarding = {'/login', '/register', '/forgot', '/salah/templates'};

GoRouter buildRouter(WidgetRef ref) {
  GoRoute page(String path, Widget Function(GoRouterState s) build) => GoRoute(path: path, builder: (_, s) => build(s));

  return GoRouter(
    initialLocation: '/home',
    redirect: (_, state) {
      final done = ref.read(prefsProvider).onboardingDone;
      final atOnboarding = state.matchedLocation == '/onboarding';
      if (!done && !atOnboarding && !_openDuringOnboarding.contains(state.matchedLocation)) return '/onboarding';
      if (done && atOnboarding) return '/home';
      return null;
    },
    routes: [
      page('/onboarding', (_) => const OnboardingScreen()),
      page('/login', (_) => const LoginScreen()),
      page('/register', (_) => const RegisterScreen()),
      page('/forgot', (_) => const ForgotPasswordScreen()),
      StatefulShellRoute.indexedStack(
        builder: (_, _, shell) => _Shell(shell: shell),
        branches: [
          StatefulShellBranch(routes: [page('/home', (_) => const HomeScreen())]),
          StatefulShellBranch(routes: [page('/tracker', (_) => const TrackerScreen())]),
          StatefulShellBranch(routes: [page('/ai', (s) => AiChatScreen(conversationId: s.uri.queryParameters['c']))]),
          StatefulShellBranch(routes: [page('/learn', (_) => const LearnScreen())]),
          StatefulShellBranch(routes: [page('/profile', (_) => const ProfileScreen())]),
        ],
      ),
      page('/salah', (_) => const SalahScreen()),
      page('/salah/history', (_) => const SalahHistoryScreen()),
      page('/salah/templates', (_) => const TemplatesScreen()),
      page('/salah/settings', (_) => const PrayerSettingsScreen()),
      page('/salah/confirm/:prayer', (s) => RakatSelectionScreen(prayer: s.pathParameters['prayer']!, date: s.uri.queryParameters['date'])),
      page('/salah/prompt/:prayer', (s) => SalahPromptScreen(prayer: s.pathParameters['prayer']!, date: s.uri.queryParameters['date'])),
      page('/salah/remind/:prayer', (s) => RemindLaterScreen(prayer: s.pathParameters['prayer']!, date: s.uri.queryParameters['date'])),
      page('/quran', (_) => const SurahListScreen()),
      page('/quran/search', (_) => const QuranSearchScreen()),
      page('/quran/record', (_) => const QuranManualRecordScreen()),
      page('/quran/player', (_) => const QuranPlayerScreen()),
      page('/quran/downloads', (_) => const QuranDownloadsScreen()),
      page('/quran/tafsir/:surah/:verse', (s) => TafsirScreen(surah: int.parse(s.pathParameters['surah']!), verse: int.parse(s.pathParameters['verse']!))),
      page('/quran/learn/:surah', (s) => WordLearningScreen(surah: int.parse(s.pathParameters['surah']!), startVerse: int.tryParse(s.uri.queryParameters['verse'] ?? ''))),
      page('/quran/:id', (s) => SurahReaderScreen(surahId: int.parse(s.pathParameters['id']!), scrollToVerse: int.tryParse(s.uri.queryParameters['verse'] ?? ''))),
      page('/dhikr', (_) => const TasbihScreen()),
      page('/fasting', (_) => const FastingScreen()),
      page('/deeds', (_) => const GoodDeedsScreen()),
      page('/sunnah', (_) => const SunnahScreen()),
      page('/goals', (_) => const GoalsScreen()),
      page('/history', (_) => const HistoryScreen()),
      page('/progress', (_) => const ProgressScreen()),
      page('/points', (_) => const PointsScreen()),
      page('/achievements', (_) => const AchievementsScreen()),
      page('/ai-history', (_) => const AiHistoryScreen()),
      page('/courses/:id', (s) => CourseScreen(courseId: int.parse(s.pathParameters['id']!))),
      page('/lessons/:id', (s) => LessonScreen(lessonId: int.parse(s.pathParameters['id']!))),
      page('/hadith', (_) => const HadithCollectionsScreen()),
      page('/hadith/:id', (s) => HadithRecordsScreen(collectionId: int.parse(s.pathParameters['id']!))),
      page('/bookmarks', (_) => const BookmarksScreen()),
      page('/settings/account', (_) => const AccountScreen()),
      page('/settings/appearance', (_) => const AppearanceScreen()),
      page('/settings/notifications', (_) => const NotificationSettingsScreen()),
      page('/settings/points', (_) => const PointPreferencesScreen()),
      page('/settings/privacy', (_) => const PrivacyScreen()),
      page('/settings/about', (_) => const AboutScreen()),
      page('/settings/help', (_) => const HelpScreen()),
    ],
  );
}

class _Shell extends StatelessWidget {
  const _Shell({required this.shell});

  final StatefulNavigationShell shell;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return Scaffold(
      body: shell,
      bottomNavigationBar: NavigationBar(
        selectedIndex: shell.currentIndex,
        onDestinationSelected: (i) => shell.goBranch(i, initialLocation: i == shell.currentIndex),
        destinations: [
          NavigationDestination(icon: const Icon(Icons.home_outlined), selectedIcon: const Icon(Icons.home), label: l.navHome),
          NavigationDestination(icon: const Icon(Icons.track_changes_outlined), selectedIcon: const Icon(Icons.track_changes), label: l.navTracker),
          NavigationDestination(icon: const Icon(Icons.auto_awesome_outlined), selectedIcon: const Icon(Icons.auto_awesome), label: l.navAi),
          NavigationDestination(icon: const Icon(Icons.menu_book_outlined), selectedIcon: const Icon(Icons.menu_book), label: l.navLearn),
          NavigationDestination(icon: const Icon(Icons.person_outline), selectedIcon: const Icon(Icons.person), label: l.navProfile),
        ],
      ),
    );
  }
}
