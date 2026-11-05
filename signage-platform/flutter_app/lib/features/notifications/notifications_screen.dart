import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/constants/app_colors.dart';
import '../../core/utils/formatters.dart';
import '../../core/widgets/common.dart';
import '../../providers/providers.dart';

class NotificationsScreen extends ConsumerWidget {
  const NotificationsScreen({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final list = ref.watch(notificationsProvider);
    return Scaffold(
      appBar: AppBar(title: const Text('Notifications'), actions: [
        TextButton(onPressed: () async { await ref.read(miscRepoProvider).markAllRead(); ref.invalidate(notificationsProvider); ref.invalidate(dashboardProvider); }, child: const Text('Mark all read')),
      ]),
      body: list.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(error: e, onRetry: () => ref.invalidate(notificationsProvider)),
        data: (items) => items.isEmpty
            ? const EmptyState(icon: Icons.notifications_none_rounded, title: 'You are all caught up')
            : ListView.separated(
                itemCount: items.length,
                separatorBuilder: (_, _) => const Divider(height: 1),
                itemBuilder: (_, i) {
                  final n = items[i];
                  final (icon, color) = switch (n.type) {
                    'device_offline' => (Icons.wifi_off_rounded, AppColors.danger),
                    'limit_reached' => (Icons.block_rounded, AppColors.warning),
                    'success' => (Icons.check_circle_outline, AppColors.success),
                    'warning' || 'error' => (Icons.warning_amber_rounded, AppColors.warning),
                    _ => (Icons.info_outline, AppColors.teal),
                  };
                  return ListTile(
                    tileColor: n.isRead ? null : Theme.of(context).colorScheme.primaryContainer.withValues(alpha: 0.4),
                    leading: CircleAvatar(backgroundColor: color.withValues(alpha: 0.12), child: Icon(icon, color: color)),
                    title: Text(n.title, style: TextStyle(fontWeight: n.isRead ? FontWeight.w500 : FontWeight.w700)),
                    subtitle: Text('${n.message}\n${timeAgo(n.createdAt)}'),
                    isThreeLine: true,
                    onTap: () async {
                      await ref.read(miscRepoProvider).markRead(n.id);
                      ref.invalidate(notificationsProvider);
                      if (n.link != null && context.mounted) {
                        final l = n.link!;
                        if (RegExp(r'^/devices/\d+$').hasMatch(l) || l == '/screens/new') {
                          context.push(l);
                        } else if (const {'/screens', '/playlists', '/devices', '/profile', '/home'}.contains(l)) {
                          context.go(l);
                        }
                      }
                    },
                  );
                },
              ),
      ),
    );
  }
}
