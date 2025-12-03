<?php
declare(strict_types=1);

namespace Swlib\Enum;

use Swlib\Coroutine\CoroutineContext;
use Swoole\Coroutine;

/**
 * CtxEnum 枚举类，用于定义在协程上下文中使用的键值对
 * 每个枚举值代表一个特定的上下文键，可以通过 set, get, del 等方法操作对应的值
 */
enum CtxEnum: string
{
    // 当前请求使用的语言
    case Lang = "lang";

    // 工作进程ID
    case WorkerId = "workerId";

    // 请求对象
    case Request = "request";

    // 响应对象
    case Response = "response";

    // 文件描述符
    case Fd = "fd";

    // 服务器对象
    case Server = "server";

    // 请求ID
    case RequestId = "request-id";

    // 当前请求的 uri
    case URI = "request-uri";

    // 事务数据库连接
    case TransactionDbh = "transaction-dbh";

    // 事务数据库名（用于事务内跨库检测）
    case TransactionDbName = "transaction-db-name";
    // 是否开启事务日志
    case EnableTransactionLog = "enable-transaction-log";

    /**
     * 本次协程的数据
     * 配合 getData 和 setData 方法调用
     */
    case Data = "ctx-data";

    /**
     * 设置当前协程上下文中对应键的值
     * @param mixed $value 要设置的值
     * @return mixed 获取到的值或回调函数返回的值
     */
    public function set(mixed $value): mixed
    {
        $context = Coroutine::getContext();
        $context[$this->name] = $value;
        return $value;
    }

    /**
     * 删除当前协程上下文中对应键的值
     */
    public function del(): void
    {
        $context = Coroutine::getContext();
        unset($context[$this->name]);
    }

    /**
     * 获取当前协程上下文中对应键的值
     *
     * @param mixed|null $default 如果键不存在时返回的默认值
     * @return mixed 获取到的值或默认值
     */
    public function get(mixed $default = null): mixed
    {
        $context = Coroutine::getContext();
        if (isset($context[$this->name]) && $context[$this->name] !== null) {
            return $context[$this->name];
        }
        return $default;
    }

    /**
     * 获取当前协程上下文中对应键的值，如果不存在则调用回调函数设置并返回
     *
     * @param callable $callable 回调函数，用于设置默认值
     * @return mixed 获取到的值或回调函数返回的值
     */
    public function getSet(callable $callable): mixed
    {
        $ret = $this->get();
        if ($ret !== null) {
            return $ret;
        } else {
            return $this->set($callable());
        }
    }

    /**
     * 设置当前协程上下文中 Data 键的子键值
     *
     * @param string $key 子键名
     * @param mixed $value 要设置的值
     */
    public function setData(string $key, mixed $value): mixed
    {
        $data = $this->get([]);
        $data[$key] = $value;
        $this->set($data);
        return $value;
    }

    /**
     * 获取当前协程上下文中 Data 键的子键值
     *
     * @param string $key 子键名
     * @param mixed $default 如果子键不存在时返回的默认值
     * @return mixed 获取到的值或默认值
     */
    public function getData(string $key, mixed $default = null): mixed
    {
        $data = $this->get([]);
        return $data[$key] ?? $default;
    }

    public function getSetData(string $key, callable $callable)
    {
        $ret = $this->getData($key);
        if ($ret !== null) {
            return $ret;
        } else {
            $value = $callable();
            $this->setData($key, $value);
            return $value;
        }
    }

}
