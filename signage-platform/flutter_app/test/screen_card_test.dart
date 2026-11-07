import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:signage_app/features/screens/screen_card.dart';
import 'package:signage_app/models/screen.dart';

void main() {
  setUpAll(() => GoogleFonts.config.allowRuntimeFetching = false);

  ScreenModel screen(String name, String orientation, String status) => ScreenModel.fromJson({
        'screen_id': 1, 'screen_uid': 'u', 'screen_name': name, 'orientation': orientation,
        'width': orientation == 'portrait' ? 1080 : 1920, 'height': orientation == 'portrait' ? 1920 : 1080,
        'status': status, 'layout_version': 1, 'updated_at': '2026-11-07 09:00:00', 'has_unpublished_changes': false,
        'layout': {'canvas': {'orientation': orientation, 'width': 1080, 'height': 1920}, 'background': {'type': 'color', 'value': '#17191C'}, 'widgets': []},
      });

  // Same formula as ScreensListScreen._cardHeight (text scale 1.0).
  double cardHeight(double cardWidth) => (cardWidth - 16) * 10 / 16 + 16 + 76 + 52;

  // Card widths of a 2-column grid on: small Android (320 dp), iPhone 15 (393), Pixel (412), 3 columns on tablets.
  for (final w in [144.0, 173.0, 184.0, 230.0]) {
    for (final s in [screen('Daily Specials', 'portrait', 'draft'), screen('A very long screen name that keeps going', 'landscape', 'published')]) {
      testWidgets('ScreenCard fits in ${w}px (${s.screenName})', (tester) async {
        await tester.pumpWidget(ProviderScope(
          child: MaterialApp(
            home: Scaffold(body: Center(child: SizedBox(width: w, height: cardHeight(w), child: ScreenCard(screen: s)))),
          ),
        ));
        await tester.pump();
        expect(tester.takeException(), isNull, reason: 'RenderFlex overflow at width $w');
      });
    }
  }

  testWidgets('ScreenCard fits at 130% system font size (173px card)', (tester) async {
    const w = 173.0;
    final h = (w - 16) * 10 / 16 + 16 + 76 * 1.3 + 52;
    await tester.pumpWidget(ProviderScope(
      child: MaterialApp(
        home: MediaQuery(
          data: const MediaQueryData(textScaler: TextScaler.linear(1.3)),
          child: Scaffold(body: Center(child: SizedBox(width: w, height: h, child: ScreenCard(screen: screen('Daily Specials', 'portrait', 'published'))))),
        ),
      ),
    ));
    await tester.pump();
    expect(tester.takeException(), isNull);
  });
}
