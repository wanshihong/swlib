/// 应用配置
/// 此文件由代码生成器自动生成，请勿手动修改

class Config {
  Config._();

  /// API 基础 URL
  static const String apiUrl = '{$API_URL}';

  /// WebSocket 主机地址
  static const String wsHost = '{$WS_HOST}';

  /// 应用 ID
  static const String appId = '{$APP_ID}';

  /// 应用密钥（用于签名）
  static const String appSecret = '{$APP_SECRET}';

  /// 请求超时时间（毫秒）
  static const int timeout = {$TIMEOUT};

  /// 默认语言
  static const String defaultLanguage = '{$DEFAULT_LANGUAGE}';
}
