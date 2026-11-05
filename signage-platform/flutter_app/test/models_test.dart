import 'package:flutter_test/flutter_test.dart';
import 'package:signage_app/core/utils/formatters.dart';
import 'package:signage_app/features/signage_player/playback_view.dart';
import 'package:signage_app/features/widgets_library/widget_renderers.dart';
import 'package:signage_app/models/layout.dart';

void main() {
  test('layout document round-trips with API field names', () {
    final json = {
      'schema_version': 1,
      'canvas': {'orientation': 'portrait', 'width': 1080, 'height': 1920},
      'background': {'type': 'gradient', 'value': null, 'media_id': null, 'config': {'gradient': {'colors': ['#008C86', '#17191C']}}},
      'widgets': [
        {'widget_uid': 'abc-123-def', 'widget_type': 'heading', 'position_x': '0.100000', 'position_y': 0.2, 'width': 0.8, 'height': 0.1, 'z_index': 3, 'is_locked': 1, 'configuration': {'text': 'Hi'}},
      ],
    };
    final l = LayoutDoc.fromJson(json);
    expect(l.width, 1080);
    expect(l.widgets.single.positionX, closeTo(0.1, 1e-9)); // DECIMAL string parsed
    expect(l.widgets.single.isLocked, isTrue);
    final back = l.toJson();
    expect(back['canvas'], {'orientation': 'portrait', 'width': 1080, 'height': 1920});
    expect((back['widgets'] as List).first['widget_uid'], 'abc-123-def');
    expect(((back['widgets'] as List).first['configuration'] as Map)['text'], 'Hi');
  });

  test('copy is deep (editing a copy does not change the original)', () {
    final l = LayoutDoc(widgets: [WidgetItem(widgetType: 'text', configuration: {'items': [{'a': 1}]})]);
    final c = l.copy();
    ((c.widgets.first.configuration['items'] as List).first as Map)['a'] = 2;
    expect(((l.widgets.first.configuration['items'] as List).first as Map)['a'], 1);
  });

  test('hex colours use CSS alpha order', () {
    expect(parseHexColor('#008C86')!.toARGB32(), 0xFF008C86);
    expect(parseHexColor('#202124CC')!.toARGB32(), 0xCC202124);
    expect(colorToHex(parseHexColor('#008C86')!), '#008C86');
  });

  test('RSS and Atom parsing', () {
    const rss = '<rss><channel><title>Daily News</title><item><title><![CDATA[Hello &amp; welcome]]></title><description>Body text</description>'
        '<media:content url="https://x/img.jpg"/></item><item><title>Second</title></item></channel></rss>';
    final items = parseFeed(rss);
    expect(items.length, 2);
    expect(items.first['title'], 'Hello & welcome');
    expect(items.first['image'], 'https://x/img.jpg');
    expect(items.first['source'], 'Daily News');
    expect(parseFeed('<feed><title>A</title><entry><title>T</title><summary>S</summary></entry></feed>').first['description'], 'S');
  });

  test('json path for live data', () {
    expect(jsonPath({'a': {'b': [1, {'c': 42}]}}, r'$.a.b[1].c'), 42);
    expect(jsonPath({'value': 7}, r'$.value'), 7);
    expect(jsonPath({'x': 1}, r'$.missing.y'), isNull);
  });

  test('playlist publication → play items (durations, transitions, validity)', () {
    final doc = {
      'transition': {'type': 'slide_left', 'duration_ms': 600},
      'items': [
        {'item_id': 1, 'screen_id': 10, 'screen_version': 2, 'duration': 15, 'transition_type': 'inherit',
          'screen': {'canvas': {'orientation': 'landscape', 'width': 1920, 'height': 1080}, 'background': {}, 'widgets': [], 'default_duration': 8}},
        {'item_id': 2, 'screen_id': 11, 'screen_version': 1, 'duration': null, 'transition_type': 'zoom', 'valid_until': '2000-01-01 00:00:00',
          'screen': {'canvas': {}, 'background': {}, 'widgets': [], 'default_duration': 8}},
      ],
    };
    final items = PlayItem.fromPublication(doc);
    expect(items[0].duration, 15);
    expect(items[0].transition, 'slide_left');
    expect(items[1].duration, 8);
    expect(items[1].transition, 'zoom');
    expect(items[1].playableAt(DateTime.now()), isFalse); // expired item skipped
  });
}
