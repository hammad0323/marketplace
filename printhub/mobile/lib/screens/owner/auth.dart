import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

import '../../state/session.dart';
import '../../widgets/common.dart';

/// Shop owners get accounts from the platform admin — there is no public registration.
class OwnerLoginScreen extends StatefulWidget {
  const OwnerLoginScreen({super.key});
  @override
  State<OwnerLoginScreen> createState() => _OwnerLoginScreenState();
}

class _OwnerLoginScreenState extends State<OwnerLoginScreen> {
  final _form = GlobalKey<FormState>();
  final _user = TextEditingController();
  final _pass = TextEditingController();
  bool _busy = false;
  bool _show = false;

  Future<void> _submit() async {
    if (!_form.currentState!.validate()) return;
    setState(() => _busy = true);
    await runAction(context, () => SessionScope.read(context).login(_user.text.trim(), _pass.text));
    if (mounted) setState(() => _busy = false);
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(leading: BackButton(onPressed: () => context.go('/'))),
        body: SafeArea(
          child: Center(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(24),
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 420),
                child: Form(
                  key: _form,
                  child: AutofillGroup(
                    child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                      const Icon(Icons.storefront, size: 56, color: Color(0xFF0F766E)),
                      const SizedBox(height: 12),
                      Text('Shop owner sign in', textAlign: TextAlign.center, style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w800)),
                      const SizedBox(height: 6),
                      const Text('Use the username and password given by PrintHub.', textAlign: TextAlign.center, style: TextStyle(color: Color(0xFF64748B))),
                      const SizedBox(height: 24),
                      TextFormField(
                        controller: _user,
                        autofillHints: const [AutofillHints.username],
                        textInputAction: TextInputAction.next,
                        decoration: const InputDecoration(labelText: 'Username, email or mobile', prefixIcon: Icon(Icons.person_outline)),
                        validator: (v) => (v ?? '').trim().isEmpty ? 'Required' : null,
                      ),
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: _pass,
                        obscureText: !_show,
                        autofillHints: const [AutofillHints.password],
                        onFieldSubmitted: (_) => _submit(),
                        decoration: InputDecoration(
                          labelText: 'Password',
                          prefixIcon: const Icon(Icons.lock_outline),
                          suffixIcon: IconButton(icon: Icon(_show ? Icons.visibility_off : Icons.visibility), onPressed: () => setState(() => _show = !_show)),
                        ),
                        validator: (v) => (v ?? '').isEmpty ? 'Required' : null,
                      ),
                      const SizedBox(height: 20),
                      FilledButton(onPressed: _busy ? null : _submit, child: _busy ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2)) : const Text('Sign in')),
                      TextButton(onPressed: () => context.push('/owner/forgot'), child: const Text('Forgot password?')),
                      const SizedBox(height: 12),
                      const Text('Want to join PrintHub? Contact the platform team — shop accounts are created by the administrator.', textAlign: TextAlign.center, style: TextStyle(fontSize: 12, color: Color(0xFF94A3B8))),
                    ]),
                  ),
                ),
              ),
            ),
          ),
        ),
      );
}

class ForgotPasswordScreen extends StatefulWidget {
  const ForgotPasswordScreen({super.key});
  @override
  State<ForgotPasswordScreen> createState() => _ForgotPasswordScreenState();
}

class _ForgotPasswordScreenState extends State<ForgotPasswordScreen> {
  final _login = TextEditingController();
  bool _sent = false;
  bool _busy = false;

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Reset password')),
        body: ListView(padding: const EdgeInsets.all(24), children: [
          if (_sent) ...[
            const Icon(Icons.mark_email_read_outlined, size: 56, color: Color(0xFF0F766E)),
            const SizedBox(height: 12),
            const Text('If an account matches, a reset link has been sent to its email address. The link works for 60 minutes. '
                'If your account has no email, contact PrintHub support to reset your password.', textAlign: TextAlign.center),
            const SizedBox(height: 20),
            OutlinedButton(onPressed: () => context.push('/owner/reset'), child: const Text('I have a reset code')),
          ] else ...[
            const Text('Enter your username or email. We will send a password reset link to the email on your account.'),
            const SizedBox(height: 16),
            TextField(controller: _login, decoration: const InputDecoration(labelText: 'Username or email')),
            const SizedBox(height: 16),
            FilledButton(
              onPressed: _busy
                  ? null
                  : () async {
                      if (_login.text.trim().isEmpty) return;
                      setState(() => _busy = true);
                      final r = await runAction(context, () => SessionScope.read(context).api.post('/auth/forgot', {'login': _login.text.trim()}));
                      if (mounted) {
                        setState(() {
                          _busy = false;
                          _sent = r != null;
                        });
                      }
                    },
              child: const Text('Send reset link'),
            ),
          ],
        ]),
      );
}

class ResetPasswordScreen extends StatefulWidget {
  const ResetPasswordScreen({super.key, required this.token});
  final String token;
  @override
  State<ResetPasswordScreen> createState() => _ResetPasswordScreenState();
}

class _ResetPasswordScreenState extends State<ResetPasswordScreen> {
  late final _token = TextEditingController(text: widget.token);
  final _pass = TextEditingController();
  final _form = GlobalKey<FormState>();

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Choose a new password')),
        body: Form(
          key: _form,
          child: ListView(padding: const EdgeInsets.all(24), children: [
            TextFormField(controller: _token, decoration: const InputDecoration(labelText: 'Reset code from the email link'), validator: (v) => (v ?? '').trim().length < 20 ? 'Paste the code from the link' : null),
            const SizedBox(height: 12),
            TextFormField(controller: _pass, obscureText: true, decoration: const InputDecoration(labelText: 'New password', helperText: 'At least 8 characters with letters and numbers'), validator: passwordRule),
            const SizedBox(height: 20),
            FilledButton(
              onPressed: () async {
                if (!_form.currentState!.validate()) return;
                final r = await runAction(context, () => SessionScope.read(context).api.post('/auth/reset', {'token': _token.text.trim(), 'password': _pass.text}), success: 'Password changed. Please sign in.');
                if (r != null && context.mounted) context.go('/owner/login');
              },
              child: const Text('Save password'),
            ),
          ]),
        ),
      );
}

String? passwordRule(String? v) {
  final s = v ?? '';
  if (s.length < 8) return 'Use at least 8 characters';
  if (!RegExp(r'[A-Za-z]').hasMatch(s) || !RegExp(r'\d').hasMatch(s)) return 'Use letters and numbers';
  return null;
}

class ChangePasswordScreen extends StatefulWidget {
  const ChangePasswordScreen({super.key});
  @override
  State<ChangePasswordScreen> createState() => _ChangePasswordScreenState();
}

class _ChangePasswordScreenState extends State<ChangePasswordScreen> {
  final _form = GlobalKey<FormState>();
  final _cur = TextEditingController();
  final _new = TextEditingController();
  final _again = TextEditingController();

  @override
  Widget build(BuildContext context) {
    final session = SessionScope.of(context);
    final forced = session.mustChangePassword;
    return Scaffold(
      appBar: AppBar(title: const Text('Change password'), automaticallyImplyLeading: !forced, actions: [if (forced) TextButton(onPressed: session.logout, child: const Text('Sign out'))]),
      body: Form(
        key: _form,
        child: ListView(padding: const EdgeInsets.all(24), children: [
          if (forced)
            const Card(
              color: Color(0xFFFFFBEB),
              child: Padding(padding: EdgeInsets.all(14), child: Text('For your security, please choose your own password before continuing.')),
            ),
          const SizedBox(height: 12),
          TextFormField(controller: _cur, obscureText: true, decoration: const InputDecoration(labelText: 'Current password'), validator: (v) => (v ?? '').isEmpty ? 'Required' : null),
          const SizedBox(height: 12),
          TextFormField(controller: _new, obscureText: true, decoration: const InputDecoration(labelText: 'New password'), validator: passwordRule),
          const SizedBox(height: 12),
          TextFormField(controller: _again, obscureText: true, decoration: const InputDecoration(labelText: 'Repeat new password'), validator: (v) => v != _new.text ? 'Passwords do not match' : null),
          const SizedBox(height: 20),
          FilledButton(
            onPressed: () async {
              if (!_form.currentState!.validate()) return;
              final r = await runAction(context, () => session.api.post('/auth/change-password', {'current_password': _cur.text, 'new_password': _new.text}), success: 'Password changed');
              if (r == null) return;
              await session.refreshOwner();
              if (context.mounted) context.go('/owner');
            },
            child: const Text('Save new password'),
          ),
        ]),
      ),
    );
  }
}
