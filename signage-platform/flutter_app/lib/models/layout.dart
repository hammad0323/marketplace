import 'package:uuid/uuid.dart';

import '../core/utils/json.dart';

const _uuid = Uuid();

/// One widget on a screen. Geometry is normalised (0..1 of the canvas).
class WidgetItem {
  WidgetItem({
    String? widgetUid, required this.widgetType, this.widgetName, this.positionX = 0.1, this.positionY = 0.1,
    this.width = 0.4, this.height = 0.3, this.rotation = 0, this.zIndex = 0, this.isLocked = false,
    this.isVisible = true, Map<String, dynamic>? configuration,
  })  : widgetUid = widgetUid ?? _uuid.v4(),
        configuration = configuration ?? {};

  String widgetUid;
  String widgetType;
  String? widgetName;
  double positionX, positionY, width, height, rotation;
  int zIndex;
  bool isLocked, isVisible;
  Map<String, dynamic> configuration;

  factory WidgetItem.fromJson(Map<String, dynamic> j) => WidgetItem(
        widgetUid: j['widget_uid']?.toString(),
        widgetType: asString(j['widget_type']),
        widgetName: j['widget_name']?.toString(),
        positionX: asDouble(j['position_x']), positionY: asDouble(j['position_y']),
        width: asDouble(j['width'], 0.4), height: asDouble(j['height'], 0.3),
        rotation: asDouble(j['rotation']), zIndex: asInt(j['z_index']),
        isLocked: asBool(j['is_locked']), isVisible: asBool(j['is_visible'], true),
        configuration: asMap(deepCopy(j['configuration'] ?? j['configuration_json'])),
      );

  Map<String, dynamic> toJson() => {
        'widget_uid': widgetUid, 'widget_type': widgetType, 'widget_name': widgetName,
        'position_x': positionX, 'position_y': positionY, 'width': width, 'height': height,
        'rotation': rotation, 'z_index': zIndex, 'is_locked': isLocked, 'is_visible': isVisible,
        'configuration': deepCopy(configuration),
      };

  WidgetItem copy({bool newId = false}) {
    final w = WidgetItem.fromJson(toJson());
    if (newId) w.widgetUid = _uuid.v4();
    return w;
  }
}

class BackgroundSpec {
  BackgroundSpec({this.type = 'color', this.value = '#17191C', this.mediaId, Map<String, dynamic>? config}) : config = config ?? {};
  String type; // color | gradient | image | video | preset
  String? value;
  int? mediaId;
  Map<String, dynamic> config;

  factory BackgroundSpec.fromJson(Map<String, dynamic> j) => BackgroundSpec(
        type: asString(j['type'], 'color'), value: j['value']?.toString(), mediaId: asIntOrNull(j['media_id']),
        config: asMap(deepCopy(j['config'])),
      );

  Map<String, dynamic> toJson() => {'type': type, 'value': value, 'media_id': mediaId, 'config': deepCopy(config)};
}

/// Layout document — identical shape to the API / DB (docs/ARCHITECTURE.md §6).
class LayoutDoc {
  LayoutDoc({this.orientation = 'landscape', this.width = 1920, this.height = 1080, BackgroundSpec? background, List<WidgetItem>? widgets})
      : background = background ?? BackgroundSpec(),
        widgets = widgets ?? [];

  String orientation;
  int width, height;
  BackgroundSpec background;
  List<WidgetItem> widgets;

  double get aspect => width / height;

  List<WidgetItem> get sorted => [...widgets]..sort((a, b) => a.zIndex.compareTo(b.zIndex));

  factory LayoutDoc.fromJson(Map<String, dynamic> j) {
    final c = asMap(j['canvas']);
    return LayoutDoc(
      orientation: asString(c['orientation'], 'landscape'),
      width: asInt(c['width'], 1920),
      height: asInt(c['height'], 1080),
      background: BackgroundSpec.fromJson(asMap(j['background'])),
      widgets: asList(j['widgets']).map((w) => WidgetItem.fromJson(asMap(w))).toList(),
    );
  }

  Map<String, dynamic> toJson() => {
        'schema_version': 1,
        'canvas': {'orientation': orientation, 'width': width, 'height': height},
        'background': background.toJson(),
        'widgets': widgets.map((w) => w.toJson()).toList(),
      };

  LayoutDoc copy() => LayoutDoc.fromJson(toJson());
}
