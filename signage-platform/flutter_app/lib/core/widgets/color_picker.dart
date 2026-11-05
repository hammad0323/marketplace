import 'package:flutter/material.dart';

import '../constants/app_colors.dart';
import '../utils/formatters.dart';

/// Lightweight colour picker: brand swatches + hue/lightness sliders + opacity + hex input.
Future<String?> pickColor(BuildContext context, {String? initial, bool allowAlpha = true, bool allowNone = false}) {
  return showModalBottomSheet<String>(
    context: context,
    isScrollControlled: true,
    builder: (c) => _ColorSheet(initial: initial, allowAlpha: allowAlpha, allowNone: allowNone),
  );
}

class _ColorSheet extends StatefulWidget {
  const _ColorSheet({this.initial, required this.allowAlpha, required this.allowNone});
  final String? initial;
  final bool allowAlpha;
  final bool allowNone;
  @override
  State<_ColorSheet> createState() => _ColorSheetState();
}

class _ColorSheetState extends State<_ColorSheet> {
  late HSLColor hsl;
  late double alpha;
  late TextEditingController hex;

  static const swatches = [
    AppColors.teal, AppColors.darkTeal, AppColors.lightTeal, AppColors.charcoal, AppColors.darkBackground, Colors.white,
    AppColors.lightGrey, AppColors.mediumGrey, Color(0xFFE53935), Color(0xFFFB8C00), Color(0xFFFDD835), Color(0xFF43A047),
    Color(0xFF1E88E5), Color(0xFF5E35B1), Color(0xFFD81B60), Color(0xFF6D4C41), Colors.black, Color(0xFFB00020),
  ];

  @override
  void initState() {
    super.initState();
    final c = parseHexColor(widget.initial) ?? AppColors.teal;
    hsl = HSLColor.fromColor(c.withValues(alpha: 1));
    alpha = c.a;
    hex = TextEditingController(text: colorToHex(c, withAlpha: true));
  }

  Color get color => hsl.toColor().withValues(alpha: alpha);
  void _sync() => hex.text = colorToHex(color, withAlpha: true);

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.fromLTRB(20, 0, 20, 20 + MediaQuery.of(context).viewInsets.bottom),
      child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Row(children: [
          Container(width: 44, height: 44, decoration: BoxDecoration(color: color, borderRadius: BorderRadius.circular(12), border: Border.all(color: AppColors.borderGrey))),
          const SizedBox(width: 12),
          Expanded(
            child: TextField(
              controller: hex,
              decoration: const InputDecoration(labelText: 'Hex', isDense: true),
              onSubmitted: (v) {
                final c = parseHexColor(v);
                if (c != null) setState(() { hsl = HSLColor.fromColor(c.withValues(alpha: 1)); alpha = c.a; });
              },
            ),
          ),
        ]),
        const SizedBox(height: 14),
        Wrap(spacing: 10, runSpacing: 10, children: [
          for (final s in swatches)
            GestureDetector(
              onTap: () => setState(() { hsl = HSLColor.fromColor(s); _sync(); }),
              child: Container(width: 34, height: 34, decoration: BoxDecoration(color: s, shape: BoxShape.circle, border: Border.all(color: AppColors.borderGrey))),
            ),
        ]),
        const SizedBox(height: 10),
        _slider('Hue', hsl.hue, 0, 360, (v) => hsl = hsl.withHue(v)),
        _slider('Saturation', hsl.saturation, 0, 1, (v) => hsl = hsl.withSaturation(v)),
        _slider('Lightness', hsl.lightness, 0, 1, (v) => hsl = hsl.withLightness(v)),
        if (widget.allowAlpha) _slider('Opacity', alpha, 0, 1, (v) => alpha = v),
        const SizedBox(height: 8),
        Row(children: [
          if (widget.allowNone)
            Expanded(child: OutlinedButton(onPressed: () => Navigator.pop(context, ''), child: const Text('None'))),
          if (widget.allowNone) const SizedBox(width: 12),
          Expanded(child: FilledButton(onPressed: () => Navigator.pop(context, colorToHex(color, withAlpha: widget.allowAlpha)), child: const Text('Use colour'))),
        ]),
      ]),
    );
  }

  Widget _slider(String label, double v, double min, double max, void Function(double) set) => Row(children: [
        SizedBox(width: 82, child: Text(label, style: const TextStyle(fontSize: 13))),
        Expanded(child: Slider(value: v.clamp(min, max), min: min, max: max, onChanged: (x) => setState(() { set(x); _sync(); }))),
      ]);
}

/// Small tappable colour swatch tile used in settings forms.
class ColorField extends StatelessWidget {
  const ColorField({super.key, required this.label, required this.value, required this.onChanged, this.allowNone = true});
  final String label;
  final String? value;
  final ValueChanged<String?> onChanged;
  final bool allowNone;

  @override
  Widget build(BuildContext context) {
    final c = parseHexColor(value);
    return ListTile(
      contentPadding: EdgeInsets.zero,
      title: Text(label),
      subtitle: Text(value == null || value!.isEmpty ? 'None' : value!),
      trailing: Container(
        width: 36, height: 36,
        decoration: BoxDecoration(
          color: c ?? Colors.transparent, borderRadius: BorderRadius.circular(10),
          border: Border.all(color: AppColors.borderGrey),
        ),
        child: c == null ? const Icon(Icons.block, size: 18, color: AppColors.mediumGrey) : null,
      ),
      onTap: () async {
        final r = await pickColor(context, initial: value, allowNone: allowNone);
        if (r != null) onChanged(r.isEmpty ? null : r);
      },
    );
  }
}
