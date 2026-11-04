import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/network/api_exception.dart';
import '../../core/widgets/common.dart';
import '../../shared/providers/providers.dart';
import '../salah/salah_repository.dart';

String authError(BuildContext context, Object e) {
  if (e is ApiException) {
    if (e.kind == ApiErrorKind.offline) return context.l10n.offlineBody;
    return e.firstError;
  }
  return context.l10n.errorBody;
}

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});

  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  final _email = TextEditingController();
  final _password = TextEditingController();
  final _form = GlobalKey<FormState>();
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_form.currentState!.validate()) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await ref.read(authProvider.notifier).login(_email.text.trim(), _password.text);
      await ref.read(prayerConfigProvider).pullFromServer();
      ref.read(prayerSettingsVersionProvider.notifier).bump();
      if (mounted) context.canPop() ? context.pop() : context.go('/home');
    } catch (e) {
      if (mounted) setState(() => _error = authError(context, e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return Scaffold(
      appBar: AppBar(title: Text(l.signIn)),
      body: Form(
        key: _form,
        child: ListView(
          padding: const EdgeInsets.all(20),
          children: [
            TextFormField(
              controller: _email,
              keyboardType: TextInputType.emailAddress,
              autofillHints: const [AutofillHints.email],
              decoration: InputDecoration(labelText: l.email),
              validator: (v) => (v == null || !v.contains('@')) ? l.emailInvalid : null,
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _password,
              obscureText: true,
              autofillHints: const [AutofillHints.password],
              decoration: InputDecoration(labelText: l.password),
              validator: (v) => (v == null || v.isEmpty) ? l.fieldRequired : null,
            ),
            if (_error != null) ...[
              const SizedBox(height: 12),
              Text(_error!, style: TextStyle(color: context.colors.error)),
            ],
            const SizedBox(height: 20),
            FilledButton(onPressed: _busy ? null : _submit, child: _busy ? const _Spinner() : Text(l.signIn)),
            TextButton(onPressed: () => context.push('/forgot'), child: Text(l.forgotPassword)),
            const Divider(height: 32),
            OutlinedButton(onPressed: () => context.pushReplacement('/register'), child: Text(l.createAccount)),
          ],
        ),
      ),
    );
  }
}

class RegisterScreen extends ConsumerStatefulWidget {
  const RegisterScreen({super.key});

  @override
  ConsumerState<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends ConsumerState<RegisterScreen> {
  final _name = TextEditingController();
  final _email = TextEditingController();
  final _password = TextEditingController();
  final _form = GlobalKey<FormState>();
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _name.dispose();
    _email.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_form.currentState!.validate()) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await ref.read(authProvider.notifier).register(_name.text.trim(), _email.text.trim(), _password.text);
      final config = ref.read(prayerConfigProvider);
      if (config.settings.hasLocation) await config.saveSettings(config.settings);
      if (mounted) context.canPop() ? context.pop() : context.go('/home');
    } catch (e) {
      if (mounted) setState(() => _error = authError(context, e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return Scaffold(
      appBar: AppBar(title: Text(l.createAccount)),
      body: Form(
        key: _form,
        child: ListView(
          padding: const EdgeInsets.all(20),
          children: [
            TextFormField(
              controller: _name,
              autofillHints: const [AutofillHints.name],
              decoration: InputDecoration(labelText: l.name),
              validator: (v) => (v == null || v.trim().isEmpty) ? l.fieldRequired : null,
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _email,
              keyboardType: TextInputType.emailAddress,
              autofillHints: const [AutofillHints.email],
              decoration: InputDecoration(labelText: l.email),
              validator: (v) => (v == null || !v.contains('@')) ? l.emailInvalid : null,
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _password,
              obscureText: true,
              autofillHints: const [AutofillHints.newPassword],
              decoration: InputDecoration(labelText: l.password, helperText: l.passwordHint),
              validator: (v) => (v == null || v.length < 8) ? l.passwordHint : null,
            ),
            if (_error != null) ...[
              const SizedBox(height: 12),
              Text(_error!, style: TextStyle(color: context.colors.error)),
            ],
            const SizedBox(height: 20),
            FilledButton(onPressed: _busy ? null : _submit, child: _busy ? const _Spinner() : Text(l.createAccount)),
            const SizedBox(height: 12),
            Text(l.registerPrivacyNote, style: context.text.bodySmall, textAlign: TextAlign.center),
          ],
        ),
      ),
    );
  }
}

class ForgotPasswordScreen extends ConsumerStatefulWidget {
  const ForgotPasswordScreen({super.key});

  @override
  ConsumerState<ForgotPasswordScreen> createState() => _ForgotPasswordScreenState();
}

class _ForgotPasswordScreenState extends ConsumerState<ForgotPasswordScreen> {
  final _email = TextEditingController();
  final _token = TextEditingController();
  final _password = TextEditingController();
  bool _sent = false;
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _email.dispose();
    _token.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _run(Future<void> Function() action) async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await action();
    } catch (e) {
      if (mounted) setState(() => _error = authError(context, e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final api = ref.read(apiClientProvider);
    return Scaffold(
      appBar: AppBar(title: Text(l.forgotPassword)),
      body: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          TextField(controller: _email, keyboardType: TextInputType.emailAddress, decoration: InputDecoration(labelText: l.email)),
          const SizedBox(height: 12),
          if (!_sent)
            FilledButton(
              onPressed: _busy
                  ? null
                  : () => _run(() async {
                      await api.post('/auth/forgot-password', {'email': _email.text.trim()});
                      if (mounted) setState(() => _sent = true);
                    }),
              child: Text(l.sendResetEmail),
            )
          else ...[
            Text(l.resetEmailSent),
            const SizedBox(height: 12),
            TextField(controller: _token, decoration: InputDecoration(labelText: l.resetCode)),
            const SizedBox(height: 12),
            TextField(controller: _password, obscureText: true, decoration: InputDecoration(labelText: l.newPassword, helperText: l.passwordHint)),
            const SizedBox(height: 12),
            FilledButton(
              onPressed: _busy
                  ? null
                  : () => _run(() async {
                      await api.post('/auth/reset-password', {
                        'email': _email.text.trim(),
                        'token': _token.text.trim(),
                        'password': _password.text,
                        'password_confirmation': _password.text,
                      });
                      if (!context.mounted) return;
                      context.toast(l.passwordChanged);
                      context.pop();
                    }),
              child: Text(l.resetPassword),
            ),
          ],
          if (_error != null) ...[
            const SizedBox(height: 12),
            Text(_error!, style: TextStyle(color: context.colors.error)),
          ],
        ],
      ),
    );
  }
}

class _Spinner extends StatelessWidget {
  const _Spinner();

  @override
  Widget build(BuildContext context) => const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2.5));
}
