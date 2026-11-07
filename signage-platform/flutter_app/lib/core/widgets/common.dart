import 'package:flutter/material.dart';

import '../constants/app_colors.dart';
import '../network/api_client.dart';

/// Rounded surface card used throughout the app.
class AppCard extends StatelessWidget {
  const AppCard({super.key, required this.child, this.padding = const EdgeInsets.all(16), this.onTap, this.color});
  final Widget child;
  final EdgeInsetsGeometry padding;
  final VoidCallback? onTap;
  final Color? color;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Material(
      color: color ?? scheme.surface,
      borderRadius: BorderRadius.circular(20),
      child: InkWell(
        borderRadius: BorderRadius.circular(20),
        onTap: onTap,
        child: Container(
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(20),
            border: Border.all(color: scheme.outline),
          ),
          padding: padding,
          child: child,
        ),
      ),
    );
  }
}

class SectionHeader extends StatelessWidget {
  const SectionHeader(this.title, {super.key, this.action, this.onAction});
  final String title;
  final String? action;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.fromLTRB(4, 8, 0, 8),
        child: Row(children: [
          Expanded(child: Text(title, style: Theme.of(context).textTheme.titleMedium)),
          if (action != null) TextButton(onPressed: onAction, child: Text(action!)),
        ]),
      );
}

class StatCard extends StatelessWidget {
  const StatCard({super.key, required this.label, required this.value, required this.icon, this.color, this.onTap, this.caption});
  final String label;
  final String value;
  final IconData icon;
  final Color? color;
  final VoidCallback? onTap;
  final String? caption;

  @override
  Widget build(BuildContext context) {
    final c = color ?? AppColors.teal;
    final t = Theme.of(context).textTheme;
    return AppCard(
      onTap: onTap,
      padding: const EdgeInsets.all(14),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Container(
          padding: const EdgeInsets.all(8),
          decoration: BoxDecoration(color: c.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(12)),
          child: Icon(icon, color: c, size: 20),
        ),
        const SizedBox(height: 12),
        Text(value, style: t.headlineSmall),
        const SizedBox(height: 2),
        Text(label, style: t.bodySmall?.copyWith(color: AppColors.mediumGrey), maxLines: 1, overflow: TextOverflow.ellipsis),
        if (caption != null) Text(caption!, style: t.labelSmall?.copyWith(color: c), maxLines: 1, overflow: TextOverflow.ellipsis),
      ]),
    );
  }
}

class StatusChip extends StatelessWidget {
  const StatusChip(this.label, {super.key, this.color});
  final String label;
  final Color? color;

  static Color colorFor(String status) => switch (status) {
        'published' || 'active' || 'online' || 'playing' || 'completed' => AppColors.success,
        'draft' || 'pending' || 'idle' || 'paused' || 'unpaired' => AppColors.warning,
        'suspended' || 'offline' || 'error' || 'failed' || 'inactive' || 'archived' => AppColors.danger,
        _ => AppColors.mediumGrey,
      };

  @override
  Widget build(BuildContext context) {
    final c = color ?? colorFor(label.toLowerCase());
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(color: c.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(20)),
      child: Row(mainAxisSize: MainAxisSize.min, children: [
        Container(width: 6, height: 6, decoration: BoxDecoration(color: c, shape: BoxShape.circle)),
        const SizedBox(width: 5),
        Flexible(
          child: Text(label[0].toUpperCase() + label.substring(1),
              maxLines: 1, overflow: TextOverflow.ellipsis, softWrap: false, style: TextStyle(color: c, fontSize: 11.5, fontWeight: FontWeight.w600)),
        ),
      ]),
    );
  }
}

class EmptyState extends StatelessWidget {
  const EmptyState({super.key, required this.icon, required this.title, this.message, this.action, this.onAction});
  final IconData icon;
  final String title;
  final String? message;
  final String? action;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) {
    final t = Theme.of(context).textTheme;
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          Container(
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(color: Theme.of(context).colorScheme.primaryContainer, shape: BoxShape.circle),
            child: Icon(icon, size: 36, color: AppColors.teal),
          ),
          const SizedBox(height: 16),
          Text(title, style: t.titleMedium, textAlign: TextAlign.center),
          if (message != null) ...[
            const SizedBox(height: 6),
            Text(message!, style: t.bodyMedium?.copyWith(color: AppColors.mediumGrey), textAlign: TextAlign.center),
          ],
          if (action != null) ...[
            const SizedBox(height: 18),
            FilledButton.icon(onPressed: onAction, icon: const Icon(Icons.add), label: Text(action!)),
          ],
        ]),
      ),
    );
  }
}

class ErrorView extends StatelessWidget {
  const ErrorView({super.key, required this.error, this.onRetry});
  final Object error;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) => Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            const Icon(Icons.cloud_off_rounded, size: 44, color: AppColors.mediumGrey),
            const SizedBox(height: 12),
            Text(error is ApiException ? error.toString() : 'Something went wrong', textAlign: TextAlign.center),
            if (onRetry != null) ...[
              const SizedBox(height: 12),
              OutlinedButton.icon(onPressed: onRetry, icon: const Icon(Icons.refresh), label: const Text('Try again')),
            ],
          ]),
        ),
      );
}

class LoadingView extends StatelessWidget {
  const LoadingView({super.key});
  @override
  Widget build(BuildContext context) => const Center(child: CircularProgressIndicator(strokeWidth: 2.6));
}

/// Progress bar with label "used / limit".
class UsageBar extends StatelessWidget {
  const UsageBar({super.key, required this.label, required this.used, required this.limit, this.format});
  final String label;
  final num used;
  final num limit; // -1 = unlimited
  final String Function(num)? format;

  @override
  Widget build(BuildContext context) {
    final f = format ?? (n) => n.toString();
    final unlimited = limit < 0;
    final ratio = unlimited || limit == 0 ? 0.0 : (used / limit).clamp(0.0, 1.0).toDouble();
    final c = ratio >= 1 ? AppColors.danger : (ratio > 0.8 ? AppColors.warning : AppColors.teal);
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Row(children: [
        Expanded(child: Text(label, style: Theme.of(context).textTheme.bodyMedium)),
        Text(unlimited ? '${f(used)} · Unlimited' : '${f(used)} / ${f(limit)}',
            style: Theme.of(context).textTheme.bodySmall?.copyWith(color: AppColors.mediumGrey, fontWeight: FontWeight.w600)),
      ]),
      const SizedBox(height: 6),
      ClipRRect(
        borderRadius: BorderRadius.circular(8),
        child: LinearProgressIndicator(value: unlimited ? 0.04 : ratio, minHeight: 7, color: c, backgroundColor: c.withValues(alpha: 0.12)),
      ),
    ]);
  }
}

// ---------------------------------------------------------------------------
// Feedback helpers
// ---------------------------------------------------------------------------
void showToast(BuildContext context, String message, {bool error = false}) {
  ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(SnackBar(
      content: Text(message),
      backgroundColor: error ? AppColors.danger : null,
      duration: Duration(milliseconds: error ? 4500 : 2600),
    ));
}

Future<bool> confirmDialog(BuildContext context, {required String title, String? message, String confirm = 'Confirm', bool destructive = false}) async {
  final r = await showDialog<bool>(
    context: context,
    builder: (c) => AlertDialog(
      title: Text(title),
      content: message == null ? null : Text(message),
      actions: [
        TextButton(onPressed: () => Navigator.pop(c, false), child: const Text('Cancel')),
        FilledButton(
          style: destructive ? FilledButton.styleFrom(backgroundColor: AppColors.danger, minimumSize: const Size(64, 44)) : FilledButton.styleFrom(minimumSize: const Size(64, 44)),
          onPressed: () => Navigator.pop(c, true),
          child: Text(confirm),
        ),
      ],
    ),
  );
  return r ?? false;
}

Future<String?> promptText(BuildContext context, {required String title, String? initial, String label = 'Name', String action = 'Save'}) {
  final ctrl = TextEditingController(text: initial);
  return showDialog<String>(
    context: context,
    builder: (c) => AlertDialog(
      title: Text(title),
      content: TextField(controller: ctrl, autofocus: true, decoration: InputDecoration(labelText: label), onSubmitted: (v) => Navigator.pop(c, v.trim())),
      actions: [
        TextButton(onPressed: () => Navigator.pop(c), child: const Text('Cancel')),
        FilledButton(style: FilledButton.styleFrom(minimumSize: const Size(64, 44)), onPressed: () => Navigator.pop(c, ctrl.text.trim()), child: Text(action)),
      ],
    ),
  );
}

/// Runs an async action with a blocking spinner and turns errors into toasts.
Future<T?> runWithProgress<T>(BuildContext context, Future<T> Function() action, {String? success}) async {
  showDialog(context: context, barrierDismissible: false, builder: (_) => const Center(child: CircularProgressIndicator()));
  try {
    final r = await action();
    if (context.mounted) {
      Navigator.of(context, rootNavigator: true).pop();
      if (success != null) showToast(context, success);
    }
    return r;
  } catch (e) {
    if (context.mounted) {
      Navigator.of(context, rootNavigator: true).pop();
      showToast(context, e.toString(), error: true);
    }
    return null;
  }
}
