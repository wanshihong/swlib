<?php
declare(strict_types=1);

namespace Swlib\Parse\Router\GenApi;

use Exception;
use Generate\ConfigEnum;
use Swlib\Parse\Router\GenApi\Abstract\AbstractApiGenerator;
use Swlib\Parse\Router\GenApi\Helper\ApiGeneratorHelper;
use Swlib\Router\Router;
use Swlib\Utils\File;

/**
 * Flutter API 生成器
 * 生成完整的 API 调用代码，包括 http.dart、crypto.dart 等通用库
 */
class FlutterApiGenerator extends AbstractApiGenerator
{
    /**
     * 模板目录路径
     */
    private const string TEMPLATE_DIR = __DIR__ . '/Templates/flutter/';

    protected function getSaveDir(): string
    {
        return RUNTIME_DIR . "codes/flutterApi/";
    }

    protected function getFileExtension(): string
    {
        return 'dart';
    }

    protected function getHttpTemplate(): string
    {
        return <<<'DART'
  /// {$desc}
  static Future<{$responseType}> {$name}({$params}) async {
    return Http.callApi<{$responseType}>(
      url: '${Config.apiUrl}/{$url}',
      params: {$paramsValue},
      showError: showErr,
      errorMessage: '{$title}',
      toProto: {$toProtoFunc},
      fromProto: (bytes) => {$responseType}.fromBuffer(bytes),
      cacheTime: {$cache},
    );
  }

DART;
    }

    protected function getWebSocketTemplate(): string
    {
        return <<<'DART'
  /// {$desc} - WebSocket 监听
  static void {$name}SocketOn({
    bool showErr = true,
    required void Function({$responseType} res) callback,
  }) {
    Socket.getInstance().on(SocketBindOnResponse<{$responseType}>(
      url: '{$url}',
      fromProto: (bytes) => {$responseType}.fromBuffer(bytes),
      callback: callback,
      showError: showErr,
      errorMessage: '{$title}',
    ));
  }

  /// {$desc} - 取消 WebSocket 监听
  static void {$name}SocketOff([void Function({$responseType})? callback]) {
    Socket.getInstance().off('{$url}', callback);
  }

  /// {$desc} - 发送 WebSocket 消息
  static void {$name}SocketSend({$params}) {
    Socket.getInstance().send(SocketSendRequest(
      url: '{$url}',
      params: {$paramsValue},
      toProto: {$toProtoFunc},
    ));
  }

DART;
    }

    protected function getFileHeader(string $filePath): string
    {
        $dateTime = ApiGeneratorHelper::getCurrentDateTime();

        return <<<DART
// Auto-generated file. Do not edit manually.
// Generated at: {$dateTime}

import 'dart:typed_data';
import './Lib/lib.dart';
import './config.dart';

// TODO: 根据实际项目调整 Protobuf 导入路径
// import 'package:your_app/proto/generated/proto.dart';

/// API 调用类
class Api {

DART;
    }

    protected function getFileFooter(): string
    {
        return "}\n";
    }

    /**
     * 合并所有 API 到单个文件
     */
    protected function getSavePath(string $url): string
    {
        return $this->getSaveDir() . 'api.' . $this->getFileExtension();
    }

    protected function processRouterItem(Router $item, string $requestType): ?array
    {
        $response = $requestType === ApiGeneratorHelper::REQUEST_TYPE_WEBSOCKET
            ? $item->broadcastMessage
            : $item->response;

        // 转换为 Dart 类型
        $response = $this->convertToDartType($response);
        $request = $this->convertToDartType($item->request);

        // 获取方法名称（小驼峰）
        $name = lcfirst(ApiGeneratorHelper::getApiMethodName($item->className, $item->methodName));
        if(str_ends_with($name, 'Run')) {
            $name = substr($name, 0, -3);
        }


        // 获取响应类型
        $responseType = $this->getDartResponseType($response);

        // 生成参数列表
        $params = $this->generateDartParams($item->request);
        $paramsValue = $this->generateDartParamsValue($item->request);
        $toProtoFunc = $this->generateToProtoFunc($item->request);

        return [
            '{$url}' => $item->url,
            '{$name}' => $name,
            '{$request}' => $request ?: 'null',
            '{$response}' => $response,
            '{$responseType}' => $responseType,
            '{$params}' => $params,
            '{$paramsValue}' => $paramsValue,
            '{$toProtoFunc}' => $toProtoFunc,
            '{$cache}' => $item->cache ?: 0,
            '{$title}' => $item->errorTitle,
            '{$desc}' => str_replace('失败', '', $item->errorTitle),
        ];
    }

    /**
     * 生成前清理目录
     */
    public function generate(array $attributes): bool
    {
        $this->cleanDirectory();
        return parent::generate($attributes);
    }

    /**
     * 清理生成目录（保留 Lib 目录）
     */
    private function cleanDirectory(): void
    {
        $dir = $this->getSaveDir();
        if (!is_dir($dir)) {
            return;
        }

        // 只删除根目录下的 .dart 文件，保留子目录
        $files = glob($dir . '*.dart');
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    /**
     * 保存文件后复制模板和生成配置
     * @throws Exception
     */
    protected function saveFiles(): void
    {
        parent::saveFiles();
        $this->copyTemplates();
        $this->generateConfig();
    }

    /**
     * 复制模板文件到目标目录
     * @throws Exception
     */
    private function copyTemplates(): void
    {
        $templateLibDir = self::TEMPLATE_DIR . 'Lib/';
        $targetLibDir = $this->getSaveDir() . 'Lib/';

        // 复制整个 Lib 目录
        if (is_dir($templateLibDir)) {
            File::copyDirectory($templateLibDir, $targetLibDir);
        }
    }

    /**
     * 生成 config.dart 配置文件
     */
    private function generateConfig(): void
    {
        $templateFile = self::TEMPLATE_DIR . 'config.dart.tpl';

        if (!file_exists($templateFile)) {
            return;
        }

        $template = file_get_contents($templateFile);

        // 构建 API URL
        $protocol = ConfigEnum::get('HTTPS', true) ? 'https' : 'http';
        $port = ConfigEnum::get('PORT', 9511);
        $apiUrl = ConfigEnum::get('API_URL', "$protocol://localhost:$port");

        // 构建 WebSocket URL
        $wsProtocol = ConfigEnum::get('HTTPS', true) ? 'wss' : 'ws';
        $wsHost = ConfigEnum::get('WS_HOST', "$wsProtocol://localhost:$port");

        // 替换模板变量
        $replacements = [
            '{$API_URL}' => $apiUrl,
            '{$WS_HOST}' => $wsHost,
            '{$APP_ID}' => ConfigEnum::get('APP_ID', '1'),
            '{$APP_SECRET}' => ConfigEnum::get('APP_SECRET', 'secret'),
            '{$TIMEOUT}' => ConfigEnum::get('TIMEOUT', 30000),
            '{$DEFAULT_LANGUAGE}' => ConfigEnum::get('DEFAULT_LANGUAGE', 'zh'),
        ];

        $content = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $template
        );

        File::save($this->getSaveDir() . 'config.dart', $content);
    }

    /**
     * 转换 PHP 类型为 Dart 类型
     */
    private function convertToDartType(string $phpType): string
    {
        if (empty($phpType)) {
            return 'void';
        }
        $dartType = str_replace('\\', '.', $phpType);
        return str_replace('Protobuf.', '', $dartType);
    }

    /**
     * 获取 Dart 响应类型（提取最后一段作为类名）
     */
    private function getDartResponseType(string $response): string
    {
        if (empty($response) || $response === 'void') {
            return 'void';
        }
        $parts = explode('.', $response);
        return end($parts);
    }

    /**
     * 生成 Dart 参数列表
     */
    private function generateDartParams(string $request): string
    {
        if (empty($request) || !ApiGeneratorHelper::isProtobufType($request)) {
            return '{bool showErr = true}';
        }

        $parts = explode('.', $this->convertToDartType($request));
        $requestClass = end($parts);

        return "{required $requestClass params, bool showErr = true}";
    }

    /**
     * 生成 Dart 参数值
     */
    private function generateDartParamsValue(string $request): string
    {
        if (empty($request) || !ApiGeneratorHelper::isProtobufType($request)) {
            return 'null';
        }
        return 'params';
    }

    /**
     * 生成 toProto 函数
     */
    private function generateToProtoFunc(string $request): string
    {
        if (empty($request) || !ApiGeneratorHelper::isProtobufType($request)) {
            return 'null';
        }

        $requestType = $this->getDartResponseType($this->convertToDartType($request));
        return "(data) => (data as $requestType).writeToBuffer()";
    }
}
