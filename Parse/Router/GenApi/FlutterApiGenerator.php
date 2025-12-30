<?php
declare(strict_types=1);

namespace Swlib\Parse\Router\GenApi;

use Swlib\Parse\Router\GenApi\Abstract\AbstractApiGenerator;
use Swlib\Parse\Router\GenApi\Helper\ApiGeneratorHelper;
use Swlib\Router\Router;
use Swlib\Utils\StringConverter;

/**
 * Flutter API 生成器
 */
class FlutterApiGenerator extends AbstractApiGenerator
{
    /**
     * 配置变量名（如 miYaoBiJiUrl）
     */
    protected string $configVarName;

    public function __construct()
    {
        parent::__construct();
        $this->configVarName = $this->convertDbNameToConfigVar($this->dbName);
    }

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
    return ApiClient.instance.request<{$responseType}>(
      '${AppConfig.{$configVar}}/{$url}',
      requestData: {$paramsMap},
      fromProto: (bytes) => {$responseType}.fromBuffer(bytes),
      toProto: {$toProtoFunc},
      showError: showErr,
      errorTitle: '{$title}',
    );
  }

DART;
    }

    protected function getWebSocketTemplate(): string
    {
        return <<<'DART'
  /// {$desc} - WebSocket 监听
  static void {$name}On({
    bool showErr = true,
    required void Function({$responseType} res) callback,
  }) {
    // TODO: WebSocket 实现
  }

  /// {$desc} - 取消 WebSocket 监听
  static void {$name}Off(void Function({$responseType} res) callback) {
    // TODO: WebSocket 实现
  }

  /// {$desc} - 发送 WebSocket 消息
  static void {$name}Send({$params}) {
    // TODO: WebSocket 实现
  }

DART;
    }

    protected function getFileHeader(string $filePath): string
    {
        // 从文件路径提取类名
        $fileName = basename($filePath, '.dart');
        $fileName = str_replace('-', '_', $fileName);
        $className = StringConverter::underscoreToCamelCase($fileName);
        $className = ucfirst($className) . 'Api';

        $dateTime = ApiGeneratorHelper::getCurrentDateTime();

        return <<<DART
// Auto-generated file. Do not edit manually.
// Generated at: {$dateTime}

import 'package:mi_yao_bi_ji/core/network/api_client.dart';
import 'package:mi_yao_bi_ji/core/config/app_config.dart';
import 'package:mi_yao_bi_ji/proto/generated/proto.dart';

/// $className
class $className {

DART;
    }

    protected function getFileFooter(): string
    {
        return "}\n";
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

        // 获取响应类型
        $responseType = $this->getDartResponseType($response);

        // 生成参数列表
        $params = $this->generateDartParams($item->request);
        $paramsMap = $this->generateDartParamsMap($item->request);
        $toProtoFunc = $this->generateToProtoFunc($item->request);

        return [
            '{$url}' => $item->url,
            '{$name}' => $name,
            '{$request}' => $request ?: 'null',
            '{$response}' => $response,
            '{$responseType}' => $responseType,
            '{$params}' => $params,
            '{$paramsMap}' => $paramsMap,
            '{$toProtoFunc}' => $toProtoFunc,
            '{$cache}' => $item->cache ?: 0,
            '{$title}' => $item->errorTitle,
            '{$desc}' => str_replace('失败', '', $item->errorTitle),
            '{$dbNameUpper}' => $this->dbNameUpper,
            '{$configVar}' => $this->configVarName,
        ];
    }

    /**
     * 将数据库名转换为配置变量名
     * 例如：mi_yao_bi_ji -> miYaoBiJiUrl
     */
    private function convertDbNameToConfigVar(string $dbName): string
    {
        $camelCase = StringConverter::underscoreToCamelCase($dbName, '_', false);
        return $camelCase . 'Url';
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
     * 生成 Dart 参数映射
     */
    private function generateDartParamsMap(string $request): string
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

