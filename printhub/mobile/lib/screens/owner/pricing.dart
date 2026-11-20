import 'package:flutter/services.dart';
import 'package:material_ui/material_ui.dart';

import '../../state/session.dart';
import '../../util/format.dart';
import '../../widgets/common.dart';


/// The shop's own price list. There are no market-wide default rates: every shop enters its prices.
class PricingScreen extends StatefulWidget {
  const PricingScreen({super.key});
  @override
  State<PricingScreen> createState() => _PricingScreenState();
}

class _PricingScreenState extends State<PricingScreen> {
  static const types = {'print': 'Printing (per page / sheet)', 'item': 'Item price (cards, photos…)', 'paper': 'Paper / GSM surcharge', 'finishing': 'Finishing (binding, lamination…)', 'discount': 'Bulk discount (%)'};
  Map<String, dynamic>? _d;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  Future<void> _load() async {
    final d = await runAction(context, () => SessionScope.read(context).api.get('/owner/pricing'));
    if (mounted && d != null) setState(() => _d = d);
  }

  List<Map<String, dynamic>> _opts(String group) => (_d!['options'] as List).cast<Map<String, dynamic>>().where((o) => o['option_group'] == group).toList();

  Future<void> _edit([Map<String, dynamic>? rule]) async {
    final r = Map<String, dynamic>.from(rule ?? {'rule_type': 'print', 'unit': 'per_page', 'min_qty': 1, 'is_active': true});
    final price = TextEditingController(text: rule == null ? '' : '${(rule['price'] as num) % 1 == 0 ? (rule['price'] as num).round() : rule['price']}');
    final minQty = TextEditingController(text: '${r['min_qty'] ?? 1}');
    final api = SessionScope.read(context).api;
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (c) => StatefulBuilder(builder: (c, set) {
        Widget drop(String key, String label, List<DropdownMenuItem<Object?>> items) => Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: DropdownButtonFormField<Object?>(
                initialValue: r[key],
                isExpanded: true,
                decoration: InputDecoration(labelText: label, isDense: true),
                items: [const DropdownMenuItem(value: null, child: Text('Any')), ...items],
                onChanged: (v) => set(() => r[key] = v),
              ),
            );
        List<DropdownMenuItem<Object?>> optItems(String g) => [for (final o in _opts(g)) DropdownMenuItem(value: o['id'], child: Text('${o['label']}'))];
        final type = r['rule_type'];
        return Padding(
          padding: EdgeInsets.fromLTRB(20, 0, 20, MediaQuery.viewInsetsOf(c).bottom + 20),
          child: SingleChildScrollView(
            child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              Text(rule == null ? 'Add price' : 'Edit price', style: Theme.of(c).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800)),
              const SizedBox(height: 12),
              DropdownButtonFormField<String>(
                initialValue: type,
                isExpanded: true,
                decoration: const InputDecoration(labelText: 'This price is for', isDense: true),
                items: [for (final t in types.entries) DropdownMenuItem(value: t.key, child: Text(t.value))],
                onChanged: (v) => set(() {
                  r['rule_type'] = v;
                  r['unit'] = v == 'discount' ? 'percent' : (v == 'item' ? 'per_item' : (v == 'finishing' ? 'per_copy' : 'per_page'));
                }),
              ),
              const SizedBox(height: 10),
              if (type == 'finishing') drop('finishing_id', 'Finishing service', optItems('finishing')),
              drop('category_id', 'Category', [for (final cat in (_d!['categories'] as List)) DropdownMenuItem(value: cat['id'], child: Text('${cat['name']}'))]),
              if (type != 'finishing') ...[
                drop('paper_size_id', 'Paper size', optItems('paper_size')),
                drop('color', 'Colour', const [DropdownMenuItem(value: 'bw', child: Text('Black & white')), DropdownMenuItem(value: 'color', child: Text('Colour'))]),
                drop('sides', 'Sides', const [DropdownMenuItem(value: 'single', child: Text('Single-sided')), DropdownMenuItem(value: 'double', child: Text('Double-sided'))]),
                drop('paper_type_id', 'Paper type', optItems('paper_type')),
                drop('gsm_id', 'GSM', optItems('gsm')),
              ],
              Row(children: [
                Expanded(
                  child: TextField(
                    controller: price,
                    keyboardType: const TextInputType.numberWithOptions(decimal: true),
                    inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[\d.]'))],
                    decoration: InputDecoration(labelText: type == 'discount' ? 'Discount %' : 'Price (PKR)', isDense: true),
                  ),
                ),
                const SizedBox(width: 10),
                if (type != 'discount')
                  Expanded(
                    child: DropdownButtonFormField<String>(
                      initialValue: r['unit'] as String?,
                      isExpanded: true,
                      decoration: const InputDecoration(labelText: 'Unit', isDense: true),
                      items: [for (final u in (_d!['units'] as Map).entries) if (u.key != 'percent') DropdownMenuItem(value: u.key as String, child: Text('${u.value}'))],
                      onChanged: (v) => set(() => r['unit'] = v),
                    ),
                  ),
              ]),
              const SizedBox(height: 10),
              TextField(controller: minQty, keyboardType: TextInputType.number, decoration: const InputDecoration(labelText: 'Applies from quantity', helperText: 'Use for bulk rates, e.g. 100+ pages at a lower price', isDense: true)),
              SwitchListTile(contentPadding: EdgeInsets.zero, title: const Text('Active'), value: r['is_active'] != false, onChanged: (v) => set(() => r['is_active'] = v)),
              FilledButton(
                onPressed: () async {
                  final body = {...r, 'price': double.tryParse(price.text) ?? -1, 'min_qty': int.tryParse(minQty.text) ?? 1}..remove('applies_to');
                  final res = await runAction(c, () => rule == null ? api.post('/owner/pricing', body) : api.post('/owner/pricing/${rule['id']}', body));
                  if (res != null && c.mounted) Navigator.pop(c, true);
                },
                child: const Text('Save price'),
              ),
              if (rule != null)
                TextButton(
                  style: TextButton.styleFrom(foregroundColor: const Color(0xFFDC2626)),
                  onPressed: () async {
                    if (!await confirm(c, 'Remove this price?', yes: 'Remove') || !c.mounted) return;
                    final res = await runAction(c, () => api.delete('/owner/pricing/${rule['id']}'));
                    if (res != null && c.mounted) Navigator.pop(c, true);
                  },
                  child: const Text('Remove'),
                ),
            ]),
          ),
        );
      }),
    );
    if (saved == true) await _load();
  }

  Future<void> _tester() async {
    final t = <String, dynamic>{'category_id': (_d!['categories'] as List).first['id'], 'paper_size_id': _opts('paper_size').firstOrNull?['id'], 'color': 'bw', 'sides': 'single', 'pages': 10, 'copies': 1, 'quantity': 1, 'finishing': <int>[]};
    Map<String, dynamic>? result;
    final api = SessionScope.read(context).api;
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (c) => StatefulBuilder(builder: (c, set) {
        Future<void> run() async {
          final r = await runAction(c, () => api.post('/owner/pricing/test', t));
          set(() => result = r);
        }

        Widget num(String k, String label) => Expanded(child: TextFormField(initialValue: '${t[k]}', keyboardType: TextInputType.number, decoration: InputDecoration(labelText: label, isDense: true), onChanged: (v) => t[k] = int.tryParse(v) ?? 1));
        final item = (result?['items'] as List?)?.firstOrNull as Map<String, dynamic>?;
        return Padding(
          padding: EdgeInsets.fromLTRB(20, 0, 20, MediaQuery.viewInsetsOf(c).bottom + 20),
          child: SingleChildScrollView(
            child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              Text('Test a price', style: Theme.of(c).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800)),
              const SizedBox(height: 12),
              DropdownButtonFormField<Object?>(initialValue: t['category_id'], isExpanded: true, decoration: const InputDecoration(labelText: 'Category', isDense: true), items: [for (final cat in (_d!['categories'] as List)) DropdownMenuItem(value: cat['id'], child: Text('${cat['name']}'))], onChanged: (v) => t['category_id'] = v),
              const SizedBox(height: 10),
              DropdownButtonFormField<Object?>(initialValue: t['paper_size_id'], isExpanded: true, decoration: const InputDecoration(labelText: 'Paper size', isDense: true), items: [for (final o in _opts('paper_size')) DropdownMenuItem(value: o['id'], child: Text('${o['label']}'))], onChanged: (v) => t['paper_size_id'] = v),
              const SizedBox(height: 10),
              Row(children: [
                Expanded(child: DropdownButtonFormField<String>(initialValue: 'bw', decoration: const InputDecoration(labelText: 'Colour', isDense: true), items: const [DropdownMenuItem(value: 'bw', child: Text('B&W')), DropdownMenuItem(value: 'color', child: Text('Colour'))], onChanged: (v) => t['color'] = v)),
                const SizedBox(width: 10),
                Expanded(child: DropdownButtonFormField<String>(initialValue: 'single', decoration: const InputDecoration(labelText: 'Sides', isDense: true), items: const [DropdownMenuItem(value: 'single', child: Text('Single')), DropdownMenuItem(value: 'double', child: Text('Double'))], onChanged: (v) => t['sides'] = v)),
              ]),
              const SizedBox(height: 10),
              Row(children: [num('pages', 'Pages'), const SizedBox(width: 10), num('copies', 'Copies'), const SizedBox(width: 10), num('quantity', 'Qty')]),
              const SizedBox(height: 10),
              Wrap(spacing: 6, children: [
                for (final f in _opts('finishing'))
                  FilterChip(label: Text('${f['label']}'), selected: (t['finishing'] as List).contains(f['id']), onSelected: (v) => set(() => v ? (t['finishing'] as List).add(f['id']) : (t['finishing'] as List).remove(f['id']))),
              ]),
              const SizedBox(height: 10),
              FilledButton(onPressed: run, child: const Text('Calculate')),
              if (result != null && item != null) ...[
                const SizedBox(height: 12),
                for (final l in (item['lines'] as List)) Row(children: [Expanded(child: Text('${l['label']}')), Text(money(l['amount']))]),
                if (asDouble(result!['minimum_adjustment'])! > 0) Row(children: [const Expanded(child: Text('Minimum order charge')), Text(money(result!['minimum_adjustment']))]),
                const Divider(),
                Row(children: [const Expanded(child: Text('Total', style: TextStyle(fontWeight: FontWeight.w800))), Text(money(result!['total']), style: const TextStyle(fontWeight: FontWeight.w800))]),
                if (result!['priced'] != true)
                  Padding(
                    padding: const EdgeInsets.only(top: 8),
                    child: Text('Not priced: ${(item['missing'] as List? ?? []).join(', ')} — customers will get a quote request.', style: const TextStyle(color: Color(0xFFB45309))),
                  ),
              ],
            ]),
          ),
        );
      }),
    );
  }

  @override
  Widget build(BuildContext context) {
    final rules = (_d?['rules'] as List?)?.cast<Map<String, dynamic>>() ?? [];
    final units = (_d?['units'] as Map?) ?? {};
    return Scaffold(
      appBar: AppBar(title: const Text('Prices'), actions: [if (_d != null) IconButton(tooltip: 'Test a price', icon: const Icon(Icons.calculate_outlined), onPressed: _tester)]),
      floatingActionButton: _d == null ? null : FloatingActionButton.extended(onPressed: () => _edit(), icon: const Icon(Icons.add), label: const Text('Add price')),
      body: _d == null
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _load,
              child: ListView(padding: const EdgeInsets.fromLTRB(12, 12, 12, 90), children: [
                Card(
                  color: const Color(0xFFF0FDFA),
                  child: Padding(
                    padding: const EdgeInsets.all(14),
                    child: Text('Enter your own rates. The most specific matching price is used; for bulk rates, the highest "from quantity" reached wins. '
                        'Minimum order charge: ${money(_d!['min_order_charge'])} (change in Shop settings).'),
                  ),
                ),
                if (rules.isEmpty) const EmptyState(icon: Icons.sell_outlined, title: 'No prices yet', message: 'Until you add prices, every order arrives as a quote request.'),
                for (final r in rules)
                  Card(
                    child: ListTile(
                      onTap: () => _edit(r),
                      leading: Icon(r['is_active'] == true ? Icons.sell : Icons.sell_outlined, color: r['is_active'] == true ? const Color(0xFF0F766E) : const Color(0xFF94A3B8)),
                      title: Text('${r['applies_to']}'),
                      subtitle: Text('${types[r['rule_type']]?.split(' (').first ?? r['rule_type']}${asInt(r['min_qty']) > 1 ? ' · from ${r['min_qty']}' : ''}${r['is_active'] == true ? '' : ' · inactive'}'),
                      trailing: Text(r['rule_type'] == 'discount' ? '${r['price']}%' : '${money(r['price'])}\n${units[r['unit']] ?? ''}', textAlign: TextAlign.end, style: const TextStyle(fontWeight: FontWeight.w700)),
                    ),
                  ),
              ]),
            ),
    );
  }
}
