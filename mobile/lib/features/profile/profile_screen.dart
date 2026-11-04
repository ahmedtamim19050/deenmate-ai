import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../app/theme/app_theme.dart';
import '../../core/widgets/common.dart';
import '../../core/widgets/geometric_pattern.dart';
import '../../shared/providers/providers.dart';

class ProfileScreen extends ConsumerWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final auth = ref.watch(authProvider);
    return Scaffold(
      body: ListView(
        padding: EdgeInsets.zero,
        children: [
          Container(
            clipBehavior: Clip.antiAlias,
            decoration: const BoxDecoration(
              gradient: LinearGradient(colors: [AppColors.emerald, AppColors.green]),
              borderRadius: BorderRadius.vertical(bottom: Radius.circular(26)),
            ),
            child: Stack(
              children: [
                const Positioned.fill(child: GeometricPattern()),
                SafeArea(
                  bottom: false,
                  child: Padding(
                    padding: const EdgeInsets.all(20),
                    child: Row(
                      children: [
                        CircleAvatar(
                          radius: 28,
                          backgroundColor: AppColors.gold,
                          foregroundColor: Colors.white,
                          child: Text(auth.user?.name.characters.first.toUpperCase() ?? '?', style: context.text.titleLarge?.copyWith(color: Colors.white)),
                        ),
                        const SizedBox(width: 14),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(auth.user?.name ?? l.guest, style: context.text.titleLarge?.copyWith(color: Colors.white)),
                              Text(auth.user?.email ?? l.guestSubtitle, style: context.text.bodySmall?.copyWith(color: Colors.white.withValues(alpha: 0.85))),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                if (!auth.signedIn) ...[
                  FilledButton(onPressed: () => context.push('/register'), child: Text(l.createAccount)),
                  const SizedBox(height: 8),
                  OutlinedButton(onPressed: () => context.push('/login'), child: Text(l.signIn)),
                  const SizedBox(height: 14),
                ],
                Card(
                  child: Column(
                    children: [
                      if (auth.signedIn) NavTile(icon: Icons.person_outline, title: l.personalInformation, onTap: () => context.push('/settings/account')),
                      NavTile(icon: Icons.palette_outlined, title: l.languageAndTheme, onTap: () => context.push('/settings/appearance')),
                      NavTile(icon: Icons.notifications_outlined, title: l.notificationPreferences, onTap: () => context.push('/settings/notifications')),
                      NavTile(icon: Icons.mosque_outlined, title: l.prayerSettings, onTap: () => context.push('/salah/settings')),
                      NavTile(icon: Icons.stars_outlined, title: l.pointPreferences, onTap: () => context.push('/settings/points')),
                      NavTile(icon: Icons.emoji_events_outlined, title: l.achievements, onTap: () => context.push('/achievements')),
                    ],
                  ),
                ),
                const SizedBox(height: 12),
                Card(
                  child: Column(
                    children: [
                      NavTile(icon: Icons.shield_outlined, title: l.privacyAndData, subtitle: l.privacyAndDataBody, onTap: () => context.push('/settings/privacy')),
                      NavTile(icon: Icons.help_outline, title: l.helpAndSupport, onTap: () => context.push('/settings/help')),
                      NavTile(icon: Icons.info_outline, title: l.about, onTap: () => context.push('/settings/about')),
                    ],
                  ),
                ),
                if (auth.signedIn) ...[
                  const SizedBox(height: 16),
                  OutlinedButton.icon(
                    icon: const Icon(Icons.logout),
                    label: Text(l.signOut),
                    onPressed: () async {
                      final yes = await showDialog<bool>(
                        context: context,
                        builder: (c) => AlertDialog(
                          title: Text(l.signOut),
                          content: Text(l.signOutBody),
                          actions: [
                            TextButton(onPressed: () => Navigator.pop(c, false), child: Text(l.cancel)),
                            FilledButton(onPressed: () => Navigator.pop(c, true), child: Text(l.signOut)),
                          ],
                        ),
                      );
                      if (yes == true) await ref.read(authProvider.notifier).signOut();
                    },
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}
