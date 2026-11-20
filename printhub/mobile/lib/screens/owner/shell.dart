import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

import '../../main.dart';
import '../../state/session.dart';
import '../../util/format.dart';
import '../../widgets/common.dart';
import 'more.dart';
import 'orders.dart';

class OwnerShell extends StatefulWidget {
  const OwnerShell({super.key, this.tab = 0});
  final int tab;
  @override
  State<OwnerShell> createState() => _OwnerShellState();
}

class _OwnerShellState extends State<OwnerShell> {
  late int _tab = widget.tab;

  @override
  void didUpdateWidget(OwnerShell old) {
    super.didUpdateWidget(old);
    if (old.tab != widget.tab) _tab = widget.tab;
  }

  @override
  Widget build(BuildContext context) {
    final session = SessionScope.of(context);
    final pages = [const DashboardTab(), const OrdersTab(), const QrTab(), const MoreTab()];
    final titles = ['Dashboard', 'Orders', 'Shop QR code', 'More'];
    return Scaffold(
      appBar: AppBar(
        title: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(titles[_tab]),
          Text('${session.shop['name'] ?? ''}', style: const TextStyle(fontSize: 12, color: Color(0xFF64748B), fontWeight: FontWeight.w400)),
        ]),
        actions: [IconButton(tooltip: 'Notifications', icon: const Icon(Icons.notifications_outlined), onPressed: () => context.push('/owner/notifications'))],
      ),
      body: Column(children: [
        const SubscriptionBanner(),
        Expanded(child: IndexedStack(index: _tab, children: pages)),
      ]),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _tab,
        onDestinationSelected: (i) => setState(() => _tab = i),
        destinations: const [
          NavigationDestination(icon: Icon(Icons.space_dashboard_outlined), selectedIcon: Icon(Icons.space_dashboard), label: 'Home'),
          NavigationDestination(icon: Icon(Icons.receipt_long_outlined), selectedIcon: Icon(Icons.receipt_long), label: 'Orders'),
          NavigationDestination(icon: Icon(Icons.qr_code_2), label: 'QR code'),
          NavigationDestination(icon: Icon(Icons.menu), label: 'More'),
        ],
      ),
    );
  }
}

/// Warns when the subscription is in grace, expired or suspended (orders/edits are blocked server-side).
class SubscriptionBanner extends StatelessWidget {
  const SubscriptionBanner({super.key});
  @override
  Widget build(BuildContext context) {
    final s = SessionScope.of(context).subscription;
    final state = s['state'];
    if (state == 'active' && asInt(s['days_left'], 99) > 5) return const SizedBox.shrink();
    final (Color c, String text) = switch (state) {
      'active' => (const Color(0xFFF59E0B), 'Your subscription renews in ${s['days_left']} days (${date(s['end_date'])}).'),
      'grace' => (const Color(0xFFF59E0B), 'Subscription expired — grace period until ${date(s['grace_until'])}. Renew to avoid interruption.'),
      'suspended' => (const Color(0xFFDC2626), 'Your shop is suspended. Contact PrintHub support.'),
      _ => (const Color(0xFFDC2626), 'Subscription expired. Customers cannot place orders until you renew.'),
    };
    return Material(
      color: c.withValues(alpha: .12),
      child: InkWell(
        onTap: () => context.push('/owner/subscription'),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
          child: Row(children: [Icon(Icons.info_outline, color: c, size: 20), const SizedBox(width: 10), Expanded(child: Text(text, style: TextStyle(color: c, fontWeight: FontWeight.w600, fontSize: 13))), Icon(Icons.chevron_right, color: c)]),
        ),
      ),
    );
  }
}

class DashboardTab extends StatefulWidget {
  const DashboardTab({super.key});
  @override
  State<DashboardTab> createState() => _DashboardTabState();
}

class _DashboardTabState extends State<DashboardTab> {
  final _key = GlobalKey<AsyncViewState<Map<String, dynamic>>>();

  void _refresh() => _key.currentState?.reload();

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    NotifierScope.of(context).removeListener(_refresh);
    NotifierScope.of(context).addListener(_refresh);
  }

  @override
  Widget build(BuildContext context) {
    final api = SessionScope.read(context).api;
    return AsyncView<Map<String, dynamic>>(
      key: _key,
      load: () => api.get('/owner/dashboard'),
      builder: (context, d, reload) {
        final c = d['counts'] as Map<String, dynamic>;
        final av = d['availability'] as Map<String, dynamic>;
        final fresh = (d['new'] as List).cast<Map<String, dynamic>>();
        final recent = (d['recent'] as List).cast<Map<String, dynamic>>();
        void openOrders(String status) => context.go('/owner?tab=1&status=$status');
        return ListView(padding: const EdgeInsets.all(16), children: [
          Card(
            margin: EdgeInsets.zero,
            color: av['open'] == true ? const Color(0xFFF0FDFA) : const Color(0xFFFEF2F2),
            child: ListTile(
              leading: Icon(av['open'] == true ? Icons.storefront : Icons.store_mall_directory_outlined, color: av['open'] == true ? const Color(0xFF0F766E) : const Color(0xFFDC2626)),
              title: Text(av['open'] == true ? 'Accepting orders' : 'Not accepting orders', style: const TextStyle(fontWeight: FontWeight.w700)),
              subtitle: av['reason'] != null ? Text('${av['reason']}') : null,
              trailing: TextButton(onPressed: () => context.push('/owner/shop'), child: const Text('Change')),
            ),
          ),
          const SizedBox(height: 12),
          GridView.count(
            crossAxisCount: MediaQuery.sizeOf(context).width > 700 ? 4 : 2,
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            mainAxisSpacing: 10,
            crossAxisSpacing: 10,
            childAspectRatio: 2.2,
            children: [
              StatTile(label: "Today's orders", value: '${c['today']}', icon: Icons.today),
              StatTile(label: 'Pending', value: '${c['pending']}', icon: Icons.hourglass_top, color: const Color(0xFFF59E0B), onTap: () => openOrders('new')),
              StatTile(label: 'Printing', value: '${c['printing']}', icon: Icons.print, color: const Color(0xFF7C3AED), onTap: () => openOrders('printing')),
              StatTile(label: 'Ready', value: '${c['ready']}', icon: Icons.inventory_2_outlined, color: const Color(0xFF16A34A), onTap: () => openOrders('ready')),
              StatTile(label: 'Completed', value: '${c['completed']}', icon: Icons.task_alt, color: const Color(0xFF15803D), onTap: () => openOrders('completed')),
              StatTile(label: 'Cancelled', value: '${c['cancelled']}', icon: Icons.cancel_outlined, color: const Color(0xFF64748B), onTap: () => openOrders('closed')),
              StatTile(label: "Today's revenue", value: money(c['revenue_today']), icon: Icons.payments_outlined, color: const Color(0xFF0891B2)),
              StatTile(label: 'This month', value: money(c['revenue_month']), icon: Icons.bar_chart, color: const Color(0xFF0891B2), onTap: () => context.push('/owner/reports')),
            ],
          ),
          SectionTitle('New orders', trailing: TextButton(onPressed: () => openOrders('new'), child: const Text('All'))),
          if (fresh.isEmpty) const Card(child: Padding(padding: EdgeInsets.all(18), child: Text('No new orders right now.', style: TextStyle(color: Color(0xFF64748B))))),
          for (final o in fresh) OrderTile(o, onReturn: reload),
          const SectionTitle('Recent activity'),
          for (final o in recent) OrderTile(o, onReturn: reload),
        ]);
      },
    );
  }
}

class MoreTab extends StatelessWidget {
  const MoreTab({super.key});
  @override
  Widget build(BuildContext context) {
    final session = SessionScope.of(context);
    Widget item(IconData i, String t, String route, [String? sub]) =>
        ListTile(leading: Icon(i, color: const Color(0xFF0F766E)), title: Text(t), subtitle: sub == null ? null : Text(sub), trailing: const Icon(Icons.chevron_right), onTap: () => context.push(route));
    return ListView(children: [
      ListTile(
        leading: CircleAvatar(backgroundColor: const Color(0xFFCCFBF1), child: Text('${session.user['name'] ?? '?'}'.substring(0, 1).toUpperCase(), style: const TextStyle(color: Color(0xFF0F766E), fontWeight: FontWeight.w800))),
        title: Text('${session.user['name']}'),
        subtitle: Text('${session.shop['shop_code']} · ${session.user['username']}'),
        onTap: () => context.push('/owner/profile'),
      ),
      const Divider(),
      item(Icons.wifi, 'Wi-Fi sharing', '/owner/wifi', 'Let customers join your Wi-Fi from the shop page'),
      item(Icons.category_outlined, 'Services', '/owner/services', 'Categories, paper sizes and finishing you offer'),
      item(Icons.sell_outlined, 'Prices', '/owner/pricing', 'Your own rates — used for automatic pricing'),
      item(Icons.store_outlined, 'Shop & order settings', '/owner/shop', 'Details, pricing mode, payments, file retention'),
      item(Icons.insights_outlined, 'Reports', '/owner/reports'),
      item(Icons.workspace_premium_outlined, 'Subscription', '/owner/subscription', '${session.subscription['label'] ?? ''}'),
      item(Icons.lock_outline, 'Change password', '/owner/password'),
      const Divider(),
      ListTile(
        leading: const Icon(Icons.logout, color: Color(0xFFDC2626)),
        title: const Text('Sign out', style: TextStyle(color: Color(0xFFDC2626))),
        onTap: () async {
          if (await confirm(context, 'Sign out of this device?', yes: 'Sign out')) await session.logout();
        },
      ),
    ]);
  }
}
