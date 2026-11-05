import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/utils/json.dart';
import '../../core/widgets/common.dart';
import '../../models/media.dart';
import '../../providers/providers.dart';
import '../signage_player/playback_view.dart';
import '../widgets_library/render_context.dart';

final _previewProvider = FutureProvider.autoDispose.family<Map<String, dynamic>, int>((ref, id) => ref.watch(playlistsRepoProvider).preview(id));

/// Plays the whole playlist (draft) exactly like a display would.
class PlaylistPreviewScreen extends ConsumerStatefulWidget {
  const PlaylistPreviewScreen({super.key, required this.playlistId});
  final int playlistId;
  @override
  ConsumerState<PlaylistPreviewScreen> createState() => _PlaylistPreviewScreenState();
}

class _PlaylistPreviewScreenState extends ConsumerState<PlaylistPreviewScreen> {
  final key = GlobalKey<PlaylistPlaybackViewState>();
  bool paused = false;
  String label = '';

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
    final p = ref.watch(_previewProvider(widget.playlistId));
    return Scaffold(
      backgroundColor: Colors.black,
      body: p.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(error: e),
        data: (doc) {
          final media = <int, MediaItem>{};
          for (final m in asList(doc['media']).map(asMap)) {
            media[asInt(m['media_id'])] = MediaItem.fromJson({...m, 'original_name': 'media', 'file_type': m['file_type'] ?? 'image', 'mime_type': m['mime_type'] ?? ''});
          }
          final count = asList(doc['items']).length;
          return Stack(fit: StackFit.expand, children: [
            Positioned.fill(
              child: PlaylistPlaybackView(
                key: key,
                doc: doc,
                media: NetworkMediaSource(media),
                mode: RenderMode.preview,
                paused: paused,
                onItemStart: (it) => WidgetsBinding.instance.addPostFrameCallback((_) { if (mounted) setState(() => label = 'Screen ${asList(doc['items']).indexWhere((x) => asInt(asMap(x)['item_id']) == it.itemId) + 1} of $count · ${it.duration}s'); }),
                idle: const Center(child: Text('Nothing to play — add enabled screens to this playlist', style: TextStyle(color: Colors.white70))),
              ),
            ),
            Positioned(top: 0, left: 0, right: 0, child: SafeArea(
              child: Padding(
                padding: const EdgeInsets.all(8),
                child: Row(children: [
                  IconButton.filled(style: IconButton.styleFrom(backgroundColor: Colors.black54), icon: const Icon(Icons.close, color: Colors.white), onPressed: () => Navigator.pop(context)),
                  const Spacer(),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                    decoration: BoxDecoration(color: Colors.black54, borderRadius: BorderRadius.circular(20)),
                    child: Text(label, style: const TextStyle(color: Colors.white)),
                  ),
                  const SizedBox(width: 8),
                  IconButton.filled(style: IconButton.styleFrom(backgroundColor: Colors.black54), icon: Icon(paused ? Icons.play_arrow : Icons.pause, color: Colors.white), onPressed: () => setState(() => paused = !paused)),
                  IconButton.filled(style: IconButton.styleFrom(backgroundColor: Colors.black54), icon: const Icon(Icons.skip_next, color: Colors.white), onPressed: () => key.currentState?.skip()),
                ]),
              ),
            )),
          ]);
        },
      ),
    );
  }
}
