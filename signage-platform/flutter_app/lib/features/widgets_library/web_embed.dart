import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:webview_flutter/webview_flutter.dart';

import 'render_context.dart';
import 'widget_renderers.dart' show EditorPlaceholder;

/// Website / custom HTML widget. Uses a WebView on Android & iOS players;
/// other platforms (and the editor) show a labelled placeholder.
class WebEmbedView extends StatefulWidget {
  const WebEmbedView({super.key, this.url, this.html, this.refreshMinutes = 0, this.allowScripts = false});
  final String? url;
  final String? html;
  final int refreshMinutes;
  final bool allowScripts;
  @override
  State<WebEmbedView> createState() => _WebEmbedViewState();
}

class _WebEmbedViewState extends State<WebEmbedView> {
  WebViewController? ctrl;
  Timer? refresh;

  bool get supported => !kIsWeb && (defaultTargetPlatform == TargetPlatform.android || defaultTargetPlatform == TargetPlatform.iOS);

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (ctrl != null || !supported || !RenderScope.of(context).live) return;
    final c = WebViewController()
      ..setJavaScriptMode(widget.html != null && !widget.allowScripts ? JavaScriptMode.disabled : JavaScriptMode.unrestricted)
      ..setBackgroundColor(const Color(0x00000000));
    if (widget.html != null) {
      c.loadHtmlString('<html><head><meta name="viewport" content="width=device-width, initial-scale=1"></head><body style="margin:0">${widget.html}</body></html>');
    } else if ((widget.url ?? '').startsWith('http')) {
      c.loadRequest(Uri.parse(widget.url!));
    }
    ctrl = c;
    if (widget.refreshMinutes > 0) {
      refresh = Timer.periodic(Duration(minutes: widget.refreshMinutes), (_) => ctrl?.reload());
    }
  }

  @override
  void dispose() {
    refresh?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final scope = RenderScope.of(context);
    if (ctrl != null) return IgnorePointer(child: WebViewWidget(controller: ctrl!));
    if (scope.mode == RenderMode.player) return const SizedBox.shrink();
    return EditorPlaceholder(
      icon: widget.html != null ? Icons.code : Icons.language,
      label: widget.html != null ? 'Custom HTML (shows on the display)' : (widget.url?.isNotEmpty ?? false ? Uri.tryParse(widget.url!)?.host ?? 'Website' : 'Enter a URL'),
    );
  }
}
