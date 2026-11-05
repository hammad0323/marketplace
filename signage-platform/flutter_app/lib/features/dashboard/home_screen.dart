import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/constants/app_colors.dart';
import '../../core/utils/formatters.dart';
import '../../core/utils/json.dart';
import '../../core/widgets/common.dart';
import '../../models/screen.dart';
import '../../models/user.dart';
import '../../providers/providers.dart';
import '../screens/screen_card.dart';

class HomeScreen extends ConsumerWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final dash = ref.watch(dashboardProvider);
    final session = ref.watch(sessionProvider).value;
    final t = Theme.of(context).textTheme;
    return Scaffold(
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => context.push('/screens/new'),
        icon: const Icon(Icons.add_rounded),
        label: const Text('Create New Screen'),
      ),
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: () async => invalidateContent(ref),
          child: dash.when(
            loading: () => const LoadingView(),
            error: (e, _) => ErrorView(error: e, onRetry: () => ref.invalidate(dashboardProvider)),
            data: (d) {
              final stats = asMap(d['stats']);
              final limits = UserLimits.fromJson(asMap(d['limits']));
              final usage = UserUsage.fromJson(asMap(d['usage']));
              final recent = asList(d['recent_screens']).map((e) => ScreenModel.fromJson(asMap(e))).toList();
              final activity = asList(d['recent_activity']).map(asMap).toList();
              final unread = asInt(d['unread_notifications']);
              return ListView(padding: const EdgeInsets.fromLTRB(16, 12, 16, 100), children: [
                Row(children: [
                  Expanded(
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text(_greeting(), style: t.bodyMedium?.copyWith(color: AppColors.mediumGrey)),
                      Text(session?.user.firstName ?? '', style: t.headlineMedium),
                    ]),
                  ),
                  if (session?.user.isAdmin ?? false)
                    IconButton.filledTonal(onPressed: () => context.go('/admin'), icon: const Icon(Icons.admin_panel_settings_outlined), tooltip: 'Admin panel'),
                  const SizedBox(width: 6),
                  Badge(
                    isLabelVisible: unread > 0,
                    label: Text('$unread'),
                    child: IconButton.filledTonal(onPressed: () => context.push('/notifications'), icon: const Icon(Icons.notifications_none_rounded)),
                  ),
                ]),
                const SizedBox(height: 18),
                _HeroCard(stats: stats, limits: limits),
                const SizedBox(height: 14),
                GridView.count(
                  crossAxisCount: MediaQuery.of(context).size.width > 700 ? 4 : 2,
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  mainAxisSpacing: 12,
                  crossAxisSpacing: 12,
                  childAspectRatio: 1.35,
                  children: [
                    StatCard(label: 'Published screens', value: '${stats['published_screens']}', icon: Icons.cloud_done_outlined, caption: '${stats['draft_screens']} drafts', onTap: () => context.go('/screens')),
                    StatCard(label: 'Active playlists', value: '${stats['active_playlists']}', icon: Icons.playlist_play_rounded, color: const Color(0xFF5E35B1), caption: '${stats['total_playlists']} total', onTap: () => context.go('/playlists')),
                    StatCard(label: 'Displays online', value: '${stats['online_devices']}/${stats['total_devices']}', icon: Icons.tv_rounded, color: AppColors.success, onTap: () => context.go('/devices')),
                    StatCard(label: 'Storage used', value: formatBytes(usage.storageUsedBytes), icon: Icons.cloud_outlined, color: const Color(0xFF1E88E5), caption: 'of ${formatMb(limits.maxStorageMb)}', onTap: () => context.push('/media')),
                  ],
                ),
                const SizedBox(height: 18),
                const SectionHeader('Quick actions'),
                SizedBox(
                  height: 96,
                  child: ListView(scrollDirection: Axis.horizontal, children: [
                    _QuickAction(icon: Icons.add_to_queue_rounded, label: 'New screen', onTap: () => context.push('/screens/new')),
                    _QuickAction(icon: Icons.playlist_add_rounded, label: 'New playlist', onTap: () => context.push('/playlists/new')),
                    _QuickAction(icon: Icons.cloud_upload_outlined, label: 'Upload media', onTap: () => context.push('/media')),
                    _QuickAction(icon: Icons.auto_awesome_mosaic_outlined, label: 'Templates', onTap: () => context.push('/screens/new')),
                    _QuickAction(icon: Icons.cast_connected_rounded, label: 'Connect TV', onTap: () => context.push('/devices/add')),
                    _QuickAction(icon: Icons.event_note_outlined, label: 'Schedules', onTap: () => context.push('/schedules')),
                    _QuickAction(icon: Icons.warning_amber_rounded, label: 'Emergency', onTap: () => context.push('/emergency'), color: AppColors.danger),
                  ]),
                ),
                const SizedBox(height: 10),
                SectionHeader('Recent screens', action: 'See all', onAction: () => context.go('/screens')),
                if (recent.isEmpty)
                  AppCard(child: EmptyState(icon: Icons.dashboard_customize_outlined, title: 'No screens yet', message: 'Start from a template or a blank canvas.', action: 'Create screen', onAction: () => context.push('/screens/new')))
                else
                  SizedBox(
                    height: 252,
                    child: ListView.separated(
                      scrollDirection: Axis.horizontal,
                      itemCount: recent.length,
                      separatorBuilder: (_, _) => const SizedBox(width: 12),
                      itemBuilder: (_, i) => SizedBox(width: 230, child: ScreenCard(screen: recent[i], compact: true)),
                    ),
                  ),
                const SizedBox(height: 14),
                const SectionHeader('Plan usage'),
                AppCard(
                  child: Column(children: [
                    UsageBar(label: 'Screens', used: usage.screensUsed, limit: limits.maxScreens),
                    const SizedBox(height: 14),
                    UsageBar(label: 'Playlists', used: usage.playlistsUsed, limit: limits.maxPlaylists),
                    const SizedBox(height: 14),
                    UsageBar(label: 'Displays', used: usage.devicesUsed, limit: limits.maxDevices),
                    const SizedBox(height: 14),
                    UsageBar(label: 'Storage', used: usage.storageUsedBytes / 1048576, limit: limits.maxStorageMb, format: (n) => formatMb(n.round())),
                  ]),
                ),
                const SizedBox(height: 14),
                const SectionHeader('Recent activity'),
                AppCard(
                  padding: const EdgeInsets.symmetric(vertical: 6),
                  child: activity.isEmpty
                      ? const Padding(padding: EdgeInsets.all(16), child: Text('No activity yet'))
                      : Column(children: [
                          for (final a in activity.take(6))
                            ListTile(
                              dense: true,
                              leading: CircleAvatar(radius: 16, backgroundColor: Theme.of(context).colorScheme.primaryContainer, child: Icon(_activityIcon(asString(a['action'])), size: 16, color: AppColors.teal)),
                              title: Text(asString(a['description'], asString(a['action']))),
                              subtitle: Text(timeAgo(asDate(a['created_at']))),
                            ),
                        ]),
                ),
              ]);
            },
          ),
        ),
      ),
    );
  }

  String _greeting() {
    final h = DateTime.now().hour;
    return h < 12 ? 'Good morning,' : (h < 18 ? 'Good afternoon,' : 'Good evening,');
  }

  IconData _activityIcon(String a) {
    if (a.startsWith('screen')) return Icons.dashboard_outlined;
    if (a.startsWith('playlist')) return Icons.playlist_play_rounded;
    if (a.startsWith('device')) return Icons.tv_rounded;
    if (a.startsWith('media')) return Icons.photo_library_outlined;
    return Icons.bolt_rounded;
  }
}

class _HeroCard extends StatelessWidget {
  const _HeroCard({required this.stats, required this.limits});
  final Map<String, dynamic> stats;
  final UserLimits limits;
  @override
  Widget build(BuildContext context) {
    final total = asInt(stats['total_screens']);
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: const LinearGradient(colors: [AppColors.teal, AppColors.darkTeal], begin: Alignment.topLeft, end: Alignment.bottomRight),
        borderRadius: BorderRadius.circular(24),
      ),
      child: Row(children: [
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('Total screens', style: TextStyle(color: Colors.white.withValues(alpha: 0.85))),
            const SizedBox(height: 4),
            Text('$total', style: const TextStyle(color: Colors.white, fontSize: 40, fontWeight: FontWeight.w800, height: 1.1)),
            const SizedBox(height: 4),
            Text(limits.maxScreens < 0 ? 'Unlimited on ${limits.planName ?? 'your plan'}' : '${limits.maxScreens - total} of ${limits.maxScreens} remaining · ${limits.planName ?? ''}',
                style: TextStyle(color: Colors.white.withValues(alpha: 0.85), fontSize: 13)),
          ]),
        ),
        FilledButton.tonalIcon(
          style: FilledButton.styleFrom(backgroundColor: Colors.white, foregroundColor: AppColors.darkTeal, minimumSize: const Size(0, 44)),
          onPressed: () => context.push('/playlists/new'),
          icon: const Icon(Icons.view_carousel_rounded, size: 18),
          label: const Text('Carousel'),
        ),
      ]),
    );
  }
}

class _QuickAction extends StatelessWidget {
  const _QuickAction({required this.icon, required this.label, required this.onTap, this.color});
  final IconData icon;
  final String label;
  final VoidCallback onTap;
  final Color? color;
  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(right: 10),
        child: SizedBox(
          width: 92,
          child: AppCard(
            onTap: onTap,
            padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 6),
            child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
              Icon(icon, color: color ?? AppColors.teal, size: 26),
              const SizedBox(height: 8),
              Text(label, textAlign: TextAlign.center, maxLines: 2, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
            ]),
          ),
        ),
      );
}
