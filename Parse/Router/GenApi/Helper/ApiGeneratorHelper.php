<?php
declare(strict_types=1);

namespace Swlib\Parse\Router\GenApi\Helper;

use Swlib\Utils\StringConverter;

/**
 * API 生成器工具类
 * 包含公共的工具方法
 */
class ApiGeneratorHelper
{
    // 请求类型常量
    public const string REQUEST_TYPE_HTTP = 'http';
    public const string REQUEST_TYPE_WEBSOCKET = 'websocket';
    public const string REQUEST_TYPE_MIXED = 'mixed'; // 错误情况：HTTP 和 WebSocket 同时存在

    // HTTP 方法列表
    private const array HTTP_METHODS = ['get', 'post', 'put', 'delete', 'patch', 'head', 'options'];

    // WebSocket 方法列表
    private const array WEBSOCKET_METHODS = ['ws', 'wss'];

    /**
     * 归一化 method 为小写数组
     * @param string|array $method
     * @return array
     */
    public static function normalizeMethod(string|array $method): array
    {
        if (empty($method)) {
            return [];
        }

        if (is_string($method)) {
            $method = [$method];
        }

        return array_map('strtolower', $method);
    }

    /**
     * 判断请求类型
     * @param array $methods 归一化后的 method 数组
     * @return string 返回 http/websocket/mixed
     */
    public static function getRequestType(array $methods): string
    {
        $hasHttp = false;
        $hasWebSocket = false;

        foreach ($methods as $m) {
            if (in_array($m, self::HTTP_METHODS, true)) {
                $hasHttp = true;
            }
            if (in_array($m, self::WEBSOCKET_METHODS, true)) {
                $hasWebSocket = true;
            }
        }

        if ($hasHttp && $hasWebSocket) {
            return self::REQUEST_TYPE_MIXED;
        }

        if ($hasWebSocket) {
            return self::REQUEST_TYPE_WEBSOCKET;
        }

        return self::REQUEST_TYPE_HTTP;
    }

    /**
     * 检查是否为 Protobuf 类型
     * @param string $type
     * @return bool
     */
    public static function isProtobufType(string $type): bool
    {
        if (empty($type) || $type === 'void' || $type === 'null') {
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
     * 获取 API 方法名称
     * 规则（参考 ParseRouterRouter::getUrlPath）：
     * 1. 去掉顶层命名空间（App/Swlib）
     * 2. 移除通用目录段（Controller/Ctrl）
     * 3. 仅移除末段类名的 Controller/Ctrl 后缀
     * 4. 将剩余各段 + 方法名拼成大驼峰
     *
     * @param string $class 完整类名（含命名空间）
     * @param string $method 方法名
     * @return string
     */
    public static function getApiMethodName(string $class, string $method): string
    {
        $parts = explode('\\', ltrim($class, '\\'));

        $appRoot = defined('APP_DIR') ? strtolower(basename(rtrim(APP_DIR, DIRECTORY_SEPARATOR))) : 'app';
        $swlibRoot = defined('SWLIB_DIR') ? strtolower(basename(rtrim(SWLIB_DIR, DIRECTORY_SEPARATOR))) : 'swlib';

        $firstPart = strtolower($parts[0] ?? '');
        if ($firstPart !== '' && ($firstPart === $appRoot || $firstPart === $swlibRoot)) {
            array_shift($parts);
        }

        $parts = array_values(array_filter($parts, static function (string $part): bool {
            return !in_array(strtolower($part), ['controller', 'controllers', 'ctrl', 'api', 'apis'], true);
        }));

        if (!empty($parts)) {
            $lastIndex = count($parts) - 1;
            $original = $parts[$lastIndex];
            $trimmed = preg_replace('/(Controller|Ctrl)$/', '', $original);
            if (is_string($trimmed) && $trimmed !== '') {
                $parts[$lastIndex] = $trimmed;
            }
        }

        $methodSegment = trim($method);
        if ($methodSegment !== '') {
            $parts[] = $methodSegment;
        }

        $parts = array_values(array_filter($parts, static fn(string $part): bool => $part !== ''));
        if (empty($parts)) {
            return StringConverter::underscoreToCamelCase($method);
        }

        $result = '';
        foreach ($parts as $segment) {
            $segment = str_replace(['-', '.'], '_', $segment);
            if (str_contains($segment, '_')) {
                $result .= StringConverter::underscoreToCamelCase($segment);
            } else {
                $result .= ucfirst($segment);
            }
        }

        return $result;
    }

    /**
     * 给最后一段字符串前加上 I（用于 TypeScript 接口类型）
     * protobuf.User.PasswordRegister => protobuf.User.IPasswordRegister
     * @param string $originalClassName
     * @return string
     */
    public static function appendInterfacePrefix(string $originalClassName): string
    {
        $parts = explode('.', $originalClassName);
        $lastPart = end($parts);
        $modifiedLastPart = 'I' . $lastPart;
        return implode('.', array_slice($parts, 0, -1)) . '.' . $modifiedLastPart;
    }

    /**
     * 获取当前日期时间
     * @return string
     */
    public static function getCurrentDateTime(): string
    {
        return date('Y-m-d H:i:s');
    }
}
