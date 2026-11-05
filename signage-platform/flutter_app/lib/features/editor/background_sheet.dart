import 'package:flutter/material.dart';

import '../../core/constants/app_colors.dart';
import '../../core/utils/formatters.dart';
import '../../core/utils/json.dart';
import '../../core/widgets/color_picker.dart';
import '../../models/layout.dart';
import '../../models/media.dart';
import '../media_library/media_library_screen.dart';
import '../widgets_library/layout_view.dart';

/// Full-screen background editor: colour, gradient, image, video, presets,
/// plus opacity, blur, overlay, fit and position.
Future<BackgroundSpec?> showBackgroundSheet(BuildContext context, BackgroundSpec current, void Function(MediaItem) onMedia) {
  return showModalBottomSheet<BackgroundSpec>(
    context: context,
    isScrollControlled: true,
    builder: (_) => FractionallySizedBox(heightFactor: 0.85, child: _BackgroundEditor(initial: current, onMedia: onMedia)),
  );
}

class _BackgroundEditor extends StatefulWidget {
  const _BackgroundEditor({required this.initial, required this.onMedia});
  final BackgroundSpec initial;
  final void Function(MediaItem) onMedia;
  @override
  State<_BackgroundEditor> createState() => _BackgroundEditorState();
}

class _BackgroundEditorState extends State<_BackgroundEditor> {
  late BackgroundSpec bg = BackgroundSpec.fromJson(widget.initial.toJson());
  String? mediaName;

  Map<String, dynamic> get grad => bg.config['gradient'] is Map ? asMap(bg.config['gradient']) : {'type': 'linear', 'angle': 135, 'colors': ['#008C86', '#17191C'], 'stops': [0, 1]};

  void setType(String t) => setState(() {
        bg.type = t;
        if (t == 'color' && parseHexColor(bg.value) == null) bg.value = '#17191C';
        if (t == 'gradient') bg.config['gradient'] = grad;
        if (t == 'preset' && !backgroundPresets.containsKey(bg.value)) bg.value = 'teal_night';
        if (t != 'image' && t != 'video') bg.mediaId = null;
      });

  @override
  Widget build(BuildContext context) {
    final c = bg.config;
    Widget slider(String label, String key, double min, double max, double def) => Row(children: [
          SizedBox(width: 100, child: Text(label)),
          Expanded(child: Slider(value: asDouble(c[key], def).clamp(min, max), min: min, max: max, onChanged: (v) => setState(() => c[key] = double.parse(v.toStringAsFixed(2))))),
        ]);
    return Column(children: [
      Padding(
        padding: const EdgeInsets.fromLTRB(20, 0, 12, 8),
        child: Row(children: [
          Expanded(child: Text('Background', style: Theme.of(context).textTheme.titleLarge)),
          FilledButton(style: FilledButton.styleFrom(minimumSize: const Size(0, 42)), onPressed: () => Navigator.pop(context, bg), child: const Text('Apply')),
        ]),
      ),
      SingleChildScrollView(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 16),
        child: SegmentedButton<String>(
          showSelectedIcon: false,
          segments: const [
            ButtonSegment(value: 'color', icon: Icon(Icons.format_color_fill, size: 18), label: Text('Colour')),
            ButtonSegment(value: 'gradient', icon: Icon(Icons.gradient, size: 18), label: Text('Gradient')),
            ButtonSegment(value: 'image', icon: Icon(Icons.image_outlined, size: 18), label: Text('Image')),
            ButtonSegment(value: 'video', icon: Icon(Icons.movie_outlined, size: 18), label: Text('Video')),
            ButtonSegment(value: 'preset', icon: Icon(Icons.auto_awesome, size: 18), label: Text('Presets')),
          ],
          selected: {bg.type},
          onSelectionChanged: (s) => setType(s.first),
        ),
      ),
      Expanded(
        child: ListView(padding: const EdgeInsets.all(20), children: [
          if (bg.type == 'color') ColorField(label: 'Colour', value: bg.value, allowNone: false, onChanged: (v) => setState(() => bg.value = v)),
          if (bg.type == 'gradient') ...[
            for (var i = 0; i < asList(grad['colors']).length; i++)
              ColorField(label: 'Colour ${i + 1}', value: asList(grad['colors'])[i]?.toString(), allowNone: false, onChanged: (v) => setState(() {
                    final g = grad;
                    final cols = [...asList(g['colors'])];
                    cols[i] = v;
                    g['colors'] = cols;
                    bg.config['gradient'] = g;
                  })),
            Row(children: [
              TextButton.icon(onPressed: () => setState(() {
                    final g = grad;
                    final cols = [...asList(g['colors']), '#FFFFFF'];
                    g['colors'] = cols;
                    g['stops'] = List.generate(cols.length, (i) => i / (cols.length - 1));
                    bg.config['gradient'] = g;
                  }), icon: const Icon(Icons.add), label: const Text('Add colour')),
              const Spacer(),
              SegmentedButton<String>(
                showSelectedIcon: false,
                segments: const [ButtonSegment(value: 'linear', label: Text('Linear')), ButtonSegment(value: 'radial', label: Text('Radial'))],
                selected: {asString(grad['type'], 'linear')},
                onSelectionChanged: (s) => setState(() { final g = grad; g['type'] = s.first; bg.config['gradient'] = g; }),
              ),
            ]),
            Row(children: [
              const SizedBox(width: 100, child: Text('Angle')),
              Expanded(child: Slider(value: asDouble(grad['angle'], 135).clamp(0, 360), min: 0, max: 360, divisions: 36, onChanged: (v) => setState(() { final g = grad; g['angle'] = v.round(); bg.config['gradient'] = g; }))),
            ]),
          ],
          if (bg.type == 'image' || bg.type == 'video') ...[
            FilledButton.tonalIcon(
              icon: const Icon(Icons.photo_library_outlined),
              label: Text(bg.mediaId == null ? 'Choose ${bg.type}' : (mediaName ?? 'Replace ${bg.type} (#${bg.mediaId})')),
              onPressed: () async {
                final m = await showMediaPicker(context, type: bg.type);
                if (m == null) return;
                widget.onMedia(m);
                setState(() { bg.mediaId = m.mediaId; bg.value = '${m.mediaId}'; mediaName = m.originalName; });
              },
            ),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              initialValue: asString(c['fit'], 'cover'),
              decoration: const InputDecoration(labelText: 'Fit'),
              items: const [DropdownMenuItem(value: 'cover', child: Text('Cover (fill, crop edges)')), DropdownMenuItem(value: 'contain', child: Text('Contain (show all)')), DropdownMenuItem(value: 'fill', child: Text('Stretch'))],
              onChanged: (v) => setState(() => c['fit'] = v),
            ),
            const SizedBox(height: 12),
            if (bg.type == 'image')
              DropdownButtonFormField<String>(
                initialValue: asString(c['position'], 'center'),
                decoration: const InputDecoration(labelText: 'Position'),
                items: [for (final p in ['center', 'top', 'bottom', 'left', 'right']) DropdownMenuItem(value: p, child: Text(p[0].toUpperCase() + p.substring(1)))],
                onChanged: (v) => setState(() => c['position'] = v),
              ),
          ],
          if (bg.type == 'preset')
            Wrap(spacing: 12, runSpacing: 12, children: [
              for (final e in backgroundPresets.entries)
                GestureDetector(
                  onTap: () => setState(() => bg.value = e.key),
                  child: Container(
                    width: 96, height: 64,
                    decoration: BoxDecoration(
                      gradient: LinearGradient(colors: e.value.map((h) => parseHexColor(h)!).toList()),
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(color: bg.value == e.key ? AppColors.teal : Colors.transparent, width: 3),
                    ),
                    alignment: Alignment.bottomLeft,
                    padding: const EdgeInsets.all(6),
                    child: Text(e.key.replaceAll('_', ' '), style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w700)),
                  ),
                ),
            ]),
          const Divider(height: 32),
          Text('Effects', style: Theme.of(context).textTheme.titleSmall),
          slider('Opacity', 'opacity', 0, 1, 1),
          slider('Blur', 'blur', 0, 40, 0),
          ColorField(label: 'Overlay colour', value: c['overlay_color']?.toString() ?? '#000000', allowNone: false, onChanged: (v) => setState(() => c['overlay_color'] = v)),
          slider('Overlay', 'overlay_opacity', 0, 1, 0),
        ]),
      ),
    ]);
  }
}
