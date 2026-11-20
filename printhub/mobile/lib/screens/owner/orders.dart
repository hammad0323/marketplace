import 'dart:async';

import 'package:flutter/services.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

import '../../api/api.dart';
import '../../main.dart';
import '../../state/session.dart';
import '../../util/format.dart';
import '../../util/share.dart';
import '../../widgets/common.dart';
import '../../widgets/order_parts.dart';

class OrderTile extends StatelessWidget {
  const OrderTile(this.o, {super.key, this.onReturn});
  final Map<String, dynamic> o;
  final VoidCallback? onReturn;
  @override
  Widget build(BuildContext context) => OrderSummaryTile(
        number: '${o['order_number']}',
        status: '${o['status']}',
        statusLabel: '${o['status_label']}',
        subtitle: '${o['customer_name']} · ${o['categories'] ?? ''}\n${timeAgo(o['created_at'])}${o['source'] == 'app' ? ' · app' : ''}',
        amount: o['amount'] as num?,
        onTap: () async {
          await context.push('/owner/orders/${o['id']}');
          onReturn?.call();
        },
      );
}

class OrdersTab extends StatefulWidget {
  const OrdersTab({super.key});
  @override
  State<OrdersTab> createState() => _OrdersTabState();
}

class _OrdersTabState extends State<OrdersTab> {
  static const filters = {'new': 'New', 'active': 'In progress', 'ready': 'Ready', 'today': 'Today', 'completed': 'Completed', 'closed': 'Rejected / cancelled', '': 'All'};
  String _status = 'new';
  String _q = '';
  final _items = <Map<String, dynamic>>[];
  int _page = 1;
  int _pages = 1;
  bool _loading = false;
  String? _error;
  Timer? _debounce;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final s = GoRouterState.of(context).uri.queryParameters['status'];
    if (s != null && filters.containsKey(s) && s != _status) {
      _status = s;
      _load(reset: true);
    } else if (_items.isEmpty && !_loading) {
      _load(reset: true);
    }
    NotifierScope.of(context).removeListener(_onNotify);
    NotifierScope.of(context).addListener(_onNotify);
  }

  void _onNotify() {
    if (mounted) _load(reset: true);
  }

  Future<void> _load({bool reset = false}) async {
    if (reset) _page = 1;
    setState(() => _loading = true);
    try {
      final d = await SessionScope.read(context).api.get('/owner/orders', {'status': _status, 'q': _q, 'page': _page});
      if (!mounted) return;
      setState(() {
        if (reset) _items.clear();
        _items.addAll((d['items'] as List).cast<Map<String, dynamic>>());
        _pages = asInt(d['pages'], 1);
        _error = null;
      });
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) => Column(children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
          child: TextField(
            decoration: const InputDecoration(hintText: 'Search order no., name or mobile', prefixIcon: Icon(Icons.search), isDense: true),
            onChanged: (v) {
              _debounce?.cancel();
              _debounce = Timer(const Duration(milliseconds: 400), () {
                _q = v.trim();
                _load(reset: true);
              });
            },
          ),
        ),
        SizedBox(
          height: 48,
          child: ListView(scrollDirection: Axis.horizontal, padding: const EdgeInsets.symmetric(horizontal: 12), children: [
            for (final f in filters.entries)
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 4),
                child: ChoiceChip(label: Text(f.value), selected: _status == f.key, onSelected: (_) {
                  setState(() => _status = f.key);
                  _load(reset: true);
                }),
              ),
          ]),
        ),
        Expanded(
          child: RefreshIndicator(
            onRefresh: () => _load(reset: true),
            child: _error != null && _items.isEmpty
                ? ErrorState(message: _error!, onRetry: () => _load(reset: true))
                : _items.isEmpty && !_loading
                    ? const EmptyState(icon: Icons.receipt_long_outlined, title: 'No orders here', message: 'New orders appear automatically.')
                    : ListView.builder(
                        padding: const EdgeInsets.fromLTRB(16, 4, 16, 16),
                        itemCount: _items.length + 1,
                        itemBuilder: (c, i) {
                          if (i < _items.length) return OrderTile(_items[i], onReturn: () => _load(reset: true));
                          if (_loading) return const Padding(padding: EdgeInsets.all(20), child: Center(child: CircularProgressIndicator()));
                          if (_page < _pages) {
                            return TextButton(onPressed: () {
                              _page++;
                              _load();
                            }, child: const Text('Load more'));
                          }
                          return const SizedBox(height: 20);
                        },
                      ),
          ),
        ),
      ]);
}

class OwnerOrderScreen extends StatefulWidget {
  const OwnerOrderScreen({super.key, required this.id});
  final int id;
  @override
  State<OwnerOrderScreen> createState() => _OwnerOrderScreenState();
}

class _OwnerOrderScreenState extends State<OwnerOrderScreen> {
  final _view = GlobalKey<AsyncViewState<Map<String, dynamic>>>();
  Map<String, dynamic>? _override;

  Future<void> _act(Map<String, dynamic> order, String action, String label) async {
    final api = SessionScope.read(context).api;
    final body = await _askDetails(order, action, label);
    if (body == null || !mounted) return;
    final r = await runAction(context, () => api.post('/owner/orders/${widget.id}/action', {'action': action, ...body}), success: 'Order updated');
    if (r != null && mounted) setState(() => _override = r);
  }

  /// Collects the amount / reason / payment method an action needs; null when cancelled.
  Future<Map<String, dynamic>?> _askDetails(Map<String, dynamic> o, String action, String label) async {
    final needsAmount = action == 'quote' || (action == 'accept' && o['amount'] == null);
    final optionalAmount = action == 'accept' || action == 'confirm_payment' || action == 'complete';
    final needsReason = action == 'reject';
    final needsMethod = action == 'confirm_payment' || (action == 'complete' && o['payment_status'] != 'paid');
    if (!needsAmount && !optionalAmount && !needsReason) {
      return await confirm(context, '$label?', yes: label) ? {} : null;
    }
    final amount = TextEditingController(text: o['amount'] != null && action != 'quote' ? '${(o['amount'] as num).round()}' : '');
    final note = TextEditingController();
    var method = 'cash';
    final key = GlobalKey<FormState>();
    return showModalBottomSheet<Map<String, dynamic>>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (c) => Padding(
        padding: EdgeInsets.fromLTRB(20, 0, 20, MediaQuery.viewInsetsOf(c).bottom + 20),
        child: StatefulBuilder(
          builder: (c, set) => Form(
            key: key,
            child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              Text(label, style: Theme.of(c).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800)),
              const SizedBox(height: 14),
              if (needsAmount || optionalAmount)
                TextFormField(
                  controller: amount,
                  keyboardType: const TextInputType.numberWithOptions(decimal: true),
                  inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[\d.]'))],
                  decoration: InputDecoration(labelText: needsAmount ? 'Price (PKR)' : 'Final amount (PKR, optional)', prefixText: 'Rs. '),
                  validator: (v) => needsAmount && (double.tryParse(v ?? '') ?? 0) <= 0 ? 'Enter the price' : null,
                ),
              if (needsMethod) ...[
                const SizedBox(height: 12),
                DropdownButtonFormField<String>(
                  initialValue: method,
                  decoration: const InputDecoration(labelText: 'Payment method'),
                  items: [for (final m in methods.entries) DropdownMenuItem(value: m.key, child: Text(m.value))],
                  onChanged: (v) => set(() => method = v ?? 'cash'),
                ),
              ],
              const SizedBox(height: 12),
              TextFormField(
                controller: note,
                maxLines: 2,
                decoration: InputDecoration(labelText: needsReason ? 'Reason for the customer' : 'Note for the customer (optional)'),
                validator: (v) => needsReason && (v ?? '').trim().isEmpty ? 'Please give a reason' : null,
              ),
              const SizedBox(height: 16),
              FilledButton(
                style: needsReason ? FilledButton.styleFrom(backgroundColor: const Color(0xFFDC2626)) : null,
                onPressed: () {
                  if (!key.currentState!.validate()) return;
                  Navigator.pop(c, {
                    if (amount.text.trim().isNotEmpty) 'amount': double.parse(amount.text.trim()),
                    if (needsReason) 'reason': note.text.trim() else 'note': note.text.trim(),
                    if (needsMethod) 'method': method,
                  });
                },
                child: Text(label),
              ),
            ]),
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final api = SessionScope.read(context).api;
    return Scaffold(
      appBar: AppBar(title: const Text('Order')),
      body: AsyncView<Map<String, dynamic>>(
        key: _view,
        load: () async {
          _override = null;
          return api.get('/owner/orders/${widget.id}');
        },
        builder: (context, loaded, reload) {
          final o = _override ?? loaded;
          final actions = (o['owner_actions'] as List).map((a) => (a as List).cast<String>()).toList();
          final phone = '${o['customer_phone'] ?? ''}';
          return ListView(padding: const EdgeInsets.all(16), children: [
            Row(children: [
              Expanded(child: Text('${o['order_number']}', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800))),
              StatusChip('${o['status']}', '${o['status_label']}'),
            ]),
            Text('${dateTime(o['created_at'])} · ${o['source'] == 'app' ? 'App' : 'Web'} · ${o['pricing_mode'] == 'auto' ? 'Auto price' : 'Quote'}', style: const TextStyle(color: Color(0xFF64748B))),
            const SizedBox(height: 12),
            Card(
              child: ListTile(
                leading: const CircleAvatar(child: Icon(Icons.person)),
                title: Text('${o['customer_name']}'),
                subtitle: Text(phone),
                trailing: Row(mainAxisSize: MainAxisSize.min, children: [
                  IconButton(tooltip: 'Call', icon: const Icon(Icons.call), onPressed: () => callPhone(phone)),
                  IconButton(tooltip: 'WhatsApp', icon: const Icon(Icons.chat), onPressed: () => whatsapp(phone, 'Assalam o Alaikum, about your print order ${o['order_number']}:')),
                ]),
              ),
            ),
            AmountRow(o),
            if (o['special_instructions'] != null) Card(child: ListTile(leading: const Icon(Icons.sticky_note_2_outlined), title: const Text('Instructions'), subtitle: Text('${o['special_instructions']}'))),
            if (o['files_deleted'] == true) const Card(color: Color(0xFFFFFBEB), child: ListTile(leading: Icon(Icons.auto_delete_outlined), title: Text('Files were deleted after the retention period.'))),
            if (actions.isNotEmpty) ...[
              const SectionTitle('Actions'),
              Wrap(spacing: 8, runSpacing: 8, children: [
                for (final a in actions)
                  a[0] == 'reject'
                      ? OutlinedButton(style: OutlinedButton.styleFrom(foregroundColor: const Color(0xFFDC2626)), onPressed: () => _act(o, a[0], a[1]), child: Text(a[1]))
                      : FilledButton(onPressed: () => _act(o, a[0], a[1]), child: Text(a[1])),
              ]),
            ],
            const SectionTitle('Items & files'),
            for (final it in (o['items'] as List).cast<Map<String, dynamic>>()) OrderItemCard(it),
            PaymentsList(o),
            const SectionTitle('History'),
            OrderHistory(o),
          ]);
        },
      ),
    );
  }
}
