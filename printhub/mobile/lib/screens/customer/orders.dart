import 'dart:async';
import 'dart:typed_data';

import 'package:file_picker/file_picker.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

import '../../api/api.dart';
import '../../state/session.dart';
import '../../util/format.dart';
import '../../util/share.dart';
import '../../widgets/common.dart';
import '../../widgets/order_parts.dart';

class TrackLookupScreen extends StatefulWidget {
  const TrackLookupScreen({super.key});
  @override
  State<TrackLookupScreen> createState() => _TrackLookupScreenState();
}

class _TrackLookupScreenState extends State<TrackLookupScreen> {
  final _number = TextEditingController();
  final _phone = TextEditingController();

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Track an order')),
        body: ListView(padding: const EdgeInsets.all(20), children: [
          const Text('Enter your order number and the mobile number used for the order.'),
          const SizedBox(height: 16),
          TextField(controller: _number, textCapitalization: TextCapitalization.characters, decoration: const InputDecoration(labelText: 'Order number', hintText: 'PO…')),
          const SizedBox(height: 12),
          TextField(controller: _phone, keyboardType: TextInputType.phone, decoration: const InputDecoration(labelText: 'Mobile number')),
          const SizedBox(height: 20),
          FilledButton(
            onPressed: () {
              final n = _number.text.trim().toUpperCase();
              if (n.length < 6) return;
              context.push('/track/$n?phone=${Uri.encodeComponent(_phone.text.trim())}');
            },
            child: const Text('Track'),
          ),
        ]),
      );
}

class TrackScreen extends StatefulWidget {
  const TrackScreen({super.key, required this.number, this.token});
  final String number;
  final String? token;
  @override
  State<TrackScreen> createState() => _TrackScreenState();
}

class _TrackScreenState extends State<TrackScreen> {
  Map<String, dynamic>? _o;
  String? _error;
  Timer? _timer;
  late String? _token = widget.token;

  Api get api => SessionScope.read(context).api;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _load();
      final isNew = GoRouterState.of(context).uri.queryParameters['new'] == '1';
      if (isNew) showMessage(context, 'Order sent! The shop has been notified.');
    });
    _timer = Timer.periodic(const Duration(seconds: 20), (_) => _load());
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  Future<void> _load() async {
    final phone = GoRouterState.of(context).uri.queryParameters['phone'];
    try {
      final o = await api.get('/orders/${widget.number}', {if (_token != null) 't': _token, if (phone != null && phone.isNotEmpty) 'phone': phone});
      _token ??= '${o['tracking_url']}'.split('t=').last;
      if (mounted) {
        setState(() {
          _o = o;
          _error = null;
        });
      }
    } on ApiException catch (e) {
      if (mounted && _o == null) setState(() => _error = e.message);
    }
  }

  Future<void> _action(String action, String question) async {
    if (!await confirm(context, question)) return;
    if (!mounted) return;
    await SessionScope.read(context).ensureCustomer();
    if (!mounted) return;
    final r = await runAction(context, () => api.post('/orders/${widget.number}/action', {'action': action, 't': _token}), success: 'Order updated');
    if (r != null && mounted) setState(() => _o = r);
  }

  Future<void> _pay() async {
    var method = 'jazzcash';
    final ref = TextEditingController();
    Uint8List? proof;
    String? proofName;
    final ok = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (c) => StatefulBuilder(
        builder: (c, set) => Padding(
          padding: EdgeInsets.fromLTRB(20, 0, 20, MediaQuery.viewInsetsOf(c).bottom + 20),
          child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Text('I have paid', style: Theme.of(c).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800)),
            if (_o!['shop']['payment_instructions'] != null) Padding(padding: const EdgeInsets.only(top: 8), child: Text('${_o!['shop']['payment_instructions']}')),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              initialValue: method,
              decoration: const InputDecoration(labelText: 'Paid with'),
              items: [for (final m in methods.entries) if (m.key != 'cash') DropdownMenuItem(value: m.key, child: Text(m.value))],
              onChanged: (v) => method = v ?? method,
            ),
            const SizedBox(height: 12),
            TextField(controller: ref, decoration: const InputDecoration(labelText: 'Transaction ID / reference')),
            const SizedBox(height: 12),
            OutlinedButton.icon(
              icon: Icon(proof == null ? Icons.attach_file : Icons.check_circle),
              label: Text(proof == null ? 'Attach receipt screenshot (optional)' : proofName ?? 'Receipt attached'),
              onPressed: () async {
                final f = await FilePicker.pickFiles(type: FileType.custom, allowedExtensions: const ['jpg', 'jpeg', 'png', 'pdf']);
                if (f.isEmpty) return;
                final b = await f.first.readAsBytes();
                set(() {
                  proof = b;
                  proofName = f.first.name;
                });
              },
            ),
            const SizedBox(height: 16),
            FilledButton(onPressed: () => Navigator.pop(c, true), child: const Text('Send to shop')),
          ]),
        ),
      ),
    );
    if (ok != true || !mounted) return;
    final fields = {'method': method, 'reference': ref.text.trim(), 't': ?_token};
    final r = await runAction(
      context,
      () => proof != null ? api.upload('/orders/${widget.number}/payment', field: 'proof', bytes: proof!, filename: proofName ?? 'receipt.jpg', fields: fields) : api.post('/orders/${widget.number}/payment', fields),
      success: 'Thank you! The shop will confirm your payment.',
    );
    if (r != null && mounted) setState(() => _o = r);
  }

  @override
  Widget build(BuildContext context) {
    final o = _o;
    return Scaffold(
      appBar: AppBar(
        title: Text(widget.number),
        leading: BackButton(onPressed: () => context.canPop() ? context.pop() : context.go('/orders')),
        actions: [if (o != null) IconButton(tooltip: 'Share tracking link', icon: const Icon(Icons.share), onPressed: () => shareText('Track my print order ${o['order_number']}: ${o['tracking_url']}'))],
      ),
      body: _error != null
          ? ErrorState(message: _error!, onRetry: _load)
          : o == null
              ? const Center(child: CircularProgressIndicator())
              : RefreshIndicator(
                  onRefresh: _load,
                  child: ListView(padding: const EdgeInsets.all(16), children: [
                    Row(children: [Expanded(child: Text('${o['shop']['name']}', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16))), StatusChip('${o['status']}', '${o['status_label']}')]),
                    Text('Placed ${dateTime(o['created_at'])}', style: const TextStyle(color: Color(0xFF64748B))),
                    const SizedBox(height: 8),
                    AmountRow(o),
                    if (o['can_accept_quote'] == true)
                      Card(
                        color: const Color(0xFFEFF6FF),
                        child: Padding(
                          padding: const EdgeInsets.all(14),
                          child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                            Text('The shop quoted ${money(o['quoted_amount'])} for this order.', style: const TextStyle(fontWeight: FontWeight.w700)),
                            const SizedBox(height: 10),
                            Row(children: [
                              Expanded(child: FilledButton(onPressed: () => _action('accept_quote', 'Accept the price of ${money(o['quoted_amount'])}?'), child: const Text('Accept price'))),
                              const SizedBox(width: 10),
                              Expanded(child: OutlinedButton(onPressed: () => _action('decline_quote', 'Decline the price and cancel the order?'), child: const Text('Decline'))),
                            ]),
                          ]),
                        ),
                      ),
                    if (o['can_submit_payment'] == true)
                      Card(
                        color: const Color(0xFFFFFBEB),
                        child: ListTile(
                          leading: const Icon(Icons.account_balance_wallet_outlined),
                          title: const Text('Advance payment', style: TextStyle(fontWeight: FontWeight.w700)),
                          subtitle: Text('${o['shop']['payment_instructions'] ?? 'Pay using the shop’s instructions, then tell the shop.'}'),
                          trailing: FilledButton(onPressed: _pay, child: const Text('I have paid')),
                        ),
                      ),
                    if (o['status'] == 'ready')
                      const Card(color: Color(0xFFF0FDF4), child: ListTile(leading: Icon(Icons.inventory_2, color: Color(0xFF16A34A)), title: Text('Your prints are ready for collection!', style: TextStyle(fontWeight: FontWeight.w800)))),
                    const SectionTitle('Progress'),
                    OrderSteps(o),
                    const SectionTitle('Items'),
                    for (final it in (o['items'] as List).cast<Map<String, dynamic>>()) OrderItemCard(it, showFiles: false),
                    PaymentsList(o),
                    const SectionTitle('Shop'),
                    Card(
                      child: Column(children: [
                        ListTile(leading: const Icon(Icons.place_outlined), title: Text('${o['shop']['address'] ?? ''}')),
                        if (o['shop']['phone'] != null) ListTile(leading: const Icon(Icons.call), title: Text('${o['shop']['phone']}'), onTap: () => callPhone('${o['shop']['phone']}')),
                        ListTile(leading: const Icon(Icons.chat), title: const Text('WhatsApp the shop'), onTap: () => whatsapp('${o['shop']['whatsapp'] ?? o['shop']['phone']}', 'About my order ${o['order_number']}')),
                        ListTile(leading: const Icon(Icons.add_shopping_cart), title: const Text('New order at this shop'), onTap: () => context.go('/shop/${o['shop']['shop_code']}')),
                      ]),
                    ),
                    if (o['can_cancel'] == true)
                      TextButton(
                        style: TextButton.styleFrom(foregroundColor: const Color(0xFFDC2626)),
                        onPressed: () => _action('cancel', 'Cancel this order?'),
                        child: const Text('Cancel order'),
                      ),
                    const Text('This page updates automatically.', textAlign: TextAlign.center, style: TextStyle(fontSize: 12, color: Color(0xFF94A3B8))),
                  ]),
                ),
    );
  }
}

class MyOrdersScreen extends StatelessWidget {
  const MyOrdersScreen({super.key});
  @override
  Widget build(BuildContext context) {
    final session = SessionScope.read(context);
    return Scaffold(
      appBar: AppBar(title: const Text('My orders'), leading: homeLeading(context), actions: [IconButton(tooltip: 'Track by number', icon: const Icon(Icons.search), onPressed: () => context.push('/track'))]),
      body: AsyncView<Map<String, dynamic>>(
        load: () async {
          await session.ensureCustomer();
          return session.api.get('/customer/orders');
        },
        builder: (context, d, reload) {
          final orders = (d['orders'] as List).cast<Map<String, dynamic>>();
          if (orders.isEmpty) {
            return EmptyState(icon: Icons.receipt_long_outlined, title: 'No orders on this device yet', message: 'Scan a shop’s QR code to place your first order.', action: FilledButton(onPressed: () => context.go('/'), child: const Text('Find a shop')));
          }
          return ListView(padding: const EdgeInsets.all(16), children: [
            for (final o in orders)
              OrderSummaryTile(
                number: '${o['order_number']}',
                status: '${o['status']}',
                statusLabel: '${o['status_label']}',
                subtitle: '${o['shop_name']}\n${dateTime(o['created_at'])}',
                amount: o['amount'] as num?,
                onTap: () async {
                  await context.push('/track/${o['order_number']}?t=${o['tracking_token']}');
                  reload();
                },
              ),
          ]);
        },
      ),
    );
  }
}
