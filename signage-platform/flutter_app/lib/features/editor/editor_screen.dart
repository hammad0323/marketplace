import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/constants/app_colors.dart';
import '../../core/utils/formatters.dart';
import '../../core/widgets/common.dart';
import '../../models/layout.dart';
import '../../models/media.dart';
import '../../models/template.dart';
import '../../providers/providers.dart';
import '../media_library/media_library_screen.dart';
import '../widgets_library/layout_view.dart';
import '../widgets_library/render_context.dart';
import '../widgets_library/widget_animation.dart';
import '../widgets_library/widget_renderers.dart';
import 'animation_editor.dart';
import 'background_sheet.dart';
import 'config_editor.dart';
import 'editor_controller.dart';

const _widgetIcons = <String, IconData>{
  'image': Icons.image_outlined, 'image_carousel': Icons.view_carousel_outlined, 'video': Icons.smart_display_outlined,
  'logo': Icons.verified_outlined, 'background_image': Icons.wallpaper_outlined, 'pdf_viewer': Icons.picture_as_pdf_outlined,
  'heading': Icons.title_rounded, 'text': Icons.notes_rounded, 'ticker': Icons.text_rotation_none_rounded,
  'announcement': Icons.campaign_outlined, 'quote': Icons.format_quote_rounded, 'daily_message': Icons.self_improvement,
  'emergency': Icons.warning_amber_rounded, 'clock': Icons.schedule_rounded, 'world_clock': Icons.public,
  'countdown': Icons.hourglass_bottom_rounded, 'countup': Icons.timer_outlined, 'calendar': Icons.calendar_month_outlined,
  'daily_schedule': Icons.event_note_outlined, 'weather': Icons.wb_cloudy_outlined, 'rss_feed': Icons.rss_feed_rounded,
  'live_data': Icons.monitor_heart_outlined, 'battery_indicator': Icons.battery_full, 'qr_code': Icons.qr_code_2_rounded,
  'menu_board': Icons.restaurant_menu_rounded, 'price_list': Icons.sell_outlined, 'product_showcase': Icons.shopping_bag_outlined,
  'contact_info': Icons.contact_phone_outlined, 'opening_hours': Icons.storefront_outlined, 'location_info': Icons.location_on_outlined,
  'webpage': Icons.language_rounded, 'custom_html': Icons.code_rounded, 'shape': Icons.crop_square_rounded,
  'youtube': Icons.smart_display, 'social_feed': Icons.share_outlined, 'presentation': Icons.slideshow_outlined,
};
IconData widgetIcon(String k) => _widgetIcons[k] ?? Icons.extension_outlined;
const _mediaFirst = {'image': 'image', 'logo': 'image', 'background_image': 'image', 'video': 'video', 'image_carousel': 'image'};

class EditorScreen extends ConsumerStatefulWidget {
  const EditorScreen({super.key, required this.screenId});
  final int screenId;
  @override
  ConsumerState<EditorScreen> createState() => _EditorScreenState();
}

class _EditorScreenState extends ConsumerState<EditorScreen> {
  EditorController? ctrl;
  Object? loadError;
  final tc = TransformationController();
  double zoom = 1;
  bool multiSelect = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final s = await ref.read(screensRepoProvider).details(widget.screenId);
      if (!mounted) return;
      setState(() => ctrl = EditorController(repo: ref.read(screensRepoProvider), screen: s)..addListener(_onCtrl));
    } catch (e) {
      setState(() => loadError = e);
    }
  }

  void _onCtrl() {
    if (!mounted) return;
    setState(() {});
    if (ctrl!.saveState == SaveState.conflict) _conflict();
  }

  @override
  void dispose() {
    ctrl?.removeListener(_onCtrl);
    final c = ctrl;
    if (c != null && c.dirty) {
      c.save().whenComplete(c.dispose);
    } else {
      c?.dispose();
    }
    tc.dispose();
    super.dispose();
  }

  Future<void> _conflict() async {
    final c = ctrl!;
    c.saveState = SaveState.error;
    final reload = await showDialog<bool>(
      context: context,
      builder: (d) => AlertDialog(
        title: const Text('Screen changed elsewhere'),
        content: const Text('This screen was saved on another device. Load their version, or keep yours and overwrite it?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(d, true), child: const Text('Load latest')),
          FilledButton(onPressed: () => Navigator.pop(d, false), child: const Text('Keep mine')),
        ],
      ),
    );
    if (reload == true) {
      await c.reload();
    } else if (reload == false) {
      await c.save(force: true);
    }
  }

  Future<void> _back() async {
    final c = ctrl;
    if (c != null && c.dirty) {
      final ok = await c.save();
      if (!ok && mounted) {
        final leave = await confirmDialog(context, title: 'Changes not saved', message: c.lastError ?? 'Leave without saving?', confirm: 'Leave', destructive: true);
        if (!leave) return;
      }
      invalidateContent(ref);
    }
    if (mounted) Navigator.of(context).pop();
  }

  Future<void> _publish() async {
    final ok = await ctrl!.save(publish: true);
    if (!mounted) return;
    showToast(context, ok ? 'Published — connected displays will update shortly' : (ctrl!.lastError ?? 'Publish failed'), error: !ok);
    invalidateContent(ref);
  }

  void _setZoom(double z) {
    z = z.clamp(1.0, 5.0);
    tc.value = Matrix4.identity()..scaleByDouble(z, z, 1, 1);
    setState(() => zoom = z);
  }

  @override
  Widget build(BuildContext context) {
    if (loadError != null) return Scaffold(appBar: AppBar(), body: ErrorView(error: loadError!, onRetry: () { setState(() => loadError = null); _load(); }));
    final c = ctrl;
    if (c == null) return const Scaffold(body: LoadingView());
    final dark = Theme.of(context).brightness == Brightness.dark;
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) { if (!didPop) _back(); },
      child: CallbackShortcuts(
        bindings: {
          const SingleActivator(LogicalKeyboardKey.keyZ, control: true): c.undo,
          const SingleActivator(LogicalKeyboardKey.keyY, control: true): c.redo,
          const SingleActivator(LogicalKeyboardKey.keyZ, control: true, shift: true): c.redo,
          const SingleActivator(LogicalKeyboardKey.keyC, control: true): c.copySelected,
          const SingleActivator(LogicalKeyboardKey.keyV, control: true): c.paste,
          const SingleActivator(LogicalKeyboardKey.keyD, control: true): c.duplicateSelected,
          const SingleActivator(LogicalKeyboardKey.delete): c.deleteSelected,
          const SingleActivator(LogicalKeyboardKey.backspace): c.deleteSelected,
          const SingleActivator(LogicalKeyboardKey.keyS, control: true): () => c.save(),
          const SingleActivator(LogicalKeyboardKey.escape): () => c.select(null),
        },
        child: Focus(
          autofocus: true,
          child: Scaffold(
            backgroundColor: dark ? const Color(0xFF111315) : const Color(0xFFE9ECEE),
            appBar: _topBar(c),
            body: Column(children: [
              Expanded(child: Stack(children: [
                Positioned.fill(child: _canvas(c)),
                Positioned(right: 12, top: 12, child: _zoomControls()),
                if (multiSelect)
                  Positioned(left: 12, top: 12, child: Chip(avatar: const Icon(Icons.select_all, size: 18), label: const Text('Multi-select: tap widgets'), onDeleted: () => setState(() => multiSelect = false))),
              ])),
              if (c.selection.isNotEmpty) _selectionBar(c),
              _bottomBar(c),
            ]),
          ),
        ),
      ),
    );
  }

  // ------------------------------------------------------------- top bar
  PreferredSizeWidget _topBar(EditorController c) {
    final state = switch (c.saveState) {
      SaveState.saved => ('Saved', AppColors.success, Icons.cloud_done_outlined),
      SaveState.dirty => ('Unsaved', AppColors.warning, Icons.edit_note_rounded),
      SaveState.saving => ('Saving…', AppColors.mediumGrey, Icons.cloud_sync_outlined),
      SaveState.error || SaveState.conflict => ('Not saved', AppColors.danger, Icons.cloud_off_outlined),
    };
    final narrow = MediaQuery.of(context).size.width < 600;
    Future<void> animateAll() async {
      final r = await showScreenAnimationSheet(context);
      if (r == null) return;
      c.mutate(() => applyScreenAnimation(c.layout.widgets, r.$1, r.$2));
      if (mounted) showToast(context, r.$1 == 'none' ? 'Animations removed' : 'Animations applied — tap Preview to watch');
    }
    return AppBar(
      leading: IconButton(icon: const Icon(Icons.arrow_back_rounded), onPressed: _back, tooltip: 'Back'),
      titleSpacing: 0,
      title: InkWell(
        onTap: () async {
          final n = await promptText(context, title: 'Screen name', initial: c.name);
          if (n != null && n.isNotEmpty) c.mutate(() => c.name = n, record: false);
        },
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(c.name, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700)),
          Row(children: [
            Icon(state.$3, size: 13, color: state.$2),
            const SizedBox(width: 4),
            Flexible(child: Text(state.$1, maxLines: 1, overflow: TextOverflow.ellipsis, softWrap: false, style: TextStyle(fontSize: 11.5, color: state.$2, fontWeight: FontWeight.w600))),
          ]),
        ]),
      ),
      actions: [
        IconButton(onPressed: c.canUndo ? c.undo : null, icon: const Icon(Icons.undo_rounded), tooltip: 'Undo'),
        if (!narrow) ...[
          IconButton(onPressed: c.canRedo ? c.redo : null, icon: const Icon(Icons.redo_rounded), tooltip: 'Redo'),
          IconButton(onPressed: animateAll, icon: const Icon(Icons.auto_awesome_outlined), tooltip: 'Animate whole screen'),
        ],
        IconButton(onPressed: () => _preview(c), icon: const Icon(Icons.play_circle_outline_rounded), tooltip: 'Preview'),
        if (!narrow) IconButton(onPressed: () => c.save(), icon: const Icon(Icons.save_outlined), tooltip: 'Save draft'),
        if (narrow)
          PopupMenuButton<String>(
            tooltip: 'More',
            icon: const Icon(Icons.more_vert_rounded),
            onSelected: (v) => switch (v) {
              'redo' => c.redo(),
              'animate' => animateAll(),
              'save' => c.save(),
              _ => null,
            },
            itemBuilder: (_) => [
              PopupMenuItem(value: 'redo', enabled: c.canRedo, child: const ListTile(leading: Icon(Icons.redo_rounded), title: Text('Redo'), dense: true, contentPadding: EdgeInsets.zero)),
              const PopupMenuItem(value: 'animate', child: ListTile(leading: Icon(Icons.auto_awesome_outlined), title: Text('Animate whole screen'), dense: true, contentPadding: EdgeInsets.zero)),
              const PopupMenuItem(value: 'save', child: ListTile(leading: Icon(Icons.save_outlined), title: Text('Save draft'), dense: true, contentPadding: EdgeInsets.zero)),
            ],
          ),
        Padding(
          padding: const EdgeInsets.only(right: 8),
          child: FilledButton(style: FilledButton.styleFrom(minimumSize: const Size(0, 38), padding: const EdgeInsets.symmetric(horizontal: 14)), onPressed: _publish, child: const Text('Publish')),
        ),
      ],
    );
  }

  Widget _zoomControls() => Material(
        color: Theme.of(context).colorScheme.surface,
        elevation: 2,
        borderRadius: BorderRadius.circular(14),
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          IconButton(icon: const Icon(Icons.add), onPressed: () => _setZoom(zoom + 0.5), tooltip: 'Zoom in'),
          Text('${(zoom * 100).round()}%', style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w600)),
          IconButton(icon: const Icon(Icons.remove), onPressed: () => _setZoom(zoom - 0.5), tooltip: 'Zoom out'),
          IconButton(icon: const Icon(Icons.fit_screen_outlined), onPressed: () => _setZoom(1), tooltip: 'Fit'),
        ]),
      );

  // -------------------------------------------------------------- canvas
  Widget _canvas(EditorController c) {
    final l = c.layout;
    final w = l.width.toDouble(), h = l.height.toDouble();
    return LayoutBuilder(builder: (context, box) {
      final fit = math.min((box.maxWidth - 32) / w, (box.maxHeight - 32) / h);
      final visual = fit * zoom;
      final handle = 22 / visual;
      final stroke = 2 / visual;
      final canvasSize = Size(w, h);
      return InteractiveViewer(
        transformationController: tc,
        minScale: 1,
        maxScale: 5,
        panEnabled: zoom > 1.01 && c.selection.isEmpty,
        scaleEnabled: true,
        boundaryMargin: const EdgeInsets.all(240),
        onInteractionEnd: (_) => setState(() => zoom = tc.value.getMaxScaleOnAxis()),
        child: GestureDetector(
          behavior: HitTestBehavior.opaque,
          onTap: () => c.select(null),
          child: SizedBox(
            width: box.maxWidth,
            height: box.maxHeight,
            child: Center(
              child: Container(
                width: w * fit,
                height: h * fit,
                decoration: const BoxDecoration(boxShadow: [BoxShadow(color: Color(0x33000000), blurRadius: 24, offset: Offset(0, 8))]),
                child: FittedBox(
                  child: SizedBox(
                    width: w,
                    height: h,
                    child: RenderScope(
                      mode: RenderMode.editor,
                      media: NetworkMediaSource(c.media),
                      child: Builder(builder: (context) => Stack(clipBehavior: Clip.none, children: [
                        Positioned.fill(child: ClipRect(child: BackgroundView(bg: l.background))),
                        for (final item in l.sorted) _widgetLayer(context, c, item, canvasSize),
                        for (final g in c.guides)
                          g.vertical
                              ? Positioned(left: g.pos * w - stroke / 2, top: -h, width: stroke, height: h * 3, child: const ColoredBox(color: Color(0xFFFF2D87)))
                              : Positioned(top: g.pos * h - stroke / 2, left: -w, height: stroke, width: w * 3, child: const ColoredBox(color: Color(0xFFFF2D87))),
                        for (final item in c.selectedItems) ..._selectionOverlay(c, item, canvasSize, handle, stroke),
                      ])),
                    ),
                  ),
                ),
              ),
            ),
          ),
        ),
      );
    });
  }

  Widget _widgetLayer(BuildContext context, EditorController c, WidgetItem item, Size canvas) {
    final selected = c.selection.contains(item.widgetUid);
    final canDrag = !item.isLocked && (selected || zoom <= 1.01) && !multiSelect;
    return positionedWidget(
      context,
      item,
      canvas,
      child: GestureDetector(
        behavior: HitTestBehavior.opaque,
        onTap: () => c.select(item.widgetUid, additive: multiSelect),
        onDoubleTap: () { c.select(item.widgetUid); _openSettings(c); },
        onLongPress: () { setState(() => multiSelect = true); c.select(item.widgetUid, additive: true); HapticFeedback.selectionClick(); },
        onPanStart: canDrag ? (_) { if (!selected) c.select(item.widgetUid); c.checkpoint(); } : null,
        onPanUpdate: canDrag ? (d) => c.moveSelection(d.delta.dx / canvas.width, d.delta.dy / canvas.height, item.widgetUid) : null,
        onPanEnd: canDrag ? (_) => c.endGesture() : null,
        child: IgnorePointer(
          child: Opacity(opacity: item.isVisible ? 1 : 0.3, child: buildWidgetContent(context, item)),
        ),
      ),
    );
  }

  List<Widget> _selectionOverlay(EditorController c, WidgetItem item, Size canvas, double handle, double stroke) {
    final left = item.positionX * canvas.width, top = item.positionY * canvas.height;
    final width = item.width * canvas.width, height = item.height * canvas.height;
    final color = item.isLocked ? AppColors.mediumGrey : AppColors.teal;
    final out = <Widget>[
      Positioned(
        left: left, top: top, width: width, height: height,
        child: IgnorePointer(child: DecoratedBox(decoration: BoxDecoration(border: Border.all(color: color, width: stroke)))),
      ),
    ];
    if (item.isLocked) {
      out.add(Positioned(left: left + width - handle * 1.4, top: top - handle * 1.4, width: handle * 1.2, height: handle * 1.2,
          child: IgnorePointer(child: Container(decoration: const BoxDecoration(color: AppColors.mediumGrey, shape: BoxShape.circle), child: FittedBox(child: Icon(Icons.lock, color: Colors.white))))));
      return out;
    }
    if (c.selection.length != 1) return out;
    for (final hx in [-1, 0, 1]) {
      for (final hy in [-1, 0, 1]) {
        if (hx == 0 && hy == 0) continue;
        final cx = left + width * (hx + 1) / 2, cy = top + height * (hy + 1) / 2;
        final corner = hx != 0 && hy != 0;
        out.add(Positioned(
          left: cx - handle, top: cy - handle, width: handle * 2, height: handle * 2,
          child: GestureDetector(
            behavior: HitTestBehavior.opaque,
            onPanStart: (_) => c.checkpoint(),
            onPanUpdate: (d) => c.resize(item, hx, hy, d.delta.dx / canvas.width, d.delta.dy / canvas.height,
                keepAspect: corner && (item.widgetType == 'image' || item.widgetType == 'logo' || item.widgetType == 'qr_code' || HardwareKeyboard.instance.isShiftPressed)),
            onPanEnd: (_) => c.endGesture(),
            child: Center(
              child: Container(
                width: corner ? handle * 0.95 : (hx == 0 ? handle * 1.5 : handle * 0.6),
                height: corner ? handle * 0.95 : (hy == 0 ? handle * 1.5 : handle * 0.6),
                decoration: BoxDecoration(
                  color: Colors.white,
                  border: Border.all(color: AppColors.teal, width: stroke),
                  borderRadius: BorderRadius.circular(corner ? handle : handle * 0.3),
                ),
              ),
            ),
          ),
        ));
      }
    }
    return out;
  }

  // ---------------------------------------------------------- toolbars
  Widget _selectionBar(EditorController c) {
    final single = c.selected;
    final locked = c.selectedItems.every((w) => w.isLocked);
    Widget b(IconData i, String label, VoidCallback? f, {Color? color}) => Padding(
          padding: const EdgeInsets.symmetric(horizontal: 2),
          child: TextButton(
            onPressed: f,
            style: TextButton.styleFrom(foregroundColor: color, padding: const EdgeInsets.symmetric(horizontal: 10), minimumSize: const Size(56, 52)),
            child: Column(mainAxisSize: MainAxisSize.min, children: [Icon(i, size: 21), const SizedBox(height: 2), Text(label, style: const TextStyle(fontSize: 11))]),
          ),
        );
    return Material(
      color: Theme.of(context).colorScheme.surface,
      child: SafeArea(
        top: false,
        bottom: false,
        child: SizedBox(
          height: 60,
          child: ListView(scrollDirection: Axis.horizontal, padding: const EdgeInsets.symmetric(horizontal: 6), children: [
            if (single != null) b(Icons.tune_rounded, 'Edit', () => _openSettings(c), color: AppColors.teal),
            b(Icons.copy_all_rounded, 'Duplicate', c.duplicateSelected),
            b(Icons.content_copy_rounded, 'Copy', c.copySelected),
            b(Icons.content_paste_rounded, 'Paste', c.canPaste ? c.paste : null),
            b(locked ? Icons.lock_open_rounded : Icons.lock_outline_rounded, locked ? 'Unlock' : 'Lock', c.toggleLock),
            b(Icons.flip_to_front_rounded, 'Front', c.bringToFront),
            b(Icons.flip_to_back_rounded, 'Back', c.sendToBack),
            b(Icons.align_horizontal_left_rounded, 'Align', () => _alignSheet(c)),
            b(Icons.select_all_rounded, multiSelect ? 'Single' : 'Multi', () => setState(() => multiSelect = !multiSelect)),
            b(Icons.delete_outline_rounded, 'Delete', locked ? null : c.deleteSelected, color: AppColors.danger),
          ]),
        ),
      ),
    );
  }

  Widget _bottomBar(EditorController c) {
    Widget tab(IconData i, String label, VoidCallback f, {bool primary = false}) => Expanded(
          child: InkWell(
            onTap: f,
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 8),
              child: Column(mainAxisSize: MainAxisSize.min, children: [
                Container(
                  padding: const EdgeInsets.all(6),
                  decoration: primary ? BoxDecoration(color: AppColors.teal, borderRadius: BorderRadius.circular(12)) : null,
                  child: Icon(i, color: primary ? Colors.white : null, size: 22),
                ),
                const SizedBox(height: 3),
                Text(label, style: const TextStyle(fontSize: 11.5, fontWeight: FontWeight.w600)),
              ]),
            ),
          ),
        );
    return Material(
      color: Theme.of(context).colorScheme.surface,
      child: SafeArea(
        top: false,
        child: DecoratedBox(
          decoration: BoxDecoration(border: Border(top: BorderSide(color: Theme.of(context).colorScheme.outline))),
          child: Row(children: [
            tab(Icons.add_rounded, 'Add widget', () => _addWidgetSheet(c), primary: true),
            tab(Icons.format_paint_outlined, 'Background', () async {
              final bg = await showBackgroundSheet(context, c.layout.background, (m) => c.media[m.mediaId] = m);
              if (bg != null) c.setBackground(bg);
            }),
            tab(Icons.layers_outlined, 'Layers', () => _layersSheet(c)),
            tab(Icons.auto_awesome_mosaic_outlined, 'Templates', () => _templatesSheet(c)),
            tab(Icons.settings_outlined, 'Settings', () => _screenSettings(c)),
          ]),
        ),
      ),
    );
  }

  // ------------------------------------------------------------ sheets
  Future<void> _addWidgetSheet(EditorController c) async {
    final data = await ref.read(widgetTypesProvider.future);
    if (!mounted) return;
    final groups = <String, List<WidgetTypeInfo>>{};
    for (final t in data.types) {
      groups.putIfAbsent(t.category, () => []).add(t);
    }
    const names = {'media': 'Media', 'text': 'Text', 'time': 'Time & date', 'data': 'Live data', 'business': 'Business', 'embed': 'Web & embeds', 'layout': 'Layout', 'system': 'Alerts'};
    final recent = data.recent.map((k) => data.types.where((t) => t.widgetKey == k).firstOrNull).whereType<WidgetTypeInfo>().toList();
    final picked = await showModalBottomSheet<WidgetTypeInfo>(
      context: context,
      isScrollControlled: true,
      builder: (s) => FractionallySizedBox(
        heightFactor: 0.8,
        child: ListView(padding: const EdgeInsets.fromLTRB(16, 0, 16, 24), children: [
          Text('Add widget', style: Theme.of(context).textTheme.titleLarge),
          if (recent.isNotEmpty) ...[const SizedBox(height: 10), const Text('Recently used', style: TextStyle(color: AppColors.mediumGrey, fontWeight: FontWeight.w600)), _typeGrid(s, recent)],
          for (final e in groups.entries) ...[
            const SizedBox(height: 14),
            Text(names[e.key] ?? e.key, style: const TextStyle(color: AppColors.mediumGrey, fontWeight: FontWeight.w600)),
            _typeGrid(s, e.value),
          ],
        ]),
      ),
    );
    if (picked == null || !mounted) return;
    if (!picked.isAllowed) {
      showToast(context, '${picked.widgetName} is not included in your plan', error: true);
      return;
    }
    final w = c.addWidget(picked);
    final mediaType = _mediaFirst[picked.widgetKey];
    if (mediaType != null) {
      final m = await showMediaPicker(context, type: mediaType);
      if (m != null) {
        c.media[m.mediaId] = m;
        c.mutate(() {
          if (picked.widgetKey == 'image_carousel' || picked.widgetKey == 'video') {
            w.configuration['items'] = [{'media_id': m.mediaId, if (mediaType == 'image') 'duration': 5}];
          } else {
            w.configuration['media_id'] = m.mediaId;
          }
          // Size images to their real aspect ratio.
          if (m.width != null && m.height != null && picked.widgetKey != 'background_image') {
            final ar = (m.width! / m.height!) / c.layout.aspect;
            w.height = (w.width / ar).clamp(0.05, 1.0);
            w.positionY = ((1 - w.height) / 2).clamp(0.0, 1.0);
          }
        }, record: false);
      }
    }
    if (mounted && mediaType == null) _openSettings(c);
  }

  Widget _typeGrid(BuildContext s, List<WidgetTypeInfo> list) => GridView.count(
        crossAxisCount: MediaQuery.of(context).size.width > 600 ? 6 : 4,
        shrinkWrap: true,
        physics: const NeverScrollableScrollPhysics(),
        childAspectRatio: 0.9,
        padding: const EdgeInsets.only(top: 8),
        children: [
          for (final t in list)
            InkWell(
              borderRadius: BorderRadius.circular(14),
              onTap: () => Navigator.pop(s, t),
              child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                Stack(children: [
                  Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(color: Theme.of(context).colorScheme.primaryContainer, borderRadius: BorderRadius.circular(14)),
                    child: Icon(widgetIcon(t.widgetKey), color: t.isAllowed ? AppColors.teal : AppColors.mediumGrey),
                  ),
                  if (!t.isAllowed) const Positioned(right: 0, top: 0, child: Icon(Icons.lock, size: 14, color: AppColors.mediumGrey)),
                  if (t.availability == 'beta') Positioned(right: 0, bottom: 0, child: Container(padding: const EdgeInsets.symmetric(horizontal: 3), color: AppColors.warning, child: const Text('BETA', style: TextStyle(fontSize: 8, color: Colors.white, fontWeight: FontWeight.w800)))),
                ]),
                const SizedBox(height: 6),
                Text(t.widgetName, textAlign: TextAlign.center, maxLines: 2, style: const TextStyle(fontSize: 11.5, fontWeight: FontWeight.w600)),
              ]),
            ),
        ],
      );

  Future<void> _openSettings(EditorController c) async {
    final w = c.selected;
    if (w == null) return;
    c.checkpoint();
    await showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      barrierColor: Colors.black26,
      builder: (s) => FractionallySizedBox(
        heightFactor: 0.58,
        child: DefaultTabController(
          length: 3,
          child: ListenableBuilder(
            listenable: c,
            builder: (s, _) => Column(children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 0, 8, 0),
                child: Row(children: [
                  Icon(widgetIcon(w.widgetType), color: AppColors.teal),
                  const SizedBox(width: 8),
                  Expanded(child: Text(w.widgetName ?? w.widgetType, style: Theme.of(context).textTheme.titleMedium, overflow: TextOverflow.ellipsis)),
                  TextButton(onPressed: () => Navigator.pop(s), child: const Text('Done')),
                ]),
              ),
              const TabBar(isScrollable: true, tabAlignment: TabAlignment.start, tabs: [Tab(text: 'Content & style'), Tab(text: 'Animation'), Tab(text: 'Position & size')]),
              Expanded(
                child: TabBarView(children: [
                  ListView(padding: const EdgeInsets.all(16), children: [
                    ConfigEditor(
                      key: ValueKey(w.widgetUid),
                      config: w.configuration,
                      widgetType: w.widgetType,
                      onMediaPicked: (m) => c.media[m.mediaId] = m,
                      onChanged: () => c.mutate(() {}, record: false),
                    ),
                  ]),
                  ListView(padding: const EdgeInsets.all(16), children: [
                    AnimationEditor(
                      key: ValueKey('anim${w.widgetUid}'),
                      config: w.configuration,
                      onChanged: () => c.mutate(() {}, record: false),
                      onPreview: () { Navigator.pop(s); _preview(c); },
                    ),
                  ]),
                  ListView(padding: const EdgeInsets.all(16), children: _layoutFields(c, w)),
                ]),
              ),
            ]),
          ),
        ),
      ),
    );
  }

  List<Widget> _layoutFields(EditorController c, WidgetItem w) {
    Widget s(String label, double v, double min, double max, void Function(double) set, {String Function(double)? fmt}) => Row(children: [
          SizedBox(width: 82, child: Text(label)),
          Expanded(child: Slider(value: v.clamp(min, max), min: min, max: max, onChanged: (x) => c.mutate(() => set(x), record: false))),
          SizedBox(width: 52, child: Text(fmt?.call(v) ?? '${(v * 100).round()}%', textAlign: TextAlign.end, style: const TextStyle(fontSize: 12, color: AppColors.mediumGrey))),
        ]);
    return [
      TextFormField(initialValue: w.widgetName, decoration: const InputDecoration(labelText: 'Layer name', isDense: true), onChanged: (v) => c.mutate(() => w.widgetName = v, record: false)),
      const SizedBox(height: 10),
      s('Left', w.positionX, -0.5, 1, (x) => w.positionX = x),
      s('Top', w.positionY, -0.5, 1, (x) => w.positionY = x),
      s('Width', w.width, 0.02, 1.5, (x) => w.width = x),
      s('Height', w.height, 0.02, 1.5, (x) => w.height = x),
      s('Rotation', w.rotation, -180, 180, (x) => w.rotation = x.roundToDouble(), fmt: (v) => '${v.round()}°'),
      Text('${(w.width * c.layout.width).round()} × ${(w.height * c.layout.height).round()} px on a ${c.layout.width}×${c.layout.height} canvas',
          style: const TextStyle(color: AppColors.mediumGrey, fontSize: 12)),
      SwitchListTile(contentPadding: EdgeInsets.zero, title: const Text('Locked'), value: w.isLocked, onChanged: (v) => c.mutate(() => w.isLocked = v, record: false)),
      SwitchListTile(contentPadding: EdgeInsets.zero, title: const Text('Visible on display'), value: w.isVisible, onChanged: (v) => c.mutate(() => w.isVisible = v, record: false)),
      Wrap(spacing: 8, children: [
        OutlinedButton.icon(onPressed: c.bringToFront, icon: const Icon(Icons.flip_to_front), label: const Text('Front')),
        OutlinedButton.icon(onPressed: c.sendToBack, icon: const Icon(Icons.flip_to_back), label: const Text('Back')),
        OutlinedButton.icon(onPressed: () => c.align('fill'), icon: const Icon(Icons.fullscreen), label: const Text('Fill screen')),
      ]),
    ];
  }

  void _alignSheet(EditorController c) => showModalBottomSheet(
        context: context,
        builder: (s) => SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
            child: Wrap(spacing: 10, runSpacing: 10, children: [
              for (final a in const [
                ('left', Icons.align_horizontal_left, 'Left'), ('hcenter', Icons.align_horizontal_center, 'Centre H'), ('right', Icons.align_horizontal_right, 'Right'),
                ('top', Icons.align_vertical_top, 'Top'), ('vcenter', Icons.align_vertical_center, 'Centre V'), ('bottom', Icons.align_vertical_bottom, 'Bottom'),
                ('fill', Icons.fullscreen, 'Fill'),
              ])
                SizedBox(
                  width: 100,
                  child: OutlinedButton(onPressed: () { c.align(a.$1); Navigator.pop(s); }, child: Column(mainAxisSize: MainAxisSize.min, children: [Icon(a.$2), Text(a.$3, style: const TextStyle(fontSize: 12))])),
                ),
            ]),
          ),
        ),
      );

  void _layersSheet(EditorController c) => showModalBottomSheet(
        context: context,
        isScrollControlled: true,
        builder: (s) => FractionallySizedBox(
          heightFactor: 0.6,
          child: ListenableBuilder(
            listenable: c,
            builder: (s, _) {
              final order = c.layout.sorted.reversed.toList();
              return Column(children: [
                const ListTile(title: Text('Layers', style: TextStyle(fontWeight: FontWeight.w700)), subtitle: Text('Drag to reorder · top of the list is in front')),
                Expanded(
                  child: order.isEmpty
                      ? const EmptyState(icon: Icons.layers_clear_outlined, title: 'No widgets yet')
                      : ReorderableListView.builder(
                          itemCount: order.length,
                          onReorderItem: (a, b) {
                            final ids = order.map((w) => w.widgetUid).toList();
                            final x = ids.removeAt(a);
                            ids.insert(b, x);
                            c.setLayerOrder(ids);
                          },
                          itemBuilder: (_, i) {
                            final w = order[i];
                            final sel = c.selection.contains(w.widgetUid);
                            return ListTile(
                              key: ValueKey(w.widgetUid),
                              selected: sel,
                              leading: Icon(widgetIcon(w.widgetType)),
                              title: Text(w.widgetName ?? w.widgetType, maxLines: 1, overflow: TextOverflow.ellipsis),
                              onTap: () => c.select(w.widgetUid),
                              trailing: Row(mainAxisSize: MainAxisSize.min, children: [
                                IconButton(icon: Icon(w.isVisible ? Icons.visibility_outlined : Icons.visibility_off_outlined), onPressed: () => c.toggleVisible(w)),
                                IconButton(icon: Icon(w.isLocked ? Icons.lock_outline : Icons.lock_open_outlined), onPressed: () { c.select(w.widgetUid); c.toggleLock(); }),
                                const Icon(Icons.drag_handle_rounded),
                              ]),
                            );
                          },
                        ),
                ),
              ]);
            },
          ),
        ),
      );

  Future<void> _templatesSheet(EditorController c) async {
    final list = await ref.read(templatesProvider.future);
    if (!mounted) return;
    final t = await showModalBottomSheet<ScreenTemplate>(
      context: context,
      isScrollControlled: true,
      builder: (s) => FractionallySizedBox(
        heightFactor: 0.75,
        child: Column(children: [
          const ListTile(title: Text('Apply a template', style: TextStyle(fontWeight: FontWeight.w700)), subtitle: Text('Replaces the current design (you can undo)')),
          Expanded(
            child: GridView.builder(
              padding: const EdgeInsets.all(12),
              gridDelegate: const SliverGridDelegateWithMaxCrossAxisExtent(maxCrossAxisExtent: 220, mainAxisSpacing: 10, crossAxisSpacing: 10, childAspectRatio: 1.1),
              itemCount: list.length,
              itemBuilder: (_, i) => InkWell(
                onTap: list[i].isLocked ? null : () => Navigator.pop(s, list[i]),
                child: Opacity(
                  opacity: list[i].isLocked ? 0.45 : 1,
                  child: Column(children: [
                    Expanded(child: LayoutThumbnail(layout: list[i].layout, media: NetworkMediaSource(list[i].media))),
                    const SizedBox(height: 4),
                    Text(list[i].templateName, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
                  ]),
                ),
              ),
            ),
          ),
        ]),
      ),
    );
    if (t != null) c.applyTemplate(t);
  }

  Future<void> _screenSettings(EditorController c) async {
    final repo = ref.read(screensRepoProvider);
    final screen = await repo.details(c.screenId);
    if (!mounted) return;
    var duration = screen.defaultDuration.toDouble();
    await showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      builder: (s) => StatefulBuilder(
        builder: (s, setS) => SafeArea(
          child: Padding(
            padding: EdgeInsets.fromLTRB(20, 0, 20, 20 + MediaQuery.of(s).viewInsets.bottom),
            child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              Text('Screen settings', style: Theme.of(context).textTheme.titleLarge),
              const SizedBox(height: 12),
              Text('Canvas: ${orientationLabel(c.layout.orientation)} · ${c.layout.width}×${c.layout.height}', style: const TextStyle(color: AppColors.mediumGrey)),
              const SizedBox(height: 8),
              Wrap(spacing: 8, children: [
                for (final o in const [('landscape', 1920, 1080), ('portrait', 1080, 1920), ('square', 1080, 1080)])
                  ChoiceChip(label: Text(orientationLabel(o.$1)), selected: c.layout.orientation == o.$1, onSelected: (_) { c.setCanvas(o.$1, o.$2, o.$3); setS(() {}); }),
              ]),
              const Text('Widgets keep their relative positions when you change the canvas.', style: TextStyle(fontSize: 12, color: AppColors.mediumGrey)),
              const SizedBox(height: 16),
              Text('Duration in playlists: ${duration.round()} s'),
              Slider(value: duration.clamp(3, 120), min: 3, max: 120, divisions: 117, onChanged: (v) => setS(() => duration = v),
                  onChangeEnd: (v) => repo.update(c.screenId, {'default_duration': v.round()})),
              const SizedBox(height: 8),
              OutlinedButton.icon(
                icon: const Icon(Icons.bookmark_add_outlined),
                label: const Text('Save as my template'),
                onPressed: () async {
                  await c.save();
                  if (!s.mounted) return;
                  final n = await promptText(s, title: 'Template name', initial: c.name);
                  if (n != null && n.isNotEmpty && s.mounted) {
                    await runWithProgress(s, () => repo.saveAsTemplate(c.screenId, n), success: 'Template saved');
                    ref.invalidate(templatesProvider);
                  }
                },
              ),
            ]),
          ),
        ),
      ),
    );
  }

  void _preview(EditorController c) {
    final layout = c.layout.copy();
    final media = Map<int, MediaItem>.from(c.media);
    Navigator.of(context).push(MaterialPageRoute(
      fullscreenDialog: true,
      builder: (_) => Scaffold(
        backgroundColor: Colors.black,
        body: Stack(fit: StackFit.expand, children: [
          Positioned.fill(child: LayoutView(layout: layout, media: NetworkMediaSource(media), mode: RenderMode.preview)),
          Positioned(top: 0, left: 0, child: SafeArea(child: Padding(padding: const EdgeInsets.all(8), child: Builder(builder: (ctx) => IconButton.filled(style: IconButton.styleFrom(backgroundColor: Colors.black54), icon: const Icon(Icons.close, color: Colors.white), onPressed: () => Navigator.pop(ctx)))))),
        ]),
      ),
    ));
  }
}
