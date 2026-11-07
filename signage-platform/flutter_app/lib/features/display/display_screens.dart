import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:wakelock_plus/wakelock_plus.dart';

import '../../core/constants/app_colors.dart';
import '../../core/constants/app_config.dart';
import '../../core/network/api_client.dart';
import '../../core/utils/formatters.dart';
import '../../core/utils/json.dart';
import '../authentication/login_screen.dart' show BrandMark;
import '../signage_player/playback_view.dart';
import '../signage_player/player_app.dart' show PlayerScreen;
import '../signage_player/player_service.dart';
import '../widgets_library/render_context.dart';
import '../widgets_library/widget_renderers.dart';
import 'code_display_service.dart';

/// "Show Display": turn this device into a signage screen by typing the code of
/// a screen or playlist. Works on TVs (remote + on-screen keyboard), tablets and phones.
class DisplayEntryScreen extends StatefulWidget {
  const DisplayEntryScreen({super.key, this.initialError});
  final String? initialError;
  @override
  State<DisplayEntryScreen> createState() => _DisplayEntryScreenState();
}

class _DisplayEntryScreenState extends State<DisplayEntryScreen> {
  final code = TextEditingController();
  final focus = FocusNode();
  bool busy = false;
  String? error;

  @override
  void initState() {
    super.initState();
    error = widget.initialError;
  }

  Future<void> _start() async {
    final c = code.text.toUpperCase().replaceAll(RegExp(r'[^A-Z0-9]'), '');
    if (c.length < 4) {
      setState(() => error = 'Enter the display code (at least 4 characters).');
      return;
    }
    setState(() { busy = true; error = null; });
    try {
      // Check the code before switching into full-screen mode.
      await ApiClient().get('display/content.php', query: {'code': c, 'have': 'check'});
      await DisplayModePrefs.setCode(c);
      if (mounted) context.go('/display/play/$c');
    } on ApiException catch (e) {
      setState(() => error = e.isNetwork ? 'No internet connection. Check the network and try again.' : e.message);
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final t = Theme.of(context).textTheme;
    return Scaffold(
      appBar: AppBar(title: const Text('Show Display')),
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 520),
              child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                const Center(child: BrandMark(size: 64)),
                const SizedBox(height: 20),
                Text('Show a screen on this device', textAlign: TextAlign.center, style: t.headlineSmall),
                const SizedBox(height: 8),
                Text('Enter the display code of a screen or playlist. You can find it in the app under My Screens or Playlists → "Show on a TV".',
                    textAlign: TextAlign.center, style: t.bodyMedium?.copyWith(color: AppColors.mediumGrey)),
                const SizedBox(height: 28),
                TextField(
                  controller: code,
                  focusNode: focus,
                  autofocus: true,
                  textAlign: TextAlign.center,
                  textCapitalization: TextCapitalization.characters,
                  textInputAction: TextInputAction.go,
                  maxLength: 12,
                  inputFormatters: [
                    FilteringTextInputFormatter.allow(RegExp(r'[A-Za-z0-9]')),
                    TextInputFormatter.withFunction((o, n) => n.copyWith(text: n.text.toUpperCase())),
                  ],
                  style: const TextStyle(fontSize: 34, fontWeight: FontWeight.w800, letterSpacing: 8),
                  decoration: const InputDecoration(hintText: 'CODE', counterText: ''),
                  onSubmitted: (_) => _start(),
                ),
                if (error != null)
                  Padding(
                    padding: const EdgeInsets.only(top: 12),
                    child: Text(error!, textAlign: TextAlign.center, style: const TextStyle(color: AppColors.danger)),
                  ),
                const SizedBox(height: 18),
                FilledButton.icon(
                  onPressed: busy ? null : _start,
                  icon: busy ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2.5, color: Colors.white)) : const Icon(Icons.play_arrow_rounded),
                  label: const Text('Show'),
                ),
                const SizedBox(height: 28),
                const Divider(),
                const SizedBox(height: 8),
                TextButton.icon(
                  onPressed: () async {
                    await DisplayModePrefs.setManaged();
                    if (context.mounted) context.go('/display/managed');
                  },
                  icon: const Icon(Icons.cast_connected_rounded),
                  label: const Text('Managed display: connect to an account with a pairing code\n(adds remote control, schedules and monitoring)', textAlign: TextAlign.center),
                ),
              ]),
            ),
          ),
        ),
      ),
    );
  }
}

/// Full-screen playback of a display code.
/// Remote / touch: OK, Enter, Menu, Back or long-press opens the menu.
class DisplayPlayScreen extends StatefulWidget {
  const DisplayPlayScreen({super.key, required this.code});
  final String code;
  @override
  State<DisplayPlayScreen> createState() => _DisplayPlayScreenState();
}

class _DisplayPlayScreenState extends State<DisplayPlayScreen> {
  late final CodeDisplayService s = CodeDisplayService(widget.code);

  @override
  void initState() {
    super.initState();
    SystemChrome.setEnabledSystemUIMode(SystemUiMode.immersiveSticky);
    WakelockPlus.enable().catchError((_) {});
    s.addListener(_changed);
    s.start();
  }

  void _changed() {
    if (mounted) setState(() {});
  }

  @override
  void dispose() {
    s.removeListener(_changed);
    s.dispose();
    WakelockPlus.disable().catchError((_) {});
    SystemChrome.setEnabledSystemUIMode(SystemUiMode.edgeToEdge);
    super.dispose();
  }

  Future<void> _menu() async {
    final a = await showModalBottomSheet<String>(
      context: context,
      builder: (c) => SafeArea(
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          ListTile(
            title: Text(s.name ?? 'Display', style: const TextStyle(fontWeight: FontWeight.w700)),
            subtitle: Text('Code ${widget.code} · ${s.online ? 'online' : 'offline (cached content)'} · checked ${timeAgo(s.lastCheck)}'),
          ),
          ListTile(autofocus: true, leading: const Icon(Icons.play_arrow_rounded), title: const Text('Continue'), onTap: () => Navigator.pop(c)),
          ListTile(leading: const Icon(Icons.refresh_rounded), title: const Text('Check for updates now'), onTap: () => Navigator.pop(c, 'refresh')),
          ListTile(leading: const Icon(Icons.dialpad_rounded), title: const Text('Show a different code'), onTap: () => Navigator.pop(c, 'change')),
          ListTile(leading: const Icon(Icons.logout_rounded), title: const Text('Exit display mode'), onTap: () => Navigator.pop(c, 'exit')),
        ]),
      ),
    );
    if (!mounted) return;
    if (a == 'refresh') s.refresh();
    if (a == 'change') {
      await DisplayModePrefs.clear();
      if (mounted) context.go('/display');
    }
    if (a == 'exit') {
      await DisplayModePrefs.clear();
      if (mounted) context.go('/login');
    }
  }

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) { if (!didPop) _menu(); },
      child: Focus(
        autofocus: true,
        onKeyEvent: (_, e) {
          if (e is KeyDownEvent && [LogicalKeyboardKey.select, LogicalKeyboardKey.enter, LogicalKeyboardKey.contextMenu, LogicalKeyboardKey.gameButtonA].contains(e.logicalKey)) {
            _menu();
            return KeyEventResult.handled;
          }
          return KeyEventResult.ignored;
        },
        child: GestureDetector(
          onLongPress: _menu,
          child: Scaffold(backgroundColor: Colors.black, body: _body()),
        ),
      ),
    );
  }

  Widget _body() {
    if (s.emergency.isNotEmpty) {
      final e = s.emergency.first;
      final st = asMap(e['style_json']);
      return EmergencyBanner(
        title: asString(e['title']), message: asString(e['body']),
        background: parseHexColor(st['background']) ?? const Color(0xFFB00020), color: parseHexColor(st['color']) ?? Colors.white,
        flash: asBool(st['flash'], true),
      );
    }
    if (s.doc != null) {
      return PlaylistPlaybackView(
        key: ValueKey(s.contentHash),
        doc: s.doc!,
        media: s.media,
        mode: RenderMode.player,
        idle: _message(Icons.event_busy_rounded, 'Nothing to show right now', s.name ?? ''),
      );
    }
    if (s.loading) return _message(Icons.cast_connected_rounded, 'Loading…', 'Code ${widget.code}', spinner: true);
    if (s.errorMessage != null) return _message(Icons.info_outline_rounded, s.errorMessage!, 'Code ${widget.code} · press OK or long-press for options');
    return _message(Icons.wifi_off_rounded, 'Waiting for connection…', 'This display will start as soon as it is online · code ${widget.code}', spinner: true);
  }

  Widget _message(IconData icon, String title, String sub, {bool spinner = false}) => Container(
        decoration: const BoxDecoration(gradient: LinearGradient(colors: [AppColors.darkTeal, AppColors.darkBackground], begin: Alignment.topLeft, end: Alignment.bottomRight)),
        child: Center(
          child: FittedBox(
            child: Padding(
              padding: const EdgeInsets.all(40),
              child: Column(mainAxisSize: MainAxisSize.min, children: [
                Ticking(
                  interval: const Duration(seconds: 1),
                  builder: (_) => Text(DateFormat('HH:mm').format(DateTime.now()), style: const TextStyle(color: Colors.white, fontSize: 120, fontWeight: FontWeight.w300)),
                ),
                const SizedBox(height: 24),
                Icon(icon, color: Colors.white70, size: 48),
                const SizedBox(height: 12),
                ConstrainedBox(
                  constraints: const BoxConstraints(maxWidth: 900),
                  child: Text(title, textAlign: TextAlign.center, style: const TextStyle(color: Colors.white, fontSize: 30, fontWeight: FontWeight.w600)),
                ),
                const SizedBox(height: 8),
                Text(sub, textAlign: TextAlign.center, style: const TextStyle(color: Colors.white54, fontSize: 20)),
                if (spinner) const Padding(padding: EdgeInsets.only(top: 24), child: CircularProgressIndicator(color: AppColors.teal)),
                const SizedBox(height: 24),
                Text(AppConfig.appName, style: const TextStyle(color: Colors.white24, fontSize: 18)),
              ]),
            ),
          ),
        ),
      );
}

/// Managed display (pairing with an account): the full player with remote
/// control, schedules and monitoring — inside the same app.
final managedPlayerProvider = Provider.autoDispose<PlayerService>((ref) {
  final s = PlayerService();
  ref.onDispose(s.dispose);
  return s;
});

class ManagedDisplayScreen extends ConsumerWidget {
  const ManagedDisplayScreen({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final service = ref.watch(managedPlayerProvider);
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) async {
        if (didPop) return;
        final exit = await showDialog<bool>(
          context: context,
          builder: (c) => AlertDialog(
            title: const Text('Exit display mode?'),
            content: const Text('This device stops showing content until you start display mode again.'),
            actions: [
              TextButton(autofocus: true, onPressed: () => Navigator.pop(c, false), child: const Text('Stay')),
              FilledButton(onPressed: () => Navigator.pop(c, true), child: const Text('Exit')),
            ],
          ),
        );
        if (exit == true) {
          await DisplayModePrefs.clear();
          if (context.mounted) context.go('/login');
        }
      },
      child: PlayerScreen(service: service),
    );
  }
}
