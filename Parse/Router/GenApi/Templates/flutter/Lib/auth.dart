import 'cache_mgr.dart';

/// 短 Token 缓存键
const String _shortTokenKey = 'auth:shortToken';

/// 长 Token 缓存键
const String _longTokenKey = 'auth:longToken';

/// 用户信息缓存键
const String _userInfoKey = 'auth:userInfo';

/// 用户信息类
class UserInfo {
  final int? id;
  final String? username;
  final String? nickname;
  final String? avatar;
  final Map<String, dynamic>? extra;

  UserInfo({
    this.id,
    this.username,
    this.nickname,
    this.avatar,
    this.extra,
  });

  factory UserInfo.fromJson(Map<String, dynamic> json) {
    return UserInfo(
      id: json['id'] as int?,
      username: json['username'] as String?,
      nickname: json['nickname'] as String?,
      avatar: json['avatar'] as String?,
      extra: json,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'username': username,
      'nickname': nickname,
      'avatar': avatar,
      ...?extra,
    };
  }
}

/// Token 管理类
class Auth {
  /// Token 刷新回调
  static Future<String> Function()? _refreshCallback;

  /// 登出回调
  static void Function()? _logoutCallback;

  /// 内存中缓存的短 Token（避免频繁读取存储）
  static String? _cachedShortToken;

  /// 设置 Token 刷新回调
  static void setRefreshCallback(Future<String> Function() callback) {
    _refreshCallback = callback;
  }

  /// 设置登出回调
  static void setLogoutCallback(void Function() callback) {
    _logoutCallback = callback;
  }

  /// 获取短 Token
  static String? getShortToken() {
    return _cachedShortToken;
  }

  /// 异步获取短 Token（从存储中读取）
  static Future<String?> getShortTokenAsync() async {
    if (_cachedShortToken != null) {
      return _cachedShortToken;
    }
    _cachedShortToken = await CacheMgr.get<String>(_shortTokenKey);
    return _cachedShortToken;
  }

  /// 设置短 Token
  /// [token] 短 Token
  /// [expired] 过期时间（秒），默认 1 小时
  static Future<void> setShortToken(String token, {int expired = 3600}) async {
    _cachedShortToken = token;
    await CacheMgr.set(_shortTokenKey, token, expired: expired);
  }

  /// 获取长 Token
  static Future<String?> getLongToken() async {
    return await CacheMgr.get<String>(_longTokenKey);
  }

  /// 设置长 Token
  /// [token] 长 Token
  /// [expired] 过期时间（秒），默认 30 天
  static Future<void> setLongToken(String token, {int expired = 86400 * 30}) async {
    await CacheMgr.set(_longTokenKey, token, expired: expired);
  }

  /// 获取用户信息
  static Future<UserInfo?> getUserInfo() async {
    final data = await CacheMgr.get<Map<String, dynamic>>(_userInfoKey);
    if (data == null) return null;
    return UserInfo.fromJson(data);
  }

  /// 设置用户信息
  /// [userInfo] 用户信息
  /// [expired] 过期时间（秒），默认 30 天
  static Future<void> setUserInfo(UserInfo userInfo, {int expired = 86400 * 30}) async {
    await CacheMgr.set(_userInfoKey, userInfo.toJson(), expired: expired);
  }

  /// 检查是否已登录
  static Future<bool> isLoggedIn() async {
    final shortToken = await getShortTokenAsync();
    if (shortToken != null && shortToken.isNotEmpty) {
      return true;
    }
    final longToken = await getLongToken();
    return longToken != null && longToken.isNotEmpty;
  }

  /// 刷新 Token
  static Future<String> refreshToken() async {
    if (_refreshCallback == null) {
      throw Exception('未设置 Token 刷新回调');
    }

    try {
      final newToken = await _refreshCallback!();
      return newToken;
    } catch (e) {
      print('刷新 Token 失败: $e');
      // 刷新失败，执行登出
      await logout();
      rethrow;
    }
  }

  /// 登出
  static Future<void> logout() async {
    // 清除内存缓存
    _cachedShortToken = null;

    // 清除所有认证信息
    await CacheMgr.del(_shortTokenKey);
    await CacheMgr.del(_longTokenKey);
    await CacheMgr.del(_userInfoKey);

    // 执行登出回调
    _logoutCallback?.call();
  }

  /// 登录
  /// [shortToken] 短 Token
  /// [longToken] 长 Token
  /// [userInfo] 用户信息
  static Future<void> login(
    String shortToken,
    String longToken, {
    UserInfo? userInfo,
  }) async {
    await setShortToken(shortToken);
    await setLongToken(longToken);
    if (userInfo != null) {
      await setUserInfo(userInfo);
    }
  }

  /// 初始化（从存储中加载 Token 到内存）
  static Future<void> init() async {
    _cachedShortToken = await CacheMgr.get<String>(_shortTokenKey);
  }
}
