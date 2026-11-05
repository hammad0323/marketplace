import '../core/utils/json.dart';
import 'layout.dart';

class TemplateCategory {
  TemplateCategory({required this.categoryId, required this.categoryKey, required this.categoryName, this.icon, this.templateCount = 0});
  final int categoryId;
  final String categoryKey, categoryName;
  final String? icon;
  final int templateCount;
  factory TemplateCategory.fromJson(Map<String, dynamic> j) => TemplateCategory(
        categoryId: asInt(j['category_id']), categoryKey: asString(j['category_key']), categoryName: asString(j['category_name']),
        icon: j['icon']?.toString(), templateCount: asInt(j['template_count']),
      );
}

class ScreenTemplate {
  ScreenTemplate({
    required this.templateId, required this.templateName, required this.categoryId, required this.orientation,
    required this.layout, this.description, this.categoryName, this.isPremium = false, this.isLocked = false,
    this.isFavorite = false, this.tags, this.templateKey,
  });
  final int templateId, categoryId;
  final String templateName, orientation;
  final String? description, categoryName, tags, templateKey;
  final LayoutDoc layout;
  final bool isPremium, isLocked, isFavorite;

  factory ScreenTemplate.fromJson(Map<String, dynamic> j) => ScreenTemplate(
        templateId: asInt(j['template_id']), templateName: asString(j['template_name']), categoryId: asInt(j['category_id']),
        orientation: asString(j['orientation'], 'landscape'), layout: LayoutDoc.fromJson(asMap(j['template_json'])),
        description: j['description']?.toString(), categoryName: j['category_name']?.toString(),
        isPremium: asBool(j['is_premium']), isLocked: asBool(j['is_locked']), isFavorite: asBool(j['is_favorite']),
        tags: j['tags']?.toString(), templateKey: j['template_key']?.toString(),
      );
}

class WidgetTypeInfo {
  WidgetTypeInfo({
    required this.widgetKey, required this.widgetName, required this.category, required this.defaultConfig,
    this.description, this.icon, this.defaultWidth = 0.4, this.defaultHeight = 0.3, this.availability = 'stable',
    this.isPremium = false, this.isAllowed = true, this.requiresNetwork = false, this.status = 'active', this.usageCount = 0,
  });
  final String widgetKey, widgetName, category, availability, status;
  final String? description, icon;
  final Map<String, dynamic> defaultConfig;
  final double defaultWidth, defaultHeight;
  final bool isPremium, isAllowed, requiresNetwork;
  final int usageCount;

  factory WidgetTypeInfo.fromJson(Map<String, dynamic> j) => WidgetTypeInfo(
        widgetKey: asString(j['widget_key']), widgetName: asString(j['widget_name']), category: asString(j['category']),
        defaultConfig: asMap(j['default_config_json']), description: j['description']?.toString(), icon: j['icon']?.toString(),
        defaultWidth: asDouble(j['default_width'], 0.4), defaultHeight: asDouble(j['default_height'], 0.3),
        availability: asString(j['availability'], 'stable'), isPremium: asBool(j['is_premium']),
        isAllowed: asBool(j['is_allowed'], true), requiresNetwork: asBool(j['requires_network']),
        status: asString(j['status'], 'active'), usageCount: asInt(j['usage_count']),
      );
}
