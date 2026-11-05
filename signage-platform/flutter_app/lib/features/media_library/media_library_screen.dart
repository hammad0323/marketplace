import 'package:cached_network_image/cached_network_image.dart';
import 'package:dio/dio.dart' show MultipartFile;
import 'package:file_picker/file_picker.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/constants/app_colors.dart';
import '../../core/utils/formatters.dart';
import '../../core/utils/json.dart';
import '../../core/widgets/common.dart';
import '../../models/media.dart';
import '../../providers/providers.dart';
import '../../repositories/repositories.dart';

class MediaQueryArgs {
  const MediaQueryArgs({this.type, this.folderId, this.q});
  final String? type, q;
  final int? folderId;
  @override
  bool operator ==(Object other) => other is MediaQueryArgs && other.type == type && other.folderId == folderId && other.q == q;
  @override
  int get hashCode => Object.hash(type, folderId, q);
}

final mediaListProvider = FutureProvider.autoDispose.family<Paged<MediaItem>, MediaQueryArgs>(
    (ref, a) => ref.watch(mediaRepoProvider).list(type: a.type, folderId: a.folderId, q: a.q));

/// Picks files and uploads them with progress. Returns uploaded items.
Future<List<MediaItem>> pickAndUpload(BuildContext context, WidgetRef ref, {String kind = 'any', int? folderId, bool asLogo = false}) async {
  final exts = switch (kind) {
    'image' => ['jpg', 'jpeg', 'png', 'webp', 'gif'],
    'video' => ['mp4'],
    'document' => ['pdf'],
    _ => ['jpg', 'jpeg', 'png', 'webp', 'gif', 'mp4', 'pdf'],
  };
  List<PlatformFile> files;
  try {
    files = await FilePicker.pickFiles(type: FileType.custom, allowedExtensions: exts);
  } catch (e) {
    if (context.mounted) showToast(context, 'Could not open the file picker', error: true);
    return [];
  }
  if (files.isEmpty || !context.mounted) return [];
  final progress = ValueNotifier<(int, double)>((0, 0));
  final out = <MediaItem>[];
  final errors = <String>[];
  showDialog(
    context: context,
    barrierDismissible: false,
    builder: (_) => AlertDialog(
      title: const Text('Uploading'),
      content: ValueListenableBuilder<(int, double)>(
        valueListenable: progress,
        builder: (_, v, _) => Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text('File ${v.$1 + 1} of ${files.length}: ${files[v.$1.clamp(0, files.length - 1)].name}', maxLines: 1, overflow: TextOverflow.ellipsis),
          const SizedBox(height: 12),
          LinearProgressIndicator(value: v.$2 == 0 ? null : v.$2),
        ]),
      ),
    ),
  );
  final repo = ref.read(mediaRepoProvider);
  for (var i = 0; i < files.length; i++) {
    final f = files[i];
    progress.value = (i, 0);
    try {
      final mf = (!kIsWeb && f.path != null) ? await MultipartFile.fromFile(f.path!, filename: f.name) : MultipartFile.fromBytes(await f.readAsBytes(), filename: f.name);
      out.add(await repo.upload(mf, folderId: folderId, asLogo: asLogo, onProgress: (p) => progress.value = (i, p)));
    } catch (e) {
      errors.add('${f.name}: $e');
    }
  }
  if (context.mounted) {
    Navigator.of(context, rootNavigator: true).pop();
    if (errors.isNotEmpty) {
      showToast(context, errors.join('\n'), error: true);
    } else {
      showToast(context, out.length == 1 ? 'Uploaded' : '${out.length} files uploaded');
    }
  }
  ref.invalidate(mediaListProvider);
  ref.read(sessionProvider.notifier).refresh();
  return out;
}

/// Bottom-sheet picker used by the editor. Returns the chosen media.
Future<MediaItem?> showMediaPicker(BuildContext context, {String? type}) => showModalBottomSheet<MediaItem>(
      context: context,
      isScrollControlled: true,
      builder: (_) => FractionallySizedBox(heightFactor: 0.88, child: MediaBrowser(pickType: type, picking: true)),
    );

class MediaLibraryScreen extends StatelessWidget {
  const MediaLibraryScreen({super.key});
  @override
  Widget build(BuildContext context) => Scaffold(appBar: AppBar(title: const Text('Media library')), body: const MediaBrowser());
}

/// Media grid with folders, filters, search, upload and file actions.
class MediaBrowser extends ConsumerStatefulWidget {
  const MediaBrowser({super.key, this.pickType, this.picking = false});
  final String? pickType;
  final bool picking;
  @override
  ConsumerState<MediaBrowser> createState() => _MediaBrowserState();
}

class _MediaBrowserState extends ConsumerState<MediaBrowser> {
  String? type;
  int? folderId;
  String q = '';

  @override
  void initState() {
    super.initState();
    type = widget.pickType;
  }

  MediaQueryArgs get args => MediaQueryArgs(type: type, folderId: folderId, q: q.isEmpty ? null : q);

  @override
  Widget build(BuildContext context) {
    final data = ref.watch(mediaListProvider(args));
    final width = MediaQuery.of(context).size.width;
    final folders = asList(data.value?.extra['folders']).map((e) => MediaFolder.fromJson(asMap(e))).toList();
    final storage = asMap(data.value?.extra['storage']);
    return Column(children: [
      Padding(
        padding: const EdgeInsets.fromLTRB(16, 4, 16, 8),
        child: Row(children: [
          Expanded(
            child: TextField(
              decoration: const InputDecoration(hintText: 'Search media', prefixIcon: Icon(Icons.search_rounded), isDense: true),
              onSubmitted: (v) => setState(() => q = v.trim()),
            ),
          ),
          const SizedBox(width: 10),
          FilledButton.icon(
            style: FilledButton.styleFrom(minimumSize: const Size(0, 48)),
            onPressed: () async {
              final up = await pickAndUpload(context, ref, kind: widget.pickType ?? 'any', folderId: folderId);
              if (widget.picking && up.length == 1 && context.mounted) Navigator.pop(context, up.first);
            },
            icon: const Icon(Icons.upload_rounded),
            label: const Text('Upload'),
          ),
        ]),
      ),
      if (storage.isNotEmpty && !widget.picking)
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
          child: UsageBar(label: 'Storage', used: asInt(storage['used_bytes']) / 1048576, limit: asInt(storage['limit_mb']), format: (n) => formatMb(n.round())),
        ),
      SizedBox(
        height: 44,
        child: ListView(scrollDirection: Axis.horizontal, padding: const EdgeInsets.symmetric(horizontal: 16), children: [
          if (widget.pickType == null) ...[
            for (final t in const [(null, 'All'), ('image', 'Images'), ('video', 'Videos'), ('document', 'PDFs'), ('logo', 'Logos')])
              Padding(padding: const EdgeInsets.only(right: 8), child: FilterChip(showCheckmark: false, label: Text(t.$2), selected: type == t.$1, onSelected: (_) => setState(() => type = t.$1))),
            const VerticalDivider(width: 16, indent: 10, endIndent: 10),
          ],
          Padding(padding: const EdgeInsets.only(right: 8), child: FilterChip(showCheckmark: false, avatar: const Icon(Icons.folder_open_outlined, size: 16), label: const Text('All folders'), selected: folderId == null, onSelected: (_) => setState(() => folderId = null))),
          for (final f in folders)
            Padding(
              padding: const EdgeInsets.only(right: 8),
              child: GestureDetector(
                onLongPress: () => _folderMenu(f),
                child: FilterChip(showCheckmark: false, avatar: const Icon(Icons.folder_outlined, size: 16), label: Text('${f.folderName} (${f.itemCount})'), selected: folderId == f.folderId, onSelected: (_) => setState(() => folderId = f.folderId)),
              ),
            ),
          ActionChip(avatar: const Icon(Icons.create_new_folder_outlined, size: 16), label: const Text('New folder'), onPressed: () async {
            final n = await promptText(context, title: 'New folder', label: 'Folder name', action: 'Create');
            if (n != null && n.isNotEmpty && context.mounted) {
              await runWithProgress(context, () => ref.read(mediaRepoProvider).createFolder(n));
              ref.invalidate(mediaListProvider);
            }
          }),
        ]),
      ),
      Expanded(
        child: data.when(
          loading: () => const LoadingView(),
          error: (e, _) => ErrorView(error: e, onRetry: () => ref.invalidate(mediaListProvider(args))),
          data: (page) => page.items.isEmpty
              ? const EmptyState(icon: Icons.photo_library_outlined, title: 'No media yet', message: 'Upload images, MP4 videos or PDFs to reuse them across screens.')
              : RefreshIndicator(
                  onRefresh: () async => ref.invalidate(mediaListProvider(args)),
                  child: GridView.builder(
                    padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
                    gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(crossAxisCount: width > 900 ? 5 : (width > 600 ? 4 : 3), mainAxisSpacing: 10, crossAxisSpacing: 10, childAspectRatio: 0.82),
                    itemCount: page.items.length,
                    itemBuilder: (_, i) => _MediaTile(
                      m: page.items[i],
                      onTap: () => widget.picking ? Navigator.pop(context, page.items[i]) : _details(page.items[i], folders),
                    ),
                  ),
                ),
        ),
      ),
    ]);
  }

  Future<void> _folderMenu(MediaFolder f) async {
    final a = await showModalBottomSheet<String>(
      context: context,
      builder: (c) => SafeArea(
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          ListTile(leading: const Icon(Icons.edit_outlined), title: const Text('Rename folder'), onTap: () => Navigator.pop(c, 'rename')),
          ListTile(leading: const Icon(Icons.delete_outline), title: const Text('Delete folder (keeps files)'), onTap: () => Navigator.pop(c, 'delete')),
        ]),
      ),
    );
    if (!mounted || a == null) return;
    final repo = ref.read(mediaRepoProvider);
    if (a == 'rename') {
      final n = await promptText(context, title: 'Rename folder', initial: f.folderName);
      if (n != null && n.isNotEmpty && mounted) await runWithProgress(context, () => repo.renameFolder(f.folderId, n));
    } else {
      await runWithProgress(context, () => repo.deleteFolder(f.folderId));
      setState(() => folderId = null);
    }
    ref.invalidate(mediaListProvider);
  }

  Future<void> _details(MediaItem m, List<MediaFolder> folders) async {
    final repo = ref.read(mediaRepoProvider);
    await showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      builder: (c) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 0, 20, 16),
          child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            if (m.isImage && m.url != null)
              ClipRRect(borderRadius: BorderRadius.circular(16), child: ConstrainedBox(constraints: const BoxConstraints(maxHeight: 260), child: CachedNetworkImage(imageUrl: m.url!, fit: BoxFit.contain))),
            const SizedBox(height: 12),
            Text(m.originalName, style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 4),
            Text([
              m.fileType.toUpperCase(), formatBytes(m.fileSize),
              if (m.width != null) '${m.width}×${m.height}',
              if (m.createdAt != null) formatDateTime(m.createdAt),
              if (m.usageCount > 0) 'used in ${m.usageCount} place(s)',
            ].join(' · '), style: const TextStyle(color: AppColors.mediumGrey)),
            const SizedBox(height: 12),
            if (m.isGlobal)
              const Text('Shared library file (read-only)', style: TextStyle(color: AppColors.mediumGrey))
            else
              Wrap(spacing: 8, runSpacing: 8, children: [
                OutlinedButton.icon(icon: const Icon(Icons.edit_outlined), label: const Text('Rename'), onPressed: () async {
                  final n = await promptText(c, title: 'Rename file', initial: m.originalName);
                  if (n != null && n.isNotEmpty && c.mounted) {
                    await runWithProgress(c, () => repo.rename(m.mediaId, n));
                    if (c.mounted) Navigator.pop(c);
                    ref.invalidate(mediaListProvider);
                  }
                }),
                OutlinedButton.icon(icon: const Icon(Icons.drive_file_move_outlined), label: const Text('Move'), onPressed: () async {
                  final to = await showModalBottomSheet<int>(context: c, builder: (cc) => SafeArea(child: ListView(shrinkWrap: true, children: [
                        ListTile(title: const Text('No folder'), onTap: () => Navigator.pop(cc, 0)),
                        for (final f in folders) ListTile(leading: const Icon(Icons.folder_outlined), title: Text(f.folderName), onTap: () => Navigator.pop(cc, f.folderId)),
                      ])));
                  if (to != null && c.mounted) {
                    await runWithProgress(c, () => repo.move([m.mediaId], to == 0 ? null : to), success: 'Moved');
                    if (c.mounted) Navigator.pop(c);
                    ref.invalidate(mediaListProvider);
                  }
                }),
                OutlinedButton.icon(
                  style: OutlinedButton.styleFrom(foregroundColor: AppColors.danger),
                  icon: const Icon(Icons.delete_outline),
                  label: const Text('Delete'),
                  onPressed: () async {
                    try {
                      await repo.delete([m.mediaId]);
                    } catch (e) {
                      if (!c.mounted) return;
                      final force = await confirmDialog(c, title: 'File is in use', message: '$e', confirm: 'Delete anyway', destructive: true);
                      if (!force) return;
                      await repo.delete([m.mediaId], force: true);
                    }
                    if (c.mounted) Navigator.pop(c);
                    if (mounted) showToast(context, 'File deleted');
                    ref.invalidate(mediaListProvider);
                    ref.read(sessionProvider.notifier).refresh();
                  },
                ),
              ]),
          ]),
        ),
      ),
    );
  }
}

class _MediaTile extends StatelessWidget {
  const _MediaTile({required this.m, required this.onTap});
  final MediaItem m;
  final VoidCallback onTap;
  @override
  Widget build(BuildContext context) {
    final thumb = m.thumbnailUrl;
    return AppCard(
      padding: const EdgeInsets.all(6),
      onTap: onTap,
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Expanded(
          child: ClipRRect(
            borderRadius: BorderRadius.circular(12),
            child: Stack(fit: StackFit.expand, children: [
              Container(color: AppColors.charcoal),
              if (thumb != null && m.isImage) CachedNetworkImage(imageUrl: thumb, fit: BoxFit.cover, errorWidget: (_, _, _) => const Icon(Icons.broken_image_outlined, color: Colors.white54)),
              if (m.isVideo) const Center(child: Icon(Icons.play_circle_fill_rounded, color: Colors.white70, size: 36)),
              if (m.fileType == 'document') const Center(child: Icon(Icons.picture_as_pdf_rounded, color: Colors.white70, size: 36)),
              if (m.isGlobal) const Positioned(top: 4, right: 4, child: Icon(Icons.public, color: Colors.white, size: 16)),
            ]),
          ),
        ),
        const SizedBox(height: 6),
        Text(m.originalName, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
        Text(formatBytes(m.fileSize), style: const TextStyle(fontSize: 11, color: AppColors.mediumGrey)),
      ]),
    );
  }
}
