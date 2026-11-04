import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import 'common.dart';

/// Shown where a feature needs an account. Public content stays open to guests.
class AccountRequired extends StatelessWidget {
  const AccountRequired({super.key, required this.reason});

  final String reason;

  @override
  Widget build(BuildContext context) => StateMessage(
    icon: Icons.lock_outline,
    title: context.l10n.accountRequiredTitle,
    body: reason,
    action: FilledButton(onPressed: () => context.push('/login'), child: Text(context.l10n.signIn)),
  );
}
