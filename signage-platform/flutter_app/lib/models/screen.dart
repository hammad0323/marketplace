import '../core/utils/json.dart';
import 'layout.dart';
import 'media.dart';

class ScreenModel {
  ScreenModel({
    required this.screenId, required this.screenUid, required this.screenName, required this.orientation,
    required this.width, required this.height, required this.status, required this.layoutVersion,
    this.folderId, this.templateId, this.defaultDuration = 10, this.publishedVersion, this.hasUnpublishedChanges = true,
    this.isFavorite = false, this.updatedAt, this.publishedAt, this.layout, this.media = const {}, this.widgetCount, this.ownerName,
  });
  final int screenId;
  final String screenUid, screenName, orientation, status;
  final int width, height, layoutVersion, defaultDuration;
  final int? folderId, templateId, publishedVersion, widgetCount;
  final bool hasUnpublishedChanges, isFavorite;
  final DateTime? updatedAt, publishedAt;
  final LayoutDoc? layout;
  final Map<int, MediaItem> media;
  final String? ownerName;

  factory ScreenModel.fromJson(Map<String, dynamic> j) {
    final media = <int, MediaItem>{};
    asMap(j['media']).forEach((k, v) => media[asInt(k)] = MediaItem.fromJson(asMap(v)));
    return ScreenModel(
      screenId: asInt(j['screen_id']), screenUid: asString(j['screen_uid']), screenName: asString(j['screen_name']),
      orientation: asString(j['orientation'], 'landscape'), width: asInt(j['width'], 1920), height: asInt(j['height'], 1080),
      status: asString(j['status'], 'draft'), layoutVersion: asInt(j['layout_version'], 1), folderId: asIntOrNull(j['folder_id']),
      templateId: asIntOrNull(j['template_id']), defaultDuration: asInt(j['default_duration'], 10),
      publishedVersion: asIntOrNull(j['published_version']), hasUnpublishedChanges: asBool(j['has_unpublished_changes'], true),
      isFavorite: asBool(j['is_favorite']), updatedAt: asDate(j['updated_at']), publishedAt: asDate(j['published_at']),
      layout: j['layout'] is Map ? LayoutDoc.fromJson(asMap(j['layout'])) : null, media: media,
      widgetCount: asIntOrNull(j['widget_count']), ownerName: j['owner_name']?.toString(),
    );
  }
}

class ScreenFolder {
  ScreenFolder({required this.folderId, required this.folderName, this.color, this.screenCount = 0});
  final int folderId;
  final String folderName;
  final String? color;
  final int screenCount;
  factory ScreenFolder.fromJson(Map<String, dynamic> j) => ScreenFolder(
      folderId: asInt(j['folder_id']), folderName: asString(j['folder_name']), color: j['color']?.toString(), screenCount: asInt(j['screen_count']));
}
