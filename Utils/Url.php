<?php

namespace Swlib\Utils;

use Generate\ConfigEnum;
use InvalidArgumentException;
use Swlib\Enum\CtxEnum;
use Swlib\Router\Router;
use Throwable;

class Url
{
    /** 传统 query 模式 */
    private const string MODE_QUERY = 'query';

    /** PathInfo 模式：参数以 /key/value 形式拼接在路由后面 */
    private const string MODE_PATH_INFO = 'pathInfo';


    /**
     * 生成基于当前控制器的路由
     *
     * 外部调用保持 **完全兼容旧逻辑**：
     *  - 新增 $mode 可选参数，不传时默认为 query 模式（等价旧行为）
     *  - http/https/javascript 开头的 URL 始终只追加 query 参数
     *  - $hasAddParam = false 时，不合并当前请求的参数，只使用 $params
     */
    public static function generateUrl(string $url, array $params = [], array $delParams = [], bool $hasAddParam = true, string $mode = self::MODE_PATH_INFO): string
    {
        // 1. 外部 URL：保持原有仅追加 query 的行为
        if (
            str_starts_with($url, 'http://')
            || str_starts_with($url, 'https://')
            || str_starts_with($url, 'javascript:')
        ) {
            if (empty($params)) {
                return $url;
            }
            return self::appendQueryParams($url, $params);
        }
        // 2. 归一化模式参数，非 pathInfo 值一律按 query 处理，保证健壮性
        $mode = ($mode === self::MODE_PATH_INFO) ? self::MODE_PATH_INFO : self::MODE_QUERY;

        // 3. 解析基础路径（不带参数部分），在不同模式下内部实现可以不同
        $retUrl = self::resolveBasePath($url, $mode);

        // 3.1 传统 query 模式：保持与旧逻辑完全一致
        $request = CtxEnum::Request->get();
        if ($mode === self::MODE_QUERY) {

            if ($hasAddParam === false) {
                // 旧代码行为：不合并当前请求参数，只使用 $params 生成 query
                return self::appendQueryParams($retUrl, $params);
            }

            $queryParams = array_merge($request->get ?: [], $params);

            if (empty($queryParams)) {
                // 兼容旧逻辑：仍然走 appendQueryParams（虽然等价于直接返回）
                return self::appendQueryParams($retUrl, $queryParams);
            }

            // 删除指定参数
            if (!empty($delParams)) {
                foreach ($delParams as $param) {
                    unset($queryParams[$param]);
                }
            }

            return self::appendQueryParams($retUrl, $queryParams);
        }

        // 3.2 PathInfo 模式：参数编码到路径中，兼容 OnRequestEvent + Router::parse
        if ($hasAddParam === false) {
            // 仅使用传入参数构建 PathInfo，不合并当前请求参数
            $finalParams = $params;
        } else {
            $finalParams = array_merge($request->get ?: [], $params);

            if (!empty($delParams)) {
                foreach ($delParams as $param) {
                    unset($finalParams[$param]);
                }
            }
        }

        if (empty($finalParams)) {
            // 没有需要附加的参数，直接返回基础路径
            return $retUrl;
        }

        return self::buildPathInfoUrl($retUrl, $finalParams);
    }


    /**
     * 兼容旧逻辑的 query 参数追加助手
     */
    public static function appendQueryParams(string $url, array $params = []): string
    {
        if (empty($params)) {
            return $url;
        }
        if (stripos($url, '?') === false) {
            return $url . '?' . http_build_query($params);
        }
        return $url . '&' . http_build_query($params);
    }


    /**
     * 解析基础路径：
     *  - 绝对路径：直接返回
     *  - 相对路径：基于当前路由（控制器）推导
     *
     * 为兼容 PathInfo：
     *  - query 模式：严格保持旧实现（基于 $request->server['path_info']）
     *  - pathinfo 模式：优先使用 Router::parse + CtxEnum::URI 计算基础路由，
     *    若失败则回退到旧的基于 path_info 的实现方式。
     */
    private static function resolveBasePath(string $url, string $mode): string
    {
        // 绝对路径：直接返回
        if (str_starts_with($url, '/')) {
            return $url;
        }

        $request = CtxEnum::Request->get();

        // 1. query 模式：完全复刻旧逻辑，保证兼容
        if ($mode === self::MODE_QUERY) {
            $pathInfo = $request->server['path_info'] ?? '';
            $arr = explode('/', $pathInfo);
            array_pop($arr);
            $arr[] = StringConverter::camelCaseToUnderscore($url, '-');
            return implode('/', $arr);
        }

        // 2. pathInfo 模式：优先用 Router::parse 获取基础路由
        $baseSegments = [];

        try {
            $uri = CtxEnum::URI->get();
            if (is_string($uri) && $uri !== '') {
                // 去除可能存在的 query（保险处理）
                $uriPath = explode('?', $uri, 2)[0];
                try {
                    [$routeConfig, $baseUri,] = Router::parse($uriPath);
                    if ($routeConfig !== null && $baseUri !== null && $baseUri !== '') {
                        $baseSegments = explode('/', $baseUri);
                    }
                } catch (InvalidArgumentException) {
                    // PathInfo 结构非法时回退到旧逻辑
                }
            }
        } catch (Throwable) {
            // CtxEnum::URI 可能尚未初始化，直接回退
        }

        // 3. 回退：使用 path_info（兼容旧行为）
        if (empty($baseSegments)) {
            $pathInfo = $request->server['path_info'] ?? '';
            if ($pathInfo !== '' && $pathInfo !== '/') {
                $baseSegments = array_values(array_filter(explode('/', trim($pathInfo, '/')), 'strlen'));
            }
        }

        // 4. 去掉当前 action 段，替换为传入的 $url
        if (!empty($baseSegments)) {
            array_pop($baseSegments);
        }
        $baseSegments[] = StringConverter::camelCaseToUnderscore($url, '-');

        return '/' . implode('/', $baseSegments);
    }

    /**
     * 将参数数组编码为 PathInfo 形式并拼接到基础路径后
     *
     * 规则：
     *  - 仅对「字符串 key + 标量 value」的参数使用 PathInfo
     *  - 其它（数组、对象、数字下标等）保留在 query 中，避免语义不清
     */
    private static function buildPathInfoUrl(string $baseUrl, array $params): string
    {
        $basePath = rtrim($baseUrl, '/');
        if ($basePath === '') {
            $basePath = '/';
        }

        $pathInfoParams = [];
        $queryParams = [];

        foreach ($params as $key => $value) {
            // 只对 string key + 标量值 使用 PathInfo
            if (!is_string($key) || $key === '' || is_array($value) || is_object($value)) {
                $queryParams[$key] = $value;
                continue;
            }
            $pathInfoParams[$key] = $value;
        }

        // 先拼接 PathInfo 段
        foreach ($pathInfoParams as $key => $value) {
            $basePath .= '/' . $key . '/' . $value;
        }

        // 再拼接剩余需要通过 query 传递的参数（如数组等复杂结构）
        if (!empty($queryParams)) {
            $basePath = self::appendQueryParams($basePath, $queryParams);
        }

        return $basePath;
    }

    public static function isAdmin(string $uri): bool
    {
        // 后台的路由不记录
        $adminNamespace = ConfigEnum::get('ADMIN_NAMESPACE');
        $adminNamespace = StringConverter::camelCaseToUnderscore($adminNamespace, '-');
        return array_any(explode('\\', $adminNamespace), fn($value) => str_starts_with(ltrim($uri, '/'), $value));
    }

}