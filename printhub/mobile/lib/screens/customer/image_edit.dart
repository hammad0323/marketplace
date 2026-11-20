import 'dart:typed_data';
import 'dart:ui' as ui;

import 'package:crop_your_image/crop_your_image.dart';
import 'package:material_ui/material_ui.dart';

/// Result of the image editor, applied by the server in this order: flip → rotate → crop (fractions of the rotated image).
class ImageEdit {
  const ImageEdit({this.rotate = 0, this.flip = false, this.crop});
  final int rotate;
  final bool flip;
  final Rect? crop; // x, y, w, h as fractions 0..1
  Map<String, dynamic> toLayout() => {
        'rotate': rotate,
        'flip': flip,
        'crop': crop == null ? null : {'x': crop!.left, 'y': crop!.top, 'w': crop!.width, 'h': crop!.height},
      };
}

/// Flips / rotates image bytes with dart:ui, downscaled to [maxSide] for fast on-device editing.
Future<(Uint8List, Size)> transformImage(Uint8List bytes, {int rotate = 0, bool flip = false, int maxSide = 1600}) async {
  final codec = await ui.instantiateImageCodec(bytes);
  final src = (await codec.getNextFrame()).image;
  final scale = (maxSide / (src.width > src.height ? src.width : src.height)).clamp(0.05, 1.0);
  final w = src.width * scale, h = src.height * scale;
  final quarter = ((rotate ~/ 90) % 4 + 4) % 4;
  final outW = quarter.isOdd ? h : w, outH = quarter.isOdd ? w : h;
  final rec = ui.PictureRecorder();
  final canvas = Canvas(rec)
    ..translate(outW / 2, outH / 2)
    ..rotate(quarter * 3.141592653589793 / 2);
  if (flip) canvas.scale(-1, 1);
  canvas.drawImageRect(src, Rect.fromLTWH(0, 0, src.width.toDouble(), src.height.toDouble()), Rect.fromCenter(center: Offset.zero, width: w, height: h), Paint()..filterQuality = FilterQuality.medium);
  final img = await rec.endRecording().toImage(outW.round(), outH.round());
  final data = await img.toByteData(format: ui.ImageByteFormat.png);
  return (data!.buffer.asUint8List(), Size(outW.roundToDouble(), outH.roundToDouble()));
}

class ImageEditScreen extends StatefulWidget {
  const ImageEditScreen({super.key, required this.bytes, this.initial = const ImageEdit(), this.aspectRatio});
  final Uint8List bytes;
  final ImageEdit initial;
  final double? aspectRatio;
  @override
  State<ImageEditScreen> createState() => _ImageEditScreenState();
}

class _ImageEditScreenState extends State<ImageEditScreen> {
  late int _rotate = widget.initial.rotate;
  late bool _flip = widget.initial.flip;
  late double? _aspect = widget.aspectRatio;
  Uint8List? _shown;
  Size _size = Size.zero;
  Rect? _crop;
  int _gen = 0;
  final _ctl = CropController();

  @override
  void initState() {
    super.initState();
    _render();
  }

  Future<void> _render() async {
    setState(() => _shown = null);
    final (b, s) = await transformImage(widget.bytes, rotate: _rotate, flip: _flip);
    if (!mounted) return;
    setState(() {
      _shown = b;
      _size = s;
      _crop = null;
      _gen++;
    });
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        backgroundColor: const Color(0xFF0F172A),
        appBar: AppBar(
          title: const Text('Edit photo'),
          backgroundColor: const Color(0xFF0F172A),
          foregroundColor: Colors.white,
          actions: [
            TextButton(
              onPressed: () {
                Rect? frac;
                if (_crop != null && _size.width > 0) {
                  frac = Rect.fromLTWH((_crop!.left / _size.width).clamp(0, 1), (_crop!.top / _size.height).clamp(0, 1), (_crop!.width / _size.width).clamp(0.01, 1), (_crop!.height / _size.height).clamp(0.01, 1));
                  if (frac.left < 0.005 && frac.top < 0.005 && frac.width > 0.99 && frac.height > 0.99) frac = null;
                }
                Navigator.pop(context, ImageEdit(rotate: _rotate, flip: _flip, crop: frac));
              },
              child: const Text('Done', style: TextStyle(color: Color(0xFF5EEAD4), fontWeight: FontWeight.w800)),
            ),
          ],
        ),
        body: Column(children: [
          Expanded(
            child: _shown == null
                ? const Center(child: CircularProgressIndicator())
                : Crop(
                    key: ValueKey('$_gen-$_aspect'),
                    image: _shown!,
                    controller: _ctl,
                    aspectRatio: _aspect,
                    baseColor: const Color(0xFF0F172A),
                    maskColor: Colors.black.withValues(alpha: .55),
                    onCropped: (_) {},
                    onMoved: (viewport, image) => _crop = image,
                  ),
          ),
          Container(
            color: const Color(0xFF1E293B),
            padding: const EdgeInsets.fromLTRB(8, 8, 8, 20),
            child: SafeArea(
              top: false,
              child: Column(children: [
                SingleChildScrollView(
                  scrollDirection: Axis.horizontal,
                  child: Row(children: [
                    for (final a in <(String, double?)>[('Free', null), ('Square', 1), ('Passport 35×45', 35 / 45), ('ID card', 85.6 / 54), ('4×6', 4 / 6), ('A4', 210 / 297)])
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 4),
                        child: ChoiceChip(label: Text(a.$1), selected: _aspect == a.$2, onSelected: (_) => setState(() => _aspect = a.$2)),
                      ),
                  ]),
                ),
                const SizedBox(height: 8),
                Row(mainAxisAlignment: MainAxisAlignment.spaceEvenly, children: [
                  _tool(Icons.rotate_left, 'Left', () {
                    _rotate = (_rotate - 90) % 360;
                    _render();
                  }),
                  _tool(Icons.rotate_right, 'Right', () {
                    _rotate = (_rotate + 90) % 360;
                    _render();
                  }),
                  _tool(Icons.flip, 'Flip', () {
                    _flip = !_flip;
                    _render();
                  }),
                  _tool(Icons.restart_alt, 'Reset', () {
                    _rotate = 0;
                    _flip = false;
                    _aspect = widget.aspectRatio;
                    _render();
                  }),
                ]),
              ]),
            ),
          ),
        ]),
      );

  Widget _tool(IconData i, String l, VoidCallback onTap) => InkWell(
        onTap: onTap,
        child: Padding(padding: const EdgeInsets.all(8), child: Column(children: [Icon(i, color: Colors.white), Text(l, style: const TextStyle(color: Colors.white70, fontSize: 12))])),
      );
}
