<?php
declare(strict_types=1);

namespace Swlib\Parse;

use Generate\ConfigEnum;
use Swlib\Router\Router;
use Swlib\Utils\File;
use Swlib\Utils\StringConverter;


trait ParseRouterCreateApi
{

    /**
     * 创建 TS API 方法列表
     * 只生成 Protobuf 返回类型的 API
     * @param array $attributes
     * @return void
     */
    public function createTsApi(array $attributes): void
    {
        $str = 'import {Protobuf} from "@/proto/proto.js";' . PHP_EOL;
        $str .= 'import {Http} from "@/Lib/Http";' . PHP_EOL;
        $str .= 'import {Socket} from "@/Lib/Socket";' . PHP_EOL;
        $str .= 'import {Config} from "@/Config";' . PHP_EOL;

        $dbName = ConfigEnum::DB_DATABASE;
        if(is_array($dbName)){
            $dbName = $dbName[0];
        }
        $dbName = strtoupper($dbName);

        $apiTemplate = 'export const {$name} = (params: {$requestI},showErr:boolean = true): Promise<{$responseI}> => {
    return Http.callApi<{$responseI}>(`${Config.' . $dbName . '_URL}/{$url}`, params, showErr,\'{$title}\',{$request}, {$response}, {$cache});
}';

        $socketTemplate = '/**
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

        $dir = RUNTIME_DIR . "codes/tsApi/";
        $saveString = [];
        /** @var Router $item */
        foreach ($attributes as $item) {
            $response = $item->response;

            $method = strtolower($item->method);
            if ($method === 'get') {
                continue;
            }

            // API 请求 没有返回值的 不必生成API
            if ((empty($response) || $response == 'void') && $method == 'post') {
                continue;
            }

            if (in_array($method, ['ws', 'wss'])) {
                $response = $item->broadcastMessage;
            }

            if (empty($response)) {
                continue;
            }

            // 只处理 Protobuf 返回类型
            if (!$this->isProtobufTypeForTs($response)) {
                continue;
            }

            $response = str_replace('\\', '.', $response);
            $request = str_replace('\\', '.', $item->request);
            // 获取到 请求路径的 枚举名称
            $name = $this->getClassName($item->className, $item->methodName);
            $requestI = '';
            if ($request && $this->isProtobufTypeForTs($item->request)) {
                // 给最后一段字符串前加上I protobuf.User.PasswordRegister ==>  protobuf.User.IPasswordRegister
                $requestI = $this->appendI($request);
            }

            $responseI = '';
            if ($response) {
                // 给最后一段字符串前加上I protobuf.User.PasswordRegister ==>  protobuf.User.IPasswordRegister
                $responseI = $this->appendI($response);
            }

            $cacheTime = $item->cache ?: '';

            $url = str_replace('/api/', '/', $item->url);
            $savePath = $dir . dirname($url) . '.ts';
            if (!isset($saveString[$savePath])) {
                $saveString[$savePath] = $str;
            }


            if ($item->method && in_array(strtolower($item->method), ['ws', 'wss'])) {
                $template = $socketTemplate;
            } else {
                $template = $apiTemplate;
            }

            $saveString[$savePath] .= str_replace(
                    ['{$url}', '{$name}', '{$request}', '{$requestI}', '{$response}', '{$responseI}', '{$cache}', '{$title}', '{$desc}'],
                    [$item->url, $name, $request ?: 'null', $requestI ?: 'null', $response, $responseI, $cacheTime ?: '0', $item->errorTitle, str_replace('失败', '', $item->errorTitle)],
                    $template
                ) . "\n";


        }

        foreach ($saveString as $path => $string) {
            if (!$string) continue;
            File::save($path, $string);
        }
    }

    /**
     * 检查是否为 Protobuf 类型（TS 版本）
     * @param string $type
     * @return bool
     */
    private function isProtobufTypeForTs(string $type): bool
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
     * 给最后一段字符串前加上I
     * protobuf.User.PasswordRegister ==>  protobuf.User.IPasswordRegister
     * @param $originalClassName
     * @return string
     */
    public function appendI($originalClassName): string
    {
        $parts = explode('.', $originalClassName);
        $lastPart = end($parts);
        $modifiedLastPart = 'I' . $lastPart;
        return implode('.', array_slice($parts, 0, -1)) . '.' . $modifiedLastPart; // 输出: Protobuf.User.IPasswordRegister
    }


    /**
     * @param string $class
     * @param string $method
     * @return string
     */
    public function getClassName(string $class, string $method): string
    {
        // 去掉控制器入口目录
        $class = str_replace('App\Controller\\', '', $class);

        // 转换成数组
        $arr = explode("\\", $class);
        // 得到模块名称 App\Controller\Api 中的 Api
        $moduleName = $arr[0];

        // 得到子目录名称，或者文件名称
        // App\Controller\Api\User\UserApi.php
        // App\Controller\Api\UserApi.php
        // 这里得到 User 或者 UserApi
        $dirName = $arr[1];

        // 如果有这种 UserUser 重复的，就去掉一次  ApiUserUserGetInfo=>ApiUserGetInfo
        if (substr_count($class, $dirName) > 1) {
            $class = preg_replace("/" . preg_quote($dirName, '/') . "/", '', $class, 1);
        }

        // 去掉控制器后缀
        $class = str_replace([$moduleName, '\\'], '', $class);

        // 拼接
        $moduleName = StringConverter::underscoreToCamelCase($moduleName);
        $methodName = StringConverter::underscoreToCamelCase($method);
        return $moduleName . $class . $methodName;
    }


}
