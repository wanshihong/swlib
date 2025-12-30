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
     * 规则：
     * 1. 去掉类名通用后缀（Api、Controller、Service）
     * 2. 忽略通用模块目录（Api、Apis、Controller、Controllers 等）
     * 3. 如果去后缀的类名以上级目录名开头，不加目录前缀；否则加目录前缀
     * 4. 拼接方法名（首字母大写）
     *
     * 示例：
     * - App\Api\Ad\AdConfigApi + lists → AdConfigLists
     * - App\Api\User\ProfileApi + update → UserProfileUpdate
     * - App\Apis\Notes\TagApi + delete → NotesTagDelete
     * - App\Apis\Language + saveAndUse → LanguageSaveAndUse
     * - Swlib\Controller\LanguageController + saveAndUse → LanguageSaveAndUse
     *
     * @param string $class 完整类名（含命名空间）
     * @param string $method 方法名
     * @return string
     */
    public static function getApiMethodName(string $class, string $method): string
    {
        // 常见后缀列表
        $suffixes = ['Api', 'Controller', 'Service', 'Ctrl'];

        // 通用模块目录（不作为前缀）
        $ignoredDirs = ['Api', 'Apis', 'Controller', 'Controllers', 'Service', 'Services', 'App', 'Swlib', 'Ctrl'];

        // 分割命名空间
        $parts = explode('\\', $class);

        // 获取类名（最后一段）
        $className = array_pop($parts);

        // 获取有效的上级目录名（跳过通用模块目录）
        $parentDir = '';
        while (!empty($parts)) {
            $dir = array_pop($parts);
            if (!in_array($dir, $ignoredDirs, true)) {
                $parentDir = $dir;
                break;
            }
        }

        // 去掉类名的通用后缀
        $cleanClassName = $className;
        foreach ($suffixes as $suffix) {
            if (str_ends_with($cleanClassName, $suffix)) {
                $cleanClassName = substr($cleanClassName, 0, -strlen($suffix));
                break; // 只去掉一个后缀
            }
        }

        // 判断是否需要加目录前缀
        // 如果类名以上级目录名开头（不区分大小写），则不加前缀
        $needPrefix = true;
        if (!empty($parentDir) && !empty($cleanClassName)) {
            if (stripos($cleanClassName, $parentDir) === 0) {
                $needPrefix = false;
            }
        }

        // 构建结果
        $result = '';
        if ($needPrefix && !empty($parentDir)) {
            $result .= $parentDir;
        }
        $result .= $cleanClassName;

        // 拼接方法名（转换为大驼峰）
        $methodName = StringConverter::underscoreToCamelCase($method);
        $result .= ucfirst($methodName);

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

