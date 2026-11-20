import 'package:material_ui/material_ui.dart';

import '../util/format.dart';
import '../util/share.dart';
import 'common.dart';

const _sides = {'single': 'Single-sided', 'double': 'Double-sided'};
const statusLabels = {
  'submitted': 'Order submitted', 'awaiting_approval': 'Awaiting shop approval', 'accepted': 'Accepted', 'quoted': 'Price quotation sent',
  'awaiting_payment': 'Awaiting payment', 'payment_confirmed': 'Payment confirmed', 'printing': 'Printing in progress',
  'ready': 'Ready for collection', 'completed': 'Completed', 'rejected': 'Rejected', 'cancelled': 'Cancelled',
};
const methods = {'cash': 'Cash', 'bank_transfer': 'Bank transfer', 'jazzcash': 'JazzCash', 'easypaisa': 'Easypaisa', 'other': 'Other'};

/// Progress bar of order steps with the current one highlighted.
class OrderSteps extends StatelessWidget {
  const OrderSteps(this.order, {super.key});
  final Map<String, dynamic> order;
  @override
  Widget build(BuildContext context) {
    final steps = (order['steps'] as List).cast<Map<String, dynamic>>();
    final history = (order['history'] as List).map((h) => h['status']).toSet();
    final closed = order['status'] == 'rejected' || order['status'] == 'cancelled';
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(children: [
          for (var i = 0; i < steps.length; i++)
            Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Column(children: [
                Icon(
                  history.contains(steps[i]['key']) ? Icons.check_circle : (steps[i]['key'] == order['status'] ? Icons.radio_button_checked : Icons.radio_button_unchecked),
                  color: history.contains(steps[i]['key']) || steps[i]['key'] == order['status'] ? const Color(0xFF0F766E) : const Color(0xFFCBD5E1),
                  size: 22,
                ),
                if (i < steps.length - 1) Container(width: 2, height: 18, color: history.contains(steps[i + 1]['key']) ? const Color(0xFF0F766E) : const Color(0xFFE2E8F0)),
              ]),
              const SizedBox(width: 10),
              Expanded(child: Padding(padding: const EdgeInsets.only(top: 2), child: Text('${steps[i]['label']}', style: TextStyle(fontWeight: steps[i]['key'] == order['status'] ? FontWeight.w800 : FontWeight.w500)))),
            ]),
          if (closed)
            Padding(
              padding: const EdgeInsets.only(top: 8),
              child: Row(children: [const Icon(Icons.cancel, color: Color(0xFFDC2626)), const SizedBox(width: 10), Expanded(child: Text('${order['status_label']}${order['rejection_reason'] != null ? ': ${order['rejection_reason']}' : ''}', style: const TextStyle(color: Color(0xFFDC2626), fontWeight: FontWeight.w700)))]),
            ),
        ]),
      ),
    );
  }
}

class OrderItemCard extends StatelessWidget {
  const OrderItemCard(this.item, {super.key, this.showFiles = true});
  final Map<String, dynamic> item;
  final bool showFiles;
  @override
  Widget build(BuildContext context) {
    final finishing = (item['finishing'] as List?)?.map((f) => f is Map ? f['label'] ?? f['name'] : '$f').join(', ') ?? '';
    final pages = (item['page_selection'] as List?)?.map((p) => '${p['page']}${asInt(p['rotate']) != 0 ? '↻' : ''}').join(', ');
    final specs = <String>[
      if (item['paper_size'] != null) item['custom_size'] ?? item['paper_size'],
      if (item['color'] != null) item['color'] == 'color' ? 'Colour' : 'B&W',
      if (item['sides'] != null) _sides[item['sides']] ?? '${item['sides']}',
      if (item['paper_type'] != null) '${item['paper_type']}',
      if (item['gsm'] != null) '${item['gsm']}',
      if (item['orientation'] == 'landscape') 'Landscape',
      if (finishing.isNotEmpty) finishing,
    ];
    final files = ((item['files'] as List?) ?? []).cast<Map<String, dynamic>>();
    final breakdown = ((item['price_breakdown'] as List?) ?? []).cast<Map<String, dynamic>>();
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [
            Expanded(child: Text('${item['title'] ?? item['category']}', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15))),
            if (item['estimated_price'] != null) Text(money(item['estimated_price']), style: const TextStyle(fontWeight: FontWeight.w700)),
          ]),
          Text([item['category'], item['subcategory']].whereType<String>().join(' › '), style: const TextStyle(color: Color(0xFF64748B), fontSize: 13)),
          const SizedBox(height: 8),
          Wrap(spacing: 6, runSpacing: 6, children: [for (final s in specs) Chip(label: Text(s), visualDensity: VisualDensity.compact, materialTapTargetSize: MaterialTapTargetSize.shrinkWrap)]),
          const SizedBox(height: 8),
          Text('${item['pages']} page(s) × ${item['copies']} cop${asInt(item['copies']) == 1 ? 'y' : 'ies'}${asInt(item['quantity']) > 1 ? ' · qty ${item['quantity']}' : ''}'),
          if (pages != null && pages.isNotEmpty) Text('Pages: $pages', style: const TextStyle(fontSize: 12, color: Color(0xFF64748B))),
          if (item['notes'] != null) Padding(padding: const EdgeInsets.only(top: 6), child: Text('Note: ${item['notes']}', style: const TextStyle(fontStyle: FontStyle.italic))),
          if (breakdown.isNotEmpty) ...[
            const Divider(height: 20),
            for (final b in breakdown) Row(children: [Expanded(child: Text('${b['label']}', style: const TextStyle(fontSize: 12, color: Color(0xFF475569)))), Text(money(b['amount']), style: const TextStyle(fontSize: 12))]),
          ],
          if (showFiles && files.isNotEmpty) ...[
            const Divider(height: 20),
            for (final f in files)
              ListTile(
                dense: true,
                contentPadding: EdgeInsets.zero,
                leading: Icon(f['kind'] == 'print_ready' ? Icons.picture_as_pdf : (('${f['mime']}').startsWith('image') ? Icons.image_outlined : Icons.description_outlined), color: const Color(0xFF0F766E)),
                title: Text('${f['name']}', maxLines: 1, overflow: TextOverflow.ellipsis),
                subtitle: Text(f['deleted'] == true ? 'Deleted after retention period' : '${f['kind'] == 'print_ready' ? 'Print-ready PDF' : 'Original'} · ${(asInt(f['size']) / 1024).round()} KB${f['pages'] != null ? ' · ${f['pages']} p' : ''}'),
                trailing: f['deleted'] == true || f['download_url'] == null
                    ? null
                    : Row(mainAxisSize: MainAxisSize.min, children: [
                        IconButton(tooltip: 'Open', icon: const Icon(Icons.open_in_new), onPressed: () => openExternal('${f['url']}')),
                        IconButton(tooltip: 'Download', icon: const Icon(Icons.download), onPressed: () => openExternal('${f['download_url']}')),
                      ]),
              ),
          ],
        ]),
      ),
    );
  }
}

class OrderHistory extends StatelessWidget {
  const OrderHistory(this.order, {super.key});
  final Map<String, dynamic> order;
  @override
  Widget build(BuildContext context) => Card(
        child: Column(children: [
          for (final h in (order['history'] as List).reversed)
            ListTile(
              dense: true,
              leading: const Icon(Icons.history, size: 20),
              title: Text(statusLabels[h['status']] ?? '${h['status']}'),
              subtitle: Text([if (h['note'] != null) '${h['note']}', dateTime(h['created_at'])].join('\n')),
            ),
        ]),
      );
}

class AmountRow extends StatelessWidget {
  const AmountRow(this.order, {super.key});
  final Map<String, dynamic> order;
  @override
  Widget build(BuildContext context) {
    final label = order['final_amount'] != null ? 'Total' : (order['quoted_amount'] != null ? 'Quoted price' : (order['estimated_amount'] != null ? 'Estimated price' : 'Price'));
    return Card(
      color: const Color(0xFFF0FDFA),
      child: ListTile(
        title: Text(label, style: const TextStyle(fontWeight: FontWeight.w600)),
        subtitle: Text('Payment: ${'${order['payment_status']}'.replaceAll('_', ' ')} · ${'${order['payment_mode']}'.replaceAll('_', ' ')}'),
        trailing: Text(order['amount'] == null ? 'Awaiting quote' : money(order['amount']), style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: Color(0xFF0F766E))),
      ),
    );
  }
}

class PaymentsList extends StatelessWidget {
  const PaymentsList(this.order, {super.key});
  final Map<String, dynamic> order;
  @override
  Widget build(BuildContext context) {
    final pays = (order['payments'] as List).cast<Map<String, dynamic>>();
    if (pays.isEmpty) return const SizedBox.shrink();
    return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      const SectionTitle('Payments'),
      Card(
        child: Column(children: [
          for (final p in pays)
            ListTile(
              leading: Icon(p['status'] == 'confirmed' ? Icons.verified : Icons.schedule, color: p['status'] == 'confirmed' ? const Color(0xFF16A34A) : const Color(0xFFF59E0B)),
              title: Text('${money(p['amount'])} · ${methods[p['method']] ?? p['method']}'),
              subtitle: Text('${p['status']}${p['reference'] != null ? ' · Ref ${p['reference']}' : ''}\n${dateTime(p['created_at'])}'),
              trailing: p['proof_url'] != null ? TextButton(onPressed: () => openExternal('${p['proof_url']}'), child: const Text('Proof')) : null,
            ),
        ]),
      ),
    ]);
  }
}

/// Small summary used in lists.
class OrderSummaryTile extends StatelessWidget {
  const OrderSummaryTile({super.key, required this.number, required this.status, required this.statusLabel, required this.subtitle, this.amount, this.onTap});
  final String number;
  final String status;
  final String statusLabel;
  final String subtitle;
  final num? amount;
  final VoidCallback? onTap;
  @override
  Widget build(BuildContext context) => Card(
        child: InkWell(
          borderRadius: BorderRadius.circular(16),
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Row(children: [Expanded(child: Text(number, style: const TextStyle(fontWeight: FontWeight.w800))), StatusChip(status, statusLabel)]),
              const SizedBox(height: 6),
              Row(children: [Expanded(child: Text(subtitle, style: const TextStyle(color: Color(0xFF64748B), fontSize: 13))), Text(amount == null ? 'Quote' : money(amount), style: const TextStyle(fontWeight: FontWeight.w700))]),
            ]),
          ),
        ),
      );
}
