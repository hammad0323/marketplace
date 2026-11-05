import 'dart:async';
import 'dart:convert';
import 'dart:math' as math;

import 'package:flutter/foundation.dart';

import '../../core/constants/app_config.dart';
import '../../core/network/api_client.dart';
import '../../models/layout.dart';
import '../../models/media.dart';
import '../../models/screen.dart';
import '../../models/template.dart';
import '../../repositories/repositories.dart';

enum SaveState { saved, dirty, saving, error, conflict }

/// A snapping guide line in normalised canvas coordinates.
class Guide {
  const Guide({required this.vertical, required this.pos});
  final bool vertical;
  final double pos;
}

/// All editor state: layout, selection, history, clipboard and autosave.
class EditorController extends ChangeNotifier {
  EditorController({required this.repo, required ScreenModel screen})
      : screenId = screen.screenId,
        name = screen.screenName,
        layoutVersion = screen.layoutVersion,
        status = screen.status,
        layout = screen.layout?.copy() ?? LayoutDoc(),
        media = {...screen.media};

  final ScreensRepository repo;
  final int screenId;
  String name;
  String status;
  int layoutVersion;
  LayoutDoc layout;
  final Map<int, MediaItem> media;

  final Set<String> selection = {};
  final List<String> _undo = [];
  final List<String> _redo = [];
  static List<WidgetItem> clipboard = [];

  SaveState saveState = SaveState.saved;
  String? lastError;
  List<Guide> guides = const [];
  Timer? _autosave;
  bool disposed = false;

  bool get canUndo => _undo.isNotEmpty;
  bool get canRedo => _redo.isNotEmpty;
  bool get dirty => saveState == SaveState.dirty || saveState == SaveState.error;

  WidgetItem? get selected => selection.length == 1 ? byUid(selection.first) : null;
  List<WidgetItem> get selectedItems => layout.widgets.where((w) => selection.contains(w.widgetUid)).toList();
  WidgetItem? byUid(String uid) {
    for (final w in layout.widgets) {
      if (w.widgetUid == uid) return w;
    }
    return null;
  }

  // ---------------------------------------------------------------- history
  String _snap() => jsonEncode(layout.toJson());

  /// Record the current state before a change.
  void checkpoint() {
    _undo.add(_snap());
    if (_undo.length > 100) _undo.removeAt(0);
    _redo.clear();
  }

  void undo() {
    if (!canUndo) return;
    _redo.add(_snap());
    layout = LayoutDoc.fromJson(jsonDecode(_undo.removeLast()) as Map<String, dynamic>);
    selection.removeWhere((u) => byUid(u) == null);
    _changed();
  }

  void redo() {
    if (!canRedo) return;
    _undo.add(_snap());
    layout = LayoutDoc.fromJson(jsonDecode(_redo.removeLast()) as Map<String, dynamic>);
    selection.removeWhere((u) => byUid(u) == null);
    _changed();
  }

  /// Apply a mutation with history + autosave.
  void mutate(void Function() f, {bool record = true}) {
    if (record) checkpoint();
    f();
    _changed();
  }

  void _changed() {
    saveState = SaveState.dirty;
    _autosave?.cancel();
    _autosave = Timer(AppConfig.autosaveDelay, () => save());
    notifyListeners();
  }

  void refresh() => notifyListeners();

  // -------------------------------------------------------------- selection
  void select(String? uid, {bool additive = false}) {
    if (uid == null) {
      selection.clear();
    } else if (additive) {
      selection.contains(uid) ? selection.remove(uid) : selection.add(uid);
    } else {
      selection
        ..clear()
        ..add(uid);
    }
    notifyListeners();
  }

  // --------------------------------------------------------------- widgets
  int get _topZ => layout.widgets.fold(0, (m, w) => math.max(m, w.zIndex));

  WidgetItem addWidget(WidgetTypeInfo type) {
    final w = WidgetItem(
      widgetType: type.widgetKey,
      widgetName: type.widgetName,
      width: type.defaultWidth.clamp(0.05, 1.0),
      height: type.defaultHeight.clamp(0.05, 1.0),
      configuration: Map<String, dynamic>.from(jsonDecode(jsonEncode(type.defaultConfig)) as Map),
    );
    w.positionX = ((1 - w.width) / 2).clamp(0.0, 1.0);
    w.positionY = ((1 - w.height) / 2).clamp(0.0, 1.0);
    mutate(() {
      w.zIndex = _topZ + 1;
      layout.widgets.add(w);
      selection
        ..clear()
        ..add(w.widgetUid);
    });
    return w;
  }

  void deleteSelected() {
    if (selection.isEmpty) return;
    mutate(() {
      layout.widgets.removeWhere((w) => selection.contains(w.widgetUid) && !w.isLocked);
      selection.clear();
    });
  }

  void duplicateSelected() {
    final items = selectedItems;
    if (items.isEmpty) return;
    mutate(() {
      selection.clear();
      var z = _topZ;
      for (final w in items) {
        final c = w.copy(newId: true)
          ..positionX = (w.positionX + 0.03).clamp(0.0, 0.97)
          ..positionY = (w.positionY + 0.03).clamp(0.0, 0.97)
          ..isLocked = false
          ..zIndex = ++z
          ..widgetName = '${w.widgetName ?? w.widgetType} copy';
        layout.widgets.add(c);
        selection.add(c.widgetUid);
      }
    });
  }

  void copySelected() => clipboard = selectedItems.map((w) => w.copy()).toList();

  bool get canPaste => clipboard.isNotEmpty;

  void paste() {
    if (clipboard.isEmpty) return;
    mutate(() {
      selection.clear();
      var z = _topZ;
      for (final w in clipboard) {
        final c = w.copy(newId: true)
          ..positionX = (w.positionX + 0.02).clamp(0.0, 0.98)
          ..positionY = (w.positionY + 0.02).clamp(0.0, 0.98)
          ..zIndex = ++z;
        layout.widgets.add(c);
        selection.add(c.widgetUid);
      }
    });
  }

  void toggleLock() => mutate(() {
        final lock = !selectedItems.every((w) => w.isLocked);
        for (final w in selectedItems) {
          w.isLocked = lock;
        }
      });

  void toggleVisible(WidgetItem w) => mutate(() => w.isVisible = !w.isVisible);

  void bringToFront() => mutate(() {
        var z = _topZ;
        for (final w in selectedItems) {
          w.zIndex = ++z;
        }
      });

  void sendToBack() => mutate(() {
        final minZ = layout.widgets.fold(0, (m, w) => math.min(m, w.zIndex));
        var z = minZ - selectedItems.length;
        for (final w in selectedItems) {
          w.zIndex = z++;
        }
      });

  /// Reorder layers: [orderTopFirst] lists uids from top to bottom.
  void setLayerOrder(List<String> orderTopFirst) => mutate(() {
        for (var i = 0; i < orderTopFirst.length; i++) {
          byUid(orderTopFirst[i])?.zIndex = orderTopFirst.length - i;
        }
      });

  /// Align selected widgets to the canvas.
  void align(String how) => mutate(() {
        for (final w in selectedItems.where((w) => !w.isLocked)) {
          switch (how) {
            case 'left': w.positionX = 0;
            case 'right': w.positionX = 1 - w.width;
            case 'top': w.positionY = 0;
            case 'bottom': w.positionY = 1 - w.height;
            case 'hcenter': w.positionX = (1 - w.width) / 2;
            case 'vcenter': w.positionY = (1 - w.height) / 2;
            case 'fill':
              w.positionX = 0;
              w.positionY = 0;
              w.width = 1;
              w.height = 1;
          }
        }
      });

  // ------------------------------------------------------- drag & resize
  static const double snapThreshold = 0.012;

  /// Move all selected (unlocked) widgets by a normalised delta, snapping the primary one.
  void moveSelection(double dx, double dy, String primaryUid) {
    final items = selectedItems.where((w) => !w.isLocked).toList();
    final p = byUid(primaryUid);
    if (p == null || p.isLocked) return;
    var nx = p.positionX + dx, ny = p.positionY + dy;
    final g = <Guide>[];
    // Snap edges/centre of the primary widget to canvas + other widgets.
    final xs = <double>[0, 0.5, 1];
    final ys = <double>[0, 0.5, 1];
    for (final o in layout.widgets) {
      if (selection.contains(o.widgetUid) || !o.isVisible) continue;
      xs.addAll([o.positionX, o.positionX + o.width / 2, o.positionX + o.width]);
      ys.addAll([o.positionY, o.positionY + o.height / 2, o.positionY + o.height]);
    }
    double? snapX, snapY;
    for (final edge in [0.0, 0.5, 1.0]) {
      for (final x in xs) {
        if ((nx + p.width * edge - x).abs() < snapThreshold && snapX == null) {
          snapX = x - p.width * edge;
          g.add(Guide(vertical: true, pos: x));
        }
      }
      for (final y in ys) {
        if ((ny + p.height * edge - y).abs() < snapThreshold && snapY == null) {
          snapY = y - p.height * edge;
          g.add(Guide(vertical: false, pos: y));
        }
      }
    }
    if (snapX != null) nx = snapX;
    if (snapY != null) ny = snapY;
    final ddx = nx - p.positionX, ddy = ny - p.positionY;
    for (final w in items) {
      w.positionX = (w.positionX + ddx).clamp(-w.width + 0.02, 0.98);
      w.positionY = (w.positionY + ddy).clamp(-w.height + 0.02, 0.98);
    }
    guides = g;
    notifyListeners();
  }

  /// Resize from a handle. [hx]/[hy]: -1 = left/top edge, 1 = right/bottom edge, 0 = unchanged.
  void resize(WidgetItem w, int hx, int hy, double dx, double dy, {bool keepAspect = false}) {
    if (w.isLocked) return;
    const minSize = 0.02;
    var x = w.positionX, y = w.positionY, width = w.width, height = w.height;
    if (hx == 1) width = math.max(minSize, width + dx);
    if (hx == -1) {
      final nw = math.max(minSize, width - dx);
      x += width - nw;
      width = nw;
    }
    if (hy == 1) height = math.max(minSize, height + dy);
    if (hy == -1) {
      final nh = math.max(minSize, height - dy);
      y += height - nh;
      height = nh;
    }
    if (keepAspect && hx != 0 && hy != 0) {
      final ratio = w.width / w.height;
      height = width / ratio;
      if (hy == -1) y = w.positionY + w.height - height;
    }
    // Snap the moving edges to canvas edges/centre.
    final g = <Guide>[];
    for (final s in [0.0, 0.5, 1.0]) {
      if (hx == 1 && (x + width - s).abs() < snapThreshold) { width = s - x; g.add(Guide(vertical: true, pos: s)); }
      if (hx == -1 && (x - s).abs() < snapThreshold) { width += x - s; x = s; g.add(Guide(vertical: true, pos: s)); }
      if (hy == 1 && (y + height - s).abs() < snapThreshold) { height = s - y; g.add(Guide(vertical: false, pos: s)); }
      if (hy == -1 && (y - s).abs() < snapThreshold) { height += y - s; y = s; g.add(Guide(vertical: false, pos: s)); }
    }
    w
      ..positionX = x
      ..positionY = y
      ..width = width.clamp(minSize, 2)
      ..height = height.clamp(minSize, 2);
    guides = g;
    notifyListeners();
  }

  void endGesture() {
    guides = const [];
    _changed();
  }

  // ------------------------------------------------------------ background
  void setBackground(BackgroundSpec bg) => mutate(() => layout.background = bg);

  void setCanvas(String orientation, int width, int height) => mutate(() {
        layout
          ..orientation = orientation
          ..width = width
          ..height = height;
      });

  void applyTemplate(ScreenTemplate t) => mutate(() {
        final l = t.layout.copy();
        for (final w in l.widgets) {
          w.widgetUid = WidgetItem(widgetType: w.widgetType).widgetUid;
        }
        layout = l;
        selection.clear();
      });

  // ------------------------------------------------------------------ save
  Future<bool> save({bool publish = false, bool force = false}) async {
    _autosave?.cancel();
    if (saveState == SaveState.saving) return false;
    saveState = SaveState.saving;
    lastError = null;
    if (!disposed) notifyListeners();
    try {
      final s = await repo.saveLayout(screenId, layout, layoutVersion, publish: publish, force: force, name: name);
      layoutVersion = s.layoutVersion;
      status = s.status;
      media.addAll(s.media);
      saveState = SaveState.saved;
      if (!disposed) notifyListeners();
      return true;
    } on ApiException catch (e) {
      saveState = e.code == 'CONFLICT' ? SaveState.conflict : SaveState.error;
      lastError = e.message;
      if (!disposed) notifyListeners();
      return false;
    }
  }

  /// Reload the latest server version (after a conflict).
  Future<void> reload() async {
    final s = await repo.details(screenId);
    layout = s.layout ?? LayoutDoc();
    layoutVersion = s.layoutVersion;
    media.addAll(s.media);
    _undo.clear();
    _redo.clear();
    selection.clear();
    saveState = SaveState.saved;
    notifyListeners();
  }

  @override
  void dispose() {
    disposed = true;
    _autosave?.cancel();
    super.dispose();
  }
}
