<?php
declare(strict_types=1);

namespace Swlib\Parse\Router\GenApi\Abstract;

use Swlib\Parse\Helper\ConsoleColor;
use Swlib\Parse\Router\GenApi\Helper\ApiGeneratorHelper;
use Swlib\Router\Router;
use Swlib\Utils\File;

/**
 * API 生成器抽象基类
 * 子类只需配置保存路径和模板字符串即可生成 API 调用方法
 */
abstract class AbstractApiGenerator
{

    /**
     * 收集的文件内容 [filePath => content]
     */
    protected array $fileContents = [];


    /**
     * 获取保存目录
     */
    abstract protected function getSaveDir(): string;

    /**
     * 获取文件扩展名
     */
    abstract protected function getFileExtension(): string;

    /**
     * 获取 HTTP 请求模板
     */
    abstract protected function getHttpTemplate(): string;

    /**
     * 获取 WebSocket 请求模板
     */
    abstract protected function getWebSocketTemplate(): string;

    /**
     * 获取文件头部内容
     * @param string $filePath 文件路径
     */
    abstract protected function getFileHeader(string $filePath): string;

    /**
     * 获取文件尾部内容
     */
    protected function getFileFooter(): string
    {
        return '';
    }

    /**
     * 处理单个路由项，返回模板变量替换数组
     * @param Router $item 路由项
     * @param string $requestType 请求类型 (http/websocket)
     * @return array|null 返回 [search => replace] 数组，返回 null 表示跳过该项
     */
    abstract protected function processRouterItem(Router $item, string $requestType): ?array;

    /**
     * 根据 URL 计算保存路径
     * @param string $url 路由 URL
     * @return string 文件保存路径
     */
    protected function getSavePath(string $url): string
    {
        $urlPath = str_replace('-', '_', dirname($url));
        return $this->getSaveDir() . $urlPath . '.' . $this->getFileExtension();
    }

    /**
     * 生成 API 文件
     * @param array $attributes 路由属性数组
     * @return bool 成功返回 true，失败返回 false
     */
    public function generate(array $attributes): bool
    {
        $this->fileContents = [];

        /** @var Router $item */
        foreach ($attributes as $item) {
            // 归一化 method
            $methods = ApiGeneratorHelper::normalizeMethod($item->method);

            // 判断请求类型
            $requestType = ApiGeneratorHelper::getRequestType($methods);

            // 检测 HTTP 和 WebSocket 同时存在的错误情况
            if ($requestType === ApiGeneratorHelper::REQUEST_TYPE_MIXED) {
                ConsoleColor::writeErrorHighlight(
                    sprintf(
                        "\n[严重错误] 路由 %s 同时包含 HTTP 和 WebSocket 方法，这是不允许的！\n" .
                        "HTTP 方法: get, post, put, delete, patch\n" .
                        "WebSocket 方法: ws, wss\n" .
                        "请修正路由配置后重试。\n",
                        $item->url
                    )
                );
                return false;
            }

            // 跳过 GET 请求（通常不需要生成 API）
            if (count($methods) === 1 && in_array('get', $methods, true)) {
                continue;
            }

            // 获取响应类型
            $response = $item->response;

            // WebSocket 使用广播消息作为响应
            if ($requestType === ApiGeneratorHelper::REQUEST_TYPE_WEBSOCKET) {
                $response = $item->broadcastMessage;
            }

            // API 请求没有返回值的不必生成 API
            if ((empty($response) || $response === 'void') && $requestType === ApiGeneratorHelper::REQUEST_TYPE_HTTP) {
                continue;
            }

            if (empty($response)) {
                continue;
            }

            // 只处理 Protobuf 返回类型
            if (!ApiGeneratorHelper::isProtobufType($response)) {
                continue;
            }

            // 处理路由项，获取模板变量
            $replacements = $this->processRouterItem($item, $requestType);
            if ($replacements === null) {
                continue;
            }

            // 计算保存路径
            $savePath = $this->getSavePath($item->url);

            // 初始化文件内容
            if (!isset($this->fileContents[$savePath])) {
                $this->fileContents[$savePath] = $this->getFileHeader($savePath);
            }

            // 选择模板
            $template = $requestType === ApiGeneratorHelper::REQUEST_TYPE_WEBSOCKET
                ? $this->getWebSocketTemplate()
                : $this->getHttpTemplate();

            // 替换模板变量
            $this->fileContents[$savePath] .= str_replace(
                array_keys($replacements),
                array_values($replacements),
                $template
            );
        }

        // 保存文件
        $this->saveFiles();

        return true;
    }

    /**
     * 保存所有生成的文件
     */
    protected function saveFiles(): void
    {
        $footer = $this->getFileFooter();

        foreach ($this->fileContents as $path => $content) {
            if (empty($content)) {
                continue;
            }

            // 添加文件尾部
            if (!empty($footer)) {
                $content .= $footer;
            }

            File::save($path, $content);
        }
    }
}

