import 'package:flutter_test/flutter_test.dart';
import 'package:signage_app/features/widgets_library/widget_animation.dart';
import 'package:signage_app/models/layout.dart';

void main() {
  test('every entrance ends exactly at the final position', () {
    for (final e in entranceEffects.keys) {
      expect(entranceFrame(e, 1).identity, isTrue, reason: e);
    }
  });

  test('entrances start hidden or off-position', () {
    for (final e in entranceEffects.keys.where((k) => k != 'none')) {
      expect(entranceFrame(e, 0).identity, isFalse, reason: e);
    }
  });

  test('loop effects return to rest at the cycle boundary (except continuous ones)', () {
    for (final l in loopEffects.keys.where((k) => !['spin', 'color_cycle', 'shine'].contains(k))) {
      final f = loopFrame(l, 0, 1);
      expect(f.tx.abs() + f.ty.abs() + (f.scale - 1).abs() + f.rotate.abs() + (1 - f.opacity).abs(), lessThan(0.02), reason: l);
    }
  });

  test('loop values stay in sane ranges at max intensity', () {
    for (final l in loopEffects.keys) {
      for (var i = 0; i <= 100; i++) {
        final f = loopFrame(l, i / 100, 3);
        expect(f.opacity, inInclusiveRange(0.0, 1.0), reason: l);
        expect(f.scale, inInclusiveRange(0.5, 1.6), reason: l);
        expect(f.tx.abs(), lessThan(0.5), reason: l);
        expect(f.ty.abs(), lessThan(0.5), reason: l);
      }
    }
  });

  test('replay timeline: hidden → enter → hold → exit', () {
    final p = replayPeriodMs(10, 500, 1000);
    expect(p, 10000);
    expect(entranceProgressAt(200, delay: 500, dur: 1000, period: p), 0);
    expect(entranceProgressAt(1000, delay: 500, dur: 1000, period: p), closeTo(0.5, 1e-9));
    expect(entranceProgressAt(5000, delay: 500, dur: 1000, period: p), 1);
    expect(entranceProgressAt(9500, delay: 500, dur: 1000, period: p), closeTo(0.5, 1e-9));
    expect(entranceProgressAt(9999, delay: 500, dur: 1000, period: 0), 1); // no replay → stays
    expect(replayPeriodMs(1, 3000, 1000), 5500); // too short → stretched to fit
  });

  test('typewriter reveals in whole characters', () {
    expect(entranceFrame('typewriter', 0.5, chars: 10).clipX, 0.5);
    expect(entranceFrame('typewriter', 0.55, chars: 10).clipX, 0.5);
  });

  test('animate whole screen staggers top to bottom and can be removed', () {
    final ws = [
      WidgetItem(widgetType: 'image', positionX: 0, positionY: 0, width: 1, height: 1),
      WidgetItem(widgetType: 'heading', positionY: 0.1),
      WidgetItem(widgetType: 'shape', positionY: 0.6, configuration: {'shape': 'circle'}),
      WidgetItem(widgetType: 'text', positionY: 0.4),
    ];
    applyScreenAnimation(ws, 'smooth', 12);
    expect(ws[0].configuration['loop_animation'], 'ken_burns');
    expect(ws[1].configuration['animation'], 'slide_up');
    expect(ws[2].configuration['loop_animation'], 'pulse');
    int d(WidgetItem w) => w.configuration['animation_delay_ms'] as int;
    expect(d(ws[1]) < d(ws[3]) && d(ws[3]) < d(ws[2]), isTrue);
    expect(ws.every((w) => w.configuration['animation_replay_seconds'] == 12), isTrue);
    applyScreenAnimation(ws, 'none', 0);
    expect(ws.every((w) => !w.configuration.containsKey('animation')), isTrue);
  });
}
