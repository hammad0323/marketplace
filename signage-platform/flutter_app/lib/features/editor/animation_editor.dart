import 'package:flutter/material.dart';

import '../../core/constants/app_colors.dart';
import '../../core/utils/json.dart';
import '../widgets_library/widget_animation.dart';

/// Animation settings for one widget (works for every widget type).
class AnimationEditor extends StatelessWidget {
  const AnimationEditor({super.key, required this.config, required this.onChanged, this.onPreview});
  final Map<String, dynamic> config;
  final VoidCallback onChanged;
  final VoidCallback? onPreview;

  void _set(String k, dynamic v) {
    config[k] = v;
    onChanged();
  }

  @override
  Widget build(BuildContext context) {
    final entrance = asString(config['animation'], 'none');
    final loop = asString(config['loop_animation'], 'none');
    final loopMs = asDouble(config['loop_duration_ms']);
    Widget slider(String label, double v, double min, double max, String Function(double) fmt, void Function(double) set, {int? divisions}) => Row(children: [
          SizedBox(width: 104, child: Text(label, style: const TextStyle(fontSize: 13))),
          Expanded(child: Slider(value: v.clamp(min, max), min: min, max: max, divisions: divisions, onChanged: set)),
          SizedBox(width: 56, child: Text(fmt(v), textAlign: TextAlign.end, style: const TextStyle(fontSize: 12, color: AppColors.mediumGrey))),
        ]);
    String secs(double ms) => '${(ms / 1000).toStringAsFixed(ms < 10000 ? 1 : 0)} s';
    Widget chips(Map<String, String> opts, String current, void Function(String) pick) => Wrap(spacing: 6, runSpacing: 6, children: [
          for (final e in opts.entries)
            ChoiceChip(label: Text(e.value), selected: current == e.key, onSelected: (_) => pick(e.key), visualDensity: VisualDensity.compact),
        ]);
    return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      const Text('Animations play on the TV and in Preview. Combine an entrance with a loop effect, and use “Replay” so the screen keeps playing like a video.',
          style: TextStyle(color: AppColors.mediumGrey, fontSize: 12.5)),
      const SizedBox(height: 14),
      Text('Entrance', style: Theme.of(context).textTheme.titleSmall),
      const SizedBox(height: 8),
      chips(entranceEffects, entrance, (k) => _set('animation', k)),
      if (entrance != 'none') ...[
        const SizedBox(height: 6),
        slider('Duration', asDouble(config['animation_duration_ms'], 800), 200, 5000, secs, (x) => _set('animation_duration_ms', x.round()), divisions: 48),
        slider('Delay', asDouble(config['animation_delay_ms']), 0, 20000, secs, (x) => _set('animation_delay_ms', (x / 50).round() * 50), divisions: 400),
        slider('Replay every', asDouble(config['animation_replay_seconds']), 0, 120, (v) => v < 1 ? 'off' : '${v.round()} s', (x) => _set('animation_replay_seconds', x.round()), divisions: 120),
      ],
      const Divider(height: 28),
      Text('Loop effect (plays continuously)', style: Theme.of(context).textTheme.titleSmall),
      const SizedBox(height: 8),
      chips(loopEffects, loop, (k) => _set('loop_animation', k)),
      if (loop != 'none') ...[
        const SizedBox(height: 6),
        SwitchListTile(
          contentPadding: EdgeInsets.zero, dense: true, title: const Text('Default speed'), value: loopMs <= 0,
          onChanged: (v) => _set('loop_duration_ms', v ? 0 : (loopDefaultMs[loop] ?? 2000)),
        ),
        if (loopMs > 0) slider('One cycle', loopMs, 300, 30000, secs, (x) => _set('loop_duration_ms', (x / 100).round() * 100)),
        slider('Intensity', asDouble(config['loop_intensity'], 1), 0.2, 3, (v) => '${(v * 100).round()}%', (x) => _set('loop_intensity', double.parse(x.toStringAsFixed(2))), divisions: 28),
      ],
      if (onPreview != null) ...[
        const SizedBox(height: 12),
        OutlinedButton.icon(onPressed: onPreview, icon: const Icon(Icons.play_arrow_rounded), label: const Text('Preview screen')),
      ],
    ]);
  }
}

/// Bottom sheet: animate every element of the screen at once.
Future<(String, int)?> showScreenAnimationSheet(BuildContext context) {
  var style = 'smooth';
  var replay = 12;
  return showModalBottomSheet<(String, int)>(
    context: context,
    isScrollControlled: true,
    builder: (s) => StatefulBuilder(
      builder: (s, set) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 0, 20, 20),
          child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Text('Animate whole screen', style: Theme.of(s).textTheme.titleLarge),
            const SizedBox(height: 6),
            const Text('Elements appear one after another from top to bottom, badges pulse, photos float and decorations sway. Fine-tune any element in its Animation tab.',
                style: TextStyle(color: AppColors.mediumGrey)),
            const SizedBox(height: 14),
            Wrap(spacing: 8, runSpacing: 8, children: [
              for (final e in screenAnimationStyles.entries) ChoiceChip(label: Text(e.value), selected: style == e.key, onSelected: (_) => set(() => style = e.key)),
            ]),
            if (style != 'none') ...[
              const SizedBox(height: 14),
              Row(children: [
                const Text('Replay scene every'),
                Expanded(child: Slider(value: replay.toDouble(), min: 0, max: 60, divisions: 60, onChanged: (v) => set(() => replay = v.round()))),
                SizedBox(width: 48, child: Text(replay == 0 ? 'off' : '$replay s', textAlign: TextAlign.end)),
              ]),
            ],
            const SizedBox(height: 12),
            FilledButton.icon(onPressed: () => Navigator.pop(s, (style, replay)), icon: const Icon(Icons.auto_awesome), label: const Text('Apply')),
          ]),
        ),
      ),
    ),
  );
}
