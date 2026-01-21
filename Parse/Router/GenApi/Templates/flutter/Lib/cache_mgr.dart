import 'dart:convert';
import 'package:shared_preferences/shared_preferences.dart';

/// 缓存数据结构
class _CacheData<T> {
  final T data;
  final int expired; // 过期时间戳（秒）

  _CacheData({required this.data, required this.expired});

  Map<String, dynamic> toJson() => {
        'data': data,
        'expired': expired,
      };

  factory _CacheData.fromJson(Map<String, dynamic> json, T Function(dynamic) fromData) {
    return _CacheData(
      data: fromData(json['data']),
      expired: json['expired'] as int,
    );
  }

  bool get isValid => expired > DateTime.now().millisecondsSinceEpoch ~/ 1000;
}

/// 缓存管理器
/// 提供本地存储的缓存管理功能，支持过期时间和哈希表操作
class CacheMgr {
  /// SharedPreferences 实例
  static SharedPreferences? _prefs;

  /// 默认过期时间（一年，单位：秒）
  static const int _defaultExpireTime = 86400 * 365;

  /// 初始化
  static Future<void> init() async {
    _prefs ??= await SharedPreferences.getInstance();
  }

  /// 确保已初始化
  static Future<SharedPreferences> _ensureInit() async {
    if (_prefs == null) {
      await init();
    }
    return _prefs!;
  }

  /// 获取当前时间戳（秒）
  static int _getCurrentTimestamp() {
    return DateTime.now().millisecondsSinceEpoch ~/ 1000;
  }

  /// 计算过期时间
  static int _getExpireTime([int? expired]) {
    return _getCurrentTimestamp() + (expired ?? _defaultExpireTime);
  }

  /// 设置缓存数据
  /// [key] 缓存键名
  /// [value] 要存储的值
  /// [expired] 过期时间（秒），默认为一年
  static Future<void> set<T>(String key, T value, {int? expired}) async {
    final prefs = await _ensureInit();
    final cacheData = _CacheData(
      data: value,
      expired: _getExpireTime(expired),
    );

    try {
      final jsonStr = jsonEncode(cacheData.toJson());
      await prefs.setString(key, jsonStr);
    } catch (e) {
      print('CacheMgr.set error: $e');
    }
  }

  /// 获取缓存数据
  /// [key] 缓存键名
  /// [isDel] 获取后是否删除该缓存，默认为 false
  /// 返回缓存的数据，如果不存在或已过期则返回 null
  static Future<T?> get<T>(String key, {bool isDel = false}) async {
    final prefs = await _ensureInit();

    try {
      final jsonStr = prefs.getString(key);
      if (jsonStr == null) return null;

      final json = jsonDecode(jsonStr) as Map<String, dynamic>;
      final cacheData = _CacheData<T>.fromJson(json, (data) => data as T);

      if (cacheData.isValid) {
        if (isDel) {
          await del(key);
        }
        return cacheData.data;
      } else {
        // 数据已过期，自动删除
        await del(key);
      }
    } catch (e) {
      print('CacheMgr.get error: $e');
      // 数据格式错误，删除无效数据
      await del(key);
    }
    return null;
  }

  /// 删除指定的缓存数据
  /// [key] 要删除的缓存键名
  static Future<void> del(String key) async {
    final prefs = await _ensureInit();
    try {
      await prefs.remove(key);
    } catch (e) {
      print('CacheMgr.del error: $e');
    }
  }

  /// 清除所有缓存数据
  static Future<void> clear() async {
    final prefs = await _ensureInit();
    try {
      await prefs.clear();
    } catch (e) {
      print('CacheMgr.clear error: $e');
    }
  }

  /// 设置哈希表中的字段值
  /// [key] 哈希表的键名
  /// [field] 要设置的字段名
  /// [value] 要设置的值
  /// [expired] 可选的过期时间（秒），默认为一年
  static Future<void> hSet<T>(String key, String field, T value, {int? expired}) async {
    final existingData = await get<Map<String, dynamic>>(key) ?? {};
    existingData[field] = value;
    await set(key, existingData, expired: expired);
  }

  /// 获取哈希表中的字段值
  /// [key] 哈希表的键名
  /// [field] 要获取的字段名，如果为 null 则返回整个哈希表
  /// [isDel] 获取后是否删除该缓存，默认为 false
  static Future<T?> hGet<T>(String key, {String? field, bool isDel = false}) async {
    final data = await get<Map<String, dynamic>>(key, isDel: isDel);
    if (data == null) return null;

    if (field == null) {
      return data as T?;
    } else {
      return data[field] as T?;
    }
  }

  /// 删除哈希表中的字段
  /// [key] 哈希表的键名
  /// [field] 要删除的字段名
  static Future<bool> hDel(String key, String field) async {
    try {
      final data = await get<Map<String, dynamic>>(key);
      if (data == null) return false;

      if (!data.containsKey(field)) return false;

      data.remove(field);
      await set(key, data);
      return true;
    } catch (e) {
      print('CacheMgr.hDel error: $e');
      return false;
    }
  }

  /// 检查键是否存在
  static Future<bool> exists(String key) async {
    final prefs = await _ensureInit();
    return prefs.containsKey(key);
  }

  /// 获取所有键
  static Future<Set<String>> keys() async {
    final prefs = await _ensureInit();
    return prefs.getKeys();
  }
}
