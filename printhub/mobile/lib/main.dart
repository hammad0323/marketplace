import 'package:material_ui/material_ui.dart';

import 'config.dart';
import 'router.dart';
import 'services/notifier.dart';
import 'state/session.dart';
import 'theme.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  final session = Session();
  await session.init();
  runApp(PrintHubApp(session: session));
}

class PrintHubApp extends StatefulWidget {
  const PrintHubApp({super.key, required this.session});
  final Session session;

  @override
  State<PrintHubApp> createState() => _PrintHubAppState();
}

class _PrintHubAppState extends State<PrintHubApp> {
  late final router = buildRouter(widget.session);
  late final notifier = Notifier(widget.session);

  @override
  void initState() {
    super.initState();
    notifier.start();
  }

  @override
  void dispose() {
    notifier.stop();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => SessionScope(
        session: widget.session,
        child: NotifierScope(
          notifier: notifier,
          child: MaterialApp.router(
            title: AppConfig.appName,
            debugShowCheckedModeBanner: false,
            theme: buildTheme(),
            routerConfig: router,
          ),
        ),
      );
}

class NotifierScope extends InheritedWidget {
  const NotifierScope({super.key, required this.notifier, required super.child});
  final Notifier notifier;
  static Notifier of(BuildContext context) => context.getInheritedWidgetOfExactType<NotifierScope>()!.notifier;
  @override
  bool updateShouldNotify(NotifierScope oldWidget) => false;
}
