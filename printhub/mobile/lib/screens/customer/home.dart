import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import '../../config.dart';
import '../../state/session.dart';

/// Extracts a shop code from a scanned QR (shop URL) or typed text.
String? shopCodeFrom(String raw) {
  final s = raw.trim();
  final m = RegExp(r'/shop/([A-Za-z0-9-]{6,20})').firstMatch(s) ?? RegExp(r'^([A-Za-z]{2}-?[A-Za-z0-9]{4,16})$').firstMatch(s);
  if (m == null) return null;
  var code = m.group(1)!.toUpperCase();
  if (!code.contains('-') && code.startsWith('PH')) code = 'PH-${code.substring(2)}';
  return code;
}

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});
  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  final _code = TextEditingController();

  void _open(String raw) {
    final code = shopCodeFrom(raw);
    if (code == null) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Enter the shop ID shown on the shop’s QR poster, e.g. PH-8GEJ9B')));
      return;
    }
    context.push('/shop/$code');
  }

  Future<void> _scan() async {
    final raw = await Navigator.of(context).push<String>(MaterialPageRoute(fullscreenDialog: true, builder: (_) => const ScanScreen()));
    if (raw != null && mounted) _open(raw);
  }

  @override
  Widget build(BuildContext context) {
    final session = SessionScope.of(context);
    return Scaffold(
      appBar: AppBar(
        title: Row(children: [const Icon(Icons.print_rounded, color: Color(0xFF0F766E)), const SizedBox(width: 8), Text(AppConfig.appName, style: const TextStyle(fontWeight: FontWeight.w900))]),
        actions: [TextButton.icon(onPressed: () => context.push(session.isOwner ? '/owner' : '/owner/login'), icon: const Icon(Icons.storefront_outlined), label: Text(session.isOwner ? 'My shop' : 'Shop owner'))],
      ),
      body: ListView(padding: const EdgeInsets.all(20), children: [
        Text('Print anything at a shop near you', style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w900)),
        const SizedBox(height: 6),
        const Text('Scan the QR code at the print shop, upload your files and collect your prints — no sign-up needed.', style: TextStyle(color: Color(0xFF64748B))),
        const SizedBox(height: 20),
          FilledButton.icon(
            style: FilledButton.styleFrom(minimumSize: const Size.fromHeight(56)),
            onPressed: _scan,
            icon: const Icon(Icons.qr_code_scanner),
            label: const Text('Scan shop QR code'),
          ),
        const SizedBox(height: 14),
        Row(children: [
          Expanded(
            child: TextField(
              controller: _code,
              textCapitalization: TextCapitalization.characters,
              decoration: const InputDecoration(labelText: 'Or enter shop ID', hintText: 'PH-XXXXXX', prefixIcon: Icon(Icons.storefront)),
              onSubmitted: _open,
            ),
          ),
          const SizedBox(width: 10),
          IconButton.filledTonal(onPressed: () => _open(_code.text), icon: const Icon(Icons.arrow_forward), iconSize: 28),
        ]),
        if (session.lastShopCode != null) ...[
          const SizedBox(height: 14),
          Card(child: ListTile(leading: const Icon(Icons.history), title: const Text('Continue with your last shop'), subtitle: Text(session.lastShopCode!), trailing: const Icon(Icons.chevron_right), onTap: () => context.push('/shop/${session.lastShopCode}'))),
        ],
        const SizedBox(height: 18),
        Row(children: [
          Expanded(child: _Tile(icon: Icons.receipt_long, label: 'My orders', onTap: () => context.push('/orders'))),
          const SizedBox(width: 10),
          Expanded(child: _Tile(icon: Icons.local_shipping_outlined, label: 'Track order', onTap: () => context.push('/track'))),
          const SizedBox(width: 10),
          Expanded(child: _Tile(icon: Icons.badge_outlined, label: 'CV maker', onTap: () => context.push(session.lastShopCode != null ? '/cv?shop=${session.lastShopCode}' : '/cv'))),
        ]),
        const SizedBox(height: 24),
        const Text('Your files are sent only to the shop you choose and deleted automatically after the shop’s retention period. They are never used for AI training.', style: TextStyle(fontSize: 12, color: Color(0xFF94A3B8))),
      ]),
    );
  }
}

class _Tile extends StatelessWidget {
  const _Tile({required this.icon, required this.label, required this.onTap});
  final IconData icon;
  final String label;
  final VoidCallback onTap;
  @override
  Widget build(BuildContext context) => Card(
        margin: EdgeInsets.zero,
        child: InkWell(
          borderRadius: BorderRadius.circular(16),
          onTap: onTap,
          child: Padding(padding: const EdgeInsets.symmetric(vertical: 18), child: Column(children: [Icon(icon, color: const Color(0xFF0F766E), size: 28), const SizedBox(height: 6), Text(label, textAlign: TextAlign.center, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13))])),
        ),
      );
}

class ScanScreen extends StatefulWidget {
  const ScanScreen({super.key});
  @override
  State<ScanScreen> createState() => _ScanScreenState();
}

class _ScanScreenState extends State<ScanScreen> {
  final _ctl = MobileScannerController(formats: const [BarcodeFormat.qrCode]);
  bool _done = false;

  @override
  void dispose() {
    _ctl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        backgroundColor: Colors.black,
        appBar: AppBar(title: const Text('Scan shop QR'), backgroundColor: Colors.black, foregroundColor: Colors.white, actions: [IconButton(icon: const Icon(Icons.flash_on), onPressed: _ctl.toggleTorch)]),
        body: Stack(children: [
          MobileScanner(
            controller: _ctl,
            onDetect: (capture) {
              if (_done) return;
              for (final b in capture.barcodes) {
                final v = b.rawValue;
                if (v != null && shopCodeFrom(v) != null) {
                  _done = true;
                  Navigator.pop(context, v);
                  return;
                }
              }
            },
            errorBuilder: (c, e) => Center(child: Padding(padding: const EdgeInsets.all(24), child: Text('Camera unavailable (${e.errorCode.name}). Type the shop ID instead.', textAlign: TextAlign.center, style: const TextStyle(color: Colors.white)))),
          ),
          Center(child: Container(width: 250, height: 250, decoration: BoxDecoration(border: Border.all(color: Colors.white, width: 3), borderRadius: BorderRadius.circular(24)))),
          const Positioned(left: 0, right: 0, bottom: 40, child: Text('Point at the PrintHub QR code at the shop', textAlign: TextAlign.center, style: TextStyle(color: Colors.white))),
        ]),
      );
}
