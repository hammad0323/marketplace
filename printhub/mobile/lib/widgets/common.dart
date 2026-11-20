import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

import '../api/api.dart';
import '../state/session.dart';

/// Loads data once and renders loading / error / content states with pull-to-refresh.
class AsyncView<T> extends StatefulWidget {
  const AsyncView({super.key, required this.load, required this.builder, this.empty});
  final Future<T> Function() load;
  final Widget Function(BuildContext context, T data, Future<void> Function() reload) builder;
  final Widget? empty;

  @override
  State<AsyncView<T>> createState() => AsyncViewState<T>();
}

class AsyncViewState<T> extends State<AsyncView<T>> {
  T? _data;
  Object? _error;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    reload();
  }

  Future<void> reload() async {
    setState(() => _loading = _data == null);
    try {
      final d = await widget.load();
      if (!mounted) return;
      setState(() {
        _data = d;
        _error = null;
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      if (e is ApiException && e.unauthorized && SessionScope.read(context).isOwner) {
        await SessionScope.read(context).signedOut();
        return;
      }
      setState(() {
        _error = e;
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading && _data == null) return const Center(child: CircularProgressIndicator());
    if (_error != null && _data == null) {
      return ErrorState(message: '$_error', onRetry: reload);
    }
    return RefreshIndicator(onRefresh: reload, child: widget.builder(context, _data as T, reload));
  }
}

class ErrorState extends StatelessWidget {
  const ErrorState({super.key, required this.message, this.onRetry});
  final String message;
  final VoidCallback? onRetry;
  @override
  Widget build(BuildContext context) => EmptyState(icon: Icons.cloud_off, title: 'Something went wrong', message: message, action: onRetry == null ? null : FilledButton(onPressed: onRetry, child: const Text('Try again')));
}

class EmptyState extends StatelessWidget {
  const EmptyState({super.key, required this.icon, required this.title, this.message, this.action});
  final IconData icon;
  final String title;
  final String? message;
  final Widget? action;
  @override
  Widget build(BuildContext context) => ListView(padding: const EdgeInsets.all(32), children: [
        const SizedBox(height: 40),
        Icon(icon, size: 56, color: const Color(0xFFCBD5E1)),
        const SizedBox(height: 12),
        Text(title, textAlign: TextAlign.center, style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
        if (message != null) Padding(padding: const EdgeInsets.only(top: 6), child: Text(message!, textAlign: TextAlign.center, style: const TextStyle(color: Color(0xFF64748B)))),
        if (action != null) Padding(padding: const EdgeInsets.only(top: 18), child: Center(child: action)),
      ]);
}

class SectionTitle extends StatelessWidget {
  const SectionTitle(this.text, {super.key, this.trailing});
  final String text;
  final Widget? trailing;
  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.fromLTRB(2, 14, 2, 8),
        child: Row(children: [Expanded(child: Text(text, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15, color: Color(0xFF0F172A)))), ?trailing]),
      );
}

class StatusChip extends StatelessWidget {
  const StatusChip(this.status, this.label, {super.key});
  final String status;
  final String label;
  static const _colors = {
    'awaiting_approval': Color(0xFFF59E0B), 'submitted': Color(0xFF64748B), 'quoted': Color(0xFF2563EB), 'accepted': Color(0xFF0891B2),
    'awaiting_payment': Color(0xFFF59E0B), 'payment_confirmed': Color(0xFF0891B2), 'printing': Color(0xFF7C3AED), 'ready': Color(0xFF16A34A),
    'completed': Color(0xFF15803D), 'rejected': Color(0xFFDC2626), 'cancelled': Color(0xFF64748B),
  };
  @override
  Widget build(BuildContext context) {
    final c = _colors[status] ?? const Color(0xFF64748B);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(color: c.withValues(alpha: .12), borderRadius: BorderRadius.circular(20)),
      child: Text(label, style: TextStyle(color: c, fontWeight: FontWeight.w700, fontSize: 12)),
    );
  }
}

class StatTile extends StatelessWidget {
  const StatTile({super.key, required this.label, required this.value, required this.icon, this.color = const Color(0xFF0F766E), this.onTap});
  final String label;
  final String value;
  final IconData icon;
  final Color color;
  final VoidCallback? onTap;
  @override
  Widget build(BuildContext context) => Card(
        margin: EdgeInsets.zero,
        child: InkWell(
          borderRadius: BorderRadius.circular(16),
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Row(children: [
              Container(width: 40, height: 40, decoration: BoxDecoration(color: color.withValues(alpha: .12), borderRadius: BorderRadius.circular(12)), child: Icon(icon, color: color)),
              const SizedBox(width: 12),
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(label, style: const TextStyle(fontSize: 12, color: Color(0xFF64748B)), maxLines: 1, overflow: TextOverflow.ellipsis),
                Text(value, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: Color(0xFF0F172A)), maxLines: 1, overflow: TextOverflow.ellipsis),
              ])),
            ]),
          ),
        ),
      );
}

/// Home button for screens opened directly from a link (nothing to go back to).
Widget? homeLeading(BuildContext context) => Navigator.of(context).canPop() ? null : IconButton(tooltip: 'Home', icon: const Icon(Icons.home_outlined), onPressed: () => GoRouter.of(context).go('/'));

void showMessage(BuildContext context, String text, {bool error = false}) {
  ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(SnackBar(content: Text(text), behavior: SnackBarBehavior.floating, backgroundColor: error ? const Color(0xFFB91C1C) : null));
}

/// Runs an API action with a loading guard and shows the server's message or error.
Future<T?> runAction<T>(BuildContext context, Future<T> Function() fn, {String? success}) async {
  try {
    final r = await fn();
    if (context.mounted && success != null) showMessage(context, success);
    return r;
  } on ApiException catch (e) {
    if (context.mounted) showMessage(context, e.errors.isNotEmpty ? e.errors.join('\n') : e.message, error: true);
  }
  return null;
}

Future<bool> confirm(BuildContext context, String text, {String yes = 'Yes'}) async =>
    await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(content: Text(text), actions: [TextButton(onPressed: () => Navigator.pop(c, false), child: const Text('Cancel')), FilledButton(onPressed: () => Navigator.pop(c, true), child: Text(yes))]),
    ) ??
    false;
