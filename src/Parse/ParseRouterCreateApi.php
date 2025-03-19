<?php
declare(strict_types=1);

namespace Swlib\Parse;

use Swlib\Router\Router;
use Swlib\Utils\Func;


trait ParseRouterCreateApi
{


    public function createTsApi(array $attributes): void
    {

        $str = 'import {Protobuf} from "@/proto/proto.js";' . PHP_EOL;
        $str .= 'import {Http} from "@/Lib/Http";' . PHP_EOL;
        $template = 'export const {$name} = (params: {$requestI},showErr:boolean = true): Promise<{$responseI}> => {
    return Http.callApi<{$responseI}>("{$url}", params, showErr,\'{$title}\',{$request}, {$response}, {$cache});
}';
        $dir = RUNTIME_DIR . "codes/ts/apis/";
        $saveString = [];
        /** @var Router $item */
        foreach ($attributes as $item) {
            $response = str_replace('\\', '.', $item->response);
            $request = str_replace('\\', '.', $item->request);
            // 获取到 请求路径的 枚举名称
            $name = $this->getClassName($item->className, $item->methodName);
            $requestI = '';
            if ($request) {
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
            $saveString[$savePath] .= str_replace(
                    ['{$url}', '{$name}', '{$request}', '{$requestI}', '{$response}', '{$responseI}', '{$cache}', '{$title}', '{$desc}'],
                    [$item->url, $name, $request ?: 'null', $requestI ?: 'null', $response, $responseI, $cacheTime ?: '0', $item->errorTitle, str_replace('失败', '', $item->errorTitle)],
                    $template
                ) . "\n";


        }

        foreach ($saveString as $path => $string) {
            if (!$string) continue;
            $dirName = dirname($path);
            if (!is_dir($dirName)) {
                mkdir($dirName, 0777, true);
            }
            file_put_contents($path, $string);
        }
    }


    /**
     * 给最后一段字符串前加上I
     * protobuf.User.PasswordRegister ==>  protobuf.User.IPasswordRegister
     * @param $originalClassName
     * @return string
     */
    private function appendI($originalClassName): string
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
    private function getClassName(string $class, string $method): string
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
        $moduleName = Func::underscoreToCamelCase($moduleName);
        $methodName = Func::underscoreToCamelCase($method);
        return $moduleName . $class . $methodName;
    }


}