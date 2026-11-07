import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/constants/app_colors.dart';
import '../../core/utils/formatters.dart';
import '../../core/utils/json.dart';
import '../../core/widgets/common.dart';
import '../../providers/providers.dart';

/// "Show on a TV": the display code of a screen or playlist, with copy,
/// choose-your-own and regenerate. Typing this code on any device running the
/// app (Show Display on the sign-in page) plays the content.
Future<void> showDisplayCodeSheet(BuildContext context, WidgetRef ref, {int? screenId, int? playlistId}) {
  return showModalBottomSheet(
    context: context,
    isScrollControlled: true,
    builder: (_) => _DisplayCodeSheet(screenId: screenId, playlistId: playlistId),
  );
}

class _DisplayCodeSheet extends ConsumerStatefulWidget {
  const _DisplayCodeSheet({this.screenId, this.playlistId});
  final int? screenId, playlistId;
  @override
  ConsumerState<_DisplayCodeSheet> createState() => _DisplayCodeSheetState();
}

class _DisplayCodeSheetState extends ConsumerState<_DisplayCodeSheet> {
  Map<String, dynamic>? data;
  Object? error;
  bool busy = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final d = await ref.read(displayCodesRepoProvider).get(screenId: widget.screenId, playlistId: widget.playlistId);
      if (mounted) setState(() => data = d);
    } catch (e) {
      if (mounted) setState(() => error = e);
    }
  }

  Future<void> _change() async {
    final c = await promptText(context, title: 'Choose a display code', initial: asString(data?['code']), label: '4–12 letters or numbers', action: 'Save');
    if (c == null || c.isEmpty || !mounted) return;
    setState(() => busy = true);
    try {
      final (d, msg) = await ref.read(displayCodesRepoProvider).set(c, screenId: widget.screenId, playlistId: widget.playlistId);
      if (mounted) {
        setState(() => data = d);
        showToast(context, msg);
      }
    } catch (e) {
      if (mounted) showToast(context, e.toString(), error: true);
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> _regenerate() async {
    if (!await confirmDialog(context, title: 'Create a new code?', message: 'Displays using the current code will stop showing this content.', confirm: 'New code')) return;
    setState(() => busy = true);
    try {
      final d = await ref.read(displayCodesRepoProvider).regenerate(screenId: widget.screenId, playlistId: widget.playlistId);
      if (mounted) setState(() => data = d);
    } catch (e) {
      if (mounted) showToast(context, e.toString(), error: true);
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final t = Theme.of(context).textTheme;
    if (error != null) return SizedBox(height: 260, child: ErrorView(error: error!, onRetry: () { setState(() => error = null); _load(); }));
    if (data == null) return const SizedBox(height: 260, child: LoadingView());
    final code = asString(data!['code']);
    final published = asBool(data!['is_published']);
    final kind = asString(data!['kind']) == 'playlist' ? 'playlist' : 'screen';
    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(20, 0, 20, 20),
        child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          Row(children: [
            const Icon(Icons.tv_rounded, color: AppColors.teal),
            const SizedBox(width: 8),
            Expanded(child: Text('Show "${data!['name']}" on a TV', style: t.titleMedium, maxLines: 2)),
          ]),
          const SizedBox(height: 16),
          Container(
            padding: const EdgeInsets.symmetric(vertical: 18),
            decoration: BoxDecoration(color: Theme.of(context).colorScheme.primaryContainer, borderRadius: BorderRadius.circular(20)),
            child: Column(children: [
              const Text('Display code', style: TextStyle(color: AppColors.mediumGrey)),
              const SizedBox(height: 4),
              FittedBox(
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  child: SelectableText(code, style: const TextStyle(fontSize: 44, fontWeight: FontWeight.w800, letterSpacing: 8, color: AppColors.teal)),
                ),
              ),
            ]),
          ),
          if (!published)
            Container(
              margin: const EdgeInsets.only(top: 12),
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(color: AppColors.warning.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(12)),
              child: Text('This $kind is not published yet. Publish it first — displays only show published content.'),
            ),
          const SizedBox(height: 14),
          Wrap(spacing: 8, runSpacing: 8, alignment: WrapAlignment.center, children: [
            OutlinedButton.icon(
              onPressed: () { Clipboard.setData(ClipboardData(text: code)); showToast(context, 'Code copied'); },
              icon: const Icon(Icons.copy_rounded), label: const Text('Copy'),
            ),
            OutlinedButton.icon(onPressed: busy ? null : _change, icon: const Icon(Icons.edit_outlined), label: const Text('Choose code')),
            OutlinedButton.icon(onPressed: busy ? null : _regenerate, icon: const Icon(Icons.refresh_rounded), label: const Text('New code')),
          ]),
          const SizedBox(height: 16),
          Text('How to show it', style: t.titleSmall),
          const SizedBox(height: 6),
          for (final s in const [
            '1.  Install Signal Signage on the TV, TV box or tablet.',
            '2.  On the sign-in page, tap "Show Display".',
            '3.  Enter the code above. That\'s it — it updates automatically when you publish changes.',
          ])
            Padding(padding: const EdgeInsets.only(bottom: 4), child: Text(s)),
          if (asInt(data!['view_count']) > 0)
            Padding(
              padding: const EdgeInsets.only(top: 8),
              child: Text('Last shown on a display: ${timeAgo(asDate(data!['last_used_at']))}', style: t.bodySmall?.copyWith(color: AppColors.mediumGrey)),
            ),
        ]),
      ),
    );
  }
}
