<?php
declare(strict_types=1);

namespace Swlib\Parse\Router\GenApi;

use Swlib\Parse\Router\GenApi\Abstract\AbstractApiGenerator;
use Swlib\Parse\Router\GenApi\Helper\ApiGeneratorHelper;
use Swlib\Router\Router;

/**
 * TypeScript API 生成器
 */
class TsApiGenerator extends AbstractApiGenerator
{
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
        return 'export const {$name} = (params: {$requestI},showErr:boolean = true): Promise<{$responseI}> => {
    return Http.callApi<{$responseI}>(`${Config.' . $this->dbNameUpper . '_URL}/{$url}`, params, showErr,\'{$title}\',{$request}, {$response}, {$cache});
}
';
    }

    protected function getWebSocketTemplate(): string
    {
        return '/**
 * 绑定 socket 事件
 * 实例 {$name}SocketOn(true, this.callback.bind(this));
 * @param showErr 
 * @param callback 
 */
 export const {$name}SocketOn = (showErr:boolean = true, callback: (res: {$responseI}) => void) => {
    Socket.getInstance().on({        
        url: "{$url}",
        responseProtoBuf: {$response},
        showError: showErr,
        errorMessage: \'{$title}\',
        callback: callback,
    })
}

/**
 * 取消绑定 socket 事件
 * 实例 {$name}SocketOff(this.callback.bind(this));
 * @param callback 
 */
export const {$name}SocketOff = (callback: (res: {$responseI}) => void) => {
    Socket.getInstance().off("{$url}",callback);
}

export const {$name}SocketSend = (params: {$requestI}) => {
    Socket.getInstance().send({
        url: "{$url}",
        params: params,
        requestProtoBuf: {$request}
    })
}
';
    }

    protected function getFileHeader(string $filePath): string
    {
        return 'import {Protobuf} from "@/proto/proto.js";' . PHP_EOL
            . 'import {Http} from "@/Lib/Http";' . PHP_EOL
            . 'import {Socket} from "@/Lib/Socket";' . PHP_EOL
            . 'import {Config} from "@/Config";' . PHP_EOL;
    }

    protected function getSavePath(string $url): string
    {
        // TS API 去掉 /api/ 前缀
        $url = str_replace('/api/', '/', $url);
        return $this->getSaveDir() . dirname($url) . '.' . $this->getFileExtension();
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
}

