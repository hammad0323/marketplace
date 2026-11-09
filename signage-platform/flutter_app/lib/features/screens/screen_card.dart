import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/constants/app_colors.dart';
import '../../core/utils/formatters.dart';
import '../../core/widgets/common.dart';
import '../../models/screen.dart';
import '../../providers/providers.dart';
import '../widgets_library/layout_view.dart';
import '../widgets_library/render_context.dart';
import '../display/display_code_sheet.dart';

/// Shared screen actions (used by cards, lists and the editor).
class ScreenActions {
  static Future<void> publish(BuildContext context, WidgetRef ref, ScreenModel s) async {
    final msg = await runWithProgress(context, () => ref.read(screensRepoProvider).publish(s.screenId));
    if (msg != null && context.mounted) {
      showToast(context, msg);
      invalidateContent(ref);
    }
  }

  static Future<void> duplicate(BuildContext context, WidgetRef ref, ScreenModel s) async {
    final r = await runWithProgress(context, () => ref.read(screensRepoProvider).duplicate(s.screenId), success: 'Screen duplicated');
    if (r != null) invalidateContent(ref);
  }

  static Future<void> rename(BuildContext context, WidgetRef ref, ScreenModel s) async {
    final name = await promptText(context, title: 'Rename screen', initial: s.screenName);
    if (name == null || name.isEmpty || !context.mounted) return;
    final r = await runWithProgress(context, () => ref.read(screensRepoProvider).update(s.screenId, {'screen_name': name}), success: 'Renamed');
    if (r != null) invalidateContent(ref);
  }

  static Future<void> delete(BuildContext context, WidgetRef ref, ScreenModel s) async {
    final ok = await confirmDialog(context,
        title: 'Delete "${s.screenName}"?', message: 'It will be removed from any playlists and stop showing on displays.', confirm: 'Delete', destructive: true);
    if (!ok || !context.mounted) return;
    final r = await runWithProgress(context, () async {
      await ref.read(screensRepoProvider).delete(s.screenId);
      return true;
    }, success: 'Screen deleted');
    if (r != null) invalidateContent(ref);
  }

  static Future<void> toggleFavorite(BuildContext context, WidgetRef ref, ScreenModel s) async {
    await runWithProgress(context, () => ref.read(screensRepoProvider).update(s.screenId, {'is_favorite': !s.isFavorite}));
    invalidateContent(ref);
  }

  static Future<void> moveToFolder(BuildContext context, WidgetRef ref, ScreenModel s) async {
    final folders = await ref.read(screensRepoProvider).folders();
    if (!context.mounted) return;
    final picked = await showModalBottomSheet<int>(
      context: context,
      builder: (c) => SafeArea(
        child: ListView(shrinkWrap: true, children: [
          const ListTile(title: Text('Move to folder', style: TextStyle(fontWeight: FontWeight.w700))),
          ListTile(leading: const Icon(Icons.folder_off_outlined), title: const Text('No folder'), onTap: () => Navigator.pop(c, 0)),
          for (final f in folders) ListTile(leading: const Icon(Icons.folder_outlined), title: Text(f.folderName), onTap: () => Navigator.pop(c, f.folderId)),
        ]),
      ),
    );
    if (picked == null || !context.mounted) return;
    await runWithProgress(context, () => ref.read(screensRepoProvider).update(s.screenId, {'folder_id': picked}), success: 'Moved');
    invalidateContent(ref);
  }

  static Future<void> saveAsTemplate(BuildContext context, WidgetRef ref, ScreenModel s) async {
    final name = await promptText(context, title: 'Save as template', initial: s.screenName, label: 'Template name');
    if (name == null || name.isEmpty || !context.mounted) return;
    await runWithProgress(context, () => ref.read(screensRepoProvider).saveAsTemplate(s.screenId, name), success: 'Saved to My templates');
    ref.invalidate(templatesProvider);
  }

  static void showMenu(BuildContext context, WidgetRef ref, ScreenModel s) {
    showModalBottomSheet(
      context: context,
      // Scrollable + allowed to grow, so every action (incl. Delete) is reachable on small phones.
      isScrollControlled: true,
      constraints: BoxConstraints(maxHeight: MediaQuery.of(context).size.height * 0.85),
      builder: (c) {
        void go(Future<void> Function() f) {
          Navigator.pop(c);
          f();
        }
        return SafeArea(
          child: ListView(shrinkWrap: true, children: [
            ListTile(title: Text(s.screenName, style: const TextStyle(fontWeight: FontWeight.w700)), subtitle: Text(orientationLabel(s.orientation))),
            ListTile(leading: const Icon(Icons.edit_outlined), title: const Text('Edit'), onTap: () { Navigator.pop(c); context.push('/editor/${s.screenId}'); }),
            ListTile(leading: const Icon(Icons.play_circle_outline), title: const Text('Preview'), onTap: () { Navigator.pop(c); context.push('/preview/screen/${s.screenId}'); }),
            ListTile(leading: const Icon(Icons.cloud_upload_outlined), title: const Text('Publish'), onTap: () => go(() => publish(context, ref, s))),
            ListTile(leading: const Icon(Icons.tv_rounded), title: const Text('Show on a TV (display code)'), onTap: () => go(() => showDisplayCodeSheet(context, ref, screenId: s.screenId))),
            ListTile(leading: const Icon(Icons.copy_rounded), title: const Text('Duplicate'), onTap: () => go(() => duplicate(context, ref, s))),
            ListTile(leading: const Icon(Icons.drive_file_rename_outline), title: const Text('Rename'), onTap: () => go(() => rename(context, ref, s))),
            ListTile(leading: const Icon(Icons.folder_open_outlined), title: const Text('Move to folder'), onTap: () => go(() => moveToFolder(context, ref, s))),
            ListTile(leading: Icon(s.isFavorite ? Icons.star_rounded : Icons.star_outline_rounded), title: Text(s.isFavorite ? 'Remove from favourites' : 'Add to favourites'), onTap: () => go(() => toggleFavorite(context, ref, s))),
            ListTile(leading: const Icon(Icons.bookmark_add_outlined), title: const Text('Save as template'), onTap: () => go(() => saveAsTemplate(context, ref, s))),
            ListTile(leading: const Icon(Icons.delete_outline, color: AppColors.danger), title: const Text('Delete', style: TextStyle(color: AppColors.danger)), onTap: () => go(() => delete(context, ref, s))),
          ]),
        );
      },
    );
  }
}

/// Visual card: live thumbnail, name, orientation, status, last update and quick actions.
class ScreenCard extends ConsumerWidget {
  const ScreenCard({super.key, required this.screen, this.selected, this.onSelect, this.compact = false});
  final ScreenModel screen;
  final bool? selected;
  final VoidCallback? onSelect;
  final bool compact;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final s = screen;
    final t = Theme.of(context).textTheme;
    final selecting = selected != null;
    final status = s.status == 'published' && s.hasUnpublishedChanges ? 'Unpublished changes' : s.status;
    return AppCard(
      padding: EdgeInsets.zero,
      onTap: selecting ? onSelect : () => context.push('/editor/${s.screenId}'),
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Stack(children: [
          AspectRatio(
            aspectRatio: 16 / 10,
            child: Padding(
              padding: const EdgeInsets.all(8),
              child: s.layout == null
                  ? Container(decoration: BoxDecoration(color: AppColors.charcoal, borderRadius: BorderRadius.circular(12)))
                  : LayoutThumbnail(layout: s.layout!, media: NetworkMediaSource(s.media)),
            ),
          ),
          if (selecting)
            Positioned(
              top: 14, right: 14,
              child: Container(
                decoration: BoxDecoration(color: selected! ? AppColors.teal : Colors.black54, shape: BoxShape.circle, border: Border.all(color: Colors.white, width: 2)),
                padding: const EdgeInsets.all(2),
                child: Icon(Icons.check, size: 18, color: selected! ? Colors.white : Colors.transparent),
              ),
            ),
          if (s.isFavorite && !selecting) const Positioned(top: 14, right: 14, child: Icon(Icons.star_rounded, color: Color(0xFFFDD835), size: 22)),
        ]),
        Padding(
          padding: const EdgeInsets.fromLTRB(12, 0, 4, 4),
          child: Row(children: [
            Expanded(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(s.screenName, style: t.titleSmall?.copyWith(fontWeight: FontWeight.w700), maxLines: 1, overflow: TextOverflow.ellipsis),
                const SizedBox(height: 4),
                Row(children: [
                  Icon(orientationIcon(s.orientation), size: 14, color: AppColors.mediumGrey),
                  const SizedBox(width: 3),
                  Flexible(child: Text(timeAgo(s.updatedAt), style: t.labelSmall?.copyWith(color: AppColors.mediumGrey), overflow: TextOverflow.ellipsis)),
                ]),
                const SizedBox(height: 6),
                StatusChip(status, color: status == 'Unpublished changes' ? AppColors.warning : null),
              ]),
            ),
            if (!selecting)
              IconButton(icon: const Icon(Icons.more_vert_rounded), onPressed: () => ScreenActions.showMenu(context, ref, s), tooltip: 'Actions'),
          ]),
        ),
        if (!selecting && !compact)
          Padding(
            padding: const EdgeInsets.fromLTRB(2, 0, 2, 6),
            // Each action gets an equal share of the card width, so the row never overflows on narrow phones.
            child: Row(children: [
              _mini(Icons.edit_outlined, 'Edit', () => context.push('/editor/${s.screenId}')),
              _mini(Icons.copy_rounded, 'Duplicate', () => ScreenActions.duplicate(context, ref, s)),
              _mini(Icons.play_circle_outline, 'Preview', () => context.push('/preview/screen/${s.screenId}')),
              _mini(Icons.cloud_upload_outlined, 'Publish', () => ScreenActions.publish(context, ref, s)),
              _mini(Icons.tv_rounded, 'Show on a TV', () => showDisplayCodeSheet(context, ref, screenId: s.screenId)),
              _mini(Icons.delete_outline, 'Delete', () => ScreenActions.delete(context, ref, s), color: AppColors.danger),
            ]),
          ),
      ]),
    );
  }

  Widget _mini(IconData i, String tip, VoidCallback f, {Color? color}) => Expanded(
        child: IconButton(
          icon: Icon(i, size: 19, color: color),
          tooltip: tip,
          onPressed: f,
          padding: EdgeInsets.zero,
          constraints: const BoxConstraints(minHeight: 40),
          style: IconButton.styleFrom(foregroundColor: AppColors.mediumGrey),
        ),
      );
}
