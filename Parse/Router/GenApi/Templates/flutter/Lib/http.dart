import 'dart:async';
import 'dart:typed_data';
import 'package:dio/dio.dart';
import 'package:fixnum/fixnum.dart';

import '../config.dart';
import 'crypto.dart';
import 'auth.dart';
import 'cache_mgr.dart';
import 'network.dart';

// 导入 Protobuf 通用响应类型
// 注意：实际使用时需要根据项目调整导入路径
// import 'package:your_app/proto/generated/common.pb.dart';

/// 显示 API 错误弹窗
/// 需要在实际项目中实现具体的 UI 显示逻辑
void showApiError(String content, {String title = '请求错误'}) {
  // TODO: 实现错误弹窗显示
  // 可以使用 Get.snackbar, showDialog 等
  print('[$title] $content');
}

/// 生成缓存键
String getCacheKey(String router, [Map<String, dynamic>? params]) {
  String cacheKey = 'Api:$router';

  if (params == null || params.isEmpty) {
    return cacheKey;
  }

  final paramValues = params.values.map((value) {
    if (value is Map) {
      return value.values.join('');
    }
    return value.toString();
  }).join('');

  return paramValues.isNotEmpty ? '$cacheKey:$paramValues' : cacheKey;
}

/// HTTP 客户端类
class Http {
  /// 单例实例
  static final Http instance = Http._internal();

  /// Dio 实例
  late final Dio _dio;

  /// Token 刷新 Completer（防止并发刷新）
  static Completer<void>? _refreshTokenCompleter;

  Http._internal() {
    _dio = Dio(
      BaseOptions(
        connectTimeout: Duration(milliseconds: Config.timeout),
        receiveTimeout: Duration(milliseconds: Config.timeout),
        responseType: ResponseType.bytes,
      ),
    );
  }

  /// 调用 API
  /// [url] API 完整 URL
  /// [params] 请求参数（Protobuf 对象）
  /// [showError] 是否显示错误
  /// [errorMessage] 错误提示消息
  /// [toProto] 将参数转换为字节的函数
  /// [fromProto] 从字节解析响应的函数
  /// [cacheTime] 缓存时间（秒），0 表示不缓存
  static Future<T> callApi<T>({
    required String url,
    dynamic params,
    bool showError = true,
    required String errorMessage,
    Uint8List Function(dynamic)? toProto,
    required T Function(Uint8List) fromProto,
    int cacheTime = 0,
  }) async {
    final cacheKey = getCacheKey(url, params is Map<String, dynamic> ? params : null);

    // 检查缓存
    if (cacheTime > 0) {
      final cacheData = await CacheMgr.get<Uint8List>(cacheKey);
      if (cacheData != null) {
        try {
          return fromProto(cacheData);
        } catch (e) {
          // 缓存解析失败，继续请求
        }
      }
    }

    // 编码请求参数
    Uint8List? sendParams;
    if (params != null && toProto != null) {
      sendParams = toProto(params);
    }

    // 发送请求
    try {
      final response = await Http.instance._send(url, sendParams);

      // 设置缓存
      if (cacheTime > 0) {
        await CacheMgr.set(cacheKey, response, expired: cacheTime);
      }

      return fromProto(response);
    } catch (e) {
      if (showError) {
        showApiError(e.toString(), title: errorMessage);
      }
      rethrow;
    }
  }

  /// 使用新 Token 重试请求
  Future<Uint8List> _retryRequestWithNewToken(String url, Uint8List? data) async {
    // 如果已经有正在进行的刷新 Token 请求，直接等待它
    if (_refreshTokenCompleter != null) {
      await _refreshTokenCompleter!.future;
    } else {
      _refreshTokenCompleter = Completer<void>();
      try {
        await Auth.refreshToken();
        _refreshTokenCompleter!.complete();
      } catch (e) {
        _refreshTokenCompleter!.completeError(e);
        rethrow;
      } finally {
        _refreshTokenCompleter = null;
      }
    }

    // 使用新 Token 重试原始请求
    return await _send(url, data, allowRetry: false);
  }

  /// 发送 HTTP 请求
  /// [url] 请求 URL
  /// [data] 请求数据（Protobuf 编码后的数据）
  /// [allowRetry] 是否允许 Token 过期后重试
  Future<Uint8List> _send(
    String url,
    Uint8List? data, {
    bool allowRetry = true,
  }) async {
    // 等待网络就绪（处理首次打开权限问题）
    await Network.waitForReady();

    final nowTime = DateTime.now().millisecondsSinceEpoch ~/ 1000;
    final random = DateTime.now().microsecond % 10000;

    // 构建请求头
    final headers = <String, String>{
      'Content-Type': 'application/x-protobuf',
      'time': nowTime.toString(),
      'random': random.toString(),
      'app-id': Config.appId,
      'url': url,
      'token': Crypto.sign(url, random, nowTime, Config.appSecret),
      'x-forwarded-proto': Config.apiUrl.startsWith('https') ? 'https' : 'http',
    };

    // 添加语言设置
    final currentLang = await CacheMgr.get<String>('currentLang');
    headers['lang'] = currentLang ?? Config.defaultLanguage;

    // 添加短 Token
    final shortToken = Auth.getShortToken();
    if (shortToken != null && shortToken.isNotEmpty) {
      headers['authorization'] = shortToken;
    }

    try {
      final response = await _dio.post<List<int>>(
        url,
        data: data,
        options: Options(
          headers: headers,
          responseType: ResponseType.bytes,
        ),
      );

      // 处理 401 未授权
      if (response.statusCode == 401 && allowRetry) {
        return await _retryRequestWithNewToken(url, data);
      }

      // 处理非 200 状态码
      if (response.statusCode != 200) {
        throw Exception('HTTP Error: ${response.statusCode}');
      }

      // 检查响应数据
      if (response.data == null) {
        throw Exception('响应数据为空');
      }

      // 返回响应数据
      // 注意：实际项目中需要解析 Protobuf 通用响应，检查 errno
      return Uint8List.fromList(response.data!);
    } on DioException catch (e) {
      if (e.response?.statusCode == 401 && allowRetry) {
        return await _retryRequestWithNewToken(url, data);
      }
      rethrow;
    }
  }
}
