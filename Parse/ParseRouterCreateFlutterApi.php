<?php
declare(strict_types=1);

namespace Swlib\Parse;

use Generate\ConfigEnum;
use Swlib\Router\Router;
use Swlib\Utils\File;
use Swlib\Utils\StringConverter;


trait ParseRouterCreateFlutterApi
{

    /**
     * 创建 Flutter API 方法列表
     * 只生成 Protobuf 返回类型的 API
     * @param array $attributes
     * @return void
     */
    public function createFlutterApi(array $attributes): void
    {
        $dbName = ConfigEnum::DB_DATABASE;
        if (is_array($dbName)) {
            $dbName = $dbName[0];
        }
        $dbNameUpper = strtoupper($dbName);
        // 转换数据库名为配置变量名：mi_yao_bi_ji -> miYaoBiJiUrl
        $configVarName = $this->convertDbNameToConfigVar($dbName);

        // Flutter API 模板
        $apiTemplate = <<<'DART'
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

        // WebSocket 模板
        $socketTemplate = <<<'DART'
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

        $dir = RUNTIME_DIR . "codes/flutterApi/";
        $saveString = [];

        /** @var Router $item */
        foreach ($attributes as $item) {
            $response = $item->response;
            $method = strtolower($item->method);

            // 跳过 GET 请求
            if ($method === 'get') {
                continue;
            }

            // API 请求没有返回值的不必生成 API
            if ((empty($response) || $response == 'void') && $method == 'post') {
                continue;
            }

            // WebSocket 使用广播消息
            if (in_array($method, ['ws', 'wss'])) {
                $response = $item->broadcastMessage;
            }

            if (empty($response)) {
                continue;
            }

            // 只处理 Protobuf 返回类型
            if (!$this->isProtobufType($response)) {
                continue;
            }

            // 转换命名空间为 Dart 风格
            $response = $this->convertToDartType($response);
            $request = $this->convertToDartType($item->request);

            // 获取方法名称（复用 TS 的方法，转换为小驼峰）
            $name = lcfirst($this->getClassName($item->className, $item->methodName));

            // 生成响应类型
            $responseType = $this->getDartResponseType($response);

            // 生成参数列表
            $params = $this->generateDartParams($item->request);
            $paramsMap = $this->generateDartParamsMap($item->request);
            $toProtoFunc = $this->generateToProtoFunc($item->request);

            $cacheTime = $item->cache ?: 0;
            $url = $item->url;

            // 确定保存路径 - 使用下划线命名
            $urlPath = str_replace('-', '_', dirname($url));
            $savePath = $dir . $urlPath . '.dart';
            if (!isset($saveString[$savePath])) {
                $saveString[$savePath] = $this->generateDartFileHeader($savePath);
            }

            // 选择模板
            if ($item->method && in_array(strtolower($item->method), ['ws', 'wss'])) {
                $template = $socketTemplate;
            } else {
                $template = $apiTemplate;
            }

            // 替换模板变量
            $saveString[$savePath] .= str_replace(
                ['{$url}', '{$name}', '{$request}', '{$response}', '{$responseType}', '{$params}', '{$paramsMap}', '{$toProtoFunc}', '{$cache}', '{$title}', '{$desc}', '{$dbNameUpper}', '{$configVar}'],
                [$item->url, $name, $request ?: 'null', $response, $responseType, $params, $paramsMap, $toProtoFunc, $cacheTime, $item->errorTitle, str_replace('失败', '', $item->errorTitle), $dbNameUpper, $configVarName],
                $template
            );
        }

        // 保存文件
        foreach ($saveString as $path => $string) {
            if (!$string) continue;

            // 添加类结尾
            $string .= "}\n";

            File::save($path, $string);
        }
    }

    /**
     * 检查是否为 Protobuf 类型
     * @param string $type
     * @return bool
     */
    private function isProtobufType(string $type): bool
    {
        if (empty($type) || $type === 'void') {
            return false;
        }

        // Protobuf 类型以 Protobuf\ 开头
        if (str_starts_with($type, 'Protobuf\\')) {
            return true;
        }

        // 或者包含 Proto 后缀
        if (str_contains($type, 'Proto')) {
            return true;
        }

        return false;
    }

    /**
     * 转换 PHP 类型为 Dart 类型
     * @param string $phpType
     * @return string
     */
    private function convertToDartType(string $phpType): string
    {
        if (empty($phpType)) {
            return 'void';
        }

        // 替换命名空间分隔符
        $dartType = str_replace('\\', '.', $phpType);

        // 移除 Protobuf 前缀（如果有）
        return str_replace('Protobuf.', '', $dartType);
    }

    /**
     * 获取 Dart 响应类型
     * @param string $response
     * @return string
     */
    private function getDartResponseType(string $response): string
    {
        if (empty($response) || $response === 'void') {
            return 'void';
        }

        // 提取最后一段作为类名
        $parts = explode('.', $response);
        return end($parts);
    }

    /**
     * 生成 Dart 参数列表
     * @param string $request
     * @return string
     */
    private function generateDartParams(string $request): string
    {
        if (empty($request) || !$this->isProtobufType($request)) {
            return '{bool showErr = true}';
        }

        // 提取请求类名
        $parts = explode('.', $this->convertToDartType($request));
        $requestClass = end($parts);

        return "{required $requestClass params, bool showErr = true}";
    }

    /**
     * 生成 Dart 参数映射
     * @param string $request
     * @return string
     */
    private function generateDartParamsMap(string $request): string
    {
        if (empty($request) || !$this->isProtobufType($request)) {
            return 'null';
        }

        return 'params';
    }

    /**
     * 生成 toProto 函数
     * @param string $request
     * @return string
     */
    private function generateToProtoFunc(string $request): string
    {
        if (empty($request) || !$this->isProtobufType($request)) {
            return 'null';
        }

        $requestType = $this->getDartResponseType($this->convertToDartType($request));
        return "(data) => (data as $requestType).writeToBuffer()";
    }

    /**
     * 生成 Dart 文件头部
     * @param string $filePath
     * @return string
     */
    private function generateDartFileHeader(string $filePath): string
    {
        // 从文件路径提取类名
        $fileName = basename($filePath, '.dart');
        // 先将连字符转换为下划线，再转换为驼峰命名
        $fileName = str_replace('-', '_', $fileName);
        $className = StringConverter::underscoreToCamelCase($fileName);
        $className = ucfirst($className) . 'Api';

        return <<<DART
// Auto-generated file. Do not edit manually.
// Generated at: {$this->getCurrentDateTime()}

import 'package:mi_yao_bi_ji/core/network/api_client.dart';
import 'package:mi_yao_bi_ji/core/config/app_config.dart';
import 'package:mi_yao_bi_ji/proto/generated/proto.dart';

/// $className
class $className {

DART;
    }

    /**
     * 获取当前日期时间
     * @return string
     */
    private function getCurrentDateTime(): string
    {
        return date('Y-m-d H:i:s');
    }

    /**
     * 将数据库名转换为配置变量名
     * 例如：mi_yao_bi_ji -> miYaoBiJiUrl
     *       common_api -> commonApiUrl
     * @param string $dbName
     * @return string
     */
    private function convertDbNameToConfigVar(string $dbName): string
    {
        // 转换为小驼峰命名（首字母小写）
        $camelCase = StringConverter::underscoreToCamelCase($dbName, '_', false);
        // 添加 Url 后缀
        return $camelCase . 'Url';
    }

}
