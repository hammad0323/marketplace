import 'dart:typed_data';

import 'package:material_ui/material_ui.dart';
import 'package:pdfx/pdfx.dart';

import '../../util/format.dart';

/// Page manager for one PDF: choose pages, reorder, rotate, or type ranges ("1-3, 5").
/// Returns a list of {page, rotate}. The original file is never changed — the server builds a print-ready copy.
class PdfPagesScreen extends StatefulWidget {
  const PdfPagesScreen({super.key, required this.bytes, required this.pageCount, this.initial = const []});
  final Uint8List bytes;
  final int pageCount;
  final List<Map<String, dynamic>> initial;
  @override
  State<PdfPagesScreen> createState() => _PdfPagesScreenState();
}

class _PdfPagesScreenState extends State<PdfPagesScreen> {
  PdfDocument? _doc;
  final _thumbs = <int, Uint8List>{};
  late List<Map<String, dynamic>> _pages; // ordered, each {page, rotate, on}
  final _ranges = TextEditingController();
  String? _error;

  @override
  void initState() {
    super.initState();
    final init = widget.initial.isNotEmpty ? widget.initial : [for (var i = 1; i <= widget.pageCount; i++) {'page': i, 'rotate': 0}];
    final chosen = {for (final p in init) p['page']: p};
    _pages = [
      for (final p in init) {'page': p['page'], 'rotate': p['rotate'] ?? 0, 'on': true},
      for (var i = 1; i <= widget.pageCount; i++)
        if (!chosen.containsKey(i)) {'page': i, 'rotate': 0, 'on': false},
    ];
    _open();
  }

  Future<void> _open() async {
    try {
      _doc = await PdfDocument.openData(widget.bytes);
      if (mounted) setState(() {});
    } catch (e) {
      if (mounted) setState(() => _error = 'Thumbnails are not available for this PDF, but you can still choose pages by number.');
    }
  }

  Future<Uint8List?> _thumb(int n) async {
    if (_thumbs.containsKey(n)) return _thumbs[n];
    final doc = _doc;
    if (doc == null) return null;
    try {
      final page = await doc.getPage(n);
      final w = 220.0, h = w * page.height / page.width;
      final img = await page.render(width: w, height: h, format: PdfPageImageFormat.jpeg, backgroundColor: '#FFFFFF');
      await page.close();
      if (img != null) _thumbs[n] = img.bytes;
      return img?.bytes;
    } catch (_) {
      return null;
    }
  }

  @override
  void dispose() {
    _doc?.close();
    super.dispose();
  }

  void _applyRanges() {
    final list = parseRanges(_ranges.text, widget.pageCount);
    if (list.isEmpty) return;
    setState(() {
      final byPage = {for (final p in _pages) p['page']: p};
      _pages = [
        for (final n in list) {...byPage[n]!, 'on': true},
        for (var i = 1; i <= widget.pageCount; i++)
          if (!list.contains(i)) {...byPage[i]!, 'on': false},
      ];
    });
  }

  @override
  Widget build(BuildContext context) {
    final count = _pages.where((p) => p['on'] == true).length;
    return Scaffold(
      appBar: AppBar(title: Text('Pages ($count of ${widget.pageCount})'), actions: [
        PopupMenuButton<String>(
          onSelected: (v) => setState(() {
            for (final p in _pages) {
              p['on'] = switch (v) { 'all' => true, 'none' => false, 'odd' => (p['page'] as int).isOdd, _ => (p['page'] as int).isEven };
            }
            if (v == 'all') _pages.sort((a, b) => (a['page'] as int).compareTo(b['page'] as int));
          }),
          itemBuilder: (_) => const [PopupMenuItem(value: 'all', child: Text('Select all (original order)')), PopupMenuItem(value: 'none', child: Text('Clear')), PopupMenuItem(value: 'odd', child: Text('Odd pages')), PopupMenuItem(value: 'even', child: Text('Even pages'))],
        ),
      ]),
      bottomNavigationBar: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: FilledButton(
            onPressed: count == 0 ? null : () => Navigator.pop(context, [for (final p in _pages) if (p['on'] == true) {'page': p['page'], 'rotate': p['rotate']}]),
            child: Text('Use $count page${count == 1 ? '' : 's'}'),
          ),
        ),
      ),
      body: Column(children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(12, 12, 12, 4),
          child: Row(children: [
            Expanded(child: TextField(controller: _ranges, decoration: const InputDecoration(labelText: 'Pages to print', hintText: 'e.g. 1-3, 5, 8', isDense: true), onSubmitted: (_) => _applyRanges())),
            const SizedBox(width: 8),
            FilledButton.tonal(onPressed: _applyRanges, child: const Text('Apply')),
          ]),
        ),
        if (_error != null) Padding(padding: const EdgeInsets.symmetric(horizontal: 12), child: Text(_error!, style: const TextStyle(color: Color(0xFFB45309), fontSize: 12))),
        const Padding(padding: EdgeInsets.fromLTRB(12, 4, 12, 4), child: Text('Tap to include or skip a page · long-press and drag to reorder · ↻ rotates', style: TextStyle(fontSize: 12, color: Color(0xFF64748B)))),
        Expanded(
          child: ReorderableListView.builder(
            padding: const EdgeInsets.fromLTRB(12, 0, 12, 12),
            itemCount: _pages.length,
            onReorderItem: (a, b) => setState(() {
              _pages.insert(b, _pages.removeAt(a));
            }),
            itemBuilder: (c, i) {
              final p = _pages[i];
              final n = p['page'] as int;
              return Card(
                key: ValueKey(n),
                color: p['on'] == true ? null : const Color(0xFFF1F5F9),
                child: InkWell(
                  borderRadius: BorderRadius.circular(16),
                  onTap: () => setState(() => p['on'] = p['on'] != true),
                  child: Padding(
                    padding: const EdgeInsets.all(8),
                    child: Row(children: [
                      SizedBox(
                        width: 70,
                        height: 90,
                        child: FutureBuilder<Uint8List?>(
                          future: _doc == null ? Future.value(null) : _thumb(n),
                          builder: (c, s) => Opacity(
                            opacity: p['on'] == true ? 1 : .35,
                            child: s.data == null
                                ? Container(color: const Color(0xFFE2E8F0), alignment: Alignment.center, child: Text('$n'))
                                : RotatedBox(quarterTurns: (asInt(p['rotate']) ~/ 90) % 4, child: Image.memory(s.data!, fit: BoxFit.contain)),
                          ),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(child: Text('Page $n${asInt(p['rotate']) != 0 ? ' · rotated ${p['rotate']}°' : ''}', style: TextStyle(fontWeight: FontWeight.w700, color: p['on'] == true ? null : const Color(0xFF94A3B8)))),
                      IconButton(tooltip: 'Rotate', icon: const Icon(Icons.rotate_right), onPressed: () => setState(() => p['rotate'] = (asInt(p['rotate']) + 90) % 360)),
                      Checkbox(value: p['on'] == true, onChanged: (v) => setState(() => p['on'] = v)),
                    ]),
                  ),
                ),
              );
            },
          ),
        ),
      ]),
    );
  }
}

/// Renders every page of a PDF (e.g. the server's print preview) in a scrollable list.
class PdfPreviewView extends StatefulWidget {
  const PdfPreviewView({super.key, required this.bytes});
  final Uint8List bytes;
  @override
  State<PdfPreviewView> createState() => _PdfPreviewViewState();
}

class _PdfPreviewViewState extends State<PdfPreviewView> {
  late final PdfControllerPinch _ctl = PdfControllerPinch(document: PdfDocument.openData(widget.bytes));
  @override
  void dispose() {
    _ctl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Container(color: const Color(0xFFE2E8F0), child: PdfViewPinch(controller: _ctl, scrollDirection: Axis.vertical));
}
