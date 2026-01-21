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
 * TypeScript API 生成器
 * 生成完整的 API 调用代码，包括 Http.ts、Crypto.ts 等通用库
 */
class TsApiGenerator extends AbstractApiGenerator
{
    /**
     * 模板目录路径
     */
    private const string TEMPLATE_DIR = __DIR__ . '/Templates/ts/';

    protected function getSaveDir(): string
    {
        return RUNTIME_DIR . "codes/tsApi/";
    }

    protected function getFileExtension(): string
    {
        return 'ts';
    }

    protected function getHttpTemplate(): string
    {
        return 'export const {$name} = (params: {$requestI}, showErr: boolean = true): Promise<{$responseI}> =>
    Http.callApi<{$responseI}>(
        `${Config.API_URL}/{$url}`,
        params, showErr, \'{$title}\',
        {$request}, {$response}, {$cache}
    );

';
    }

    protected function getWebSocketTemplate(): string
    {
        return '/**
 * 绑定 socket 事件
 * @param showErr 是否显示错误
 * @param callback 回调函数
 */
export const {$name}SocketOn = (showErr: boolean = true, callback: (res: {$responseI}) => void) => {
    Socket.getInstance().on({
        url: "{$url}",
        responseProtoBuf: {$response},
        showError: showErr,
        errorMessage: \'{$title}\',
        callback: callback,
    });
};

/**
 * 取消绑定 socket 事件
 * @param callback 回调函数
 */
export const {$name}SocketOff = (callback: (res: {$responseI}) => void) => {
    Socket.getInstance().off("{$url}", callback);
};

/**
 * 发送 socket 消息
 * @param params 请求参数
 */
export const {$name}SocketSend = (params: {$requestI}) => {
    Socket.getInstance().send({
        url: "{$url}",
        params: params,
        requestProtoBuf: {$request}
    });
};

';
    }

    protected function getFileHeader(string $filePath): string
    {
        return 'import { Protobuf } from "@/proto/proto.js";' . PHP_EOL
            . 'import { Http } from "./Lib/Http";' . PHP_EOL
            . 'import { Socket } from "./Lib/Socket";' . PHP_EOL
            . 'import { Config } from "./Config";' . PHP_EOL . PHP_EOL;
    }

    /**
     * 合并所有 API 到单个文件
     */
    protected function getSavePath(string $url): string
    {
        return $this->getSaveDir() . 'Api.' . $this->getFileExtension();
    }

    protected function processRouterItem(Router $item, string $requestType): ?array
    {
        $response = $requestType === ApiGeneratorHelper::REQUEST_TYPE_WEBSOCKET
            ? $item->broadcastMessage
            : $item->response;

        // 转换命名空间分隔符
        $response = str_replace('\\', '.', $response);
        $request = str_replace('\\', '.', $item->request);

        // 获取方法名称
        $name = ApiGeneratorHelper::getApiMethodName($item->className, $item->methodName);

        // 生成接口类型（加 I 前缀）
        $requestI = '';
        if ($request && ApiGeneratorHelper::isProtobufType($item->request)) {
            $requestI = ApiGeneratorHelper::appendInterfacePrefix($request);
        }

        $responseI = '';
        if ($response) {
            $responseI = ApiGeneratorHelper::appendInterfacePrefix($response);
        }

        $cacheTime = $item->cache ?: '';

        return [
            '{$url}' => $item->url,
            '{$name}' => $name,
            '{$request}' => $request ?: 'null',
            '{$requestI}' => $requestI ?: 'null',
            '{$response}' => $response,
            '{$responseI}' => $responseI,
            '{$cache}' => $cacheTime ?: '0',
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

        // 只删除根目录下的 .ts 文件，保留子目录
        $files = glob($dir . '*.ts');
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
     * 生成 Config.ts 配置文件
     */
    private function generateConfig(): void
    {
        $templateFile = self::TEMPLATE_DIR . 'Config.ts.tpl';

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

        File::save($this->getSaveDir() . 'Config.ts', $content);
    }
}
