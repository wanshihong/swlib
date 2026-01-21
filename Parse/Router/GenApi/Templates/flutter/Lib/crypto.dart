import 'dart:convert';
import 'package:crypto/crypto.dart';

/// 加密工具类
/// 提供 SHA-256 和 HMAC-SHA256 签名功能
class Crypto {
  /// 计算字符串的 SHA-256 哈希值
  /// [message] 要哈希的消息
  /// 返回十六进制格式的哈希值
  static String sha256Hash(String message) {
    final bytes = utf8.encode(message);
    final digest = sha256.convert(bytes);
    return digest.toString();
  }

  /// 计算 HMAC-SHA256
  /// [message] 要签名的消息
  /// [key] 密钥
  /// 返回十六进制格式的 HMAC 值
  static String hmacSha256(String message, String key) {
    final keyBytes = utf8.encode(key);
    final messageBytes = utf8.encode(message);
    final hmac = Hmac(sha256, keyBytes);
    final digest = hmac.convert(messageBytes);
    return digest.toString();
  }

  /// 生成 API 签名
  /// [url] API 路径
  /// [random] 随机数
  /// [timestamp] 时间戳
  /// [appSecret] 应用密钥
  /// 返回签名字符串
  static String sign(String url, int random, int timestamp, String appSecret) {
    final message = '$url.$random.$timestamp';
    return hmacSha256(message, appSecret);
  }
}
