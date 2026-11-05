import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/constants/app_colors.dart';
import '../../core/widgets/common.dart';
import '../../providers/providers.dart';
import 'screen_card.dart';

class ScreensListScreen extends ConsumerStatefulWidget {
  const ScreensListScreen({super.key});
  @override
  ConsumerState<ScreensListScreen> createState() => _ScreensListScreenState();
}

class _ScreensListScreenState extends ConsumerState<ScreensListScreen> {
  String? status;
  int? folderId;
  bool favorites = false;
  String q = '';
  final searchCtrl = TextEditingController();
  final Set<int> selected = {};
  bool selecting = false;

  ScreenFilter get filter => ScreenFilter(q: q.isEmpty ? null : q, status: status, folderId: folderId, favorites: favorites);

  @override
  Widget build(BuildContext context) {
    final screens = ref.watch(screensProvider(filter));
    final folders = ref.watch(screenFoldersProvider).value ?? [];
    final width = MediaQuery.of(context).size.width;
    final cols = width > 1100 ? 4 : (width > 760 ? 3 : 2);
    return Scaffold(
      appBar: AppBar(
        title: Text(selecting ? '${selected.length} selected' : 'My Screens'),
        leading: selecting ? IconButton(icon: const Icon(Icons.close), onPressed: () => setState(() { selecting = false; selected.clear(); })) : null,
        actions: [
          if (!selecting) ...[
            IconButton(tooltip: 'Folders', icon: const Icon(Icons.create_new_folder_outlined), onPressed: _manageFolders),
            IconButton(tooltip: 'Select screens for a carousel', icon: const Icon(Icons.checklist_rounded), onPressed: () => setState(() => selecting = true)),
          ],
        ],
      ),
      floatingActionButton: selecting
          ? FloatingActionButton.extended(
              onPressed: selected.isEmpty ? null : () => context.push('/playlists/new?screens=${selected.join(',')}'),
              backgroundColor: selected.isEmpty ? AppColors.mediumGrey : AppColors.teal,
              icon: const Icon(Icons.view_carousel_rounded),
              label: Text('Create Screen Carousel (${selected.length})'),
            )
          : FloatingActionButton.extended(onPressed: () => context.push('/screens/new'), icon: const Icon(Icons.add_rounded), label: const Text('New screen')),
      body: Column(children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 4, 16, 8),
          child: TextField(
            controller: searchCtrl,
            decoration: InputDecoration(
              hintText: 'Search screens',
              prefixIcon: const Icon(Icons.search_rounded),
              suffixIcon: q.isEmpty ? null : IconButton(icon: const Icon(Icons.clear), onPressed: () => setState(() { q = ''; searchCtrl.clear(); })),
              isDense: true,
            ),
            onSubmitted: (v) => setState(() => q = v.trim()),
          ),
        ),
        SizedBox(
          height: 44,
          child: ListView(scrollDirection: Axis.horizontal, padding: const EdgeInsets.symmetric(horizontal: 16), children: [
            _chip('All', status == null && !favorites, () => setState(() { status = null; favorites = false; })),
            _chip('Published', status == 'published', () => setState(() { status = 'published'; favorites = false; })),
            _chip('Drafts', status == 'draft', () => setState(() { status = 'draft'; favorites = false; })),
            _chip('★ Favourites', favorites, () => setState(() { favorites = !favorites; status = null; })),
            const VerticalDivider(width: 20, indent: 10, endIndent: 10),
            _chip('All folders', folderId == null, () => setState(() => folderId = null)),
            for (final f in folders) _chip('📁 ${f.folderName} (${f.screenCount})', folderId == f.folderId, () => setState(() => folderId = f.folderId)),
          ]),
        ),
        Expanded(
          child: RefreshIndicator(
            onRefresh: () async => ref.invalidate(screensProvider(filter)),
            child: screens.when(
              loading: () => const LoadingView(),
              error: (e, _) => ErrorView(error: e, onRetry: () => ref.invalidate(screensProvider(filter))),
              data: (list) => list.isEmpty
                  ? ListView(children: [
                      const SizedBox(height: 60),
                      EmptyState(icon: Icons.dashboard_customize_outlined, title: q.isEmpty ? 'No screens here yet' : 'No screens match "$q"', message: 'Each screen is a design you can show on your TVs.', action: 'Create screen', onAction: () => context.push('/screens/new')),
                    ])
                  : GridView.builder(
                      padding: const EdgeInsets.fromLTRB(16, 8, 16, 100),
                      gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(crossAxisCount: cols, mainAxisSpacing: 12, crossAxisSpacing: 12, mainAxisExtent: 242),
                      itemCount: list.length,
                      itemBuilder: (_, i) {
                        final s = list[i];
                        return GestureDetector(
                          onLongPress: () => setState(() { selecting = true; selected.add(s.screenId); }),
                          child: ScreenCard(
                            screen: s,
                            selected: selecting ? selected.contains(s.screenId) : null,
                            onSelect: () => setState(() => selected.contains(s.screenId) ? selected.remove(s.screenId) : selected.add(s.screenId)),
                          ),
                        );
                      },
                    ),
            ),
          ),
        ),
      ]),
    );
  }

  Widget _chip(String label, bool on, VoidCallback tap) => Padding(
        padding: const EdgeInsets.only(right: 8),
        child: FilterChip(label: Text(label), selected: on, onSelected: (_) => tap(), showCheckmark: false),
      );

  Future<void> _manageFolders() async {
    final repo = ref.read(screensRepoProvider);
    await showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      builder: (c) => Consumer(builder: (c, ref, _) {
        final folders = ref.watch(screenFoldersProvider);
        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(8, 0, 8, 12),
            child: Column(mainAxisSize: MainAxisSize.min, children: [
              ListTile(
                title: const Text('Screen folders', style: TextStyle(fontWeight: FontWeight.w700)),
                trailing: FilledButton.icon(
                  style: FilledButton.styleFrom(minimumSize: const Size(0, 40)),
                  onPressed: () async {
                    final n = await promptText(c, title: 'New folder', label: 'Folder name', action: 'Create');
                    if (n != null && n.isNotEmpty) {
                      await repo.folderAction('create', name: n);
                      ref.invalidate(screenFoldersProvider);
                    }
                  },
                  icon: const Icon(Icons.add),
                  label: const Text('New'),
                ),
              ),
              ...?folders.value?.map((f) => ListTile(
                    leading: const Icon(Icons.folder_outlined),
                    title: Text(f.folderName),
                    subtitle: Text('${f.screenCount} screens'),
                    trailing: Row(mainAxisSize: MainAxisSize.min, children: [
                      IconButton(icon: const Icon(Icons.edit_outlined), onPressed: () async {
                        final n = await promptText(c, title: 'Rename folder', initial: f.folderName);
                        if (n != null && n.isNotEmpty) {
                          await repo.folderAction('rename', folderId: f.folderId, name: n);
                          ref.invalidate(screenFoldersProvider);
                        }
                      }),
                      IconButton(icon: const Icon(Icons.delete_outline), onPressed: () async {
                        if (await confirmDialog(c, title: 'Delete folder?', message: 'Screens inside are kept.', destructive: true, confirm: 'Delete')) {
                          await repo.folderAction('delete', folderId: f.folderId);
                          ref.invalidate(screenFoldersProvider);
                          ref.invalidate(screensProvider);
                        }
                      }),
                    ]),
                  )),
            ]),
          ),
        );
      }),
    );
  }
}
