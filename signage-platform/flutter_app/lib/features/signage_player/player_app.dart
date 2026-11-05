import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:intl/intl.dart';
import 'package:qr_flutter/qr_flutter.dart';
import 'package:wakelock_plus/wakelock_plus.dart';

import '../../core/constants/app_colors.dart';
import '../../core/constants/app_config.dart';
import '../../core/utils/formatters.dart';
import '../../core/utils/json.dart';
import '../widgets_library/render_context.dart';
import '../widgets_library/widget_renderers.dart';
import 'playback_view.dart';
import 'player_service.dart';

/// Full-screen signage player app (Android TV / box / tablet).
class PlayerApp extends StatelessWidget {
  const PlayerApp({super.key, required this.service});
  final PlayerService service;
  @override
  Widget build(BuildContext context) => MaterialApp(
        debugShowCheckedModeBanner: false,
        title: '${AppConfig.appName} Player',
        theme: ThemeData(useMaterial3: true, brightness: Brightness.dark, colorSchemeSeed: AppColors.teal, scaffoldBackgroundColor: Colors.black),
        home: PlayerScreen(service: service),
      );
}

class PlayerScreen extends StatefulWidget {
  const PlayerScreen({super.key, required this.service});
  final PlayerService service;
  @override
  State<PlayerScreen> createState() => _PlayerScreenState();
}

class _PlayerScreenState extends State<PlayerScreen> {
  PlayerService get s => widget.service;
  String _orientation = '';
  bool _info = false;

  @override
  void initState() {
    super.initState();
    SystemChrome.setEnabledSystemUIMode(SystemUiMode.immersiveSticky);
    WakelockPlus.enable().catchError((_) {});
    s.addListener(_onChange);
    s.start();
  }

  void _onChange() {
    if (_orientation != s.orientation) {
      _orientation = s.orientation;
      SystemChrome.setPreferredOrientations(switch (s.orientation) {
        'landscape' => [DeviceOrientation.landscapeLeft, DeviceOrientation.landscapeRight],
        'portrait' => [DeviceOrientation.portraitUp],
        _ => DeviceOrientation.values,
      });
    }
    if (mounted) setState(() {});
  }

  @override
  void dispose() {
    s.removeListener(_onChange);
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Focus(
      autofocus: true,
      // TV remote: OK/Enter or Menu toggles the info panel.
      onKeyEvent: (_, e) {
        if (e is KeyDownEvent && (e.logicalKey == LogicalKeyboardKey.select || e.logicalKey == LogicalKeyboardKey.enter || e.logicalKey == LogicalKeyboardKey.contextMenu || e.logicalKey == LogicalKeyboardKey.keyI)) {
          setState(() => _info = !_info);
          return KeyEventResult.handled;
        }
        return KeyEventResult.ignored;
      },
      child: GestureDetector(
        onLongPress: () => setState(() => _info = !_info),
        child: Scaffold(
          backgroundColor: Colors.black,
          body: Stack(fit: StackFit.expand, children: [
            _content(),
            if (s.identifying) _identify(),
            if (_info) _infoPanel(),
          ]),
        ),
      ),
    );
  }

  Widget _content() {
    if (s.phase == PlayerPhase.pairing) return _pairing();
    if (s.phase == PlayerPhase.starting || (s.phase == PlayerPhase.error && s.docs.isEmpty)) return _splash();
    if (s.emergency.isNotEmpty) {
      final e = s.emergency.first;
      final style = asMap(e['style_json']);
      return EmergencyBanner(
        title: asString(e['title']), message: asString(e['body']),
        background: parseHexColor(style['background']) ?? const Color(0xFFB00020), color: parseHexColor(style['color']) ?? Colors.white,
        flash: asBool(style['flash'], true),
      );
    }
    final doc = s.activeDoc;
    return PlaylistPlaybackView(
      key: ValueKey('pl_${s.decision.playlistId}_${s.restartCounter}'),
      doc: doc ?? const {'items': []},
      media: s.mediaSource,
      mode: RenderMode.player,
      timezone: s.deviceTimezone,
      paused: s.paused,
      onItemStart: s.onItemStart,
      onItemEnd: s.onItemEnd,
      onError: s.onWidgetError,
      idle: _idle(),
    );
  }

  Widget _splash() => Center(
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          const Icon(Icons.cast_connected_rounded, color: AppColors.teal, size: 72),
          const SizedBox(height: 18),
          Text(AppConfig.appName, style: const TextStyle(color: Colors.white, fontSize: 28, fontWeight: FontWeight.w700)),
          const SizedBox(height: 10),
          Text(s.statusText, style: const TextStyle(color: Colors.white60)),
          const SizedBox(height: 18),
          const SizedBox(width: 28, height: 28, child: CircularProgressIndicator(strokeWidth: 2.5, color: AppColors.teal)),
        ]),
      );

  /// Branded idle screen: clock + name (never an error screen).
  Widget _idle() => Container(
        decoration: const BoxDecoration(gradient: LinearGradient(colors: [AppColors.darkTeal, AppColors.darkBackground], begin: Alignment.topLeft, end: Alignment.bottomRight)),
        child: Ticking(
          interval: const Duration(seconds: 1),
          builder: (_) {
            final now = nowIn(null, s.deviceTimezone);
            return Center(
              child: FittedBox(
                child: Padding(
                  padding: const EdgeInsets.all(40),
                  child: Column(mainAxisSize: MainAxisSize.min, children: [
                    Text(DateFormat('HH:mm').format(now), style: const TextStyle(color: Colors.white, fontSize: 160, fontWeight: FontWeight.w300)),
                    Text(DateFormat('EEEE, d MMMM').format(now), style: const TextStyle(color: Colors.white70, fontSize: 36)),
                    const SizedBox(height: 30),
                    Text(s.deviceName ?? AppConfig.appName, style: const TextStyle(color: Colors.white38, fontSize: 22)),
                    Text(s.assignments.isEmpty ? 'No content assigned yet' : s.decision.reason, style: const TextStyle(color: Colors.white24, fontSize: 18)),
                  ]),
                ),
              ),
            );
          },
        ),
      );

  Widget _pairing() {
    final code = s.pairingCode;
    return Container(
      decoration: const BoxDecoration(gradient: LinearGradient(colors: [AppColors.darkBackground, Color(0xFF0D2E2C)], begin: Alignment.topLeft, end: Alignment.bottomRight)),
      child: Center(
        child: FittedBox(
          child: Padding(
            padding: const EdgeInsets.all(48),
            child: Row(mainAxisSize: MainAxisSize.min, children: [
              Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
                const Row(children: [
                  Icon(Icons.cast_connected_rounded, color: AppColors.teal, size: 44),
                  SizedBox(width: 12),
                  Text('Connect this display', style: TextStyle(color: Colors.white, fontSize: 40, fontWeight: FontWeight.w700)),
                ]),
                const SizedBox(height: 28),
                const Text('1.  Open the Signal Signage app on your phone', style: TextStyle(color: Colors.white70, fontSize: 24)),
                const Text('2.  Go to Devices → Add device', style: TextStyle(color: Colors.white70, fontSize: 24)),
                const Text('3.  Enter this code:', style: TextStyle(color: Colors.white70, fontSize: 24)),
                const SizedBox(height: 22),
                Row(children: [
                  for (final ch in (code ?? '······').split(''))
                    Container(
                      margin: const EdgeInsets.only(right: 12),
                      width: 84, height: 108,
                      alignment: Alignment.center,
                      decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.08), borderRadius: BorderRadius.circular(18), border: Border.all(color: AppColors.teal, width: 2)),
                      child: Text(ch, style: const TextStyle(color: Colors.white, fontSize: 64, fontWeight: FontWeight.w800)),
                    ),
                ]),
                const SizedBox(height: 18),
                Text(s.pairingExpires == null ? s.statusText : 'Code refreshes automatically · Display ID ${s.deviceCode ?? ''}',
                    style: const TextStyle(color: Colors.white38, fontSize: 18)),
                Row(children: [
                  Icon(s.online ? Icons.wifi : Icons.wifi_off, color: s.online ? AppColors.success : AppColors.danger, size: 18),
                  const SizedBox(width: 6),
                  Text(s.online ? 'Online' : 'No connection — retrying', style: const TextStyle(color: Colors.white38, fontSize: 16)),
                ]),
              ]),
              if (code != null) ...[
                const SizedBox(width: 60),
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(20)),
                  child: QrImageView(data: 'signage-pair:$code', size: 220, padding: EdgeInsets.zero),
                ),
              ],
            ]),
          ),
        ),
      ),
    );
  }

  Widget _identify() => Container(
        color: AppColors.teal.withValues(alpha: 0.92),
        child: Center(
          child: FittedBox(
            child: Padding(
              padding: const EdgeInsets.all(40),
              child: Column(mainAxisSize: MainAxisSize.min, children: [
                const Icon(Icons.highlight_rounded, color: Colors.white, size: 120),
                Text(s.deviceName ?? '', style: const TextStyle(color: Colors.white, fontSize: 90, fontWeight: FontWeight.w800)),
                Text(s.deviceCode ?? '', style: const TextStyle(color: Colors.white70, fontSize: 44)),
              ]),
            ),
          ),
        ),
      );

  Widget _infoPanel() => Align(
        alignment: Alignment.bottomRight,
        child: Container(
          margin: const EdgeInsets.all(24),
          padding: const EdgeInsets.all(20),
          width: 420,
          decoration: BoxDecoration(color: Colors.black.withValues(alpha: 0.85), borderRadius: BorderRadius.circular(20), border: Border.all(color: Colors.white24)),
          child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(s.deviceName ?? 'Display', style: const TextStyle(color: Colors.white, fontSize: 22, fontWeight: FontWeight.w700)),
            const SizedBox(height: 8),
            for (final r in [
              ('Display ID', s.deviceCode ?? '—'), ('Status', s.online ? 'Online' : 'Offline (playing cached content)'),
              ('Playing', '${s.decision.playlistId ?? 'idle'} · ${s.decision.reason}'), ('Playlists cached', '${s.docs.length}'),
              ('Media cached', '${s.localPaths.length} files'), ('Last sync', formatDateTime(s.lastSync)), ('Server', AppConfig.apiBaseUrl),
              ('Version', AppConfig.playerVersion),
            ])
              Padding(padding: const EdgeInsets.only(bottom: 3), child: Text('${r.$1}: ${r.$2}', style: const TextStyle(color: Colors.white70))),
            const SizedBox(height: 12),
            Row(children: [
              FilledButton(onPressed: () { s.sync(); setState(() => _info = false); }, child: const Text('Sync now')),
              const SizedBox(width: 10),
              OutlinedButton(onPressed: () async { setState(() => _info = false); await s.reset(); }, child: const Text('Re-pair')),
              const Spacer(),
              TextButton(onPressed: () => setState(() => _info = false), child: const Text('Close')),
            ]),
          ]),
        ),
      );
}
