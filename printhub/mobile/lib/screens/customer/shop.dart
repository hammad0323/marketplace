import 'package:flutter/services.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

import '../../state/session.dart';
import '../../util/format.dart';
import '../../util/share.dart';
import '../../widgets/common.dart';
import '../owner/more.dart' show hexColor;

IconData categoryIcon(String? icon) => switch (icon) {
      'file-earmark-text' => Icons.description_outlined,
      'mortarboard' => Icons.school_outlined,
      'person-vcard' => Icons.badge_outlined,
      'briefcase' => Icons.business_center_outlined,
      'camera' || 'image' => Icons.photo_camera_outlined,
      'stars' || 'magic' => Icons.auto_awesome_outlined,
      _ => Icons.print_outlined,
    };

/// Customer landing page reached by scanning the shop's QR code (or an App Link to /shop/{code}).
class ShopScreen extends StatelessWidget {
  const ShopScreen({super.key, required this.code});
  final String code;

  @override
  Widget build(BuildContext context) {
    final session = SessionScope.read(context);
    return Scaffold(
      body: AsyncView<Map<String, dynamic>>(
        load: () async {
          final d = await session.api.get('/shops/$code');
          await session.rememberShop(code);
          session.ensureCustomer().ignore();
          return d;
        },
        builder: (context, d, reload) {
          final s = d['shop'] as Map<String, dynamic>;
          final cats = (d['categories'] as List).cast<Map<String, dynamic>>();
          final brand = hexColor(s['theme_color']);
          final open = s['is_open'] == true;
          return CustomScrollView(slivers: [
            SliverAppBar(
              pinned: true,
              expandedHeight: 190,
              backgroundColor: brand,
              leading: homeLeading(context),
              foregroundColor: Colors.white,
              actions: [IconButton(tooltip: 'Share', icon: const Icon(Icons.share), onPressed: () => shareText('${s['name']} — print your files here: ${s['url']}'))],
              flexibleSpace: FlexibleSpaceBar(
                titlePadding: const EdgeInsetsDirectional.only(start: 56, bottom: 14, end: 56),
                title: Text('${s['name']}', maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16, color: Colors.white)),
                background: Container(
                  decoration: BoxDecoration(gradient: LinearGradient(colors: [brand, Color.lerp(brand, Colors.black, .35)!], begin: Alignment.topLeft, end: Alignment.bottomRight)),
                  padding: const EdgeInsets.fromLTRB(20, 70, 20, 50),
                  child: Row(children: [
                    if (s['logo_url'] != null) CircleAvatar(radius: 30, backgroundColor: Colors.white, backgroundImage: NetworkImage('${s['logo_url']}')),
                    if (s['logo_url'] != null) const SizedBox(width: 14),
                    Expanded(child: Text('${s['heading'] ?? 'Print anything in minutes'}', style: const TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.w800))),
                  ]),
                ),
              ),
            ),
            SliverPadding(
              padding: const EdgeInsets.all(16),
              sliver: SliverList.list(children: [
                Card(
                  margin: EdgeInsets.zero,
                  child: Padding(
                    padding: const EdgeInsets.all(14),
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Row(children: [
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                          decoration: BoxDecoration(color: (open ? const Color(0xFF16A34A) : const Color(0xFFDC2626)).withValues(alpha: .12), borderRadius: BorderRadius.circular(20)),
                          child: Text('${s['status_label']}', style: TextStyle(color: open ? const Color(0xFF16A34A) : const Color(0xFFDC2626), fontWeight: FontWeight.w700)),
                        ),
                        const SizedBox(width: 8),
                        if (open && s['estimated_minutes'] != null) Text('Usually ready in ~${s['estimated_minutes']} min', style: const TextStyle(color: Color(0xFF64748B), fontSize: 13)),
                      ]),
                      if (s['status_message'] != null) Padding(padding: const EdgeInsets.only(top: 6), child: Text('${s['status_message']}')),
                      if (s['message'] != null) Padding(padding: const EdgeInsets.only(top: 8), child: Text('${s['message']}')),
                      const SizedBox(height: 8),
                      Text([s['address'], s['area'], s['city']].whereType<String>().join(', '), style: const TextStyle(color: Color(0xFF475569))),
                      if (s['opening_hours'] != null) Text('Hours: ${s['opening_hours']}', style: const TextStyle(color: Color(0xFF475569))),
                      const SizedBox(height: 8),
                      Wrap(spacing: 8, children: [
                        if (s['phone'] != null) ActionChip(avatar: const Icon(Icons.call, size: 18), label: const Text('Call'), onPressed: () => callPhone('${s['phone']}')),
                        if (s['whatsapp'] != null || s['phone'] != null) ActionChip(avatar: const Icon(Icons.chat, size: 18), label: const Text('WhatsApp'), onPressed: () => whatsapp('${s['whatsapp'] ?? s['phone']}')),
                        if (s['latitude'] != null) ActionChip(avatar: const Icon(Icons.map_outlined, size: 18), label: const Text('Directions'), onPressed: () => openExternal('https://www.google.com/maps/search/?api=1&query=${s['latitude']},${s['longitude']}')),
                      ]),
                    ]),
                  ),
                ),
                if (s['wifi'] != null) _WifiCard(code: code, wifi: s['wifi'] as Map<String, dynamic>),
                const SectionTitle('What would you like to print?'),
                if (!open) const Card(color: Color(0xFFFEF2F2), child: Padding(padding: EdgeInsets.all(14), child: Text('This shop is not accepting online orders right now. Please visit or call the shop.'))),
                GridView.count(
                  crossAxisCount: MediaQuery.sizeOf(context).width > 600 ? 3 : 2,
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  mainAxisSpacing: 10,
                  crossAxisSpacing: 10,
                  childAspectRatio: 1.15,
                  children: [
                    for (final c in cats)
                      Card(
                        margin: EdgeInsets.zero,
                        child: InkWell(
                          borderRadius: BorderRadius.circular(16),
                          onTap: open ? () => context.push('/shop/$code/print?category=${c['id']}') : null,
                          child: Padding(
                            padding: const EdgeInsets.all(12),
                            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                              Container(padding: const EdgeInsets.all(8), decoration: BoxDecoration(color: brand.withValues(alpha: .1), borderRadius: BorderRadius.circular(12)), child: Icon(categoryIcon(c['icon'] as String?), color: brand)),
                              const Spacer(),
                              Text('${c['name']}', style: const TextStyle(fontWeight: FontWeight.w800)),
                              if (c['name_ur'] != null) Text('${c['name_ur']}', textDirection: TextDirection.rtl, style: const TextStyle(fontSize: 12, color: Color(0xFF64748B))),
                              Text('${c['description'] ?? ''}', maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 11, color: Color(0xFF64748B))),
                            ]),
                          ),
                        ),
                      ),
                  ],
                ),
                const SizedBox(height: 12),
                Card(
                  margin: EdgeInsets.zero,
                  child: ListTile(
                    leading: const Icon(Icons.badge_outlined, color: Color(0xFF0F766E)),
                    title: const Text('Need a CV?', style: TextStyle(fontWeight: FontWeight.w700)),
                    subtitle: const Text('Make one with free templates and print it here'),
                    trailing: const Icon(Icons.chevron_right),
                    onTap: () => context.push('/cv?shop=$code'),
                  ),
                ),
                const SizedBox(height: 12),
                Text(
                  '${s['pricing_mode'] == 'auto' ? 'Prices are calculated from this shop’s own rates' : 'The shop reviews your files and sends you the price'}'
                  '${asDouble(s['min_order_charge'])! > 0 ? ' · minimum order ${money(s['min_order_charge'])}' : ''}. Files are deleted after ${s['retention_days']} days.',
                  style: const TextStyle(fontSize: 12, color: Color(0xFF94A3B8)),
                ),
                const SizedBox(height: 20),
              ]),
            ),
          ]);
        },
      ),
    );
  }
}

class _WifiCard extends StatefulWidget {
  const _WifiCard({required this.code, required this.wifi});
  final String code;
  final Map<String, dynamic> wifi;
  @override
  State<_WifiCard> createState() => _WifiCardState();
}

class _WifiCardState extends State<_WifiCard> {
  Uint8List? _qr;
  @override
  Widget build(BuildContext context) {
    final w = widget.wifi;
    return Card(
      margin: const EdgeInsets.only(top: 12),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          const Row(children: [Icon(Icons.wifi, color: Color(0xFF0F766E)), SizedBox(width: 8), Text('Free Wi-Fi for customers', style: TextStyle(fontWeight: FontWeight.w800))]),
          const SizedBox(height: 8),
          Text('Network: ${w['ssid']}${w['hidden'] == true ? ' (hidden)' : ''}'),
          if (w['security'] != 'nopass')
            Row(children: [
              Expanded(child: Text('Password: ${w['password']}')),
              TextButton.icon(
                icon: const Icon(Icons.copy, size: 18),
                label: const Text('Copy'),
                onPressed: () {
                  Clipboard.setData(ClipboardData(text: '${w['password']}'));
                  showMessage(context, 'Password copied — open Wi-Fi settings and paste it.');
                },
              ),
            ]),
          if (_qr != null) Center(child: Image.memory(_qr!, width: 180, height: 180)),
          if (_qr == null)
            TextButton.icon(
              icon: const Icon(Icons.qr_code),
              label: const Text('Show Wi-Fi QR code'),
              onPressed: () async {
                final b = await runAction(context, () => SessionScope.read(context).api.bytes('/shops/${widget.code}/wifi.png'));
                if (b != null && mounted) setState(() => _qr = b);
              },
            ),
          const Text('Android and iPhone can join by scanning the Wi-Fi QR code with the camera from another phone, or by entering the password.', style: TextStyle(fontSize: 11, color: Color(0xFF94A3B8))),
        ]),
      ),
    );
  }
}
