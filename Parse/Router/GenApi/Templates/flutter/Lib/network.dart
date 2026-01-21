import 'dart:async';
import 'dart:io';
import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:permission_handler/permission_handler.dart';

/// 网络状态管理类
/// 处理 iOS/Android 首次打开 App 时的网络权限问题
class Network {
  /// 网络是否就绪
  static bool _isReady = false;

  /// 等待网络就绪的 Completer
  static Completer<bool>? _readyCompleter;

  /// 检测间隔（毫秒）
  static const int _checkInterval = 500;

  /// 最大等待时间（毫秒）
  static const int _maxWaitTime = 10000;

  /// 权限被拒绝时的回调
  static void Function()? _permissionDeniedCallback;

  /// Connectivity 实例
  static final Connectivity _connectivity = Connectivity();

  /// 设置权限被拒绝时的回调（引导用户开启权限）
  static void onPermissionDenied(void Function() callback) {
    _permissionDeniedCallback = callback;
  }

  /// 等待网络就绪（处理首次打开 App 的权限问题）
  /// 轮询检测网络状态，超时后引导用户开启权限
  static Future<bool> waitForReady() async {
    if (_isReady) return true;
    if (_readyCompleter != null) return _readyCompleter!.future;

    _readyCompleter = Completer<bool>();
    int elapsed = 0;

    Future<void> check() async {
      final connectivityResult = await _connectivity.checkConnectivity();

      if (connectivityResult != ConnectivityResult.none) {
        _isReady = true;
        _readyCompleter?.complete(true);
        _readyCompleter = null;
      } else {
        elapsed += _checkInterval;
        if (elapsed >= _maxWaitTime) {
          await _handlePermissionDenied();
        } else {
          await Future.delayed(Duration(milliseconds: _checkInterval));
          await check();
        }
      }
    }

    await check();
    return _readyCompleter?.future ?? Future.value(true);
  }

  /// 处理权限被拒绝的情况
  static Future<void> _handlePermissionDenied() async {
    if (_permissionDeniedCallback != null) {
      _permissionDeniedCallback!();
    } else {
      // 默认行为：请求打开设置
      await openAppSettings();
    }

    // 重置状态，继续等待
    _readyCompleter = null;
    await waitForReady();
  }

  /// 监听网络状态变化
  static StreamSubscription<ConnectivityResult> onStatusChange(
    void Function(bool isConnected) callback,
  ) {
    return _connectivity.onConnectivityChanged.listen((result) {
      _isReady = result != ConnectivityResult.none;
      callback(_isReady);
    });
  }

  /// 获取当前网络类型
  static Future<ConnectivityResult> getNetworkType() async {
    return await _connectivity.checkConnectivity();
  }

  /// 检查网络是否已连接
  static Future<bool> isConnected() async {
    final result = await _connectivity.checkConnectivity();
    return result != ConnectivityResult.none;
  }

  /// 重置网络状态（用于测试或重新检测）
  static void reset() {
    _isReady = false;
    _readyCompleter = null;
  }

  /// 检查并请求网络权限（Android）
  static Future<bool> checkAndRequestPermission() async {
    if (Platform.isAndroid) {
      // Android 不需要特殊的网络权限请求
      // 但可能需要检查 INTERNET 权限是否在 manifest 中声明
      return true;
    } else if (Platform.isIOS) {
      // iOS 的网络权限会在首次请求时自动弹出
      // 这里只是检查当前状态
      final result = await _connectivity.checkConnectivity();
      return result != ConnectivityResult.none;
    }
    return true;
  }
}
