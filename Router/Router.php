<?php
declare(strict_types=1);

namespace Swlib\Router;

use Attribute;
use Exception;
use Generate\ConfigEnum;
use Generate\RouterPath;
use Google\Protobuf\Internal\Message;
use InvalidArgumentException;
use Redis;
use ReflectionException;
use Swlib\Connect\PoolRedis;
use Swlib\Controller\Abstract\AbstractController;
use Swlib\DataManager\ReflectionManager;
use Swlib\Exception\AppException;
use Swlib\Response\ProtobufResponse;
use Swlib\Response\ResponseInterface;
use Swlib\Utils\Log;
use Swoole\Http\Request;
use Throwable;


/**
 * @property string $className
 * @property string $methodName
 * @property string $url
 * @property string $request
 * @property string $response
 */
#[Attribute]
class Router
{

    private array $_data = [];

    /**
     * 构造函数执行用来定义路由用的
     *
     * @param string|array $method 请求类型，默认是所有类型，可以指定 GET POST PUT DELETE 等
     * @param string $url 请求路径，默认是自动根据目录生成
     * @param int $cache 缓存时间，单位秒，默认是0，不缓存
     * @param string $errorTitle 请求如果错误，提示标题, 定义了这个错误标题，前台才会生成对应的 API 调用方法
     * @param class-string<RouterMiddleware>|RouterMiddleware[] $middleware 路由中间件
     */
    public function __construct(
        // 允许请求的类型，  GET  POST   ["GET","POST"]  WS  WSS
        public string|array $method = ["GET", "POST"],

        // 用户访问的 URL ， 默认是自动根据目录生成
        public string       $url = '',

        // api 请求前台是否需要缓存数据,如果需要这里是缓存时间,后端并没有缓存，而是生成的前端API方法会缓存
        public int          $cache = 0,

        // 请求如果错误，提示标题, 定义了这个错误标题，前台才会生成对应的 API 调用方法
        public string       $errorTitle = '',

        // 路由中间件 className
        public string|array $middleware = '',

        // 广播出去的消息
        public mixed        $broadcastMessage = null
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
            $reflectionClass = ReflectionManager::getClass($routerConfigRequest);
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
        try {
            // 解码
            if (!empty($content) && $content !== '{}') {
                $req->mergeFromString($content);
            }
        } catch (Exception) {
            // 解析失败目前不做处理;
            // 在业务逻辑做具体的参数判断
            // 可能某些路由就是不需要参数的
        }

        $this->end($ctrl->$method($req));
    }

    /**
     * @throws Exception
     */
    private function end(mixed $res): void
    {
        if (empty($res)) {
            return;
        }
        // 处理返回值
        if ($res instanceof ResponseInterface) {
            $res->output();
        } elseif ($res instanceof Message) {
            ProtobufResponse::success($res)->output();
        } else {
            throw new AppException("返回类型 %s 不支持 需要返回 实现 ResponseInterface 接口的子类", gettype($res));
        }
    }

    public static function checkSign(Request $request): bool
    {
        $url = $request->header['url'] ?? '';
        $random = $request->header['random'] ?? '';
        $time = $request->header['time'] ?? '';
        $token = $request->header['token'] ?? '';

        // 1. 检查必填参数
        if (empty($random) || empty($token) || empty($time)) {
            Log::error('签名参数缺失', [
                'random' => $random,
                'token' => $token,
                'time' => $time,
                'url' => $url,
            ], 'sign_error');
            return false;
        }

        // 2. 时间校验：前后不能超过 5 秒
        // 5 秒是合理的时间窗口，考虑到：
        // - 网络延迟（1-2秒）
        // - 客户端与服务器时间差（1-2秒）
        // - 请求处理时间（1秒）
        $currentTime = time();
        $timeDiff = abs($currentTime - (int)$time);
        if ($timeDiff > 5) {
            Log::error('请求时间戳超出允许范围', [
                'client_time' => $time,
                'server_time' => $currentTime,
                'diff' => $timeDiff,
                'url' => $url,
            ], 'sign_error');
            return false;
        }

        // 3. 验证签名
        $myToken = md5("$url.$random.$time");
        if ($myToken !== $token) {
            Log::error('签名验证失败', [
                'random' => $random,
                'token' => $token,
                'expected_token' => $myToken,
                'time' => $time,
                'url' => $url,
            ], 'sign_error');
            return false;
        }

        // 4. 防重放攻击：检查 token 是否已使用过
        // 使用 Redis 存储已使用的 token，过期时间为 10 秒（时间窗口的 2 倍）
        try {
            $redisKey = "api_token:$token";
            $exists = PoolRedis::call(function (Redis $redis) use ($redisKey) {
                // 使用 SET NX（不存在才设置）来实现原子性检查和设置
                // 返回 true 表示设置成功（token 未使用过）
                // 返回 false 表示 key 已存在（token 已使用过）
                return $redis->set($redisKey, '1', ['NX', 'EX' => 10]);
            });

            if (!$exists) {
                Log::error('检测到重放攻击', [
                    'token' => $token,
                    'url' => $url,
                    'time' => $time,
                ], 'replay_attack');
                return false;
            }
        } catch (Throwable $e) {
            // Redis 异常不应该阻止正常请求，记录日志后继续
            Log::error('Redis 防重放检查失败', [
                'error' => $e->getMessage(),
                'token' => $token,
                'url' => $url,
            ], 'redis_error');
            // 继续执行，不因为 Redis 故障而拒绝请求
        }

        return true;
    }


    /**
     * 仅获取路由配置（兼容旧调用）
     */
    public static function get(string $path): ?array
    {
        if (str_starts_with($path, '/')) {
            return RouterPath::PATHS[$path] ?? null;
        }
        return RouterPath::PATHS['/' . $path] ?? null;
    }

    /**
     * 解析路由：返回 [路由配置, 基础 URI, PathInfo 参数数组]
     *
     * @param string $path
     * @return array{0: ?array, 1: ?string, 2: array<string, string>}
     */
    public static function parse(string $path): array
    {
        // 统一路径格式：确保以 '/' 开头
        $normalizedPath = '/' . ltrim($path, '/');

        // 先匹配基础路由，再解析 PathInfo
        [$routeConfig, $baseUri, $pathInfoSegments] = self::matchBaseRoute($normalizedPath);
        if ($routeConfig === null) {
            // 路由不存在，直接返回，PathInfo 不再解析
            return [null, $baseUri, []];
        }

        $pathInfo = self::parsePathInfo($pathInfoSegments);

        return [$routeConfig, $baseUri, $pathInfo];
    }


    /**
     * 匹配基础路由，返回 [路由配置, 基础 URI(不带前导 /), PathInfo 残余段]
     *
     * @param string $normalizedPath 以 '/' 开头的路径
     * @return array{0: ?array, 1: ?string, 2: array<int, string>}
     */
    private static function matchBaseRoute(string $normalizedPath): array
    {
        // 1. 先尝试完整匹配（包括根路径 '/'），命中则无需再做数组分割
        // 注意：这里只移除前导 '/'，不能使用 trim($normalizedPath, '/') 去掉末尾 '/'。
        // 否则像 /foo/id/ 这类以 '/' 结尾但 value 为空的 PathInfo 会在 explode 前丢失最后一个空段，
        // 导致 parsePathInfo 收到奇数个段并抛出 422 PathInfo 参数错误。
        $trimmed = ltrim($normalizedPath, '/');
        if (isset(RouterPath::PATHS[$normalizedPath])) {
            // 与 OnRequestEvent 中使用的 $uri 风格保持一致：不带前导 '/'
            $baseUri = $trimmed; // '/' 情况下为空字符串
            return [RouterPath::PATHS[$normalizedPath], $baseUri, []];
        }

        // 2. 根路径但未定义路由
        if ($trimmed === '') {
            return [null, '', []];
        }

        // 3. 从右往左裁剪，寻找最长匹配的基础路由
        $segments = explode('/', $trimmed);
        $segmentCount = count($segments);

        for ($len = $segmentCount; $len >= 1; $len--) {
            $candidateSegments = array_slice($segments, 0, $len);
            $candidate = '/' . implode('/', $candidateSegments);
            if (isset(RouterPath::PATHS[$candidate])) {
                $routeConfig = RouterPath::PATHS[$candidate];
                $baseUri = implode('/', $candidateSegments);
                $pathInfoSegments = array_slice($segments, $len);

                // 如果仅剩一个空字符串，说明请求只是多了一个结尾 '/'，不应视为 PathInfo
                // 否则会在后续 PathInfo 解析中被当作不成对的参数触发 422 错误
                if (count($pathInfoSegments) === 1 && $pathInfoSegments[0] === '') {
                    $pathInfoSegments = [];
                }

                return [$routeConfig, $baseUri, $pathInfoSegments];
            }
        }

        // 4. 未找到匹配路由
        return [null, null, []];
    }

    /**
     * 将 PathInfo 残余段解析为键值对数组
     *
     * 注意：会自动对 key 和 value 进行 URL 解码，以支持中文等特殊字符
     *
     * @param array<int, string> $pathInfoSegments
     * @return array<string, string>
     */
    private static function parsePathInfo(array $pathInfoSegments): array
    {
        $pathInfo = [];
        $pathInfoCount = count($pathInfoSegments);
        if ($pathInfoCount === 0) {
            return $pathInfo;
        }

        // 必须是成对的 key/value
        if ($pathInfoCount % 2 !== 0) {
            $error = 'PathInfo 参数必须成对出现';
            if (ConfigEnum::APP_PROD === false) {
                $error .= ' ' . implode('/', $pathInfoSegments);
            }
            throw new InvalidArgumentException($error);
        }

        for ($i = 0; $i < $pathInfoCount; $i += 2) {
            // URL 解码 key 和 value，支持中文等特殊字符
            $key = urldecode($pathInfoSegments[$i]);
            $value = urldecode($pathInfoSegments[$i + 1]);

            if ($key === '') {
                throw new InvalidArgumentException('PathInfo 参数名不能为空');
            }

            if (array_key_exists($key, $pathInfo)) {
                throw new InvalidArgumentException('PathInfo 参数重复: ' . $key);
            }

            $pathInfo[$key] = $value;
        }

        return $pathInfo;
    }

}