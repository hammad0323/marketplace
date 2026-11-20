import 'package:file_picker/file_picker.dart';
import 'package:flutter/services.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

import '../../state/session.dart';
import '../../util/format.dart';
import '../../util/share.dart';
import '../../widgets/common.dart';

Color hexColor(String? h, [Color fallback = const Color(0xFF0F766E)]) {
  final s = (h ?? '').replaceFirst('#', '');
  final v = int.tryParse(s, radix: 16);
  return s.length == 6 && v != null ? Color(0xFF000000 | v) : fallback;
}

String colorHex(Color c) => '#${(c.toARGB32() & 0xFFFFFF).toRadixString(16).padLeft(6, '0').toUpperCase()}';

/// Colour picker made of swatches plus a hex field (no extra dependency).
class ColorField extends StatelessWidget {
  const ColorField({super.key, required this.label, required this.value, required this.onChanged});
  final String label;
  final String value;
  final ValueChanged<String> onChanged;
  static const swatches = ['#0F172A', '#FFFFFF', '#0F766E', '#14B8A6', '#2563EB', '#7C3AED', '#DB2777', '#DC2626', '#EA580C', '#CA8A04', '#16A34A', '#475569'];
  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 6),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(label, style: const TextStyle(fontWeight: FontWeight.w600)),
          const SizedBox(height: 6),
          Wrap(spacing: 8, runSpacing: 8, crossAxisAlignment: WrapCrossAlignment.center, children: [
            for (final s in swatches)
              InkWell(
                onTap: () => onChanged(s),
                customBorder: const CircleBorder(),
                child: Container(
                  width: 30,
                  height: 30,
                  decoration: BoxDecoration(color: hexColor(s), shape: BoxShape.circle, border: Border.all(color: value.toUpperCase() == s ? const Color(0xFF0F766E) : const Color(0xFFCBD5E1), width: value.toUpperCase() == s ? 3 : 1)),
                ),
              ),
            SizedBox(
              width: 110,
              child: TextFormField(
                key: ValueKey(value),
                initialValue: value,
                decoration: InputDecoration(isDense: true, prefixIcon: Icon(Icons.circle, color: hexColor(value), size: 18)),
                inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[#0-9A-Fa-f]')), LengthLimitingTextInputFormatter(7)],
                onFieldSubmitted: (v) => RegExp(r'^#[0-9A-Fa-f]{6}$').hasMatch(v) ? onChanged(v.toUpperCase()) : null,
              ),
            ),
          ]),
        ]),
      );
}

// ------------------------------------------------------------------ QR code
class QrTab extends StatefulWidget {
  const QrTab({super.key});
  @override
  State<QrTab> createState() => _QrTabState();
}

class _QrTabState extends State<QrTab> {
  Map<String, dynamic>? _cfg;
  Uint8List? _png;
  String? _error;
  bool _saving = false;
  final _heading = TextEditingController();
  final _sub = TextEditingController();

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  Future<void> _load() async {
    final api = SessionScope.read(context).api;
    try {
      final c = await api.get('/owner/qr');
      final png = await api.bytes('/owner/qr.png', {'scale': 12, 'v': c['version']});
      if (!mounted) return;
      setState(() {
        _cfg = c;
        _png = png;
        _heading.text = '${c['heading'] ?? ''}';
        _sub.text = '${c['subheading'] ?? ''}';
        _error = null;
      });
    } catch (e) {
      if (mounted) setState(() => _error = '$e');
    }
  }

  Future<void> _save() async {
    setState(() => _saving = true);
    final api = SessionScope.read(context).api;
    final r = await runAction(context, () => api.post('/owner/qr', {..._cfg!, 'heading': _heading.text, 'subheading': _sub.text}), success: 'QR design saved');
    if (r != null) await _load();
    if (mounted) setState(() => _saving = false);
  }

  void _fullscreen() => Navigator.of(context).push(MaterialPageRoute(
        fullscreenDialog: true,
        builder: (c) => Scaffold(
          backgroundColor: hexColor(_cfg!['bg_color'], Colors.white),
          appBar: AppBar(backgroundColor: Colors.transparent),
          body: SafeArea(
            child: Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Column(mainAxisSize: MainAxisSize.min, children: [
                  Text(_heading.text, textAlign: TextAlign.center, style: TextStyle(fontSize: 30, fontWeight: FontWeight.w900, color: hexColor(_cfg!['accent_color']))),
                  if (_sub.text.isNotEmpty) Padding(padding: const EdgeInsets.only(top: 6), child: Text(_sub.text, textAlign: TextAlign.center, style: TextStyle(fontSize: 16, color: hexColor(_cfg!['fg_color'])))),
                  const SizedBox(height: 20),
                  Flexible(child: Image.memory(_png!, filterQuality: FilterQuality.none)),
                  const SizedBox(height: 16),
                  Text('Shop ID: ${_cfg!['shop_code']}', style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: hexColor(_cfg!['fg_color']))),
                ]),
              ),
            ),
          ),
        ),
      ));

  @override
  Widget build(BuildContext context) {
    if (_error != null && _cfg == null) return ErrorState(message: _error!, onRetry: _load);
    if (_cfg == null) return const Center(child: CircularProgressIndicator());
    final c = _cfg!;
    final api = SessionScope.read(context).api;
    final code = '${c['shop_code']}';
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(padding: const EdgeInsets.all(16), children: [
        Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(children: [
              Text(_heading.text, textAlign: TextAlign.center, style: TextStyle(fontSize: 20, fontWeight: FontWeight.w900, color: hexColor(c['accent_color']))),
              const SizedBox(height: 8),
              if (_png != null) GestureDetector(onTap: _fullscreen, child: Image.memory(_png!, width: 240, height: 240, filterQuality: FilterQuality.none)),
              const SizedBox(height: 8),
              SelectableText('Shop ID: $code', style: const TextStyle(fontWeight: FontWeight.w800)),
              SelectableText('${c['url']}', style: const TextStyle(color: Color(0xFF64748B), fontSize: 12)),
              const SizedBox(height: 12),
              Wrap(spacing: 8, runSpacing: 8, alignment: WrapAlignment.center, children: [
                FilledButton.icon(icon: const Icon(Icons.fullscreen), label: const Text('Show to customer'), onPressed: _fullscreen),
                OutlinedButton.icon(icon: const Icon(Icons.image_outlined), label: const Text('Share PNG'), onPressed: () async {
                  final b = await runAction(context, () => api.bytes('/owner/qr.png', {'scale': 20, 'download': 1}));
                  if (b != null) await shareBytes(b, '$code-qr.png', 'image/png', text: 'Scan to print at our shop: ${c['url']}');
                }),
                OutlinedButton.icon(icon: const Icon(Icons.picture_as_pdf_outlined), label: const Text('Poster PDF'), onPressed: () async {
                  final b = await runAction(context, () => api.bytes('/owner/qr.pdf', {'download': 1}));
                  if (b != null) await shareBytes(b, '$code-poster.pdf', 'application/pdf');
                }),
                OutlinedButton.icon(icon: const Icon(Icons.link), label: const Text('Copy link'), onPressed: () {
                  Clipboard.setData(ClipboardData(text: '${c['url']}'));
                  showMessage(context, 'Link copied');
                }),
                OutlinedButton.icon(icon: const Icon(Icons.share), label: const Text('Share link'), onPressed: () => shareText('Print your documents at our shop — upload here: ${c['url']} (Shop ID $code)')),
              ]),
            ]),
          ),
        ),
        const SectionTitle('Design'),
        Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              TextField(controller: _heading, maxLength: 80, decoration: const InputDecoration(labelText: 'Heading'), onChanged: (_) => setState(() {})),
              TextField(controller: _sub, maxLength: 160, decoration: const InputDecoration(labelText: 'Sub-heading')),
              ColorField(label: 'QR colour', value: '${c['fg_color']}', onChanged: (v) => setState(() => c['fg_color'] = v)),
              ColorField(label: 'Background', value: '${c['bg_color']}', onChanged: (v) => setState(() => c['bg_color'] = v)),
              ColorField(label: 'Accent (heading)', value: '${c['accent_color']}', onChanged: (v) => setState(() => c['accent_color'] = v)),
              SwitchListTile(
                contentPadding: EdgeInsets.zero,
                title: const Text('Shop logo in the centre'),
                subtitle: Text(c['has_logo'] == true ? 'Uses the logo from shop settings' : 'Upload a logo in Shop settings first'),
                value: c['show_logo'] == true,
                onChanged: c['has_logo'] == true ? (v) => setState(() => c['show_logo'] = v) : null,
              ),
              const Text('Keep strong contrast between the QR colour and background so every phone can scan it.', style: TextStyle(fontSize: 12, color: Color(0xFF64748B))),
              const SizedBox(height: 12),
              FilledButton(onPressed: _saving ? null : _save, child: const Text('Save design')),
              TextButton.icon(
                icon: const Icon(Icons.refresh),
                label: const Text('Regenerate QR code'),
                onPressed: () async {
                  if (!await confirm(context, 'Regenerate the QR image with default colours? Your shop ID and link stay the same, so printed codes keep working.', yes: 'Regenerate')) return;
                  if (!context.mounted) return;
                  final r = await runAction(context, () => api.post('/owner/qr/regenerate'));
                  if (r != null && context.mounted) {
                    showMessage(context, api.lastMessage);
                    await _load();
                  }
                },
              ),
            ]),
          ),
        ),
      ]),
    );
  }
}

// ------------------------------------------------------------------ Wi-Fi
class WifiScreen extends StatefulWidget {
  const WifiScreen({super.key});
  @override
  State<WifiScreen> createState() => _WifiScreenState();
}

class _WifiScreenState extends State<WifiScreen> {
  final _ssid = TextEditingController();
  final _pass = TextEditingController();
  String _security = 'WPA';
  bool _hidden = false;
  bool _public = false;
  bool _loaded = false;
  Uint8List? _qr;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  Future<void> _load() async {
    final api = SessionScope.read(context).api;
    final d = await runAction(context, () => api.get('/owner/wifi'));
    if (d == null || !mounted) return;
    setState(() {
      _ssid.text = '${d['ssid'] ?? ''}';
      _pass.text = '${d['password'] ?? ''}';
      _security = '${d['security'] ?? 'WPA'}';
      _hidden = d['is_hidden_network'] == true;
      _public = d['is_public'] == true;
      _loaded = true;
    });
    if (_ssid.text.isNotEmpty) {
      try {
        final b = await api.bytes('/owner/wifi/qr.png', {'t': DateTime.now().millisecondsSinceEpoch});
        if (mounted) setState(() => _qr = b);
      } catch (_) {}
    }
  }

  @override
  Widget build(BuildContext context) {
    final api = SessionScope.read(context).api;
    return Scaffold(
      appBar: AppBar(title: const Text('Wi-Fi sharing')),
      body: !_loaded
          ? const Center(child: CircularProgressIndicator())
          : ListView(padding: const EdgeInsets.all(16), children: [
              const Text('Customers can join your Wi-Fi to upload files faster. Details are shown on your shop page only when you switch on public sharing.', style: TextStyle(color: Color(0xFF64748B))),
              const SizedBox(height: 16),
              TextField(controller: _ssid, maxLength: 32, decoration: const InputDecoration(labelText: 'Network name (SSID)')),
              DropdownButtonFormField<String>(
                initialValue: _security,
                decoration: const InputDecoration(labelText: 'Security'),
                items: const [DropdownMenuItem(value: 'WPA', child: Text('WPA / WPA2 / WPA3')), DropdownMenuItem(value: 'WEP', child: Text('WEP (old)')), DropdownMenuItem(value: 'nopass', child: Text('Open (no password)'))],
                onChanged: (v) => setState(() => _security = v ?? 'WPA'),
              ),
              const SizedBox(height: 12),
              if (_security != 'nopass') TextField(controller: _pass, decoration: const InputDecoration(labelText: 'Password')),
              SwitchListTile(contentPadding: EdgeInsets.zero, title: const Text('Hidden network'), value: _hidden, onChanged: (v) => setState(() => _hidden = v)),
              SwitchListTile(
                contentPadding: EdgeInsets.zero,
                title: const Text('Show Wi-Fi details to customers'),
                subtitle: const Text('Shown on your shop page after scanning your QR code'),
                value: _public,
                onChanged: (v) => setState(() => _public = v),
              ),
              const SizedBox(height: 8),
              FilledButton(
                onPressed: () async {
                  final r = await runAction(context, () => api.post('/owner/wifi', {'ssid': _ssid.text.trim(), 'password': _pass.text, 'security': _security, 'is_hidden_network': _hidden, 'is_public': _public}), success: 'Wi-Fi settings saved');
                  if (r != null) await _load();
                },
                child: const Text('Save'),
              ),
              if (_qr != null) ...[
                const SectionTitle('Wi-Fi QR code'),
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(children: [
                      Image.memory(_qr!, width: 220, height: 220, filterQuality: FilterQuality.none),
                      const Text('Customers scan this with their camera to join the network.', textAlign: TextAlign.center, style: TextStyle(fontSize: 12, color: Color(0xFF64748B))),
                      const SizedBox(height: 10),
                      OutlinedButton.icon(icon: const Icon(Icons.share), label: const Text('Share / download'), onPressed: () => shareBytes(_qr!, 'wifi-qr.png', 'image/png')),
                    ]),
                  ),
                ),
              ],
            ]),
    );
  }
}

// ------------------------------------------------------------------ services
class ServicesScreen extends StatefulWidget {
  const ServicesScreen({super.key});
  @override
  State<ServicesScreen> createState() => _ServicesScreenState();
}

class _ServicesScreenState extends State<ServicesScreen> {
  static const groups = {'paper_size': 'Paper sizes', 'paper_type': 'Paper types', 'gsm': 'Paper weight (GSM)', 'finishing': 'Finishing'};
  Map<String, dynamic>? _d;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      final d = await runAction(context, () => SessionScope.read(context).api.get('/owner/services'));
      if (mounted) setState(() => _d = d);
    });
  }

  Future<void> _save() async {
    final cats = (_d!['categories'] as List).cast<Map<String, dynamic>>();
    final body = {
      'categories': {for (final c in cats) '${c['id']}': c['enabled'] == true},
      'subcategories': {for (final c in cats) for (final s in (c['subcategories'] as List)) '${s['id']}': s['enabled'] == true},
      'options': {for (final g in (_d!['options'] as Map).values) for (final o in (g as List)) '${o['id']}': o['enabled'] == true},
    };
    final r = await runAction(context, () => SessionScope.read(context).api.post('/owner/services', body), success: 'Services saved');
    if (r != null && mounted) setState(() => _d = r);
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Services'), actions: [if (_d != null) TextButton(onPressed: _save, child: const Text('Save'))]),
        body: _d == null
            ? const Center(child: CircularProgressIndicator())
            : ListView(padding: const EdgeInsets.all(12), children: [
                const SectionTitle('Categories'),
                for (final c in (_d!['categories'] as List).cast<Map<String, dynamic>>())
                  Card(
                    child: ExpansionTile(
                      shape: const Border(),
                      title: Text('${c['name']}', style: const TextStyle(fontWeight: FontWeight.w700)),
                      subtitle: Text('${c['description'] ?? ''}', maxLines: 1, overflow: TextOverflow.ellipsis),
                      leading: Switch(value: c['enabled'] == true, onChanged: (v) => setState(() => c['enabled'] = v)),
                      children: [
                        for (final s in (c['subcategories'] as List).cast<Map<String, dynamic>>())
                          CheckboxListTile(dense: true, title: Text('${s['name']}'), value: s['enabled'] == true, onChanged: c['enabled'] == true ? (v) => setState(() => s['enabled'] = v) : null),
                      ],
                    ),
                  ),
                for (final g in groups.entries) ...[
                  SectionTitle(g.value),
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(12),
                      child: Wrap(spacing: 8, runSpacing: 8, children: [
                        for (final o in ((_d!['options'] as Map)[g.key] as List? ?? []).cast<Map<String, dynamic>>())
                          FilterChip(label: Text('${o['label']}'), selected: o['enabled'] == true, onSelected: (v) => setState(() => o['enabled'] = v)),
                      ]),
                    ),
                  ),
                ],
                const SizedBox(height: 12),
                FilledButton(onPressed: _save, child: const Text('Save services')),
                const SizedBox(height: 24),
              ]),
      );
}

// ------------------------------------------------------------------ shop & settings
class ShopSettingsScreen extends StatefulWidget {
  const ShopSettingsScreen({super.key});
  @override
  State<ShopSettingsScreen> createState() => _ShopSettingsScreenState();
}

class _ShopSettingsScreenState extends State<ShopSettingsScreen> {
  Map<String, dynamic>? _shop;
  Map<String, dynamic>? _set;
  final _c = <String, TextEditingController>{};
  final _form = GlobalKey<FormState>();

  TextEditingController ctl(String k, Map<String, dynamic> src) => _c.putIfAbsent(k, () => TextEditingController(text: src[k] == null ? '' : '${src[k] is double && (src[k] as double) % 1 == 0 ? (src[k] as double).round() : src[k]}'));

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  Future<void> _load() async {
    final d = await runAction(context, () => SessionScope.read(context).api.get('/owner/shop'));
    if (d == null || !mounted) return;
    _c.clear();
    setState(() {
      _shop = d['shop'] as Map<String, dynamic>;
      _set = d['settings'] as Map<String, dynamic>;
    });
  }

  Future<void> _save() async {
    if (!_form.currentState!.validate()) return;
    final session = SessionScope.read(context);
    final api = session.api;
    final shopBody = {for (final k in ['name', 'phone', 'whatsapp', 'email', 'address', 'area', 'city']) k: _c[k]!.text.trim(), 'accepting_orders': _shop!['accepting_orders'] == true};
    final setBody = {
      for (final k in ['payment_instructions', 'opening_hours', 'landing_heading', 'landing_message']) k: _c[k]!.text.trim(),
      for (final k in ['min_order_charge', 'estimated_minutes', 'retention_days', 'max_upload_mb']) k: num.tryParse(_c[k]!.text.trim()) ?? 0,
      'pricing_mode': _set!['pricing_mode'],
      'payment_mode': _set!['payment_mode'],
    };
    final ok = await runAction(context, () async {
      await api.post('/owner/shop', shopBody);
      await api.post('/owner/settings', setBody);
      return true;
    }, success: 'Settings saved');
    if (ok == true) {
      await session.refreshOwner();
      await _load();
    }
  }

  Future<void> _logo() async {
    final files = await FilePicker.pickFiles(type: FileType.image);
    if (files.isEmpty || !mounted) return;
    final bytes = await files.first.readAsBytes();
    if (!mounted) return;
    final session = SessionScope.read(context);
    final r = await runAction(context, () => session.api.upload('/owner/shop/logo', field: 'logo', bytes: bytes, filename: files.first.name), success: 'Logo updated');
    if (r != null) {
      await session.refreshOwner();
      await _load();
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_shop == null) return Scaffold(appBar: AppBar(title: const Text('Shop & order settings')), body: const Center(child: CircularProgressIndicator()));
    final s = _shop!, st = _set!;
    Widget f(String k, String label, Map<String, dynamic> src, {bool number = false, int lines = 1, String? help, bool required = false}) => Padding(
          padding: const EdgeInsets.only(bottom: 12),
          child: TextFormField(
            controller: ctl(k, src),
            maxLines: lines,
            keyboardType: number ? TextInputType.number : null,
            decoration: InputDecoration(labelText: label, helperText: help, helperMaxLines: 2),
            validator: required ? (v) => (v ?? '').trim().length < 2 ? 'Required' : null : null,
          ),
        );
    return Scaffold(
      appBar: AppBar(title: const Text('Shop & order settings'), actions: [TextButton(onPressed: _save, child: const Text('Save'))]),
      body: Form(
        key: _form,
        child: ListView(padding: const EdgeInsets.all(16), children: [
          Card(
            child: SwitchListTile(
              title: const Text('Accepting orders', style: TextStyle(fontWeight: FontWeight.w700)),
              subtitle: const Text('Turn off when the shop is closed or busy'),
              value: s['accepting_orders'] == true,
              onChanged: (v) => setState(() => s['accepting_orders'] = v),
            ),
          ),
          ListTile(
            contentPadding: EdgeInsets.zero,
            leading: s['logo_url'] != null ? CircleAvatar(backgroundImage: NetworkImage('${s['logo_url']}')) : const CircleAvatar(child: Icon(Icons.store)),
            title: const Text('Shop logo'),
            trailing: OutlinedButton(onPressed: _logo, child: const Text('Upload')),
          ),
          const SectionTitle('Shop details'),
          f('name', 'Shop name', s, required: true),
          f('phone', 'Phone', s, required: true),
          f('whatsapp', 'WhatsApp (optional)', s),
          f('email', 'Email', s),
          f('address', 'Address', s, lines: 2, required: true),
          f('area', 'Area', s),
          f('city', 'City', s, required: true),
          const SectionTitle('Pricing & payments'),
          SegmentedButton<String>(
            segments: const [ButtonSegment(value: 'auto', label: Text('Automatic price')), ButtonSegment(value: 'quote', label: Text('I send a quote'))],
            selected: {'${st['pricing_mode']}'},
            onSelectionChanged: (v) => setState(() => st['pricing_mode'] = v.first),
          ),
          const Padding(padding: EdgeInsets.fromLTRB(4, 6, 4, 12), child: Text('Automatic uses your price list; services without a price always go to quote.', style: TextStyle(fontSize: 12, color: Color(0xFF64748B)))),
          DropdownButtonFormField<String>(
            initialValue: '${st['payment_mode']}',
            decoration: const InputDecoration(labelText: 'Payment'),
            items: const [
              DropdownMenuItem(value: 'pay_at_shop', child: Text('Pay at shop on collection')),
              DropdownMenuItem(value: 'advance_optional', child: Text('Advance payment optional')),
              DropdownMenuItem(value: 'advance_required', child: Text('Advance payment required')),
            ],
            onChanged: (v) => setState(() => st['payment_mode'] = v),
          ),
          const SizedBox(height: 12),
          f('payment_instructions', 'Payment instructions', st, lines: 3, help: 'e.g. JazzCash / Easypaisa / bank account details shown to customers'),
          f('min_order_charge', 'Minimum order charge (PKR)', st, number: true),
          f('estimated_minutes', 'Usual ready time (minutes)', st, number: true),
          const SectionTitle('Customer page'),
          f('landing_heading', 'Heading', st),
          f('landing_message', 'Message', st, lines: 2),
          f('opening_hours', 'Opening hours', st, help: 'e.g. Mon–Sat 9am–9pm'),
          const SectionTitle('Files'),
          f('retention_days', 'Delete customer files after (days)', st, number: true, help: 'Maximum ${st['max_retention_days']} days, set by PrintHub'),
          f('max_upload_mb', 'Maximum upload size (MB)', st, number: true, help: 'Platform limit ${st['platform_max_upload_mb']} MB'),
          FilledButton(onPressed: _save, child: const Text('Save settings')),
          const SizedBox(height: 24),
        ]),
      ),
    );
  }
}

class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key});
  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  late final u = SessionScope.read(context).user;
  late final _name = TextEditingController(text: '${u['name'] ?? ''}');
  late final _email = TextEditingController(text: '${u['email'] ?? ''}');
  late final _phone = TextEditingController(text: '${u['phone'] ?? ''}');

  @override
  Widget build(BuildContext context) {
    final session = SessionScope.read(context);
    return Scaffold(
      appBar: AppBar(title: const Text('My profile')),
      body: ListView(padding: const EdgeInsets.all(16), children: [
        TextField(enabled: false, controller: TextEditingController(text: '${u['username']}'), decoration: const InputDecoration(labelText: 'Username')),
        const SizedBox(height: 12),
        TextField(controller: _name, decoration: const InputDecoration(labelText: 'Name')),
        const SizedBox(height: 12),
        TextField(controller: _email, keyboardType: TextInputType.emailAddress, decoration: const InputDecoration(labelText: 'Email (for password resets)')),
        const SizedBox(height: 12),
        TextField(controller: _phone, keyboardType: TextInputType.phone, decoration: const InputDecoration(labelText: 'Mobile')),
        const SizedBox(height: 20),
        FilledButton(
          onPressed: () async {
            final r = await runAction(context, () => session.api.post('/auth/profile', {'name': _name.text.trim(), 'email': _email.text.trim(), 'phone': _phone.text.trim()}), success: 'Profile saved');
            if (r != null) await session.refreshOwner();
          },
          child: const Text('Save'),
        ),
        TextButton(onPressed: () => context.push('/owner/password'), child: const Text('Change password')),
      ]),
    );
  }
}

// ------------------------------------------------------------------ reports
class ReportsScreen extends StatefulWidget {
  const ReportsScreen({super.key});
  @override
  State<ReportsScreen> createState() => _ReportsScreenState();
}

class _ReportsScreenState extends State<ReportsScreen> {
  String _period = 'month';
  @override
  Widget build(BuildContext context) {
    final api = SessionScope.read(context).api;
    return Scaffold(
      appBar: AppBar(title: const Text('Reports')),
      body: Column(children: [
        Padding(
          padding: const EdgeInsets.all(12),
          child: SegmentedButton<String>(
            segments: const [ButtonSegment(value: 'day', label: Text('Today')), ButtonSegment(value: 'week', label: Text('7 days')), ButtonSegment(value: 'month', label: Text('30 days')), ButtonSegment(value: 'quarter', label: Text('90 days'))],
            selected: {_period},
            onSelectionChanged: (v) => setState(() => _period = v.first),
          ),
        ),
        Expanded(
          child: AsyncView<Map<String, dynamic>>(
            key: ValueKey(_period),
            load: () => api.get('/owner/reports', {'period': _period}),
            builder: (context, d, reload) {
              final daily = (d['daily'] as List).cast<Map<String, dynamic>>();
              final top = (d['top_categories'] as List).cast<Map<String, dynamic>>();
              return ListView(padding: const EdgeInsets.all(16), children: [
                GridView.count(crossAxisCount: 2, shrinkWrap: true, physics: const NeverScrollableScrollPhysics(), mainAxisSpacing: 10, crossAxisSpacing: 10, childAspectRatio: 2.2, children: [
                  StatTile(label: 'Orders', value: '${d['orders']}', icon: Icons.receipt_long),
                  StatTile(label: 'Revenue', value: money(d['revenue']), icon: Icons.payments_outlined, color: const Color(0xFF0891B2)),
                  StatTile(label: 'Completed', value: '${d['completed']}', icon: Icons.task_alt, color: const Color(0xFF16A34A)),
                  StatTile(label: 'Avg. order', value: money(d['average_order_value']), icon: Icons.functions, color: const Color(0xFF7C3AED)),
                ]),
                if (daily.length > 1) ...[
                  const SectionTitle('Daily revenue'),
                  Card(child: Padding(padding: const EdgeInsets.fromLTRB(12, 16, 12, 8), child: SizedBox(height: 180, child: BarChart(values: [for (final x in daily) asDouble(x['revenue']) ?? 0], labels: [for (final x in daily) '${x['date']}'.substring(8)])))),
                  const SectionTitle('Daily orders'),
                  Card(child: Padding(padding: const EdgeInsets.fromLTRB(12, 16, 12, 8), child: SizedBox(height: 140, child: BarChart(values: [for (final x in daily) asDouble(x['orders']) ?? 0], labels: [for (final x in daily) '${x['date']}'.substring(8)], color: const Color(0xFF7C3AED))))),
                ],
                const SectionTitle('Top categories'),
                Card(
                  child: Column(children: [
                    if (top.isEmpty) const ListTile(title: Text('No orders in this period')),
                    for (final t in top) ListTile(dense: true, title: Text('${t['name']}'), trailing: Text('${t['count']} orders', style: const TextStyle(fontWeight: FontWeight.w700))),
                  ]),
                ),
                const SizedBox(height: 8),
                Text('${d['cancelled']} cancelled or rejected · since ${date(d['from'])}', style: const TextStyle(color: Color(0xFF64748B), fontSize: 12)),
              ]);
            },
          ),
        ),
      ]),
    );
  }
}

/// Minimal bar chart (no chart dependency).
class BarChart extends StatelessWidget {
  const BarChart({super.key, required this.values, required this.labels, this.color = const Color(0xFF0F766E)});
  final List<double> values;
  final List<String> labels;
  final Color color;
  @override
  Widget build(BuildContext context) {
    final max = values.fold<double>(0, (a, b) => b > a ? b : a);
    final every = (values.length / 8).ceil().clamp(1, 100);
    return Row(crossAxisAlignment: CrossAxisAlignment.end, children: [
      for (var i = 0; i < values.length; i++)
        Expanded(
          child: Tooltip(
            message: '${labels[i]}: ${values[i].round()}',
            child: Column(mainAxisAlignment: MainAxisAlignment.end, children: [
              Flexible(
                child: FractionallySizedBox(
                  heightFactor: max == 0 ? 0.01 : (values[i] / max).clamp(0.01, 1.0),
                  child: Container(margin: const EdgeInsets.symmetric(horizontal: 1.5), decoration: BoxDecoration(color: color, borderRadius: const BorderRadius.vertical(top: Radius.circular(3)))),
                ),
              ),
              const SizedBox(height: 4),
              SizedBox(height: 14, child: Text(i % every == 0 ? labels[i] : '', style: const TextStyle(fontSize: 9, color: Color(0xFF94A3B8)))),
            ]),
          ),
        ),
    ]);
  }
}

// ------------------------------------------------------------------ subscription
class SubscriptionScreen extends StatelessWidget {
  const SubscriptionScreen({super.key});
  @override
  Widget build(BuildContext context) {
    final session = SessionScope.read(context);
    return Scaffold(
      appBar: AppBar(title: const Text('Subscription')),
      body: AsyncView<Map<String, dynamic>>(
        load: () async {
          final d = await session.api.get('/owner/subscription');
          await session.refreshOwner();
          return d;
        },
        builder: (context, d, reload) {
          final periods = (d['periods'] as List).cast<Map<String, dynamic>>();
          final pays = (d['payments'] as List).cast<Map<String, dynamic>>();
          final ok = d['state'] == 'active';
          return ListView(padding: const EdgeInsets.all(16), children: [
            Card(
              color: ok ? const Color(0xFFF0FDFA) : const Color(0xFFFEF2F2),
              child: Padding(
                padding: const EdgeInsets.all(18),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text('${d['label']}', style: TextStyle(fontSize: 22, fontWeight: FontWeight.w900, color: ok ? const Color(0xFF0F766E) : const Color(0xFFDC2626))),
                  const SizedBox(height: 6),
                  Text('Monthly fee: ${money(d['price'])}'),
                  if (d['end_date'] != null) Text('Paid until: ${date(d['end_date'])}${d['days_left'] != null ? ' (${d['days_left']} days left)' : ''}'),
                  if (d['grace_until'] != null && d['state'] != 'active') Text('Grace period until: ${date(d['grace_until'])}'),
                ]),
              ),
            ),
            const Card(
              child: Padding(
                padding: EdgeInsets.all(16),
                child: Text('To renew, pay the monthly fee to PrintHub by cash, bank transfer, JazzCash or Easypaisa and share the receipt with support. '
                    'Your subscription is extended as soon as the payment is confirmed.'),
              ),
            ),
            if ('${d['support_phone']}'.isNotEmpty || '${d['support_email']}'.isNotEmpty)
              Card(
                child: Column(children: [
                  if ('${d['support_phone']}'.isNotEmpty) ...[
                    ListTile(leading: const Icon(Icons.call), title: Text('${d['support_phone']}'), onTap: () => callPhone('${d['support_phone']}')),
                    ListTile(leading: const Icon(Icons.chat), title: const Text('WhatsApp support'), onTap: () => whatsapp('${d['support_phone']}', 'Subscription renewal for shop ${session.shop['shop_code']}')),
                  ],
                  if ('${d['support_email']}'.isNotEmpty) ListTile(leading: const Icon(Icons.email_outlined), title: Text('${d['support_email']}'), onTap: () => openExternal('mailto:${d['support_email']}')),
                ]),
              ),
            const SectionTitle('Billing periods'),
            Card(child: Column(children: [for (final p in periods) ListTile(dense: true, title: Text('${date(p['start_date'])} – ${date(p['end_date'])}'), subtitle: Text('${p['months']} month(s) · ${p['status']}'), trailing: Text(money(asDouble(p['price']))))])),
            if (pays.isNotEmpty) ...[
              const SectionTitle('Payments'),
              Card(child: Column(children: [for (final p in pays) ListTile(dense: true, title: Text(money(asDouble(p['amount']))), subtitle: Text('${p['method'] ?? ''} · ${date(p['paid_on'])}'), trailing: Text('${p['status'] ?? ''}'))])),
            ],
          ]);
        },
      ),
    );
  }
}

// ------------------------------------------------------------------ notifications
class OwnerNotificationsScreen extends StatelessWidget {
  const OwnerNotificationsScreen({super.key});
  @override
  Widget build(BuildContext context) {
    final api = SessionScope.read(context).api;
    final key = GlobalKey<AsyncViewState<Map<String, dynamic>>>();
    return Scaffold(
      appBar: AppBar(title: const Text('Notifications'), actions: [
        TextButton(
          onPressed: () async {
            await runAction(context, () => api.post('/owner/notifications/read'));
            key.currentState?.reload();
          },
          child: const Text('Mark all read'),
        ),
      ]),
      body: AsyncView<Map<String, dynamic>>(
        key: key,
        load: () => api.get('/owner/notifications', {'limit': 50}),
        builder: (context, d, reload) {
          final items = (d['items'] as List).cast<Map<String, dynamic>>();
          if (items.isEmpty) return const EmptyState(icon: Icons.notifications_none, title: 'No notifications yet');
          return ListView.separated(
            itemCount: items.length,
            separatorBuilder: (_, _) => const Divider(height: 1),
            itemBuilder: (c, i) {
              final n = items[i];
              return ListTile(
                tileColor: n['is_read'] == true ? null : const Color(0xFFF0FDFA),
                leading: Icon(n['is_read'] == true ? Icons.notifications_none : Icons.notifications_active, color: const Color(0xFF0F766E)),
                title: Text('${n['title']}', style: TextStyle(fontWeight: n['is_read'] == true ? FontWeight.w500 : FontWeight.w800)),
                subtitle: Text('${n['body'] ?? ''}\n${timeAgo(n['created_at'])}'),
                isThreeLine: true,
                onTap: () async {
                  api.post('/owner/notifications/read', {'id': n['id']}).ignore();
                  if (n['order_id'] != null) {
                    await context.push('/owner/orders/${n['order_id']}');
                  }
                  reload();
                },
              );
            },
          );
        },
      ),
    );
  }
}
