import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:path/path.dart' as p;
import 'package:path_provider/path_provider.dart';
import 'package:share_plus/share_plus.dart';

import '../../core/network/api_exception.dart';
import '../../core/widgets/common.dart';
import '../../shared/providers/providers.dart';

class PrivacyScreen extends ConsumerStatefulWidget {
  const PrivacyScreen({super.key});

  @override
  ConsumerState<PrivacyScreen> createState() => _PrivacyScreenState();
}

class _PrivacyScreenState extends ConsumerState<PrivacyScreen> {
  String? _exportStatus;
  bool _exporting = false;

  Future<void> _setPreference(String key, bool value) async {
    if (!ref.read(authProvider).signedIn) return;
    try {
      await ref.read(apiClientProvider).put('/profile/preferences', {key: value});
    } on ApiException catch (e) {
      if (mounted) context.toast(e.kind == ApiErrorKind.offline ? context.l10n.savedOnDevice : e.firstError);
    }
  }

  Future<void> _export() async {
    final l = context.l10n;
    final api = ref.read(apiClientProvider);
    setState(() {
      _exporting = true;
      _exportStatus = l.exportPreparing;
    });
    try {
      final created = await api.post('/privacy/export');
      final id = created['data']['id'] as String;
      for (var i = 0; i < 30; i++) {
        final status = (await api.get('/privacy/export/$id'))['data']['status'] as String;
        if (status == 'ready') {
          final body = await api.getText('/privacy/export/$id/download');
          final dir = await getTemporaryDirectory();
          final file = File(p.join(dir.path, 'deenq-ai-data-export.json'));
          await file.writeAsString(body);
          if (!mounted) return;
          setState(() => _exportStatus = l.exportReady);
          await SharePlus.instance.share(ShareParams(files: [XFile(file.path)], subject: l.exportTitle));
          return;
        }
        if (status == 'failed') break;
        await Future<void>.delayed(const Duration(seconds: 2));
      }
      if (mounted) setState(() => _exportStatus = l.exportLater);
    } on ApiException catch (e) {
      if (mounted) setState(() => _exportStatus = e.kind == ApiErrorKind.offline ? l.offlineBody : e.firstError);
    } finally {
      if (mounted) setState(() => _exporting = false);
    }
  }

  Future<void> _clearAi() async {
    final l = context.l10n;
    final yes = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        title: Text(l.clearAiHistory),
        content: Text(l.clearAiHistoryBody),
        actions: [
          TextButton(onPressed: () => Navigator.pop(c, false), child: Text(l.cancel)),
          FilledButton(onPressed: () => Navigator.pop(c, true), child: Text(l.clear)),
        ],
      ),
    );
    if (yes != true) return;
    try {
      await ref.read(apiClientProvider).delete('/ai/conversations');
      ref.read(dataVersionProvider.notifier).bump();
      if (mounted) context.toast(l.aiHistoryCleared);
    } on ApiException catch (e) {
      if (mounted) context.toast(e.firstError);
    }
  }

  Future<void> _deleteAccount() async {
    final l = context.l10n;
    final password = TextEditingController();
    final yes = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        title: Text(l.deleteAccount),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(l.deleteAccountBody),
            const SizedBox(height: 12),
            TextField(controller: password, obscureText: true, decoration: InputDecoration(labelText: l.password)),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(c, false), child: Text(l.cancel)),
          FilledButton(style: FilledButton.styleFrom(backgroundColor: context.colors.error), onPressed: () => Navigator.pop(c, true), child: Text(l.deletePermanently)),
        ],
      ),
    );
    if (yes != true) return;
    try {
      await ref.read(apiClientProvider).post('/privacy/delete-account', {'password': password.text, 'confirm': true});
      await ref.read(authProvider.notifier).signOut(callServer: false);
      if (!mounted) return;
      context.toast(l.accountDeleted);
      context.go('/home');
    } on ApiException catch (e) {
      if (mounted) context.toast(e.kind == ApiErrorKind.offline ? l.offlineBody : e.firstError);
    }
  }

  Future<void> _clearLocal() async {
    final l = context.l10n;
    final yes = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        title: Text(l.clearDeviceData),
        content: Text(l.clearDeviceDataBody),
        actions: [
          TextButton(onPressed: () => Navigator.pop(c, false), child: Text(l.cancel)),
          FilledButton(onPressed: () => Navigator.pop(c, true), child: Text(l.clear)),
        ],
      ),
    );
    if (yes != true) return;
    await ref.read(authProvider.notifier).signOut(callServer: false);
    if (mounted) context.toast(l.deviceDataCleared);
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final prefs = ref.watch(prefsProvider);
    final signedIn = ref.watch(authProvider).signedIn;
    return Scaffold(
      appBar: AppBar(title: Text(l.privacyAndData)),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text(l.privacyPromise, style: context.text.bodyMedium),
          SectionHeader(l.aiPrivacy),
          Card(
            child: SwitchListTile(
              title: Text(l.aiPersonalization),
              subtitle: Text(l.aiPersonalizationBody),
              value: prefs.aiPersonalization,
              onChanged: !signedIn
                  ? null
                  : (v) {
                      prefs.aiPersonalization = v;
                      setState(() {});
                      _setPreference('ai_personalization_enabled', v);
                    },
            ),
          ),
          if (signedIn) ...[
            const SizedBox(height: 8),
            Card(child: NavTile(icon: Icons.delete_sweep_outlined, title: l.clearAiHistory, onTap: _clearAi)),
          ],
          SectionHeader(l.adsPrivacy),
          Card(
            child: SwitchListTile(
              title: Text(l.personalizedAds),
              subtitle: Text(l.personalizedAdsBody),
              value: prefs.adsPersonalized,
              onChanged: (v) {
                prefs.adsPersonalized = v;
                ref.read(adServiceProvider).personalized = v;
                setState(() {});
                _setPreference('ads_personalized', v);
              },
            ),
          ),
          SectionHeader(l.yourData),
          if (signedIn)
            Card(
              child: NavTile(
                icon: Icons.download_outlined,
                title: l.exportTitle,
                subtitle: _exportStatus ?? l.exportBody,
                onTap: _exporting ? null : _export,
                trailing: _exporting ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2)) : null,
              ),
            ),
          const SizedBox(height: 8),
          Card(child: NavTile(icon: Icons.phonelink_erase, title: l.clearDeviceData, subtitle: l.clearDeviceDataBody, onTap: _clearLocal)),
          if (signedIn) ...[
            const SizedBox(height: 8),
            Card(
              child: NavTile(icon: Icons.delete_forever_outlined, title: l.deleteAccount, subtitle: l.deleteAccountShort, onTap: _deleteAccount),
            ),
          ],
        ],
      ),
    );
  }
}
