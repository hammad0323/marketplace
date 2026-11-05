import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/constants/app_colors.dart';
import '../../core/constants/app_config.dart';
import '../../core/widgets/common.dart';
import '../../providers/providers.dart';

class BrandMark extends StatelessWidget {
  const BrandMark({super.key, this.size = 56});
  final double size;
  @override
  Widget build(BuildContext context) => Container(
        width: size,
        height: size,
        decoration: BoxDecoration(
          gradient: const LinearGradient(colors: [AppColors.teal, AppColors.darkTeal], begin: Alignment.topLeft, end: Alignment.bottomRight),
          borderRadius: BorderRadius.circular(size * 0.3),
        ),
        child: Icon(Icons.cast_connected_rounded, color: Colors.white, size: size * 0.52),
      );
}

class SplashScreen extends StatelessWidget {
  const SplashScreen({super.key});
  @override
  Widget build(BuildContext context) => const Scaffold(body: Center(child: BrandMark(size: 72)));
}

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});
  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  final email = TextEditingController();
  final password = TextEditingController();
  final form = GlobalKey<FormState>();
  bool busy = false, hide = true;
  String? error;

  Future<void> submit() async {
    if (!form.currentState!.validate()) return;
    setState(() { busy = true; error = null; });
    try {
      await ref.read(sessionProvider.notifier).login(email.text.trim(), password.text);
    } catch (e) {
      setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final t = Theme.of(context).textTheme;
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Form(
                key: form,
                child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                  const Align(alignment: Alignment.centerLeft, child: BrandMark()),
                  const SizedBox(height: 28),
                  Text('Welcome back', style: t.headlineMedium),
                  const SizedBox(height: 6),
                  Text('Sign in to manage your screens and displays.', style: t.bodyLarge?.copyWith(color: AppColors.mediumGrey)),
                  const SizedBox(height: 32),
                  TextFormField(
                    controller: email,
                    keyboardType: TextInputType.emailAddress,
                    autofillHints: const [AutofillHints.email],
                    decoration: const InputDecoration(labelText: 'Email', prefixIcon: Icon(Icons.mail_outline_rounded)),
                    validator: (v) => (v == null || !v.contains('@')) ? 'Enter your email address' : null,
                  ),
                  const SizedBox(height: 14),
                  TextFormField(
                    controller: password,
                    obscureText: hide,
                    autofillHints: const [AutofillHints.password],
                    onFieldSubmitted: (_) => submit(),
                    decoration: InputDecoration(
                      labelText: 'Password',
                      prefixIcon: const Icon(Icons.lock_outline_rounded),
                      suffixIcon: IconButton(icon: Icon(hide ? Icons.visibility_outlined : Icons.visibility_off_outlined), onPressed: () => setState(() => hide = !hide)),
                    ),
                    validator: (v) => (v == null || v.isEmpty) ? 'Enter your password' : null,
                  ),
                  Align(alignment: Alignment.centerRight, child: TextButton(onPressed: () => context.push('/forgot'), child: const Text('Forgot password?'))),
                  if (error != null)
                    Container(
                      margin: const EdgeInsets.only(bottom: 12),
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(color: AppColors.danger.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(12)),
                      child: Row(children: [
                        const Icon(Icons.error_outline, color: AppColors.danger, size: 20),
                        const SizedBox(width: 8),
                        Expanded(child: Text(error!, style: const TextStyle(color: AppColors.danger))),
                      ]),
                    ),
                  FilledButton(
                    onPressed: busy ? null : submit,
                    child: busy ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2.5, color: Colors.white)) : const Text('Sign in'),
                  ),
                  const SizedBox(height: 24),
                  Text('Accounts are created by your administrator.', textAlign: TextAlign.center, style: t.bodySmall?.copyWith(color: AppColors.mediumGrey)),
                  const SizedBox(height: 4),
                  Text(AppConfig.appName, textAlign: TextAlign.center, style: t.labelSmall?.copyWith(color: AppColors.mediumGrey)),
                ]),
              ),
            ),
          ),
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
  final email = TextEditingController();
  final code = TextEditingController();
  final password = TextEditingController();
  bool sent = false, busy = false;

  Future<void> run(Future<String> Function() f, {bool next = false}) async {
    setState(() => busy = true);
    try {
      final msg = await f();
      if (!mounted) return;
      showToast(context, msg);
      if (next) {
        setState(() => sent = true);
      } else {
        context.go('/login');
      }
    } catch (e) {
      if (mounted) showToast(context, e.toString(), error: true);
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final repo = ref.read(authRepoProvider);
    return Scaffold(
      appBar: AppBar(title: const Text('Reset password')),
      body: ListView(padding: const EdgeInsets.all(24), children: [
        Text(sent ? 'Enter the 8-character code we emailed you and choose a new password.' : 'Enter your email and we will send you a reset code.',
            style: Theme.of(context).textTheme.bodyLarge),
        const SizedBox(height: 20),
        TextField(controller: email, enabled: !sent, keyboardType: TextInputType.emailAddress, decoration: const InputDecoration(labelText: 'Email')),
        if (sent) ...[
          const SizedBox(height: 14),
          TextField(controller: code, textCapitalization: TextCapitalization.characters, decoration: const InputDecoration(labelText: 'Reset code')),
          const SizedBox(height: 14),
          TextField(controller: password, obscureText: true, decoration: const InputDecoration(labelText: 'New password', helperText: 'At least 8 characters with letters and numbers')),
        ],
        const SizedBox(height: 24),
        FilledButton(
          onPressed: busy
              ? null
              : () => sent ? run(() => repo.resetPassword(code.text.trim(), password.text)) : run(() => repo.forgotPassword(email.text.trim()), next: true),
          child: Text(sent ? 'Update password' : 'Send code'),
        ),
      ]),
    );
  }
}
