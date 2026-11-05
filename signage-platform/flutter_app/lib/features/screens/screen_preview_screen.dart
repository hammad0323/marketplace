import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/widgets/common.dart';
import '../../models/screen.dart';
import '../../providers/providers.dart';
import '../widgets_library/layout_view.dart';
import '../widgets_library/render_context.dart';

final _screenDetailsProvider = FutureProvider.autoDispose.family<ScreenModel, int>((ref, id) => ref.watch(screensRepoProvider).details(id));

/// Full-screen live preview of a screen (exactly what a display will show).
class ScreenPreviewScreen extends ConsumerStatefulWidget {
  const ScreenPreviewScreen({super.key, required this.screenId});
  final int screenId;
  @override
  ConsumerState<ScreenPreviewScreen> createState() => _ScreenPreviewScreenState();
}

class _ScreenPreviewScreenState extends ConsumerState<ScreenPreviewScreen> {
  @override
  void initState() {
    super.initState();
    SystemChrome.setEnabledSystemUIMode(SystemUiMode.immersiveSticky);
  }

  @override
  void dispose() {
    SystemChrome.setEnabledSystemUIMode(SystemUiMode.edgeToEdge);
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final s = ref.watch(_screenDetailsProvider(widget.screenId));
    return Scaffold(
      backgroundColor: Colors.black,
      body: Stack(fit: StackFit.expand, children: [
        Positioned.fill(
          child: s.when(
            loading: () => const LoadingView(),
            error: (e, _) => ErrorView(error: e),
            data: (screen) => LayoutView(layout: screen.layout!, media: NetworkMediaSource(screen.media), mode: RenderMode.preview),
          ),
        ),
        Positioned(top: 0, left: 0, right: 0, child: SafeArea(
          child: Padding(
            padding: const EdgeInsets.all(8),
            child: IconButton.filled(
              style: IconButton.styleFrom(backgroundColor: Colors.black54),
              icon: const Icon(Icons.close, color: Colors.white),
              onPressed: () => Navigator.of(context).pop(),
            ),
          ),
        )),
      ]),
    );
  }
}
