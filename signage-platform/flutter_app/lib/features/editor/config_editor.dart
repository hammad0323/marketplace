import 'package:dio/dio.dart';
import 'package:flutter/material.dart';

import '../../core/constants/app_colors.dart';
import '../../core/utils/json.dart';
import '../../core/widgets/color_picker.dart';
import '../../core/widgets/common.dart';
import '../../models/media.dart';
import '../media_library/media_library_screen.dart';

/// Options for enum-like configuration keys.
const Map<String, List<String>> configEnums = {
  'fit': ['cover', 'contain', 'fill'],
  'align': ['left', 'center', 'right', 'justify'],
  'vertical_align': ['top', 'center', 'bottom'],
  'alignment': ['left', 'center', 'right'],
  'animation': ['none', 'fade_in', 'slide_up', 'slide_left', 'zoom_in'],
  'text_effect': ['none', 'shadow', 'glow', 'outline'],
  'style': ['digital', 'analog'],
  'design': ['minimal', 'bold'],
  'format': ['24h', '12h'],
  'direction': ['left', 'right', 'up', 'down'],
  'transition': ['fade', 'slide', 'zoom', 'flip', 'none'],
  'shape': ['rectangle', 'circle', 'line'],
  'units': ['metric', 'imperial'],
  'text_direction': ['ltr', 'rtl'],
  'mode': ['rotate', 'daily'],
  'unit': ['days', 'hours', 'minutes', 'hms'],
  'error_correction': ['L', 'M', 'Q', 'H'],
  'view': ['month'],
  'layout': ['card'],
  'provider': ['open_meteo'],
  'network': ['instagram', 'facebook', 'x'],
  'font_weight': ['100', '200', '300', '400', '500', '600', '700', '800', '900'],
  'font_family': ['Poppins', 'Inter', 'Montserrat', 'Roboto', 'Open Sans', 'Lato', 'Playfair Display', 'Oswald', 'Raleway', 'Merriweather', 'Bebas Neue', 'Nunito', 'Cairo', 'Noto Sans',
    'Amatic SC', 'Caveat', 'Pacifico', 'Lobster', 'Dancing Script', 'Anton', 'Archivo Black', 'Permanent Marker'],
};

/// Slider ranges for numeric keys: (min, max, divisions).
const Map<String, (double, double, int?)> configRanges = {
  'font_size': (8, 400, null), 'opacity': (0, 1, 20), 'border_radius': (0, 200, null), 'border_width': (0, 40, 40),
  'letter_spacing': (-5, 40, null), 'line_height': (0.8, 3, 22), 'speed': (10, 600, null), 'gap': (0, 400, null),
  'padding': (0, 200, null), 'rotate_seconds': (2, 120, 118), 'duration': (1, 600, null), 'transition_ms': (0, 3000, 30),
  'overlay_opacity': (0, 1, 20), 'blur': (0, 40, 40), 'forecast_days': (1, 7, 6), 'refresh_minutes': (0, 1440, null),
  'columns': (1, 3, 2), 'default_duration': (1, 120, 119), 'pause_ms': (0, 10000, 20), 'first_day_of_week': (1, 7, 6),
  'animation_duration_ms': (100, 3000, 29), 'max_items': (1, 30, 29), 'refresh_seconds': (10, 3600, null),
  'page_duration': (2, 120, null), 'shadow_blur': (0, 120, null), 'shadow_offset_x': (-80, 80, null), 'shadow_offset_y': (-80, 80, null),
  'effect_blur': (0, 60, null), 'effect_offset_y': (-30, 30, null), 'start_sec': (0, 3600, null), 'angle': (0, 360, 36), 'slide_duration': (2, 120, null),
};

const _multiline = {'text', 'body', 'message', 'html', 'css', 'description', 'address', 'directions'};
const _hidden = {'placeholder'};

String labelOf(String key) {
  final s = key.replaceAll('_json', '').replaceAll('_', ' ');
  return s[0].toUpperCase() + s.substring(1);
}

bool _isColorKey(String k) => k.contains('color') || k == 'foreground' || k == 'background';

/// Recursive editor for a JSON configuration map. Calls [onChanged] after every edit.
class ConfigEditor extends StatelessWidget {
  const ConfigEditor({super.key, required this.config, required this.onChanged, required this.widgetType, this.onMediaPicked});
  final Map<String, dynamic> config;
  final VoidCallback onChanged;
  final String widgetType;
  final void Function(MediaItem)? onMediaPicked;

  @override
  Widget build(BuildContext context) {
    final keys = config.keys.where((k) => !_hidden.contains(k)).toList();
    return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      if (widgetType == 'weather') _LocationSearch(config: config, onChanged: onChanged),
      if (widgetType == 'text') _RichTextBar(config: config, onChanged: onChanged),
      for (final k in keys) _field(context, config, k, onChanged),
    ]);
  }

  Widget _field(BuildContext context, Map<String, dynamic> map, String k, VoidCallback changed) {
    final v = map[k];
    void set(dynamic x) {
      map[k] = x;
      changed();
    }

    if (k == 'media_id') {
      return _MediaField(mediaId: asIntOrNull(v), widgetType: widgetType, onPicked: (m) { onMediaPicked?.call(m); set(m.mediaId); }, onClear: () => set(null));
    }
    if (k == 'spans') {
      if (v == null) return const SizedBox.shrink();
      return _ListEditor(label: 'Styled segments', list: v as List, onChanged: changed, itemTemplate: () => {'text': 'Text', 'bold': false, 'italic': false, 'underline': false, 'color': null}, editorFor: _nested);
    }
    if (configEnums.containsKey(k) && (v is String || v is int || v == null)) {
      final opts = configEnums[k]!;
      final cur = v?.toString();
      return Padding(
        padding: const EdgeInsets.symmetric(vertical: 6),
        child: DropdownButtonFormField<String>(
          initialValue: opts.contains(cur) ? cur : null,
          isExpanded: true,
          decoration: InputDecoration(labelText: labelOf(k), isDense: true),
          items: [for (final o in opts) DropdownMenuItem(value: o, child: Text(k == 'font_family' ? o : labelOf(o)))],
          onChanged: (x) => set(k == 'font_weight' ? int.parse(x!) : x),
        ),
      );
    }
    if (_isColorKey(k) && (v == null || v is String)) {
      return ColorField(label: labelOf(k), value: v as String?, onChanged: set);
    }
    if (v is bool) {
      return SwitchListTile(contentPadding: EdgeInsets.zero, title: Text(labelOf(k)), value: v, onChanged: set);
    }
    if (v is num && configRanges.containsKey(k)) {
      final r = configRanges[k]!;
      final isInt = v is int && r.$3 == null ? true : v is int;
      return _SliderField(label: labelOf(k), value: v.toDouble(), min: r.$1, max: r.$2, divisions: r.$3, isInt: isInt, onChanged: (x) => set(isInt ? x.round() : double.parse(x.toStringAsFixed(2))));
    }
    if (v is num || (v == null && (k.endsWith('_sec') || k.endsWith('_id')))) {
      return _TextField(label: labelOf(k), value: v?.toString() ?? '', keyboard: TextInputType.number, onChanged: (s) => set(num.tryParse(s)));
    }
    if (v is List && (v.isEmpty || v.first is String || v.first is num)) {
      if (k == 'days_of_week') return const SizedBox.shrink();
      return _StringListEditor(label: labelOf(k), list: v, onChanged: changed);
    }
    if (v is List) {
      final isMediaList = v.isNotEmpty && v.first is Map && (v.first as Map).containsKey('media_id') && (v.first as Map).length <= 3;
      return _ListEditor(
        label: labelOf(k),
        list: v,
        onChanged: changed,
        mediaList: isMediaList || ((k == 'items') && (widgetType == 'image_carousel' || widgetType == 'video')),
        mediaType: widgetType == 'video' ? 'video' : 'image',
        onMediaPicked: onMediaPicked,
        itemTemplate: () => _blankLike(v.isNotEmpty ? v.first : null, k),
        editorFor: _nested,
      );
    }
    if (v is Map) {
      return _Section(title: labelOf(k), child: Column(children: [for (final kk in v.keys.toList()) _field(context, v as Map<String, dynamic>, kk, changed)]));
    }
    if (k == 'target' || k == 'start' || k.endsWith('_at') || k == 'date') {
      return _DateTimeField(label: labelOf(k), value: v?.toString() ?? '', onChanged: set);
    }
    return _TextField(label: labelOf(k), value: v?.toString() ?? '', multiline: _multiline.contains(k), onChanged: set);
  }

  Widget _nested(BuildContext context, Map<String, dynamic> m, VoidCallback changed) =>
      Column(children: [for (final k in m.keys.toList()) _field(context, m, k, changed)]);

  /// New list item shaped like an existing one (values reset).
  static Map<String, dynamic> _blankLike(dynamic sample, String listKey) {
    if (sample is Map) {
      return sample.map((k, v) => MapEntry(k.toString(), switch (v) {
            String _ => k == 'start' ? '09:00' : (k == 'end' ? '10:00' : (k == 'price' ? '0.00' : '')),
            bool b => b,
            int i => k == 'day' ? i : i,
            double d => d,
            List _ => <dynamic>[],
            Map m => _blankLike(m, k),
            _ => null,
          }));
    }
    return {'title': '', 'body': ''};
  }
}

class _Section extends StatelessWidget {
  const _Section({required this.title, required this.child});
  final String title;
  final Widget child;
  @override
  Widget build(BuildContext context) => Container(
        margin: const EdgeInsets.symmetric(vertical: 6),
        decoration: BoxDecoration(border: Border.all(color: Theme.of(context).colorScheme.outline), borderRadius: BorderRadius.circular(14)),
        child: ExpansionTile(
          title: Text(title, style: const TextStyle(fontWeight: FontWeight.w600)),
          shape: const Border(),
          childrenPadding: const EdgeInsets.fromLTRB(12, 0, 12, 12),
          children: [child],
        ),
      );
}

class _TextField extends StatefulWidget {
  const _TextField({super.key, required this.label, required this.value, required this.onChanged, this.multiline = false, this.keyboard});
  final String label, value;
  final ValueChanged<String> onChanged;
  final bool multiline;
  final TextInputType? keyboard;
  @override
  State<_TextField> createState() => _TextFieldState();
}

class _TextFieldState extends State<_TextField> {
  late final c = TextEditingController(text: widget.value);
  @override
  void didUpdateWidget(_TextField old) {
    super.didUpdateWidget(old);
    if (widget.value != c.text && !FocusScope.of(context).hasFocus) c.text = widget.value;
  }

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 6),
        child: TextField(
          controller: c,
          keyboardType: widget.keyboard ?? (widget.multiline ? TextInputType.multiline : TextInputType.text),
          minLines: widget.multiline ? 2 : 1,
          maxLines: widget.multiline ? 6 : 1,
          decoration: InputDecoration(labelText: widget.label, isDense: true),
          onChanged: widget.onChanged,
        ),
      );
}

class _SliderField extends StatelessWidget {
  const _SliderField({required this.label, required this.value, required this.min, required this.max, required this.onChanged, this.divisions, this.isInt = false});
  final String label;
  final double value, min, max;
  final int? divisions;
  final bool isInt;
  final ValueChanged<double> onChanged;
  @override
  Widget build(BuildContext context) {
    final v = value.clamp(min, max).toDouble();
    return Padding(
      padding: const EdgeInsets.only(top: 4),
      child: Row(children: [
        SizedBox(width: 110, child: Text(label, style: const TextStyle(fontSize: 13))),
        Expanded(child: Slider(value: v, min: min, max: max, divisions: divisions, onChanged: onChanged)),
        SizedBox(width: 48, child: Text(isInt ? v.round().toString() : v.toStringAsFixed(2), textAlign: TextAlign.end, style: const TextStyle(fontSize: 12, color: AppColors.mediumGrey))),
      ]),
    );
  }
}

class _DateTimeField extends StatelessWidget {
  const _DateTimeField({required this.label, required this.value, required this.onChanged});
  final String label, value;
  final ValueChanged<String> onChanged;
  @override
  Widget build(BuildContext context) => ListTile(
        contentPadding: EdgeInsets.zero,
        title: Text(label),
        subtitle: Text(value.isEmpty ? 'Not set' : value.replaceFirst('T', ' ')),
        trailing: const Icon(Icons.event_outlined),
        onTap: () async {
          final init = DateTime.tryParse(value) ?? DateTime.now();
          final d = await showDatePicker(context: context, initialDate: init, firstDate: DateTime(2000), lastDate: DateTime(2100));
          if (d == null || !context.mounted) return;
          final t = await showTimePicker(context: context, initialTime: TimeOfDay.fromDateTime(init));
          final dt = DateTime(d.year, d.month, d.day, t?.hour ?? 0, t?.minute ?? 0);
          onChanged(dt.toIso8601String().substring(0, 19));
        },
      );
}

class _StringListEditor extends StatelessWidget {
  const _StringListEditor({required this.label, required this.list, required this.onChanged});
  final String label;
  final List list;
  final VoidCallback onChanged;
  @override
  Widget build(BuildContext context) => _Section(
        title: '$label (${list.length})',
        child: Column(children: [
          for (var i = 0; i < list.length; i++)
            Row(children: [
              Expanded(child: _TextField(key: ValueKey('$label$i${list.length}'), label: '#${i + 1}', value: '${list[i]}', onChanged: (s) { list[i] = s; onChanged(); })),
              IconButton(icon: const Icon(Icons.close, size: 18), onPressed: () { list.removeAt(i); onChanged(); }),
            ]),
          Align(alignment: Alignment.centerLeft, child: TextButton.icon(onPressed: () { list.add(''); onChanged(); }, icon: const Icon(Icons.add), label: const Text('Add'))),
        ]),
      );
}

class _ListEditor extends StatelessWidget {
  const _ListEditor({
    required this.label, required this.list, required this.onChanged, required this.itemTemplate, required this.editorFor,
    this.mediaList = false, this.mediaType = 'image', this.onMediaPicked,
  });
  final String label;
  final List list;
  final VoidCallback onChanged;
  final Map<String, dynamic> Function() itemTemplate;
  final Widget Function(BuildContext, Map<String, dynamic>, VoidCallback) editorFor;
  final bool mediaList;
  final String mediaType;
  final void Function(MediaItem)? onMediaPicked;

  @override
  Widget build(BuildContext context) => _Section(
        title: '$label (${list.length})',
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          for (var i = 0; i < list.length; i++)
            Container(
              margin: const EdgeInsets.only(bottom: 8),
              padding: const EdgeInsets.fromLTRB(10, 4, 4, 8),
              decoration: BoxDecoration(color: Theme.of(context).colorScheme.surfaceContainerHigh, borderRadius: BorderRadius.circular(12)),
              child: Column(children: [
                Row(children: [
                  Text('#${i + 1}', style: const TextStyle(fontWeight: FontWeight.w700)),
                  const Spacer(),
                  IconButton(visualDensity: VisualDensity.compact, icon: const Icon(Icons.arrow_upward, size: 18), onPressed: i == 0 ? null : () { final x = list.removeAt(i); list.insert(i - 1, x); onChanged(); }),
                  IconButton(visualDensity: VisualDensity.compact, icon: const Icon(Icons.arrow_downward, size: 18), onPressed: i == list.length - 1 ? null : () { final x = list.removeAt(i); list.insert(i + 1, x); onChanged(); }),
                  IconButton(visualDensity: VisualDensity.compact, icon: const Icon(Icons.delete_outline, size: 18), onPressed: () { list.removeAt(i); onChanged(); }),
                ]),
                if (list[i] is Map) editorFor(context, asMap(list[i]).isEmpty ? <String, dynamic>{} : (list[i] as Map<String, dynamic>), onChanged),
              ]),
            ),
          TextButton.icon(
            onPressed: () async {
              if (mediaList) {
                final m = await showMediaPicker(context, type: mediaType);
                if (m == null) return;
                onMediaPicked?.call(m);
                list.add({'media_id': m.mediaId, if (mediaType == 'image') 'duration': 5});
              } else {
                list.add(itemTemplate());
              }
              onChanged();
            },
            icon: const Icon(Icons.add),
            label: Text(mediaList ? 'Add ${mediaType == 'video' ? 'video' : 'image'}' : 'Add item'),
          ),
        ]),
      );
}

class _MediaField extends StatelessWidget {
  const _MediaField({required this.mediaId, required this.widgetType, required this.onPicked, required this.onClear});
  final int? mediaId;
  final String widgetType;
  final void Function(MediaItem) onPicked;
  final VoidCallback onClear;
  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 6),
        child: Row(children: [
          Expanded(
            child: FilledButton.tonalIcon(
              onPressed: () async {
                final m = await showMediaPicker(context, type: widgetType == 'video' ? 'video' : (widgetType == 'pdf_viewer' ? 'document' : 'image'));
                if (m != null) onPicked(m);
              },
              icon: const Icon(Icons.photo_library_outlined),
              label: Text(mediaId == null ? 'Choose from media library' : 'Replace media (#$mediaId)'),
            ),
          ),
          if (mediaId != null) IconButton(onPressed: onClear, icon: const Icon(Icons.close)),
        ]),
      );
}

/// Whole-text bold/italic/underline toggles + segment styling for the Text widget.
class _RichTextBar extends StatelessWidget {
  const _RichTextBar({required this.config, required this.onChanged});
  final Map<String, dynamic> config;
  final VoidCallback onChanged;
  @override
  Widget build(BuildContext context) {
    final bold = asInt(config['font_weight'], 400) >= 700;
    return Padding(
      padding: const EdgeInsets.only(bottom: 6),
      child: Wrap(spacing: 8, crossAxisAlignment: WrapCrossAlignment.center, children: [
        IconButton.outlined(isSelected: bold, icon: const Icon(Icons.format_bold), onPressed: () { config['font_weight'] = bold ? 400 : 700; onChanged(); }),
        IconButton.outlined(isSelected: asBool(config['italic']), icon: const Icon(Icons.format_italic), onPressed: () { config['italic'] = !asBool(config['italic']); onChanged(); }),
        IconButton.outlined(isSelected: asBool(config['underline']), icon: const Icon(Icons.format_underline), onPressed: () { config['underline'] = !asBool(config['underline']); onChanged(); }),
        TextButton.icon(
          onPressed: () {
            if (config['spans'] == null) {
              config['spans'] = [{'text': asString(config['text']), 'bold': false, 'italic': false, 'underline': false, 'color': null}];
            } else {
              config['spans'] = null;
            }
            onChanged();
          },
          icon: const Icon(Icons.format_color_text),
          label: Text(config['spans'] == null ? 'Style parts of the text' : 'Use plain text'),
        ),
      ]),
    );
  }
}

/// Location search for the weather widget (Open-Meteo geocoding, no key needed).
class _LocationSearch extends StatelessWidget {
  const _LocationSearch({required this.config, required this.onChanged});
  final Map<String, dynamic> config;
  final VoidCallback onChanged;
  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(bottom: 8),
        child: OutlinedButton.icon(
          icon: const Icon(Icons.travel_explore),
          label: Text('Location: ${config['location_name'] ?? '—'}'),
          onPressed: () async {
            final q = await promptText(context, title: 'Find a city', label: 'City name', action: 'Search');
            if (q == null || q.isEmpty || !context.mounted) return;
            try {
              final r = await Dio().get('https://geocoding-api.open-meteo.com/v1/search', queryParameters: {'name': q, 'count': 8});
              final results = asList(asMap(r.data)['results']).map(asMap).toList();
              if (!context.mounted) return;
              if (results.isEmpty) return showToast(context, 'No places found for "$q"', error: true);
              final pick = await showModalBottomSheet<Map<String, dynamic>>(
                context: context,
                builder: (c) => SafeArea(child: ListView(shrinkWrap: true, children: [
                  for (final p in results)
                    ListTile(leading: const Icon(Icons.place_outlined), title: Text(asString(p['name'])), subtitle: Text([p['admin1'], p['country']].whereType<String>().join(', ')), onTap: () => Navigator.pop(c, p)),
                ])),
              );
              if (pick == null) return;
              config['location_name'] = asString(pick['name']);
              config['latitude'] = asDouble(pick['latitude']);
              config['longitude'] = asDouble(pick['longitude']);
              onChanged();
            } catch (_) {
              if (context.mounted) showToast(context, 'Location search is unavailable right now', error: true);
            }
          },
        ),
      );
}
