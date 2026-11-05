import '../core/utils/json.dart';

class MediaItem {
  MediaItem({
    required this.mediaId, required this.originalName, required this.fileType, required this.mimeType,
    this.fileSize = 0, this.width, this.height, this.durationMs, this.url, this.thumbnailUrl, this.folderId,
    this.isGlobal = false, this.usageCount = 0, this.checksum, this.createdAt,
  });
  final int mediaId;
  final String originalName, fileType, mimeType;
  final int fileSize;
  final int? width, height, durationMs, folderId;
  final String? url, thumbnailUrl, checksum;
  final bool isGlobal;
  final int usageCount;
  final DateTime? createdAt;

  bool get isImage => fileType == 'image' || fileType == 'logo';
  bool get isVideo => fileType == 'video';

  factory MediaItem.fromJson(Map<String, dynamic> j) => MediaItem(
        mediaId: asInt(j['media_id']), originalName: asString(j['original_name']), fileType: asString(j['file_type']),
        mimeType: asString(j['mime_type']), fileSize: asInt(j['file_size']), width: asIntOrNull(j['width']),
        height: asIntOrNull(j['height']), durationMs: asIntOrNull(j['duration_ms']), url: j['url']?.toString(),
        thumbnailUrl: j['thumbnail_url']?.toString(), folderId: asIntOrNull(j['folder_id']),
        isGlobal: asBool(j['is_global']), usageCount: asInt(j['usage_count']), checksum: j['checksum_sha256']?.toString(),
        createdAt: asDate(j['created_at']),
      );
}

class MediaFolder {
  MediaFolder({required this.folderId, required this.folderName, this.parentFolderId, this.itemCount = 0});
  final int folderId;
  final String folderName;
  final int? parentFolderId;
  final int itemCount;
  factory MediaFolder.fromJson(Map<String, dynamic> j) => MediaFolder(
        folderId: asInt(j['folder_id']), folderName: asString(j['folder_name']),
        parentFolderId: asIntOrNull(j['parent_folder_id']), itemCount: asInt(j['item_count']),
      );
}
