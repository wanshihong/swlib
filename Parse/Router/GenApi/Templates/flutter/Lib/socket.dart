import 'dart:async';
import 'dart:typed_data';
import 'package:web_socket_channel/web_socket_channel.dart';
import 'package:connectivity_plus/connectivity_plus.dart';

import '../config.dart';
import 'auth.dart';
import 'cache_mgr.dart';
import 'crypto.dart';

/// WebSocket 发送请求接口
class SocketSendRequest {
  final String url;
  final dynamic params;
  final Uint8List Function(dynamic) toProto;
  int retryCount;

  SocketSendRequest({
    required this.url,
    required this.params,
    required this.toProto,
    this.retryCount = 0,
  });
}

/// WebSocket 绑定响应接口
class SocketBindOnResponse<T> {
  final String url;
  final T Function(Uint8List) fromProto;
  final void Function(T) callback;
  final bool showError;
  final String errorMessage;

  SocketBindOnResponse({
    required this.url,
    required this.fromProto,
    required this.callback,
    this.showError = true,
    this.errorMessage = '',
  });
}

/// WebSocket 连接管理类
/// 采用单例模式，管理应用程序中的 WebSocket 连接
/// 提供自动重连、消息队列、心跳检测等功能
class Socket {
  /// 单例实例
  static Socket? _instance;

  /// WebSocket 通道
  WebSocketChannel? _channel;

  /// 连接状态标志
  bool _isConnected = false;

  /// 消息队列，存储待发送的消息
  final List<SocketSendRequest> _messageQueue = [];

  /// 重连定时器
  Timer? _reconnectTimer;

  /// 当前重连次数
  int _reconnectCount = 0;

  /// 初始重连间隔（毫秒）
  final int _reconnectInterval = 3000;

  /// 最大重连间隔（毫秒）
  final int _maxReconnectInterval = 30000;

  /// 消息回调映射
  final Map<String, List<SocketBindOnResponse>> _messageCallbacks = {};

  /// 心跳定时器
  Timer? _heartbeatTimer;

  /// 心跳间隔（毫秒）
  final int _heartbeatInterval = 30000;

  /// 心跳响应超时时间（毫秒）
  final int _heartbeatTimeout = 10000;

  /// 上次心跳时间戳
  int _lastHeartbeatTime = 0;

  /// 心跳响应等待标志
  bool _waitingForHeartbeatResponse = false;

  /// 心跳超时检测定时器
  Timer? _heartbeatCheckTimer;

  /// 消息队列最大大小
  final int _maxQueueSize = 100;

  /// 网络状态监听器
  StreamSubscription? _networkStatusListener;

  /// 连接状态监听器列表
  final List<void Function(bool)> _connectStatusCallbacks = [];

  /// 错误监听器列表
  final List<void Function(dynamic)> _errorCallbacks = [];

  /// 连接参数
  Map<String, dynamic> _connectParams = {};

  /// 私有构造函数
  Socket._internal() {
    _setupNetworkListener();
  }

  /// 获取 Socket 实例（单例模式）
  static Socket getInstance() {
    _instance ??= Socket._internal();
    return _instance!;
  }

  /// 连接 WebSocket
  void connect(Map<String, dynamic> params) {
    _connectParams = params;
    _doConnect();
  }

  /// 设置网络状态监听
  void _setupNetworkListener() {
    _networkStatusListener = Connectivity().onConnectivityChanged.listen((result) {
      if (result != ConnectivityResult.none && !_isConnected) {
        print('网络已恢复，尝试重新连接 WebSocket');
        _reconnectCount = 0;
        _doConnect();
      } else if (result != ConnectivityResult.none && _isConnected) {
        print('网络状态变化，发送心跳验证连接');
        ping();
      }
    });
  }

  /// 添加 WebSocket 连接状态监听
  void onConnectStatus(void Function(bool) callback) {
    _connectStatusCallbacks.add(callback);
  }

  /// 移除 WebSocket 连接状态监听
  void offConnectStatus([void Function(bool)? callback]) {
    if (callback != null) {
      _connectStatusCallbacks.remove(callback);
    } else {
      _connectStatusCallbacks.clear();
    }
  }

  /// 添加 WebSocket 错误监听
  void onError(void Function(dynamic) callback) {
    _errorCallbacks.add(callback);
  }

  /// 移除 WebSocket 错误监听
  void offError([void Function(dynamic)? callback]) {
    if (callback != null) {
      _errorCallbacks.remove(callback);
    } else {
      _errorCallbacks.clear();
    }
  }

  /// 获取认证参数
  String _getAuthParam() {
    final nowTime = DateTime.now().millisecondsSinceEpoch ~/ 1000;
    final random = DateTime.now().microsecond % 10000;

    final shortToken = Auth.getShortToken() ?? '';
    final token = Crypto.sign(shortToken, random, nowTime, Config.appSecret);
    final lang = Config.defaultLanguage;
    final xForwardedProto = Config.apiUrl.startsWith('https') ? 'https' : 'http';

    return '?time=$nowTime&random=$random&appid=${Config.appId}&token=$token&lang=$lang&x-forwarded-proto=$xForwardedProto&authorization=$shortToken';
  }

  /// 创建 WebSocket 连接
  void _doConnect() {
    var sendParams = _getAuthParam();

    _connectParams.forEach((key, value) {
      sendParams += '&$key=$value';
    });

    // 如果已有连接，先关闭
    _channel?.sink.close();

    final wsUrl = '${Config.wsHost}/$sendParams';

    try {
      _channel = WebSocketChannel.connect(Uri.parse(wsUrl));

      _channel!.stream.listen(
        (data) {
          _onMessage(data);
        },
        onDone: () {
          print('WebSocket 连接已关闭');
          _isConnected = false;
          _stopHeartbeat();
          _notifyConnectStatus(false);
          _handleReconnect();
        },
        onError: (error) {
          print('WebSocket 错误: $error');
          _isConnected = false;
          _stopHeartbeat();
          _notifyError(error);
          _notifyConnectStatus(false);
          _handleReconnect();
        },
      );

      // 连接成功
      _isConnected = true;
      _reconnectCount = 0;
      _sendQueuedMessages();
      _startHeartbeat();
      _notifyConnectStatus(true);
      print('WebSocket 连接成功');
    } catch (e) {
      print('WebSocket 连接失败: $e');
      _handleReconnect();
    }
  }

  /// 处理收到的消息
  void _onMessage(dynamic data) {
    if (data == 'pong') {
      _waitingForHeartbeatResponse = false;
      _lastHeartbeatTime = DateTime.now().millisecondsSinceEpoch;
      return;
    }

    // TODO: 解析 Protobuf 响应并调用对应的回调
    // 这里需要根据实际的 Protobuf 定义来实现
  }

  /// 通知连接状态变化
  void _notifyConnectStatus(bool isConnected) {
    for (final callback in _connectStatusCallbacks) {
      try {
        callback(isConnected);
      } catch (e) {
        print('调用连接状态回调时出错: $e');
      }
    }
  }

  /// 通知错误
  void _notifyError(dynamic error) {
    for (final callback in _errorCallbacks) {
      try {
        callback(error);
      } catch (e) {
        print('调用错误回调时出错: $e');
      }
    }
  }

  /// 启动心跳检测
  void _startHeartbeat() {
    _stopHeartbeat();

    _lastHeartbeatTime = DateTime.now().millisecondsSinceEpoch;
    _waitingForHeartbeatResponse = false;

    _heartbeatTimer = Timer.periodic(Duration(milliseconds: _heartbeatInterval), (_) {
      if (!_isConnected) {
        _stopHeartbeat();
        return;
      }
      ping();
    });

    _heartbeatCheckTimer = Timer.periodic(Duration(milliseconds: _heartbeatInterval ~/ 3), (_) {
      if (!_isConnected) {
        _stopHeartbeat();
        return;
      }

      final currentTime = DateTime.now().millisecondsSinceEpoch;

      if (_waitingForHeartbeatResponse && (currentTime - _lastHeartbeatTime > _heartbeatTimeout)) {
        print('心跳响应超时，认为连接已断开');
        _isConnected = false;
        _handleReconnect();
        return;
      }

      if (currentTime - _lastHeartbeatTime > _heartbeatInterval * 0.8) {
        print('长时间没有心跳通信，发送额外心跳');
        ping();
      }
    });
  }

  /// 发送心跳包
  void ping() {
    if (!_isConnected || _channel == null) return;

    _waitingForHeartbeatResponse = true;
    _lastHeartbeatTime = DateTime.now().millisecondsSinceEpoch;

    try {
      // TODO: 发送 Protobuf 格式的心跳包
      _channel!.sink.add('ping');
    } catch (e) {
      print('心跳包发送失败: $e');
      _waitingForHeartbeatResponse = false;
      _isConnected = false;
      _handleReconnect();
    }
  }

  /// 停止心跳检测
  void _stopHeartbeat() {
    _heartbeatTimer?.cancel();
    _heartbeatTimer = null;
    _heartbeatCheckTimer?.cancel();
    _heartbeatCheckTimer = null;
    _waitingForHeartbeatResponse = false;
  }

  /// 处理 WebSocket 重连逻辑
  void _handleReconnect() {
    if (_isConnected) return;

    _reconnectTimer?.cancel();
    _stopHeartbeat();

    final backoffInterval = (_reconnectInterval * (1.5 * _reconnectCount))
        .clamp(0, _maxReconnectInterval)
        .toInt();

    _reconnectTimer = Timer(Duration(milliseconds: backoffInterval), () async {
      _reconnectCount++;
      print('WebSocket 尝试第 $_reconnectCount 次重连, 间隔: ${backoffInterval}ms');

      final result = await Connectivity().checkConnectivity();
      if (result != ConnectivityResult.none) {
        _doConnect();
      } else {
        print('当前无网络连接，等待网络恢复');
      }
    });
  }

  /// 发送队列中的消息
  void _sendQueuedMessages() {
    if (!_isConnected || _channel == null) {
      print('WebSocket 连接未就绪，消息将在连接成功后发送');
      return;
    }

    const maxRetryCount = 3;

    for (var i = 0; i < _messageQueue.length;) {
      final message = _messageQueue[i];

      try {
        final data = message.toProto(message.params);
        _channel!.sink.add(data);
        print('WebSocket 消息发送成功: ${message.url}');
        _messageQueue.removeAt(i);
      } catch (e) {
        print('WebSocket 消息发送失败: $e');
        message.retryCount++;

        if (message.retryCount >= maxRetryCount) {
          print('消息发送失败次数过多 (${message.retryCount} 次)，放弃发送: ${message.url}');
          _messageQueue.removeAt(i);
        } else {
          i++;
        }
      }
    }
  }

  /// 发送 WebSocket 消息
  void send(SocketSendRequest request) {
    if (_messageQueue.length >= _maxQueueSize) {
      print('WebSocket 消息队列已满，丢弃最早消息');
      _messageQueue.removeAt(0);
    }

    _messageQueue.add(request);
    _sendQueuedMessages();
  }

  /// 注册 WebSocket 消息监听
  void on<T>(SocketBindOnResponse<T> opt) {
    final callbacksList = _messageCallbacks[opt.url] ?? [];

    final isDuplicate = callbacksList.any((existing) =>
        existing.url == opt.url &&
        existing.callback == opt.callback &&
        existing.showError == opt.showError &&
        existing.errorMessage == opt.errorMessage);

    if (!isDuplicate) {
      callbacksList.add(opt);
      _messageCallbacks[opt.url] = callbacksList;
      print('已注册 WebSocket 监听: ${opt.url}, 当前监听数: ${callbacksList.length}');
    } else {
      print('跳过重复的 WebSocket 监听: ${opt.url}');
    }
  }

  /// 取消 WebSocket 消息监听
  void off(String url, [Function? callback]) {
    if (callback == null) {
      _messageCallbacks.remove(url);
      print('已删除 URL 的所有监听: $url');
      return;
    }

    final callbacksList = _messageCallbacks[url];
    if (callbacksList == null || callbacksList.isEmpty) {
      print('URL 没有注册的监听: $url');
      return;
    }

    callbacksList.removeWhere((item) => item.callback == callback);

    if (callbacksList.isEmpty) {
      _messageCallbacks.remove(url);
      print('已删除 URL 的最后一个监听: $url');
    } else {
      print('已删除特定回调, URL: $url, 剩余监听数: ${callbacksList.length}');
    }
  }

  /// 关闭 WebSocket 连接
  void close() {
    _channel?.sink.close();
    _channel = null;
    _isConnected = false;

    _reconnectTimer?.cancel();
    _reconnectTimer = null;

    _stopHeartbeat();

    _networkStatusListener?.cancel();
    _networkStatusListener = null;

    _messageQueue.clear();
  }
}
