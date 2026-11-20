import 'dart:async';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/foundation.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';
import 'package:webview_flutter/webview_flutter.dart';

import '../../api/api.dart';
import '../../state/session.dart';
import '../../util/format.dart';
import '../../util/share.dart';
import '../../widgets/common.dart';
import '../owner/more.dart' show ColorField, hexColor;
import 'home.dart' show shopCodeFrom;

class CvListScreen extends StatelessWidget {
  const CvListScreen({super.key, this.shop});
  final String? shop;

  @override
  Widget build(BuildContext context) {
    final session = SessionScope.read(context);
    return Scaffold(
      appBar: AppBar(title: const Text('CV maker'), leading: homeLeading(context)),
      body: AsyncView<(List<Map<String, dynamic>>, List<Map<String, dynamic>>)>(
        load: () async {
          await session.ensureCustomer();
          final t = await session.api.get('/cv/templates');
          final l = await session.api.get('/cv');
          return ((t['templates'] as List).cast<Map<String, dynamic>>(), (l['cvs'] as List).cast<Map<String, dynamic>>());
        },
        builder: (context, d, reload) {
          final (templates, cvs) = d;
          return ListView(padding: const EdgeInsets.all(16), children: [
            const Text('Create a professional, ATS-friendly CV — no account needed. Download it as a PDF or print it at a shop.', style: TextStyle(color: Color(0xFF64748B))),
            if (cvs.isNotEmpty) ...[
              const SectionTitle('Your CVs on this device'),
              for (final cv in cvs)
                Card(
                  child: ListTile(
                    leading: Icon(Icons.description, color: hexColor(cv['accent_color'])),
                    title: Text('${cv['title']}'),
                    subtitle: Text('${cv['template_name']} · ${dateTime(cv['updated_at'])}'),
                    onTap: () async {
                      await context.push('/cv/${cv['token']}${shop != null ? '?shop=$shop' : ''}');
                      reload();
                    },
                    trailing: IconButton(
                      icon: const Icon(Icons.delete_outline),
                      onPressed: () async {
                        if (!await confirm(context, 'Delete "${cv['title']}"?', yes: 'Delete')) return;
                        if (!context.mounted) return;
                        final r = await runAction(context, () => session.api.delete('/cv/${cv['token']}'), success: 'CV deleted');
                        if (r != null) reload();
                      },
                    ),
                  ),
                ),
            ],
            const SectionTitle('Choose a template'),
            GridView.count(
              crossAxisCount: MediaQuery.sizeOf(context).width > 600 ? 3 : 2,
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              mainAxisSpacing: 10,
              crossAxisSpacing: 10,
              childAspectRatio: .9,
              children: [
                for (final t in templates)
                  Card(
                    margin: EdgeInsets.zero,
                    child: InkWell(
                      borderRadius: BorderRadius.circular(16),
                      onTap: () async {
                        final r = await runAction(context, () => session.api.post('/cv', {'template_id': t['id'], 'accent_color': t['default_color'], 'title': 'My CV'}));
                        if (r != null && context.mounted) {
                          await context.push('/cv/${r['token']}${shop != null ? '?shop=$shop' : ''}');
                          reload();
                        }
                      },
                      child: Padding(
                        padding: const EdgeInsets.all(12),
                        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                          Expanded(
                            child: Container(
                              width: double.infinity,
                              decoration: BoxDecoration(color: const Color(0xFFF8FAFC), borderRadius: BorderRadius.circular(10), border: Border.all(color: const Color(0xFFE2E8F0))),
                              child: t['preview_is_image'] == true
                                  ? ClipRRect(borderRadius: BorderRadius.circular(10), child: Image.network('${t['preview_url']}', fit: BoxFit.cover))
                                  : _MiniCv(color: hexColor(t['default_color']), photo: t['supports_photo'] == true),
                            ),
                          ),
                          const SizedBox(height: 8),
                          Text('${t['name']}', style: const TextStyle(fontWeight: FontWeight.w800)),
                          Text([t['category'], if (t['ats_friendly'] == true) 'ATS-friendly'].join(' · '), style: const TextStyle(fontSize: 11, color: Color(0xFF64748B))),
                        ]),
                      ),
                    ),
                  ),
              ],
            ),
          ]);
        },
      ),
    );
  }
}

/// Simple schematic thumbnail drawn with widgets (real previews are rendered by the server).
class _MiniCv extends StatelessWidget {
  const _MiniCv({required this.color, required this.photo});
  final Color color;
  final bool photo;
  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.all(10),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [
            if (photo) CircleAvatar(radius: 12, backgroundColor: color.withValues(alpha: .3)),
            if (photo) const SizedBox(width: 6),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Container(height: 7, width: 70, color: color), const SizedBox(height: 4), Container(height: 4, width: 50, color: const Color(0xFFCBD5E1))])),
          ]),
          const SizedBox(height: 10),
          for (var i = 0; i < 3; i++) ...[
            Container(height: 4, width: 40, color: color.withValues(alpha: .7)),
            const SizedBox(height: 4),
            for (var j = 0; j < 3; j++) Padding(padding: const EdgeInsets.only(bottom: 3), child: Container(height: 3, color: const Color(0xFFE2E8F0))),
            const SizedBox(height: 4),
          ],
        ]),
      );
}

/// Field definitions for repeatable CV sections.
const cvLists = <String, List<(String, String)>>{
  'experience': [('role', 'Job title'), ('company', 'Company'), ('location', 'City'), ('start', 'From'), ('end', 'To'), ('current', 'I work here now'), ('details', 'What you did')],
  'education': [('degree', 'Degree / class'), ('institute', 'School / college / university'), ('location', 'City'), ('start', 'From'), ('end', 'To'), ('grade', 'Grade / CGPA'), ('details', 'Details')],
  'projects': [('name', 'Project name'), ('link', 'Link'), ('details', 'Description')],
  'certifications': [('name', 'Certificate'), ('issuer', 'Issued by'), ('year', 'Year')],
  'languages': [('name', 'Language'), ('level', 'Level (e.g. Fluent)')],
  'references': [('name', 'Name'), ('position', 'Position'), ('company', 'Company'), ('phone', 'Phone'), ('email', 'Email')],
};

class CvEditorScreen extends StatefulWidget {
  const CvEditorScreen({super.key, required this.token});
  final String token;
  @override
  State<CvEditorScreen> createState() => _CvEditorScreenState();
}

class _CvEditorScreenState extends State<CvEditorScreen> with SingleTickerProviderStateMixin {
  Map<String, dynamic>? _cv;
  List<Map<String, dynamic>> _templates = [];
  Map<String, String> _labels = {};
  String? _error;
  String _state = 'Saved';
  Timer? _saveTimer;
  late final _tabs = TabController(length: 4, vsync: this);
  WebViewController? _web;
  Timer? _previewTimer;

  Api get api => SessionScope.read(context).api;
  Map<String, dynamic> get data => _cv!['data'] as Map<String, dynamic>;

  @override
  void initState() {
    super.initState();
    _tabs.addListener(() {
      if (_tabs.index == 3 && !_tabs.indexIsChanging) _refreshPreview();
    });
    if (!kIsWeb) _web = WebViewController()..setJavaScriptMode(JavaScriptMode.disabled);
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  @override
  void dispose() {
    _saveTimer?.cancel();
    _previewTimer?.cancel();
    if (_state == 'Unsaved changes') _save();
    super.dispose();
  }

  Future<void> _load() async {
    try {
      await SessionScope.read(context).ensureCustomer();
      final t = await api.get('/cv/templates');
      final cv = await api.get('/cv/${widget.token}');
      if (!mounted) return;
      setState(() {
        _templates = (t['templates'] as List).cast<Map<String, dynamic>>();
        _labels = (t['sections'] as Map).map((k, v) => MapEntry('$k', '$v'));
        _cv = cv;
      });
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    }
  }

  void _changed() {
    setState(() => _state = 'Unsaved changes');
    _saveTimer?.cancel();
    _saveTimer = Timer(const Duration(milliseconds: 1500), _save);
    if (_tabs.index == 3) {
      _previewTimer?.cancel();
      _previewTimer = Timer(const Duration(milliseconds: 600), _refreshPreview);
    }
  }

  Map<String, dynamic> get _body => {'title': _cv!['title'], 'template_id': _cv!['template_id'], 'accent_color': _cv!['accent_color'], 'data': data, 'section_order': _cv!['section_order']};

  Future<void> _save() async {
    final cv = _cv;
    if (cv == null) return;
    if (mounted) setState(() => _state = 'Saving…');
    try {
      final r = await api.post('/cv/${widget.token}', _body);
      cv['photo_url'] = r['photo_url'];
      if (mounted) setState(() => _state = 'Saved');
    } on ApiException catch (e) {
      if (mounted) setState(() => _state = 'Not saved: ${e.message}');
    }
  }

  Future<void> _refreshPreview() async {
    if (_cv == null) return;
    if (_web == null) return;
    try {
      final r = await api.post('/cv/preview', {..._body, 'token': widget.token});
      final html = '${r['html']}'.replaceFirst('<head>', '<head><meta name="viewport" content="width=820">');
      await _web!.loadHtmlString(html);
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.message, error: true);
    }
  }

  Future<void> _pdf() async {
    if (_state != 'Saved') await _save();
    if (!mounted) return;
    final b = await runAction(context, () => api.bytes('/cv/${widget.token}/pdf'));
    final name = '${data['personal']['full_name']}'.trim().replaceAll(RegExp(r'[^A-Za-z0-9]+'), '-');
    if (b != null) await shareBytes(b, '${name.isEmpty ? 'My' : name}-CV.pdf', 'application/pdf');
  }

  Future<void> _sendToShop() async {
    if (_state != 'Saved') await _save();
    if (!mounted) return;
    final session = SessionScope.read(context);
    var code = GoRouterState.of(context).uri.queryParameters['shop'] ?? session.lastShopCode;
    final ctl = TextEditingController(text: code ?? '');
    final picked = await showDialog<String>(
      context: context,
      builder: (c) => AlertDialog(
        title: const Text('Print at a shop'),
        content: Column(mainAxisSize: MainAxisSize.min, children: [
          const Text('Enter the shop ID from the shop’s QR poster.'),
          const SizedBox(height: 10),
          TextField(controller: ctl, textCapitalization: TextCapitalization.characters, decoration: const InputDecoration(labelText: 'Shop ID', hintText: 'PH-XXXXXX')),
        ]),
        actions: [TextButton(onPressed: () => Navigator.pop(c), child: const Text('Cancel')), FilledButton(onPressed: () => Navigator.pop(c, ctl.text), child: const Text('Continue'))],
      ),
    );
    code = picked == null ? null : shopCodeFrom(picked);
    if (code != null && mounted) context.push('/shop/$code/print?cv=${widget.token}');
  }

  Future<void> _photo() async {
    final f = await FilePicker.pickFiles(type: FileType.image);
    if (f.isEmpty || !mounted) return;
    final bytes = await f.first.readAsBytes();
    if (!mounted) return;
    final r = await runAction(context, () => api.upload('/cv/${widget.token}/photo', field: 'photo', bytes: bytes, filename: f.first.name), success: 'Photo added');
    if (r != null && mounted) {
      setState(() => _cv!['photo_url'] = r['photo_url']);
      if (_tabs.index == 3) _refreshPreview();
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_error != null) return Scaffold(appBar: AppBar(), body: ErrorState(message: _error!, onRetry: _load));
    if (_cv == null) return const Scaffold(body: Center(child: CircularProgressIndicator()));
    return Scaffold(
      appBar: AppBar(
        title: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text('${_cv!['title']}', overflow: TextOverflow.ellipsis),
          Text(_state, style: const TextStyle(fontSize: 12, color: Color(0xFF64748B), fontWeight: FontWeight.w400)),
        ]),
        actions: [
          IconButton(tooltip: 'Download / share PDF', icon: const Icon(Icons.picture_as_pdf_outlined), onPressed: _pdf),
          IconButton(tooltip: 'Print at a shop', icon: const Icon(Icons.local_printshop_outlined), onPressed: _sendToShop),
        ],
        bottom: TabBar(controller: _tabs, isScrollable: true, tabAlignment: TabAlignment.start, tabs: const [Tab(text: 'Details'), Tab(text: 'Sections'), Tab(text: 'Design'), Tab(text: 'Preview')]),
      ),
      body: TabBarView(controller: _tabs, children: [_details(), _sections(), _design(), _preview()]),
    );
  }

  Widget _field(Map<String, dynamic> map, String key, String label, {int lines = 1, TextInputType? type, String? hint}) => Padding(
        padding: const EdgeInsets.only(bottom: 12),
        child: TextFormField(
          initialValue: '${map[key] ?? ''}',
          maxLines: lines,
          minLines: 1,
          keyboardType: lines > 1 ? TextInputType.multiline : type,
          decoration: InputDecoration(labelText: label, hintText: hint, alignLabelWithHint: lines > 1),
          onChanged: (v) {
            map[key] = v;
            _changed();
          },
        ),
      );

  Widget _details() {
    final p = data['personal'] as Map<String, dynamic>;
    final tpl = _templates.where((t) => t['id'] == _cv!['template_id']).firstOrNull;
    return ListView(padding: const EdgeInsets.all(16), children: [
      TextFormField(
        initialValue: '${_cv!['title']}',
        decoration: const InputDecoration(labelText: 'CV name (for you)'),
        onChanged: (v) {
          _cv!['title'] = v;
          _changed();
        },
      ),
      const SectionTitle('Personal details'),
      if (tpl?['supports_photo'] == true)
        ListTile(
          contentPadding: EdgeInsets.zero,
          leading: CircleAvatar(radius: 28, backgroundImage: _cv!['photo_url'] != null ? NetworkImage('${_cv!['photo_url']}') : null, child: _cv!['photo_url'] == null ? const Icon(Icons.person) : null),
          title: const Text('Profile photo (optional)'),
          subtitle: const Text('Square photo with a plain background works best'),
          trailing: Row(mainAxisSize: MainAxisSize.min, children: [
            IconButton(icon: const Icon(Icons.upload), onPressed: _photo),
            if (_cv!['photo_url'] != null)
              IconButton(
                icon: const Icon(Icons.delete_outline),
                onPressed: () async {
                  final r = await runAction(context, () => api.delete('/cv/${widget.token}/photo'));
                  if (r != null && mounted) setState(() => _cv!['photo_url'] = null);
                },
              ),
          ]),
        ),
      _field(p, 'full_name', 'Full name'),
      _field(p, 'title', 'Job title / headline', hint: 'e.g. Accountant, BS Computer Science student'),
      _field(p, 'phone', 'Phone', type: TextInputType.phone),
      _field(p, 'email', 'Email', type: TextInputType.emailAddress),
      _field(p, 'city', 'City'),
      _field(p, 'address', 'Address (optional)'),
      _field(p, 'linkedin', 'LinkedIn (optional)'),
      _field(p, 'website', 'Website / portfolio (optional)'),
      const SectionTitle('Professional summary'),
      _field(p, 'summary', 'Two or three sentences about you', lines: 5),
    ]);
  }

  Widget _sections() {
    Widget listSection(String key) {
      final rows = (data[key] as List).cast<Map<String, dynamic>>();
      final fields = cvLists[key]!;
      return Card(
        child: ExpansionTile(
          shape: const Border(),
          title: Text(_labels[key] ?? key, style: const TextStyle(fontWeight: FontWeight.w700)),
          subtitle: Text('${rows.length} entr${rows.length == 1 ? 'y' : 'ies'}'),
          childrenPadding: const EdgeInsets.fromLTRB(12, 0, 12, 12),
          children: [
            for (var i = 0; i < rows.length; i++)
              Container(
                key: ObjectKey(rows[i]),
                margin: const EdgeInsets.only(bottom: 10),
                padding: const EdgeInsets.fromLTRB(12, 12, 4, 0),
                decoration: BoxDecoration(border: Border.all(color: const Color(0xFFE2E8F0)), borderRadius: BorderRadius.circular(12)),
                child: Column(children: [
                  Row(children: [
                    Expanded(child: Text('#${i + 1}', style: const TextStyle(fontWeight: FontWeight.w700, color: Color(0xFF64748B)))),
                    if (i > 0)
                      IconButton(tooltip: 'Move up', icon: const Icon(Icons.arrow_upward, size: 18), onPressed: () {
                        setState(() => rows.insert(i - 1, rows.removeAt(i)));
                        _changed();
                      }),
                    IconButton(tooltip: 'Remove', icon: const Icon(Icons.delete_outline, size: 18), onPressed: () {
                      setState(() => rows.removeAt(i));
                      _changed();
                    }),
                  ]),
                  for (final f in fields)
                    if (f.$1 == 'current')
                      CheckboxListTile(contentPadding: EdgeInsets.zero, dense: true, title: Text(f.$2), value: rows[i]['current'] == true, onChanged: (v) {
                        setState(() => rows[i]['current'] = v);
                        _changed();
                      })
                    else
                      Padding(padding: const EdgeInsets.only(right: 8), child: _field(rows[i], f.$1, f.$2, lines: f.$1 == 'details' ? 4 : 1)),
                ]),
              ),
            OutlinedButton.icon(icon: const Icon(Icons.add), label: Text('Add ${(_labels[key] ?? key).toLowerCase()}'), onPressed: () {
              setState(() => rows.add({for (final f in fields) f.$1: f.$1 == 'current' ? false : ''}));
              _changed();
            }),
          ],
        ),
      );
    }

    Widget linesSection(String key, String help) {
      final list = (data[key] as List).map((e) => '$e').toList();
      return Card(
        child: ExpansionTile(
          shape: const Border(),
          title: Text(_labels[key] ?? key, style: const TextStyle(fontWeight: FontWeight.w700)),
          subtitle: Text('${list.length} item(s)'),
          childrenPadding: const EdgeInsets.fromLTRB(12, 0, 12, 12),
          children: [
            TextFormField(
              initialValue: list.join('\n'),
              maxLines: 8,
              minLines: 3,
              decoration: InputDecoration(helperText: help, alignLabelWithHint: true),
              onChanged: (v) {
                data[key] = v.split('\n').map((s) => s.trim()).where((s) => s.isNotEmpty).toList();
                _changed();
              },
            ),
          ],
        ),
      );
    }

    return ListView(padding: const EdgeInsets.all(12), children: [
      listSection('experience'),
      listSection('education'),
      linesSection('skills', 'One skill per line, e.g. MS Excel'),
      listSection('projects'),
      listSection('certifications'),
      listSection('languages'),
      linesSection('achievements', 'One achievement per line'),
      listSection('references'),
      SwitchListTile(
        title: const Text('Show "References available on request"'),
        value: data['references_on_request'] == true,
        onChanged: (v) {
          setState(() => data['references_on_request'] = v);
          _changed();
        },
      ),
    ]);
  }

  Widget _design() {
    final order = (_cv!['section_order'] as List).map((e) => '$e').toList();
    return ListView(padding: const EdgeInsets.all(16), children: [
      const SectionTitle('Template'),
      SizedBox(
        height: 46,
        child: ListView(scrollDirection: Axis.horizontal, children: [
          for (final t in _templates)
            Padding(
              padding: const EdgeInsets.only(right: 8),
              child: ChoiceChip(
                label: Text('${t['name']}'),
                selected: _cv!['template_id'] == t['id'],
                onSelected: (_) {
                  setState(() {
                    _cv!['template_id'] = t['id'];
                    _cv!['accent_color'] = t['default_color'];
                  });
                  _changed();
                },
              ),
            ),
        ]),
      ),
      ColorField(label: 'Accent colour', value: '${_cv!['accent_color']}', onChanged: (v) {
        setState(() => _cv!['accent_color'] = v);
        _changed();
      }),
      const SectionTitle('Section order'),
      const Text('Drag to reorder. Empty sections are hidden automatically.', style: TextStyle(fontSize: 12, color: Color(0xFF64748B))),
      ReorderableListView(
        shrinkWrap: true,
        physics: const NeverScrollableScrollPhysics(),
        buildDefaultDragHandles: true,
        onReorderItem: (a, b) {
          setState(() {
            order.insert(b, order.removeAt(a));
            _cv!['section_order'] = order;
          });
          _changed();
        },
        children: [for (final k in order) ListTile(key: ValueKey(k), leading: const Icon(Icons.drag_indicator), title: Text(_labels[k] ?? k))],
      ),
      const SizedBox(height: 8),
      const Text('Urdu labels and right-to-left layout are prepared in the template system; CV content is currently English.', style: TextStyle(fontSize: 11, color: Color(0xFF94A3B8))),
    ]);
  }

  Widget _preview() {
    if (_web == null) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            const Text('Download the PDF to see the final CV.', textAlign: TextAlign.center),
            const SizedBox(height: 12),
            FilledButton.icon(onPressed: _pdf, icon: const Icon(Icons.picture_as_pdf), label: const Text('Get PDF')),
          ]),
        ),
      );
    }
    return Column(children: [
      Expanded(child: Container(color: const Color(0xFFE2E8F0), child: WebViewWidget(controller: _web!))),
      SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Row(children: [
            Expanded(child: OutlinedButton.icon(onPressed: _pdf, icon: const Icon(Icons.share), label: const Text('PDF / share / print'))),
            const SizedBox(width: 10),
            Expanded(child: FilledButton.icon(onPressed: _sendToShop, icon: const Icon(Icons.local_printshop), label: const Text('Print at a shop'))),
          ]),
        ),
      ),
    ]);
  }
}
