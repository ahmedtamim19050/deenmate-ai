import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../l10n/gen/app_localizations.dart';
import '../network/api_exception.dart';

extension L10nX on BuildContext {
  AppLocalizations get l10n => AppLocalizations.of(this);
  ThemeData get theme => Theme.of(this);
  ColorScheme get colors => Theme.of(this).colorScheme;
  TextTheme get text => Theme.of(this).textTheme;

  void toast(String message) {
    ScaffoldMessenger.of(this)
      ..clearSnackBars()
      ..showSnackBar(SnackBar(content: Text(message)));
  }
}

/// Shared empty, error, offline and permission states, so each looks considered.
class StateMessage extends StatelessWidget {
  const StateMessage({super.key, required this.icon, required this.title, this.body, this.action, this.compact = false});

  final IconData icon;
  final String title;
  final String? body;
  final Widget? action;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: EdgeInsets.all(compact ? 16 : 32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: compact ? 52 : 72,
              height: compact ? 52 : 72,
              decoration: BoxDecoration(color: context.colors.primaryContainer, shape: BoxShape.circle),
              child: Icon(icon, size: compact ? 24 : 32, color: context.colors.onPrimaryContainer),
            ),
            SizedBox(height: compact ? 10 : 18),
            Text(title, textAlign: TextAlign.center, style: compact ? context.text.titleSmall : context.text.titleMedium),
            if (body != null) ...[
              const SizedBox(height: 6),
              Text(body!, textAlign: TextAlign.center, style: context.text.bodyMedium?.copyWith(color: context.colors.onSurfaceVariant)),
            ],
            if (action != null) ...[const SizedBox(height: 16), action!],
          ],
        ),
      ),
    );
  }
}

class ErrorState extends StatelessWidget {
  const ErrorState({super.key, required this.error, this.onRetry, this.compact = false});

  final Object error;
  final VoidCallback? onRetry;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final offline = error is ApiException && (error as ApiException).kind == ApiErrorKind.offline;
    final message = error is ApiException && !offline ? (error as ApiException).firstError : null;
    return StateMessage(
      compact: compact,
      icon: offline ? Icons.cloud_off_outlined : Icons.error_outline,
      title: offline ? l.offlineTitle : l.errorTitle,
      body: offline ? l.offlineBody : (message ?? l.errorBody),
      action: onRetry == null ? null : OutlinedButton(onPressed: onRetry, child: Text(l.retry)),
    );
  }
}

/// Renders loading, error and data for any provider value in one consistent way.
class AsyncView<T> extends StatelessWidget {
  const AsyncView({super.key, required this.value, required this.builder, this.onRetry});

  final AsyncValue<T> value;
  final Widget Function(T data) builder;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) {
    return value.when(
      data: builder,
      error: (e, _) => ErrorState(error: e, onRetry: onRetry),
      loading: () => const Center(child: CircularProgressIndicator()),
    );
  }
}

class SectionHeader extends StatelessWidget {
  const SectionHeader(this.title, {super.key, this.trailing});

  final String title;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(top: 22, bottom: 10),
    child: Row(
      children: [
        Expanded(child: Text(title, style: context.text.titleMedium)),
        ?trailing,
      ],
    ),
  );
}

/// The standard tappable row used across tracker and settings lists.
class NavTile extends StatelessWidget {
  const NavTile({super.key, required this.icon, required this.title, this.subtitle, this.onTap, this.trailing});

  final IconData icon;
  final String title;
  final String? subtitle;
  final VoidCallback? onTap;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) => ListTile(
    onTap: onTap,
    contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 2),
    leading: Container(
      width: 40,
      height: 40,
      decoration: BoxDecoration(color: context.colors.primaryContainer, borderRadius: BorderRadius.circular(12)),
      child: Icon(icon, size: 21, color: context.colors.onPrimaryContainer),
    ),
    title: Text(title, style: context.text.titleSmall),
    subtitle: subtitle == null ? null : Text(subtitle!),
    trailing: trailing ?? (onTap == null ? null : const Icon(Icons.chevron_right)),
  );
}

class StatusChip extends StatelessWidget {
  const StatusChip({super.key, required this.label, this.icon, this.tone = StatusTone.neutral});

  final String label;
  final IconData? icon;
  final StatusTone tone;

  @override
  Widget build(BuildContext context) {
    final (bg, fg) = switch (tone) {
      StatusTone.success => (context.colors.primaryContainer, context.colors.onPrimaryContainer),
      StatusTone.pending => (context.colors.secondaryContainer, context.colors.onSecondaryContainer),
      StatusTone.neutral => (context.colors.surfaceContainerHighest, context.colors.onSurfaceVariant),
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
      decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(8)),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (icon != null) ...[Icon(icon, size: 13, color: fg), const SizedBox(width: 4)],
          Flexible(
            child: Text(label, style: context.text.labelSmall?.copyWith(color: fg, fontWeight: FontWeight.w600)),
          ),
        ],
      ),
    );
  }
}

enum StatusTone { success, pending, neutral }
