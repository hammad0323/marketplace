import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/constants/app_colors.dart';
import '../../core/utils/formatters.dart';
import '../../core/widgets/common.dart';
import '../../models/playlist.dart';
import '../../providers/providers.dart';

class PlaylistsScreen extends ConsumerWidget {
  const PlaylistsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final list = ref.watch(playlistsProvider);
    return Scaffold(
      appBar: AppBar(title: const Text('Playlists'), actions: [
        IconButton(tooltip: 'Schedules', icon: const Icon(Icons.event_note_outlined), onPressed: () => context.push('/schedules')),
      ]),
      floatingActionButton: FloatingActionButton.extended(onPressed: () => context.push('/playlists/new'), icon: const Icon(Icons.view_carousel_rounded), label: const Text('Create Screen Carousel')),
      body: RefreshIndicator(
        onRefresh: () async => ref.invalidate(playlistsProvider),
        child: list.when(
          loading: () => const LoadingView(),
          error: (e, _) => ErrorView(error: e, onRetry: () => ref.invalidate(playlistsProvider)),
          data: (items) => items.isEmpty
              ? ListView(children: [
                  const SizedBox(height: 60),
                  EmptyState(
                    icon: Icons.view_carousel_outlined,
                    title: 'No playlists yet',
                    message: 'A playlist shows several of your screens one after another, on repeat.',
                    action: 'Create Screen Carousel',
                    onAction: () => context.push('/playlists/new'),
                  ),
                ])
              : ListView.separated(
                  padding: const EdgeInsets.fromLTRB(16, 8, 16, 100),
                  itemCount: items.length,
                  separatorBuilder: (_, _) => const SizedBox(height: 12),
                  itemBuilder: (_, i) => _PlaylistCard(p: items[i]),
                ),
        ),
      ),
    );
  }
}

class _PlaylistCard extends ConsumerWidget {
  const _PlaylistCard({required this.p});
  final Playlist p;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final t = Theme.of(context).textTheme;
    final repo = ref.read(playlistsRepoProvider);
    final status = p.status == 'published' && p.hasUnpublishedChanges ? 'Unpublished changes' : p.status;
    return AppCard(
      onTap: () => context.push('/playlists/${p.playlistId}/edit'),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(color: Theme.of(context).colorScheme.primaryContainer, borderRadius: BorderRadius.circular(14)),
            child: const Icon(Icons.view_carousel_rounded, color: AppColors.teal),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(p.playlistName, style: t.titleMedium, maxLines: 1, overflow: TextOverflow.ellipsis),
              Text('${p.itemCount} screens · ${formatDuration(p.totalDuration)} loop · ${p.transitionType.replaceAll('_', ' ')}', style: t.bodySmall?.copyWith(color: AppColors.mediumGrey)),
            ]),
          ),
          PopupMenuButton<String>(
            onSelected: (a) async {
              switch (a) {
                case 'preview': context.push('/playlists/${p.playlistId}/preview');
                case 'publish':
                  final m = await runWithProgress(context, () => repo.publish(p.playlistId));
                  if (m != null && context.mounted) showToast(context, m);
                  invalidateContent(ref);
                case 'duplicate':
                  await runWithProgress(context, () => repo.duplicate(p.playlistId), success: 'Playlist duplicated');
                  invalidateContent(ref);
                case 'delete':
                  if (await confirmDialog(context, title: 'Delete "${p.playlistName}"?', message: 'Displays using it will switch to their other content.', confirm: 'Delete', destructive: true) && context.mounted) {
                    await runWithProgress(context, () => repo.delete(p.playlistId), success: 'Playlist deleted');
                    invalidateContent(ref);
                  }
              }
            },
            itemBuilder: (_) => const [
              PopupMenuItem(value: 'preview', child: ListTile(leading: Icon(Icons.play_circle_outline), title: Text('Preview'))),
              PopupMenuItem(value: 'publish', child: ListTile(leading: Icon(Icons.cloud_upload_outlined), title: Text('Publish'))),
              PopupMenuItem(value: 'duplicate', child: ListTile(leading: Icon(Icons.copy_rounded), title: Text('Duplicate'))),
              PopupMenuItem(value: 'delete', child: ListTile(leading: Icon(Icons.delete_outline, color: AppColors.danger), title: Text('Delete'))),
            ],
          ),
        ]),
        const SizedBox(height: 12),
        if (p.items.isNotEmpty)
          Wrap(spacing: 6, runSpacing: 6, crossAxisAlignment: WrapCrossAlignment.center, children: [
            for (var i = 0; i < p.items.length && i < 6; i++) ...[
              Chip(visualDensity: VisualDensity.compact, label: Text(p.items[i].screenName, style: const TextStyle(fontSize: 12))),
              if (i < p.items.length - 1 && i < 5) const Icon(Icons.arrow_forward_rounded, size: 14, color: AppColors.mediumGrey),
            ],
            if (p.items.length > 6) Text('+${p.items.length - 6} more', style: const TextStyle(color: AppColors.mediumGrey)),
            if (p.loopEnabled) const Icon(Icons.repeat_rounded, size: 16, color: AppColors.teal),
          ]),
        const SizedBox(height: 10),
        Row(children: [
          StatusChip(status, color: status == 'Unpublished changes' ? AppColors.warning : null),
          const SizedBox(width: 8),
          if (p.deviceCount > 0) Text('On ${p.deviceCount} display(s)', style: t.bodySmall?.copyWith(color: AppColors.mediumGrey)),
          const Spacer(),
          if (p.schedules.isNotEmpty) ...[const Icon(Icons.schedule, size: 15, color: AppColors.mediumGrey), Text(' ${p.schedules.length} schedule(s)', style: t.bodySmall?.copyWith(color: AppColors.mediumGrey))],
        ]),
      ]),
    );
  }
}
