import 'dart:async';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/services.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../api/api.dart';
import '../../state/session.dart';
import '../../util/format.dart';
import '../../widgets/common.dart';
import '../owner/more.dart' show hexColor;
import 'image_edit.dart';
import 'pdf_pages.dart';
import 'shop.dart' show categoryIcon;

/// An uploaded file kept with its local bytes (for page thumbnails and photo editing).
class LocalUpload {
  LocalUpload(this.name, this.bytes);
  final String name;
  final Uint8List bytes;
  Map<String, dynamic>? server; // upload payload from the API
  String? error;
  int? manualPages;
  ImageEdit edit = const ImageEdit();
  bool get uploading => server == null && error == null;
  String get kind => '${server?['kind'] ?? ''}';
  int? get pages => server?['pages'] as int?;
}

/// One item being configured (category, files, options).
class DraftItem {
  DraftItem(this.category);
  Map<String, dynamic> category;
  int? subcategoryId;
  final files = <LocalUpload>[];
  int? paperSizeId, paperTypeId, gsmId;
  double? customW, customH;
  String? color, sides;
  String orientation = 'portrait';
  final finishing = <int>{};
  int copies = 1, quantity = 1;
  String notes = '';
  Map<String, dynamic> layout = {'fit': 'fit', 'margin_mm': 5, 'scale': 100};
  List<Map<String, dynamic>> pageSelection = [];
  String? cvToken, cvTitle;
  int cvPages = 1;

  List<Map<String, dynamic>> opts(String g) => ((category['options'] as Map?)?[g] as List? ?? []).cast<Map<String, dynamic>>();
  Map<String, dynamic>? get sub => (category['subcategories'] as List).cast<Map<String, dynamic>>().where((s) => s['id'] == subcategoryId).firstOrNull;
  String get basis => '${sub?['pricing_basis'] ?? category['pricing_basis'] ?? 'pages'}';
  Map<String, dynamic>? opt(String g, int? id) => opts(g).where((o) => o['id'] == id).firstOrNull;
  int get images => files.where((f) => f.kind == 'image').length;
  bool get sheetLayout => layout['fit'] == 'grid' || layout['fit'] == 'id_pair';

  int get pages {
    if (cvToken != null) return cvPages;
    if (files.length == 1 && files.first.kind != 'image' && (files.first.pages ?? files.first.manualPages) != null) {
      return pageSelection.isNotEmpty ? pageSelection.length : (files.first.pages ?? files.first.manualPages ?? 1);
    }
    var n = 0;
    for (final f in files) {
      n += f.kind == 'image' ? 1 : (f.pages ?? f.manualPages ?? 1);
    }
    return n < 1 ? 1 : n;
  }

  void applyDefaults() {
    int? def(String g) => (opts(g).where((o) => o['is_default'] == true).firstOrNull ?? opts(g).firstOrNull)?['id'] as int?;
    paperSizeId = def('paper_size');
    paperTypeId = opts('paper_type').where((o) => o['is_default'] == true).firstOrNull?['id'] as int?;
    gsmId = opts('gsm').where((o) => o['is_default'] == true).firstOrNull?['id'] as int?;
    color = (opts('color').where((o) => o['is_default'] == true).firstOrNull ?? opts('color').firstOrNull)?['code'] as String?;
    sides = opts('sides').isEmpty ? null : 'single';
  }

  /// Sensible presets for photo and ID services (same as the web flow).
  void applySubPreset() {
    final code = '${sub?['code'] ?? ''}';
    if (RegExp('passport|photo|glossy|matte|4x6|5x7|pvc|invitation').hasMatch(code) && opts('color').any((o) => o['code'] == 'color')) color = 'color';
    if (code.contains('passport')) {
      layout = {'fit': 'grid', 'margin_mm': 3, 'gap_mm': 3, 'sheet': '4x6'};
      paperSizeId = opts('paper_size').where((o) => o['code'] == 'passport').firstOrNull?['id'] as int? ?? paperSizeId;
      quantity = 8;
    }
    if (code.contains('cnic')) {
      layout = {'fit': 'id_pair', 'margin_mm': 10};
      paperSizeId = opts('paper_size').where((o) => o['code'] == 'id_card').firstOrNull?['id'] as int? ?? paperSizeId;
    }
  }

  Map<String, dynamic> toJson() {
    final single = files.length == 1 && files.first.kind == 'image' ? files.first.edit.toLayout() : const <String, dynamic>{};
    return {
      'category_id': category['id'],
      'subcategory_id': subcategoryId,
      'paper_size_id': paperSizeId,
      if (opt('paper_size', paperSizeId)?['code'] == 'custom') ...{'custom_width_mm': customW, 'custom_height_mm': customH},
      'paper_type_id': paperTypeId,
      'gsm_id': gsmId,
      'color': color,
      'sides': sides,
      'orientation': orientation,
      'finishing': finishing.toList(),
      'copies': copies,
      'quantity': quantity,
      'notes': notes,
      'layout': {...layout, ...single},
      'files': [for (final f in files) if (f.server != null) f.server!['token']],
      'page_selection': pageSelection,
      'pages': pages,
      if (cvToken != null) 'cv_token': cvToken,
    };
  }

  String get title => cvToken != null ? (cvTitle ?? 'CV') : (files.isEmpty ? '${category['name']}' : files.map((f) => f.name).join(', '));
}

/// Items waiting to be ordered, per shop (kept while the app is open).
final carts = <String, List<DraftItem>>{};

class PrintFlowScreen extends StatefulWidget {
  const PrintFlowScreen({super.key, required this.code, this.categoryId, this.cvToken});
  final String code;
  final int? categoryId;
  final String? cvToken;
  @override
  State<PrintFlowScreen> createState() => _PrintFlowScreenState();
}

class _PrintFlowScreenState extends State<PrintFlowScreen> {
  static const steps = ['Service', 'Files', 'Options', 'Review', 'Send'];
  Map<String, dynamic>? _shop;
  List<Map<String, dynamic>> _cats = [];
  DraftItem? _item;
  int _step = 0;
  String? _error;
  Map<String, dynamic>? _price;
  Timer? _priceTimer;
  Uint8List? _preview;
  bool _previewBusy = false;
  String? _previewError;
  bool _sending = false;
  final _name = TextEditingController();
  final _phone = TextEditingController();
  final _instructions = TextEditingController();

  List<DraftItem> get cart => carts.putIfAbsent(widget.code, () => []);
  Api get api => SessionScope.read(context).api;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  Future<void> _load() async {
    final session = SessionScope.read(context);
    try {
      await session.ensureCustomer();
      final d = await session.api.get('/shops/${widget.code}');
      final prefs = await SharedPreferences.getInstance();
      _name.text = prefs.getString('customer_name') ?? '';
      _phone.text = prefs.getString('customer_phone') ?? '';
      _shop = d['shop'] as Map<String, dynamic>;
      _cats = (d['categories'] as List).cast<Map<String, dynamic>>();
      await session.rememberShop(widget.code);
      if (widget.cvToken != null) {
        final cv = await session.api.get('/cv/${widget.cvToken}', {'pages': 1});
        final cat = _cats.where((c) => c['code'] == 'personal').firstOrNull ?? _cats.first;
        _item = DraftItem(cat)
          ..applyDefaults()
          ..cvToken = widget.cvToken
          ..cvTitle = '${cv['title']}'
          ..cvPages = asInt(cv['pages'], 1)
          ..subcategoryId = (cat['subcategories'] as List).cast<Map<String, dynamic>>().where((s) => '${s['code']}'.contains('cv')).firstOrNull?['id'] as int?;
        _item!.color = _item!.opts('color').any((o) => o['code'] == 'color') ? 'color' : _item!.color;
        _step = 2;
      } else if (widget.categoryId != null) {
        final cat = _cats.where((c) => c['id'] == widget.categoryId).firstOrNull;
        if (cat != null) _selectCategory(cat, advance: false);
      }
      if (mounted) setState(() {});
      _schedulePrice();
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    }
  }

  void _selectCategory(Map<String, dynamic> cat, {bool advance = true}) {
    final keep = _item?.files.toList() ?? [];
    _item = DraftItem(cat)..applyDefaults();
    _item!.files.addAll(keep);
    final subs = (cat['subcategories'] as List);
    if (subs.length == 1) _item!.subcategoryId = subs.first['id'] as int;
    if (advance && subs.length <= 1) _step = 1;
    setState(() {});
  }

  void _schedulePrice() {
    _priceTimer?.cancel();
    _priceTimer = Timer(const Duration(milliseconds: 450), _fetchPrice);
  }

  Future<void> _fetchPrice() async {
    final items = [...cart, ?_item];
    if (items.isEmpty || !mounted) return;
    try {
      final p = await api.post('/shops/${widget.code}/price', {'items': [for (final i in items) i.toJson()]});
      if (mounted) setState(() => _price = p);
    } on ApiException {
      if (mounted) setState(() => _price = null);
    }
  }

  // ------------------------------------------------------------ files
  Future<void> _pickFiles() async {
    final picked = await FilePicker.pickFiles(type: FileType.custom, allowedExtensions: const ['pdf', 'jpg', 'jpeg', 'png', 'docx']);
    if (picked.isEmpty || !mounted) return;
    final max = asInt(_shop?['max_upload_mb'], 25) * 1024 * 1024;
    for (final f in picked) {
      final bytes = await f.readAsBytes();
      if (bytes.length > max) {
        if (mounted) showMessage(context, '${f.name} is larger than ${_shop?['max_upload_mb']} MB.', error: true);
        continue;
      }
      final u = LocalUpload(f.name, bytes);
      setState(() => _item!.files.add(u));
      unawaited(_upload(u));
    }
  }

  Future<void> _upload(LocalUpload u) async {
    try {
      final r = await api.upload('/shops/${widget.code}/uploads', field: 'file', bytes: u.bytes, filename: u.name);
      u.server = r;
      if (r['notice'] != null && mounted) showMessage(context, '${r['notice']}');
    } on ApiException catch (e) {
      u.error = e.message;
    }
    if (!mounted) return;
    setState(() {
      _item!.pageSelection = [];
      if (_item!.files.length == 1 && _item!.images == 1 && _item!.basis == 'pages' && _item!.layout['fit'] == 'grid') _item!.layout = {'fit': 'fit', 'margin_mm': 5, 'scale': 100};
    });
    _schedulePrice();
  }

  void _removeFile(LocalUpload u) {
    setState(() {
      _item!.files.remove(u);
      _item!.pageSelection = [];
    });
    if (u.server != null) api.delete('/uploads/${u.server!['token']}').ignore();
    _schedulePrice();
  }

  Future<void> _editImage(LocalUpload u) async {
    final ps = _item!.opt('paper_size', _item!.paperSizeId);
    final aspect = ps != null && ps['width_mm'] != null && ps['height_mm'] != null ? (ps['width_mm'] as num) / (ps['height_mm'] as num) : null;
    final r = await Navigator.of(context).push<ImageEdit>(MaterialPageRoute(builder: (_) => ImageEditScreen(bytes: u.bytes, initial: u.edit, aspectRatio: _item!.layout['fit'] == 'fill' || _item!.sheetLayout ? aspect?.toDouble() : null)));
    if (r != null) setState(() => u.edit = r);
  }

  Future<void> _choosePages(LocalUpload u) async {
    var bytes = u.bytes;
    if (u.kind == 'docx' && u.server?['pdf_url'] != null) bytes = await api.url('${u.server!['pdf_url']}');
    if (!mounted) return;
    final r = await Navigator.of(context).push<List<Map<String, dynamic>>>(MaterialPageRoute(builder: (_) => PdfPagesScreen(bytes: bytes, pageCount: u.pages ?? 1, initial: _item!.pageSelection)));
    if (r != null) {
      final unchanged = r.length == u.pages && r.asMap().entries.every((e) => e.value['page'] == e.key + 1 && asInt(e.value['rotate']) == 0);
      setState(() => _item!.pageSelection = unchanged ? [] : r);
      _schedulePrice();
    }
  }

  bool _validFiles() {
    final it = _item!;
    if (it.cvToken != null) return true;
    if (it.files.isEmpty && it.category['accepts_upload'] == true && it.category['code'] != 'custom') return _fail('Please upload the file you want to print.');
    if (it.files.any((f) => f.uploading)) return _fail('Please wait for the upload to finish.');
    if (it.files.any((f) => f.error != null)) return _fail('Remove the files that failed to upload.');
    if (it.files.any((f) => f.server?['needs_page_count'] == true && (f.manualPages ?? 0) < 1)) return _fail('Please enter how many pages your Word file has.');
    if (it.layout['fit'] == 'id_pair' && it.images < 2) return _fail('Upload two photos: the front and the back of the card.');
    return true;
  }

  bool _fail(String m) {
    showMessage(context, m, error: true);
    return false;
  }

  bool _validOptions() {
    final it = _item!;
    if (it.opts('paper_size').isNotEmpty && it.paperSizeId == null) return _fail('Please choose a paper size.');
    if (it.opt('paper_size', it.paperSizeId)?['code'] == 'custom' && ((it.customW ?? 0) < 20 || (it.customH ?? 0) < 20)) return _fail('Enter the custom size in millimetres.');
    if (it.opts('color').isNotEmpty && it.color == null) return _fail('Choose black & white or colour.');
    return true;
  }

  Future<void> _go(int to) async {
    if (to > _step) {
      if (_step == 0 && (_item == null || ((_item!.category['subcategories'] as List).length > 1 && _item!.subcategoryId == null))) {
        _fail('Choose a service.');
        return;
      }
      if (_step <= 1 && to >= 2 && !_validFiles()) return;
      if (_step <= 2 && to >= 3 && !_validOptions()) return;
    }
    setState(() => _step = to);
    if (to == 3) {
      _fetchPrice();
      _loadPreview();
    }
  }

  Future<void> _loadPreview() async {
    final it = _item;
    if (it == null || it.files.isEmpty || it.files.every((f) => f.kind == 'docx' && f.server?['converted'] != true)) {
      setState(() => _preview = null);
      return;
    }
    setState(() {
      _previewBusy = true;
      _previewError = null;
    });
    try {
      final j = it.toJson();
      final r = await api.post('/shops/${widget.code}/preview', {
        'files': j['files'], 'paper_size_id': j['paper_size_id'], 'custom_width_mm': j['custom_width_mm'], 'custom_height_mm': j['custom_height_mm'],
        'orientation': j['orientation'], 'layout': j['layout'], 'page_selection': j['page_selection'], 'quantity': j['quantity'],
      });
      final b = await api.url('${r['url']}');
      if (mounted) setState(() => _preview = b);
    } on ApiException catch (e) {
      if (mounted) setState(() => _previewError = e.message);
    } finally {
      if (mounted) setState(() => _previewBusy = false);
    }
  }

  Future<void> _submit() async {
    if (_name.text.trim().length < 2) {
      _fail('Please enter your name.');
      return;
    }
    if (_phone.text.replaceAll(RegExp(r'\D'), '').length < 10) {
      _fail('Please enter a valid mobile number.');
      return;
    }
    setState(() => _sending = true);
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('customer_name', _name.text.trim());
    await prefs.setString('customer_phone', _phone.text.trim());
    if (!mounted) return;
    final items = [...cart, _item!];
    final r = await runAction(context, () => api.post('/shops/${widget.code}/orders', {
          'customer_name': _name.text.trim(),
          'customer_phone': _phone.text.trim(),
          'instructions': _instructions.text.trim(),
          'items': [for (final i in items) i.toJson()],
        }));
    if (!mounted) return;
    setState(() => _sending = false);
    if (r == null) return;
    cart.clear();
    final token = '${r['tracking_url']}'.split('t=').last;
    context.go('/track/${r['order_number']}?t=$token&new=1');
  }

  // ------------------------------------------------------------ UI
  @override
  Widget build(BuildContext context) {
    if (_error != null) {
      return Scaffold(
        appBar: AppBar(),
        body: ErrorState(message: _error!, onRetry: () {
          setState(() => _error = null);
          _load();
        }),
      );
    }
    if (_shop == null) return const Scaffold(body: Center(child: CircularProgressIndicator()));
    final brand = hexColor(_shop!['theme_color']);
    return PopScope(
      canPop: _step == 0 || (widget.cvToken != null && _step == 2),
      onPopInvokedWithResult: (did, _) {
        if (!did) _go(_step - 1);
      },
      child: Scaffold(
        appBar: AppBar(title: Text('${_shop!['name']}', overflow: TextOverflow.ellipsis)),
        body: Column(children: [
          _StepHeader(steps: steps, current: _step, color: brand),
          Expanded(
            child: ListView(padding: const EdgeInsets.fromLTRB(16, 8, 16, 24), children: switch (_step) {
              0 => _stepService(brand),
              1 => _stepFiles(),
              2 => _stepOptions(),
              3 => _stepReview(),
              _ => _stepSend(),
            }),
          ),
          _bottomBar(brand),
        ]),
      ),
    );
  }

  Widget _bottomBar(Color brand) {
    final p = _price;
    final auto = p != null && p['pricing_mode'] == 'auto';
    return Material(
      elevation: 8,
      child: SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 10, 16, 10),
          child: Row(children: [
            Expanded(
              child: _item == null || _step == 0
                  ? const SizedBox.shrink()
                  : Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisSize: MainAxisSize.min, children: [
                      Text(p == null ? '—' : (auto ? money(p['total']) : 'Price on review'), style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w900)),
                      Text(p == null ? 'Calculating…' : (auto ? 'Estimate${asDouble(p['minimum_adjustment'])! > 0 ? ' · incl. minimum' : ''}' : 'The shop sends the price'), style: const TextStyle(fontSize: 11, color: Color(0xFF64748B))),
                    ]),
            ),
            if (_step > 0 && !(widget.cvToken != null && _step == 2)) TextButton(onPressed: () => _go(_step - 1), child: const Text('Back')),
            const SizedBox(width: 6),
            if (_step < 4)
              FilledButton(style: FilledButton.styleFrom(backgroundColor: brand), onPressed: () => _go(_step + 1), child: Text(_step == 3 ? 'Continue' : 'Next'))
            else
              FilledButton(style: FilledButton.styleFrom(backgroundColor: brand), onPressed: _sending ? null : _submit, child: _sending ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2)) : const Text('Send order')),
          ]),
        ),
      ),
    );
  }

  List<Widget> _stepService(Color brand) {
    final it = _item;
    return [
      const SectionTitle('What would you like to print?'),
      for (final c in _cats)
        Card(
          color: it?.category['id'] == c['id'] ? brand.withValues(alpha: .08) : null,
          child: ListTile(
            leading: Icon(categoryIcon(c['icon'] as String?), color: brand),
            title: Text('${c['name']}', style: const TextStyle(fontWeight: FontWeight.w700)),
            subtitle: Text('${c['description'] ?? ''}', maxLines: 2, overflow: TextOverflow.ellipsis),
            trailing: it?.category['id'] == c['id'] ? Icon(Icons.check_circle, color: brand) : null,
            onTap: () => _selectCategory(c),
          ),
        ),
      if (it != null && (it.category['subcategories'] as List).length > 1) ...[
        SectionTitle('Choose ${it.category['name'].toString().toLowerCase()} type'),
        Wrap(spacing: 8, runSpacing: 8, children: [
          for (final s in (it.category['subcategories'] as List).cast<Map<String, dynamic>>())
            ChoiceChip(
              label: Text('${s['name']}'),
              selected: it.subcategoryId == s['id'],
              onSelected: (_) {
                setState(() {
                  it.subcategoryId = s['id'] as int;
                  it.applySubPreset();
                });
                _go(1);
              },
            ),
        ]),
      ],
    ];
  }

  List<Widget> _stepFiles() {
    final it = _item!;
    if (it.cvToken != null) {
      return [Card(child: ListTile(leading: const Icon(Icons.badge_outlined), title: Text(it.cvTitle ?? 'CV'), subtitle: Text('${it.cvPages} page(s) · A4 PDF from the CV maker')))];
    }
    final single = it.files.length == 1 ? it.files.first : null;
    return [
      SectionTitle('Upload your ${it.layout['fit'] == 'id_pair' ? 'card photos (front and back)' : 'files'}'),
      if (it.category['accepts_upload'] != true)
        const Card(child: Padding(padding: EdgeInsets.all(14), child: Text('No upload needed for this service — describe what you need in the options step.'))),
      if (it.category['accepts_upload'] == true)
        InkWell(
          borderRadius: BorderRadius.circular(16),
          onTap: _pickFiles,
          child: Container(
            padding: const EdgeInsets.all(24),
            decoration: BoxDecoration(border: Border.all(color: const Color(0xFF94A3B8), width: 1.5), borderRadius: BorderRadius.circular(16), color: const Color(0xFFF8FAFC)),
            child: Column(children: [
              const Icon(Icons.cloud_upload_outlined, size: 44, color: Color(0xFF0F766E)),
              const SizedBox(height: 8),
              const Text('Tap to choose files', style: TextStyle(fontWeight: FontWeight.w800)),
              Text('PDF, photos (JPG, PNG) or Word (.docx) · up to ${_shop!['max_upload_mb']} MB each', textAlign: TextAlign.center, style: const TextStyle(fontSize: 12, color: Color(0xFF64748B))),
            ]),
          ),
        ),
      const SizedBox(height: 8),
      for (final f in it.files)
        Card(
          child: Padding(
            padding: const EdgeInsets.all(10),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Row(children: [
                SizedBox(
                  width: 52,
                  height: 52,
                  child: f.kind == 'image' || (f.server == null && RegExp(r'\.(jpe?g|png)$', caseSensitive: false).hasMatch(f.name))
                      ? ClipRRect(borderRadius: BorderRadius.circular(8), child: Image.memory(f.bytes, fit: BoxFit.cover, cacheWidth: 120))
                      : Icon(f.name.toLowerCase().endsWith('.pdf') ? Icons.picture_as_pdf : Icons.description, size: 36, color: const Color(0xFF0F766E)),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text(f.name, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w700)),
                    Text(
                      f.error ?? (f.uploading ? 'Uploading…' : '${(f.bytes.length / 1024).round()} KB${f.pages != null ? ' · ${f.pages} page(s)' : ''}${f.kind == 'image' && f.server?['width'] != null ? ' · ${f.server!['width']}×${f.server!['height']}px' : ''}'),
                      style: TextStyle(fontSize: 12, color: f.error != null ? const Color(0xFFDC2626) : const Color(0xFF64748B)),
                    ),
                    if (f.uploading) const Padding(padding: EdgeInsets.only(top: 4), child: LinearProgressIndicator()),
                  ]),
                ),
                IconButton(tooltip: 'Remove', icon: const Icon(Icons.close), onPressed: () => _removeFile(f)),
              ]),
              if (f.server?['needs_page_count'] == true)
                Padding(
                  padding: const EdgeInsets.only(top: 6),
                  child: Row(children: [
                    const Expanded(child: Text('The shop prints Word files from the original. How many pages?', style: TextStyle(fontSize: 12, color: Color(0xFFB45309)))),
                    SizedBox(
                      width: 80,
                      child: TextFormField(
                        initialValue: f.manualPages?.toString(),
                        keyboardType: TextInputType.number,
                        inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                        decoration: const InputDecoration(isDense: true, hintText: 'Pages'),
                        onChanged: (v) {
                          f.manualPages = int.tryParse(v);
                          _schedulePrice();
                        },
                      ),
                    ),
                  ]),
                ),
              if (f.server != null)
                Wrap(spacing: 8, children: [
                  if (f.kind == 'image' && single == f)
                    TextButton.icon(
                      icon: const Icon(Icons.crop_rotate, size: 18),
                      label: Text(f.edit.crop != null || f.edit.rotate != 0 || f.edit.flip ? 'Edited · change' : 'Crop / rotate'),
                      onPressed: () => _editImage(f),
                    ),
                  if (single == f && (f.kind == 'pdf' || (f.kind == 'docx' && f.server?['converted'] == true)) && (f.pages ?? 0) > 0)
                    TextButton.icon(
                      icon: const Icon(Icons.auto_stories_outlined, size: 18),
                      label: Text(it.pageSelection.isEmpty ? 'Select / rotate / reorder pages' : '${it.pageSelection.length} page(s) selected · change'),
                      onPressed: () => _choosePages(f),
                    ),
                ]),
            ]),
          ),
        ),
      if (it.files.length > 1) const Text('With several files, each is printed with the same options. To set pages or crop a photo, upload one file per item.', style: TextStyle(fontSize: 12, color: Color(0xFF64748B))),
      const SizedBox(height: 8),
      const Text('PDFs and photos are printed as uploaded; Word files are converted to PDF for the preview when possible. Complex Word layouts are printed by the shop from your original file.', style: TextStyle(fontSize: 11, color: Color(0xFF94A3B8))),
    ];
  }

  Widget _pills<T>(String label, List<(T, String)> items, T? value, ValueChanged<T> onSelect) => Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        SectionTitle(label),
        Wrap(spacing: 8, runSpacing: 8, children: [
          for (final i in items) ChoiceChip(label: Text(i.$2), selected: value == i.$1, onSelected: (_) => onSelect(i.$1)),
        ]),
      ]);

  Widget _stepper(String label, int value, ValueChanged<int> onChange, {int max = 1000}) => Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        SectionTitle(label),
        Row(mainAxisSize: MainAxisSize.min, children: [
          IconButton.filledTonal(onPressed: value > 1 ? () => onChange(value - 1) : null, icon: const Icon(Icons.remove)),
          SizedBox(width: 56, child: Text('$value', textAlign: TextAlign.center, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800))),
          IconButton.filledTonal(onPressed: value < max ? () => onChange(value + 1) : null, icon: const Icon(Icons.add)),
        ]),
      ]);

  List<Widget> _stepOptions() {
    final it = _item!;
    void upd(VoidCallback f) {
      setState(f);
      _schedulePrice();
    }

    final ps = it.opt('paper_size', it.paperSizeId);
    final layouts = <(String, String)>[('fit', 'Fit to page'), ('fill', 'Fill page'), ('actual', 'Actual size'), if (it.images > 0) ('grid', 'Photo sheet'), if (it.images >= 2) ('id_pair', 'Card front + back')];
    return [
      Text('Only options this shop offers for ${'${it.category['name']}'.toLowerCase()} are shown.', style: const TextStyle(color: Color(0xFF64748B), fontSize: 13)),
      if (it.opts('paper_size').isNotEmpty) _pills('Paper size', [for (final o in it.opts('paper_size')) (o['id'] as int, '${o['label']}')], it.paperSizeId, (v) => upd(() => it.paperSizeId = v)),
      if (ps?['code'] == 'custom')
        Padding(
          padding: const EdgeInsets.only(top: 10),
          child: Row(children: [
            Expanded(child: TextFormField(initialValue: it.customW?.round().toString(), keyboardType: TextInputType.number, decoration: const InputDecoration(labelText: 'Width (mm)', isDense: true), onChanged: (v) => upd(() => it.customW = double.tryParse(v)))),
            const SizedBox(width: 10),
            Expanded(child: TextFormField(initialValue: it.customH?.round().toString(), keyboardType: TextInputType.number, decoration: const InputDecoration(labelText: 'Height (mm)', isDense: true), onChanged: (v) => upd(() => it.customH = double.tryParse(v)))),
          ]),
        ),
      if (it.opts('color').isNotEmpty) _pills('Colour', [for (final o in it.opts('color')) ('${o['code']}', '${o['label']}')], it.color, (v) => upd(() => it.color = v)),
      if (it.opts('sides').length > 1 && it.basis == 'pages') _pills('Sides', [for (final o in it.opts('sides')) ('${o['code']}', '${o['label']}')], it.sides, (v) => upd(() => it.sides = v)),
      if (it.opts('paper_type').isNotEmpty) _pills('Paper type', [for (final o in it.opts('paper_type')) (o['id'] as int, '${o['label']}')], it.paperTypeId, (v) => upd(() => it.paperTypeId = it.paperTypeId == v ? null : v)),
      if (it.opts('gsm').isNotEmpty) _pills('Paper thickness (GSM)', [for (final o in it.opts('gsm')) (o['id'] as int, '${o['label']}')], it.gsmId, (v) => upd(() => it.gsmId = it.gsmId == v ? null : v)),
      if (it.basis == 'pages' && !it.sheetLayout) _pills('Orientation', const [('portrait', 'Portrait'), ('landscape', 'Landscape')], it.orientation, (v) => upd(() => it.orientation = v)),
      if (it.opts('finishing').isNotEmpty) ...[
        const SectionTitle('Finishing (optional)'),
        Wrap(spacing: 8, runSpacing: 8, children: [
          for (final o in it.opts('finishing'))
            FilterChip(label: Text('${o['label']}'), selected: it.finishing.contains(o['id']), onSelected: (v) => upd(() => v ? it.finishing.add(o['id'] as int) : it.finishing.remove(o['id']))),
        ]),
      ],
      if (it.files.isNotEmpty && it.cvToken == null) ...[
        _pills('Layout', layouts, '${it.layout['fit']}', (v) => upd(() {
              it.layout = {...it.layout, 'fit': v};
              if (v == 'grid') {
                it.layout['sheet'] ??= '4x6';
                it.layout['margin_mm'] = 3;
                if (it.quantity < 2) it.quantity = 8;
              }
            })),
        if (['fit', 'fill', 'actual'].contains(it.layout['fit'])) ...[
          Row(children: [const SizedBox(width: 90, child: Text('Margins')), Expanded(child: Slider(min: 0, max: 25, divisions: 25, value: asDouble(it.layout['margin_mm'])!.clamp(0, 25), label: '${asInt(it.layout['margin_mm'])} mm', onChanged: (v) => setState(() => it.layout['margin_mm'] = v.round())))]),
          if (it.layout['fit'] != 'fill') Row(children: [const SizedBox(width: 90, child: Text('Scale')), Expanded(child: Slider(min: 25, max: 200, divisions: 35, value: asDouble(it.layout['scale'] ?? 100)!.clamp(25, 200), label: '${asInt(it.layout['scale'] ?? 100)}%', onChanged: (v) => setState(() => it.layout['scale'] = v.round())))]),
        ],
        if (it.layout['fit'] == 'grid') _pills('Print sheet', const [('4x6', '4 × 6 photo paper'), ('a4', 'A4')], '${it.layout['sheet'] ?? '4x6'}', (v) => upd(() => it.layout['sheet'] = v)),
      ],
      Wrap(spacing: 24, children: [
        if (it.basis == 'items' || it.layout['fit'] == 'grid') _stepper(it.layout['fit'] == 'grid' ? 'Number of photos' : 'Quantity (pieces)', it.quantity, (v) => upd(() => it.quantity = v), max: 100000),
        if (it.basis == 'pages' && it.layout['fit'] != 'grid') _stepper('Copies', it.copies, (v) => upd(() => it.copies = v)),
      ]),
      if (it.basis == 'pages' && it.layout['fit'] != 'grid') Padding(padding: const EdgeInsets.only(top: 6), child: Text('${it.pages} page(s) per copy', style: const TextStyle(color: Color(0xFF64748B)))),
      const SectionTitle('Note for the shop (optional)'),
      TextFormField(initialValue: it.notes, maxLength: 500, decoration: const InputDecoration(hintText: 'e.g. staple at top-left'), onChanged: (v) => it.notes = v),
    ];
  }

  List<Widget> _stepReview() {
    final it = _item!;
    final p = _price;
    final lines = <Map<String, dynamic>>[];
    for (final i in ((p?['items'] as List?) ?? [])) {
      lines.addAll(((i['lines'] as List?) ?? []).cast<Map<String, dynamic>>());
    }
    return [
      const SectionTitle('Print preview'),
      if (it.files.isEmpty && it.cvToken == null) const Card(child: Padding(padding: EdgeInsets.all(14), child: Text('No files to preview.'))),
      if (it.cvToken != null) const Card(child: Padding(padding: EdgeInsets.all(14), child: Text('Your CV is printed exactly as the PDF from the CV maker.'))),
      if (_previewBusy) const Card(child: Padding(padding: EdgeInsets.all(30), child: Center(child: Column(children: [CircularProgressIndicator(), SizedBox(height: 10), Text('Preparing print-ready preview…')])))),
      if (_previewError != null) Card(color: const Color(0xFFFFFBEB), child: Padding(padding: const EdgeInsets.all(14), child: Text(_previewError!))),
      if (_preview != null && !_previewBusy)
        ClipRRect(borderRadius: BorderRadius.circular(16), child: SizedBox(height: 420, child: PdfPreviewView(key: ValueKey(_preview.hashCode), bytes: _preview!))),
      if (_preview != null) const Padding(padding: EdgeInsets.only(top: 6), child: Text('This is how your pages will be laid out on paper. Pinch to zoom.', style: TextStyle(fontSize: 12, color: Color(0xFF64748B)))),
      const SectionTitle('Price'),
      Card(
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: p == null
              ? const Text('Calculating…')
              : Column(children: [
                  if (p['pricing_mode'] == 'auto') ...[
                    for (final l in lines) Padding(padding: const EdgeInsets.symmetric(vertical: 2), child: Row(children: [Expanded(child: Text('${l['label']}', style: const TextStyle(fontSize: 13))), Text(money(l['amount']))])),
                    if (asDouble(p['minimum_adjustment'])! > 0) Row(children: [const Expanded(child: Text('Minimum order charge', style: TextStyle(fontSize: 13))), Text(money(p['minimum_adjustment']))]),
                    const Divider(),
                    Row(children: [const Expanded(child: Text('Estimated total', style: TextStyle(fontWeight: FontWeight.w800))), Text(money(p['total']), style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 18))]),
                  ],
                  Padding(padding: const EdgeInsets.only(top: 6), child: Text('${p['message'] ?? ''}', style: const TextStyle(fontSize: 12, color: Color(0xFF64748B)))),
                ]),
        ),
      ),
      const SectionTitle('Your order'),
      for (final c in [...cart, it])
        Card(
          child: ListTile(
            leading: Icon(categoryIcon(c.category['icon'] as String?), color: const Color(0xFF0F766E)),
            title: Text(c.title, maxLines: 1, overflow: TextOverflow.ellipsis),
            subtitle: Text('${c.category['name']} · ${c.basis == 'items' || c.layout['fit'] == 'grid' ? '${c.quantity} pcs' : '${c.pages} p × ${c.copies}'}'),
            trailing: c == it
                ? null
                : IconButton(icon: const Icon(Icons.delete_outline), onPressed: () {
                    setState(() => cart.remove(c));
                    _fetchPrice();
                  }),
          ),
        ),
      if (cart.length < 9)
        OutlinedButton.icon(
          icon: const Icon(Icons.add),
          label: const Text('Add another item'),
          onPressed: () {
            cart.add(it);
            setState(() {
              _item = null;
              _preview = null;
              _step = 0;
            });
          },
        ),
    ];
  }

  List<Widget> _stepSend() {
    final s = _shop!;
    final pay = switch (s['payment_mode']) {
      'advance_required' => 'Advance payment is required. After the shop accepts your order, pay using the shop’s instructions and upload the receipt.',
      'advance_optional' => 'Pay at the shop, or pay in advance after the shop accepts your order.',
      _ => 'Pay at the shop when you collect your prints.',
    };
    return [
      const SectionTitle('Your details'),
      TextField(controller: _name, textCapitalization: TextCapitalization.words, autofillHints: const [AutofillHints.name], decoration: const InputDecoration(labelText: 'Your name')),
      const SizedBox(height: 12),
      TextField(controller: _phone, keyboardType: TextInputType.phone, autofillHints: const [AutofillHints.telephoneNumber], decoration: const InputDecoration(labelText: 'Mobile number', hintText: '03xx xxxxxxx', helperText: 'The shop uses it to contact you about your order.')),
      const SizedBox(height: 12),
      TextField(controller: _instructions, maxLines: 3, maxLength: 2000, decoration: const InputDecoration(labelText: 'Instructions for the shop (optional)')),
      const SectionTitle('Payment'),
      Card(child: Padding(padding: const EdgeInsets.all(14), child: Text(pay))),
      const SizedBox(height: 8),
      Text('By sending, you agree the shop may view and print your files. Files are deleted automatically after ${s['retention_days']} days.', style: const TextStyle(fontSize: 12, color: Color(0xFF64748B))),
    ];
  }
}

class _StepHeader extends StatelessWidget {
  const _StepHeader({required this.steps, required this.current, required this.color});
  final List<String> steps;
  final int current;
  final Color color;
  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.fromLTRB(16, 4, 16, 8),
        child: Row(children: [
          for (var i = 0; i < steps.length; i++)
            Expanded(
              child: Column(children: [
                Container(height: 4, margin: const EdgeInsets.symmetric(horizontal: 2), decoration: BoxDecoration(color: i <= current ? color : const Color(0xFFE2E8F0), borderRadius: BorderRadius.circular(2))),
                const SizedBox(height: 4),
                Text(steps[i], style: TextStyle(fontSize: 11, fontWeight: i == current ? FontWeight.w800 : FontWeight.w500, color: i <= current ? const Color(0xFF0F172A) : const Color(0xFF94A3B8))),
              ]),
            ),
        ]),
      );
}
