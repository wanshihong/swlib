<?php
declare(strict_types=1);

namespace Swlib\Parse\Router;

use Generate\RouterPath;
use mysqli;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;
use Swlib\Connect\PoolMysql;
use Swlib\Controller\AbstractController;
use Swlib\Parse\Helper\ConsoleColor;
use Swlib\Router\Router;
use Swlib\Router\RouterMiddleware;
use Swlib\Utils\File;
use Swlib\Utils\StringConverter;
use Throwable;
use function Swoole\Coroutine\parallel;


trait ParseRouterRouter
{


    /**
     * 保存路由到数据库
     * @return void
     * @throws Throwable
     */
    private function saveRouter(): void
    {
        $dbRouters = PoolMysql::query("select uri from router")->fetch_all(MYSQLI_ASSOC);
        $dbRouters = array_column($dbRouters, 'uri');

        $routers = array_keys(RouterPath::PATHS);

        parallel(64, function () use (&$routers, $dbRouters) {
            while ($route = array_shift($routers)) {
                // 如果路由已经在缓存中，跳过
                if (in_array($route, $dbRouters)) {
                    continue;
                }
                PoolMysql::query("insert into router (`uri`) values ('$route')");
            }
        });
    }

    /**
     * 清理多余的路由
     * @throws Throwable
     */
    private function cleanRouterProcess(): void
    {
        $routers = PoolMysql::query("select * from router");
        parallel(64, function () use ($routers) {
            while ($router = $routers->fetch_assoc()) {
                if (!array_key_exists($router['uri'], RouterPath::PATHS)) {
                    PoolMysql::call(function ($mysqli) use ($router) {
                        /** @var mysqli $mysqli */
                        $res = $mysqli->execute_query("delete from router where uri= ?", [$router['uri']]);
                        if ($res) {
                            ConsoleColor::writeInfo("路由删除成功: {$router['uri']}");
                        } else {
                            ConsoleColor::writeWarning("路由删除失败: {$router['uri']}");
                        }
                    });
                }
            }
        });
    }


    private function isMagicMethod(ReflectionMethod $method): bool
    {
        $magicMethods = [
            '__construct',
            '__destruct',
            '__call',
            '__callStatic',
            '__get',
            '__set',
            '__isset',
            '__unset',
            '__sleep',
            '__wakeup',
            '__toString',
            '__invoke',
            '__set_state',
            '__clone',
            '__debugInfo'
        ];

        return in_array($method->getName(), $magicMethods);
    }

    /**
     * 创建基于浏览器地址访问的路由
     * @throws ReflectionException
     */
    private function createByPathRouter(array $files): array
    {
        $attributes = [];

        $mapStr = "";
        $constStr = "";
        $urls = [];
        foreach ($files as $file) {
            $class = new ReflectionClass($file);

            // 不是控制器
            if (!$class->isSubclassOf(AbstractController::class)) {
                continue;
            }

            $tempClass = $class;
            // 获取路由注解，这里是类的路由注解，方法的路由注解会覆盖类的注解
            while (true) {
                $classAttributes = $tempClass->getAttributes(Router::class);
                if ($classAttributes) {
                    break;
                }
                // 是否有继承
                $parent = $tempClass->getParentClass();
                if (!$parent) {
                    break;
                }
                $tempClass = $parent;
            }


            /**@var Router|null $classAttribute */
            $classAttribute = null;
            if ($classAttributes) {
                $classAttribute = $classAttributes[0]->newInstance();
            }


            // 获取 public 方法
            $methods = $class->getMethods(ReflectionMethod::IS_PUBLIC);
            foreach ($methods as $method) {
                // 如果是构造方法
                if ($method->name == '__construct') continue;
                // 如果不是 public 方法
                if ($method->isPublic() === false) continue;
                if ($this->isMagicMethod($method)) continue;


                // 获取方法的注解，方法的注解会覆盖类的注解
                $methodAttributes = $method->getAttributes(Router::class, ReflectionAttribute::IS_INSTANCEOF);
                $isMethodLevel = !empty($methodAttributes);
                if (!$isMethodLevel) {
                    // 如果方法没有 Router 注解，检查类是否有 Router 注解
                    if (!$classAttribute) {
                        continue;  // 类和方法都没有 Router 注解，跳过
                    }
                    // 使用类级别的 Router 注解
                    $attribute = clone $classAttribute;
                } else {
                    /** @var Router $attribute */
                    $attribute = $methodAttributes[0]->newInstance();
                }


                // 路由的文件地址
                $urlPath = $this->getUrlPath($file, $method->name);
                $urlPathForConst = $urlPath;

                // 用户访问的 URL
                if ($isMethodLevel) {
                    $url = $attribute->url ?: $this->formatUrlPath($urlPath);
                } else {
                    $baseUrl = trim($attribute->url ?: '', '/');
                    if ($baseUrl !== '') {
                        $url = $baseUrl . '/' . $this->formatUrlPath($method->name);
                        $urlPathForConst = $this->getUrlPath($file, '') . '/' . $method->name;
                    } else {
                        $classUrlPath = $this->getUrlPath($file, '');
                        $composedPath = trim($classUrlPath . '/' . $method->name, '/');
                        $url = $this->formatUrlPath($composedPath);
                        $urlPathForConst = $composedPath;
                    }
                }
                $url = ltrim($url, '/');

                if (in_array($url, $urls)) {
                    ConsoleColor::writeErrorHighlight("路由地址重复：$url  on file: $file->$method->name" . PHP_EOL);
                    exit(1);
                }
                $urls[] = $url;

                // 允许请求的方法
                $allowMethod = $attribute->method ?: $classAttribute?->method;
                // 没有允许访问的方法，不生成路由
                if (empty($allowMethod)) {
                    continue;
                }


                // 获取请求参数
                $request = '';
                $paramRequests = $method->getParameters();
                if ($paramRequests) {
                    $paramRequest = $paramRequests[0];
                    $request = $paramRequest->getType()?->getName();
                    if (empty($request)) {
                        $request = '';
                    }
                }


                // 做好记录，后面用于生成 TS API 供前台调用
                // 是 单一 的返回类型，不是联合返回类型
                if ($method->getReturnType() instanceof ReflectionNamedType && $attribute->errorTitle) {
                    $response = $method->getReturnType()->getName();
                    $attribute->className = $method->class;
                    $attribute->methodName = $method->name;
                    $attribute->url = $url;// 用户访问的地址可能没有设置，所以要记录
                    $attribute->request = $request;
                    $attribute->response = $response;
                    $attributes[] = $attribute;
                }

                // api 请求前台是否需要缓存数据,如果需要这里是缓存时间
                $cache = $attribute->cache ?: ($classAttribute?->cache ?: 0);
                // 中间件
                $middleware = $attribute->middleware ?: ($classAttribute?->middleware ?: []);


                // 验证中间件是否定义正确
                if ($middleware) {
                    if (is_string($middleware)) {
                        $middleware = [$middleware];
                    }
                    foreach ($middleware as $mid) {
                        $middlewareTemp = new $mid();
                        if (!$middlewareTemp instanceof RouterMiddleware) {
                            ConsoleColor::writeErrorHighlight("$file 文件中， $method->name 方法的中间件定义错误：$mid ,应为 RouterMiddleware 的子类" . PHP_EOL);
                            exit(1);
                        }
                    }

                }


                $constKey = StringConverter::underscoreToCamelCase($urlPathForConst, '/');
                $constStr .= "    const string $constKey = '/$url';" . PHP_EOL;
                $mapStr .= $this->generateItem($constKey, $file, $method->name, $request, $cache, $allowMethod, $middleware);
            }
        }

        $saveDir = RUNTIME_DIR . "Generate/";
        File::save($saveDir . "RouterPath.php", $this->_gen($constStr, $mapStr));

        return $attributes;


    }


    private function generateItem(
        string       $constKey = '',// 用户访问的 URL
        string       $class = '', // 执行的控制器
        string       $method = '',// 执行的控制器方法
        string       $request = '',// 请求的 protobuf message 名称
        int          $cache = 0,// api 请求前台是否需要缓存数据,如果需要这里是缓存时间
        string|array $allowMethod = '',// 允许请求的方法
        array        $middleware = []// 中间件
    ): string
    {
        $str = "        self::$constKey => [";
        $str .= "'run' => ['$class', '$method'],";

        if ($request) {
            $str .= "'request' => '$request',";
        }
        if ($cache) {
            $str .= "'cache' => $cache,";
        }
        if ($middleware) {
            $t = implode("','", $middleware);
            $str .= "'middleware' => ['$t'],";
        }


        if ($allowMethod) {
            if (is_string($allowMethod)) {
                $allowMethod = [$allowMethod];
            }
            $allowMethod = array_map(function ($item) {
                return strtoupper($item);
            }, $allowMethod);
            $tempStr = trim(implode("','", $allowMethod), '\'');
            $str .= "'method' => ['$tempStr'],";
        }

        $str = rtrim($str, ',');
        $str .= "]," . PHP_EOL;

        return $str;
    }

    private function _gen(string $const, string $paths): string
    {
        return <<<STR
<?php

namespace Generate;


class RouterPath
{

$const

    const array PATHS = [
        $paths
    ];
}
STR;

    }

    private function getUrlPath(string $file, string $methodName): string
    {
        $urlPathArr = explode('\\', $file);

        // 如果文件名称后缀是某个目录名称，则删除文件后缀
        // App\Tools\Image\Ctrl\ImageCtrl  ->  App\Tools\Image\Ctrl\Image
        // App\Tools\Image\Ctrl\ImageTools ->  App\Tools\Image\Ctrl\Image
        // App\Tools\Image\Ctrl\ImageApp   ->  App\Tools\Image\Ctrl\Image
        // 转换成
        //

        foreach ($urlPathArr as $key => $value) {
            if ($key === 0) continue;
            for ($i = $key - 1; $i >= 0; $i--) {
                $dir = $urlPathArr[$i];
                if (str_ends_with($value, $dir)) {
                    $urlPathArr[$key] = substr($value, 0, -strlen($dir));
                }
            }
        }


        // 去掉 应用目录和 控制器目录
        foreach ($urlPathArr as $key => $value) {
            if (in_array(strtolower($value), ['app', 'ctrl', 'controller'])) {
                unset($urlPathArr[$key]);
            }
        }

        // 添加上方法
        $urlPathArr[] = $methodName;

        // 数组去重
        $urlPathArr = array_unique(array_filter($urlPathArr));

        return implode('/', $urlPathArr);

    }

    /**
     * 对这种 "Admin/Login/changePasswordAction" 字符串转小写，
     * 并且对驼峰的前面加中横线
     * 输出结果为 admin/login/change-password-action
     * @param $input
     * @return string
     */
    public function formatUrlPath($input): string
    {
        // 使用正则表达式分割字符串
        $parts = explode('/', $input);

        // 初始化结果字符串
        $result = '';

        foreach ($parts as $part) {
            // 处理每个部分
            $formattedPart = '';
            $isFirstChar = true;

            for ($i = 0; $i < strlen($part); $i++) {
                $char = $part[$i];

                if (ctype_upper($char)) {
                    if (!$isFirstChar) {
                        $formattedPart .= '-';
                    }
                    $formattedPart .= strtolower($char);
                } else {
                    $formattedPart .= $char;
                }
                $isFirstChar = false;
            }

            // 检查是否是首部分
            if ($result !== '') {
                $result .= '/';
            }

            // 添加处理后的部分
            $result .= $formattedPart;
        }

        return $result;
    }


}
