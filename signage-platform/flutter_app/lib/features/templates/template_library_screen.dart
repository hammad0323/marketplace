import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/constants/app_colors.dart';
import '../../core/network/api_client.dart';
import '../../core/utils/json.dart';
import '../../core/widgets/common.dart';
import '../../models/template.dart';
import '../../providers/providers.dart';
import '../widgets_library/layout_view.dart';
import '../widgets_library/render_context.dart';

const _categoryIcons = {
  'business': Icons.business_center_outlined, 'restaurant': Icons.restaurant_outlined, 'shopping_bag': Icons.shopping_bag_outlined,
  'local_hospital': Icons.local_hospital_outlined, 'school': Icons.school_outlined, 'hotel': Icons.hotel_outlined,
  'celebration': Icons.celebration_outlined, 'groups': Icons.groups_outlined, 'spa': Icons.spa_outlined,
  'fitness_center': Icons.fitness_center_outlined, 'real_estate_agent': Icons.real_estate_agent_outlined,
  'auto_awesome': Icons.auto_awesome_outlined, 'newspaper': Icons.newspaper_outlined, 'menu_book': Icons.menu_book_outlined,
};

/// Step 1 of "Create New Screen": template library or blank canvas.
class TemplateLibraryScreen extends ConsumerStatefulWidget {
  const TemplateLibraryScreen({super.key, this.startBlank = false});
  final bool startBlank;
  @override
  ConsumerState<TemplateLibraryScreen> createState() => _TemplateLibraryScreenState();
}

class _TemplateLibraryScreenState extends ConsumerState<TemplateLibraryScreen> {
  int? categoryId;
  String? orientation;
  bool favorites = false;
  String q = '';

  @override
  void initState() {
    super.initState();
    if (widget.startBlank) WidgetsBinding.instance.addPostFrameCallback((_) => _blank());
  }

  @override
  Widget build(BuildContext context) {
    final cats = ref.watch(templateCategoriesProvider).value ?? [];
    final tpls = ref.watch(templatesProvider);
    final width = MediaQuery.of(context).size.width;
    return Scaffold(
      appBar: AppBar(title: const Text('Create new screen')),
      body: CustomScrollView(slivers: [
        SliverToBoxAdapter(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 4, 16, 8),
            child: AppCard(
              onTap: _blank,
              color: Theme.of(context).colorScheme.primaryContainer,
              child: Row(children: [
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(color: AppColors.teal, borderRadius: BorderRadius.circular(14)),
                  child: const Icon(Icons.add_box_outlined, color: Colors.white),
                ),
                const SizedBox(width: 14),
                const Expanded(
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text('Start with a blank screen', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
                    Text('Landscape, portrait, square or custom size', style: TextStyle(color: AppColors.mediumGrey)),
                  ]),
                ),
                const Icon(Icons.chevron_right_rounded),
              ]),
            ),
          ),
        ),
        SliverToBoxAdapter(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 4, 16, 4),
            child: TextField(
              decoration: const InputDecoration(hintText: 'Search templates', prefixIcon: Icon(Icons.search_rounded), isDense: true),
              onChanged: (v) => setState(() => q = v.trim().toLowerCase()),
            ),
          ),
        ),
        SliverToBoxAdapter(
          child: SizedBox(
            height: 50,
            child: ListView(scrollDirection: Axis.horizontal, padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6), children: [
              _chip(const Text('All'), categoryId == null && !favorites, () => setState(() { categoryId = null; favorites = false; })),
              _chip(const Text('★ Favourites'), favorites, () => setState(() => favorites = !favorites)),
              for (final c in cats)
                _chip(Row(mainAxisSize: MainAxisSize.min, children: [Icon(_categoryIcons[c.icon] ?? Icons.category_outlined, size: 16), const SizedBox(width: 6), Text(c.categoryName)]),
                    categoryId == c.categoryId, () => setState(() => categoryId = categoryId == c.categoryId ? null : c.categoryId)),
            ]),
          ),
        ),
        SliverToBoxAdapter(
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: SegmentedButton<String?>(
              showSelectedIcon: false,
              segments: const [
                ButtonSegment(value: null, label: Text('Any')),
                ButtonSegment(value: 'landscape', label: Text('Landscape')),
                ButtonSegment(value: 'portrait', label: Text('Portrait')),
              ],
              selected: {orientation},
              onSelectionChanged: (s) => setState(() => orientation = s.first),
            ),
          ),
        ),
        ...tpls.when(
          loading: () => [const SliverFillRemaining(child: LoadingView())],
          error: (e, _) => [SliverFillRemaining(child: ErrorView(error: e, onRetry: () => ref.invalidate(templatesProvider)))],
          data: (all) {
            final list = all.where((t) =>
                (categoryId == null || t.categoryId == categoryId) &&
                (!favorites || t.isFavorite) &&
                (orientation == null || t.orientation == orientation) &&
                (q.isEmpty || t.templateName.toLowerCase().contains(q) || (t.tags ?? '').contains(q))).toList();
            if (list.isEmpty) return [const SliverFillRemaining(child: EmptyState(icon: Icons.search_off_rounded, title: 'No templates match'))];
            return [
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(16, 14, 16, 32),
                sliver: SliverGrid(
                  gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                    crossAxisCount: width > 1000 ? 4 : (width > 650 ? 3 : 2), mainAxisSpacing: 12, crossAxisSpacing: 12, mainAxisExtent: 220),
                  delegate: SliverChildBuilderDelegate((_, i) => _TemplateTile(t: list[i], onUse: () => _use(list[i])), childCount: list.length),
                ),
              ),
            ];
          },
        ),
      ]),
    );
  }

  Widget _chip(Widget label, bool on, VoidCallback tap) => Padding(
        padding: const EdgeInsets.only(right: 8),
        child: FilterChip(label: label, selected: on, onSelected: (_) => tap(), showCheckmark: false),
      );

  Future<void> _use(ScreenTemplate t) async {
    if (t.isLocked) {
      showToast(context, 'This premium template is not included in your plan. Ask your administrator to upgrade.', error: true);
      return;
    }
    final name = await promptText(context, title: 'Name your screen', initial: t.templateName, action: 'Create');
    if (name == null || name.isEmpty || !mounted) return;
    await _create(() => ref.read(screensRepoProvider).create(name: name, templateId: t.templateId));
  }

  Future<void> _blank() async {
    final r = await showModalBottomSheet<(String, String, int?, int?)>(context: context, isScrollControlled: true, builder: (_) => const _BlankSheet());
    if (r == null || !mounted) return;
    await _create(() => ref.read(screensRepoProvider).create(name: r.$1, orientation: r.$2, width: r.$3, height: r.$4));
  }

  Future<void> _create(Future<ApiResponse> Function() f) async {
    final r = await runWithProgress(context, f);
    if (r == null || !mounted) return;
    invalidateContent(ref);
    if (r.raw['skipped_widgets'] is List && (r.raw['skipped_widgets'] as List).isNotEmpty) showToast(context, r.message);
    context.pushReplacement('/editor/${asInt(asMap(r.data)['screen_id'])}');
  }
}

class _TemplateTile extends ConsumerWidget {
  const _TemplateTile({required this.t, required this.onUse});
  final ScreenTemplate t;
  final VoidCallback onUse;
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return AppCard(
      padding: EdgeInsets.zero,
      onTap: () => showModalBottomSheet(
        context: context,
        isScrollControlled: true,
        builder: (c) => SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(20, 0, 20, 20),
            child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              ConstrainedBox(
                constraints: BoxConstraints(maxHeight: MediaQuery.of(context).size.height * 0.5),
                child: AspectRatio(aspectRatio: t.layout.aspect, child: LayoutThumbnail(layout: t.layout, media: NetworkMediaSource(t.media))),
              ),
              const SizedBox(height: 14),
              Text(t.templateName, style: Theme.of(context).textTheme.titleLarge),
              Text('${t.categoryName ?? ''} · ${t.orientation}', style: const TextStyle(color: AppColors.mediumGrey)),
              if (t.description != null) Padding(padding: const EdgeInsets.only(top: 8), child: Text(t.description!)),
              const SizedBox(height: 16),
              Row(children: [
                IconButton.outlined(
                  onPressed: () async {
                    Navigator.pop(c);
                    await ref.read(templatesRepoProvider).toggleFavorite(t.templateId);
                    ref.invalidate(templatesProvider);
                  },
                  icon: Icon(t.isFavorite ? Icons.star_rounded : Icons.star_outline_rounded),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: FilledButton.icon(
                    onPressed: () { Navigator.pop(c); onUse(); },
                    icon: Icon(t.isLocked ? Icons.lock_outline : Icons.check_rounded),
                    label: Text(t.isLocked ? 'Premium — upgrade required' : 'Use this template'),
                  ),
                ),
              ]),
            ]),
          ),
        ),
      ),
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Expanded(
          child: Padding(
            padding: const EdgeInsets.all(8),
            child: Stack(children: [
              Positioned.fill(child: LayoutThumbnail(layout: t.layout, media: NetworkMediaSource(t.media))),
              if (t.isPremium)
                Positioned(
                  top: 6, left: 6,
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                    decoration: BoxDecoration(color: const Color(0xFFC49A2C), borderRadius: BorderRadius.circular(8)),
                    child: Row(mainAxisSize: MainAxisSize.min, children: [
                      Icon(t.isLocked ? Icons.lock : Icons.workspace_premium, size: 12, color: Colors.white),
                      const SizedBox(width: 3),
                      const Text('PRO', style: TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w800)),
                    ]),
                  ),
                ),
              if (t.isFavorite) const Positioned(top: 6, right: 6, child: Icon(Icons.star_rounded, color: Color(0xFFFDD835), size: 20)),
            ]),
          ),
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(12, 0, 12, 10),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(t.templateName, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w700)),
            Text('${t.categoryName ?? ''} · ${t.orientation}', maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AppColors.mediumGrey, fontSize: 12)),
          ]),
        ),
      ]),
    );
  }
}

class _BlankSheet extends StatefulWidget {
  const _BlankSheet();
  @override
  State<_BlankSheet> createState() => _BlankSheetState();
}

class _BlankSheetState extends State<_BlankSheet> {
  final name = TextEditingController(text: 'Untitled screen');
  final w = TextEditingController(text: '1920');
  final h = TextEditingController(text: '1080');
  String o = 'landscape';

  @override
  Widget build(BuildContext context) {
    Widget opt(String value, String label, String ratio, double aw, double ah) {
      final on = o == value;
      return Expanded(
        child: GestureDetector(
          onTap: () => setState(() => o = value),
          child: Container(
            padding: const EdgeInsets.symmetric(vertical: 14),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: on ? AppColors.teal : Theme.of(context).colorScheme.outline, width: on ? 2 : 1),
              color: on ? Theme.of(context).colorScheme.primaryContainer : null,
            ),
            child: Column(children: [
              SizedBox(height: 46, child: Center(child: Container(width: aw, height: ah, decoration: BoxDecoration(color: on ? AppColors.teal : AppColors.mediumGrey, borderRadius: BorderRadius.circular(4))))),
              const SizedBox(height: 8),
              Text(label, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13)),
              Text(ratio, style: const TextStyle(color: AppColors.mediumGrey, fontSize: 12)),
            ]),
          ),
        ),
      );
    }

    return Padding(
      padding: EdgeInsets.fromLTRB(20, 0, 20, 20 + MediaQuery.of(context).viewInsets.bottom),
      child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Text('Blank screen', style: Theme.of(context).textTheme.titleLarge),
        const SizedBox(height: 14),
        TextField(controller: name, decoration: const InputDecoration(labelText: 'Screen name')),
        const SizedBox(height: 14),
        Row(children: [
          opt('landscape', 'Landscape', '16:9', 44, 25), const SizedBox(width: 8),
          opt('portrait', 'Portrait', '9:16', 25, 44), const SizedBox(width: 8),
          opt('square', 'Square', '1:1', 36, 36), const SizedBox(width: 8),
          opt('custom', 'Custom', 'W × H', 44, 16),
        ]),
        if (o == 'custom') ...[
          const SizedBox(height: 12),
          Row(children: [
            Expanded(child: TextField(controller: w, keyboardType: TextInputType.number, decoration: const InputDecoration(labelText: 'Width (px)'))),
            const Padding(padding: EdgeInsets.symmetric(horizontal: 8), child: Text('×')),
            Expanded(child: TextField(controller: h, keyboardType: TextInputType.number, decoration: const InputDecoration(labelText: 'Height (px)'))),
          ]),
        ],
        const SizedBox(height: 18),
        FilledButton(
          onPressed: () => Navigator.pop(context, (name.text.trim().isEmpty ? 'Untitled screen' : name.text.trim(), o, o == 'custom' ? int.tryParse(w.text) : null, o == 'custom' ? int.tryParse(h.text) : null)),
          child: const Text('Create and open editor'),
        ),
      ]),
    );
  }
}
