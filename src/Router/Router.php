<?php
declare(strict_types=1);

namespace Swlib\Router;

use Attribute;
use Exception;
use Generate\RouterPath;
use Google\Protobuf\Internal\Message;
use Swlib\Controller\AbstractController;
use Swlib\Exception\AppException;
use Swlib\Response\ProtobufResponse;
use Swlib\Response\ResponseInterface;
use ReflectionClass;
use ReflectionException;
use Throwable;


/**
 * @property string $className
 * @property string $methodName
 * @property string $url
 * @property string $request
 * @property string $response
 */
#[Attribute] class Router
{

    private array $_data = [];

    /**
     * 构造函数执行用来定义路由用的
     *
     * @param string|array $method 请求类型，默认是所有类型，可以指定 GET POST PUT DELETE 等
     * @param string $url 请求路径，默认是自动根据目录生成
     * @param int $cache 缓存时间，单位秒，默认是0，不缓存
     * @param string $message 是否通过websocket广播 protobuf 消息
     * @param string $errorTitle 请求如果错误，提示标题, 定义了这个错误标题，前台才会生成对应的 API 调用方法
     * @param class-string<RouterMiddleware>|RouterMiddleware[] $middleware 路由中间件
     */
    public function __construct(
        // 允许请求的类型，  GET  POST   ["GET","POST"]  WS  WSS
        public string|array $method = '',

        // 用户访问的 URL ， 默认是自动根据目录生成
        public string       $url = '',

        // api 请求前台是否需要缓存数据,如果需要这里是缓存时间
        public int          $cache = 0,

        // 是否通过websocket广播 protobuf 消息
        public string       $message = '',

        // 请求如果错误，提示标题, 定义了这个错误标题，前台才会生成对应的 API 调用方法
        public string       $errorTitle = '',

        // 路由中间件 className
        public string|array $middleware = ''
    )
    {
    }


    public function __get(string $name)
    {
        return $this->_data[$name] ?? null;
    }

    public function __set(string $name, $value): void
    {
        $this->_data[$name] = $value;
    }

    /**
     * 执行路由
     * @throws Throwable
     */
    public function run(mixed $content, array $routerConfig): void
    {
        // 是否有中间件
        if (isset($routerConfig['middleware'])) {
            foreach ($routerConfig['middleware'] as $routerMiddleware) {
                /** @var RouterMiddleware $middleware */
                $middleware = new $routerMiddleware();
                /**
                 *  返回 true 执行以后的逻辑
                 *  返回 ResponseInterface 路由执行结束，返回数据给前台
                 *  抛出异常，路由执行结束,路由执行会捕获异常，返回  error 错误给前台
                 */
                $res = $middleware->handle();
                if ($res !== true) {
                    // 路由执行结束
                    $this->end($res);
                    return;
                }
            }
        }

        list($ctrlPath, $method) = $routerConfig['run'];

        /**@var AbstractController $ctrl */
        $ctrl = new $ctrlPath();
        $routerConfigRequest = $routerConfig['request'] ?? '';
        if (empty($routerConfigRequest)) {
            $this->end($ctrl->$method($content));
            return;
        }

        try {
            // 判断请求是否是 protobuf
            $reflectionClass = new ReflectionClass($routerConfigRequest);
            if (!$reflectionClass->isSubclassOf(Message::class)) {
                // 不是 protobuf 直接执行
                $this->end($ctrl->$method($content));
                return;
            }
        } catch (ReflectionException) {
            // 通过反射判断是否是 protobuf 消息体，出错了
            // 不是 protobuf 直接执行
            $this->end($ctrl->$method($content));
            return;
        }

        // 实例化 protobuf
        $req = new $routerConfigRequest();
        // 解码
        $req->mergeFromString($content);

        $this->end($ctrl->$method($req));
    }

    /**
     * @throws Exception
     */
    private function end(mixed $res): void
    {
        // 处理返回值
        if ($res instanceof ResponseInterface) {
            $res->output();
        } elseif ($res instanceof Message) {
            ProtobufResponse::success($res)->output();
        } else {
            throw new AppException("返回类型 %s 不支持 需要返回 实现 ResponseInterface 接口的子类", gettype($res));
        }
    }


    public static function checkSign(string $random, string $token, float|int $time, string $uri): bool
    {
        $myToken = md5("$uri.$random.$time");
        return $myToken === $token;
    }


    public static function get(string $path): ?array
    {
        if(str_starts_with($path, '/')){
            return RouterPath::PATHS[$path] ?? null;
        }
        return RouterPath::PATHS['/' . $path] ?? null;
    }

}