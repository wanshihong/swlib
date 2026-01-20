<?php
declare(strict_types=1);

namespace Swlib\Queue;

use Generate\ConfigEnum;
use Generate\Tables\Main\MessageQueueTable;
use Swlib\Connect\PoolRedis;
use Swlib\Exception\AppException;
use Swlib\Lock\RedisLock;
use Swlib\Proxy\ProxyDispatcher;
use Swlib\Table\Db;
use Swlib\Utils\Log;
use Redis;
use Swoole\Timer;
use Throwable;

/**
 * 延迟消息队列
 * 主要是解决延迟执行的任务
 * 需要立即执行的任务 可以用 task 进程直接执行就可以了
 */
class MessageQueue
{

    const string MESSAGE_LOCK = 'message_queue_lock';

    const string MIN_LAST_RUN_TIME = 'min_last_run_time';

    private static array $runTimer = [];


    /**
     * 通过代理调度器入队（由 ProxyDispatcher 调用）
     *
     * @param array $data 代理数据
     *   - class: 类名
     *   - method: 方法名（带 __proxy 后缀）
     *   - arguments: 参数数组
     *   - config: 队列配置
     *   - transaction?: 事务配置（如有）
     * @return int 队列消息ID
     * @throws Throwable
     */
    public static function pushProxy(array $data): int
    {
        // 从 config 中获取队列配置
        $config = $data['config'] ?? [];
        $delay = $config['arguments']['delay'] ?? 0;
        $maxRetry = $config['arguments']['maxRetry'] ?? 3;
        $retryIntervals = $config['arguments']['retryIntervals'] ?? [60, 300, 900];
        $clear = $config['arguments']['clear'] ?? false;

        // 生成消息唯一标识
        $key = md5($data['class'] . '::' . $data['method'] . json_encode($data['arguments']));

        if ($clear) {
            new MessageQueueTable()
                ->addWhere(MessageQueueTable::MSG_KEY, $key)
                ->update([
                    MessageQueueTable::IS_DISCARD => 1,
                ]);
        }

        // 存储完整的代理数据，包括事务配置
        $id = new MessageQueueTable()->insert([
            MessageQueueTable::NEXT_RUN_TIME => time() + $delay,
            MessageQueueTable::MAX_NUM => $maxRetry,
            MessageQueueTable::DELAY_TIMES => json_encode($retryIntervals, JSON_UNESCAPED_UNICODE),
            MessageQueueTable::DATA => json_encode($data, JSON_UNESCAPED_UNICODE),
            MessageQueueTable::CONSUMER => ConfigEnum::SERVER_ID,
            MessageQueueTable::MSG_KEY => $key,
        ]);

        if (empty($id)) {
            throw new AppException("写入消息队列失败");
        }
        self::unLock();
        return $id;
    }


    /**
     * @throws Throwable
     */
    public static function run(): void
    {

        self::lock(function () {
            $lists = new MessageQueueTable()->where([
                [MessageQueueTable::IS_DISCARD, '=', 0],
                [MessageQueueTable::IS_SUCCESS, '=', 0],
                [MessageQueueTable::CONSUMER, '=', ConfigEnum::SERVER_ID],
                [MessageQueueTable::NEXT_RUN_TIME, '<', time()],
            ])->order([
                MessageQueueTable::LAST_RUN_TIME => 'asc',
            ])->limit(96)->selectAll();

            if ($lists->isEmpty()) {
                echo "没有要执行的了" . PHP_EOL;
                return;
            }

            // 更新最后一次执行时间
            self::updateMinLastRunTime();

            foreach ($lists as $messageQueueTableDto) {
                $msgId = $messageQueueTableDto->id;

                // 每个消息只允许同时一个执行
                RedisLock::withLock(
                    lockKey: "message_queue_exec_lock:$msgId",
                    callback: function () use ($messageQueueTableDto) {
                        self::exec($messageQueueTableDto);
                    },
                    autoRenew: true
                );
            }
        });


        //清理, 没必要每次都执行,有需要的时候才执行
        PoolRedis::call(function (Redis $redis) {
            $minLastRunTime = $redis->get(self::MIN_LAST_RUN_TIME);
            $delMinTime = time() - 86400 * 7;
            if ($minLastRunTime > 0 && $minLastRunTime < $delMinTime) {
                new MessageQueueTable()->where([
                    [MessageQueueTable::CONSUMER, '=', ConfigEnum::SERVER_ID],
                    [MessageQueueTable::IS_DISCARD, '=', 1],
                    [MessageQueueTable::IS_SUCCESS, '=', 1],
                    [MessageQueueTable::NEXT_RUN_TIME, '<', $minLastRunTime],
                ])->limit(3000)->delete();
            }
        });

    }

    /**
     * @throws Throwable
     */
    private static function updateMinLastRunTime(): void
    {
        PoolRedis::call(function (Redis $redis) {
            $redis->set(self::MIN_LAST_RUN_TIME, time(), 86400 * 7);
        });
    }

    /**
     * @throws Throwable
     * @throws AppException
     */
    private static function lock(callable $call): void
    {
        $lock = PoolRedis::call(function (Redis $redis) {
            return $redis->get(self::MESSAGE_LOCK);
        });
        if ($lock) {
//            echo "被锁住了" . PHP_EOL;
            return;
        }
        //执行
        $call();

        $nextRunTime = new MessageQueueTable()->where([
            [MessageQueueTable::IS_DISCARD, '=', 0],
            [MessageQueueTable::IS_SUCCESS, '=', 0],
            [MessageQueueTable::CONSUMER, '=', ConfigEnum::SERVER_ID],
        ])->min(MessageQueueTable::NEXT_RUN_TIME);
        if (empty($nextRunTime)) {
            // 没有要执行的了，就锁定
            PoolRedis::call(function (Redis $redis) {
                $redis->set(self::MESSAGE_LOCK, 1, 86400);
            });
        } else {

            $time = time();
            if ($nextRunTime <= $time) {
                // 需要立即执行，不锁定
                return;
            }

            // 锁定到，下次执行的时间
            $ttl = $nextRunTime - time();
            PoolRedis::call(function (Redis $redis) use ($ttl) {
                $redis->set(self::MESSAGE_LOCK, 1, $ttl);
            });
        }
    }

    /**
     * @throws Throwable
     * @throws AppException
     */
    public static function unLock(): void
    {
        PoolRedis::call(function (Redis $redis) {
            $redis->del(self::MESSAGE_LOCK);
        });
    }

    private static function getProgressCacheKey($msgId): string
    {
        return "messageQueueProgress:$msgId";
    }


    /**
     * @throws Throwable
     */
    private static function exec($messageQueueTableDto): void
    {
        $data = $messageQueueTableDto->data;
        $data = json_decode($data, true);

        try {
            if ($messageQueueTableDto->runNum >= $messageQueueTableDto->maxNum) {
                new MessageQueueTable()->addWhere(MessageQueueTable::ID, $messageQueueTableDto->id)->update([
                    MessageQueueTable::IS_DISCARD => 1,
                    MessageQueueTable::ERROR => '执行次数超出边界',
                ]);
                return;
            }

            try {
                $data['arguments'][] = $messageQueueTableDto;
                // 判断是否是代理队列消息
                self::execProxy($data);

                // 没有异常就视为成功
                new MessageQueueTable()->addWhere(MessageQueueTable::ID, $messageQueueTableDto->id)->update([
                    MessageQueueTable::IS_SUCCESS => 1,
                ]);
            } catch (Throwable $e) {

                $delayTimes = $messageQueueTableDto->delayTimes;
                $delayTimes = json_decode($delayTimes, true);
                $runNum = $messageQueueTableDto->runNum;
                if (isset($delayTimes[$runNum])) {
                    $nextRunTime = $messageQueueTableDto->nextRunTime + $delayTimes[$runNum];
                } else {
                    $nextRunTime = $messageQueueTableDto->nextRunTime + end($delayTimes);
                }

                new MessageQueueTable()->addWhere(MessageQueueTable::ID, $messageQueueTableDto->id)->update([
                    MessageQueueTable::RUN_NUM => Db::incr(),
                    MessageQueueTable::NEXT_RUN_TIME => $nextRunTime,
                    MessageQueueTable::LAST_RUN_TIME => time(),
                ]);
                throw $e;
            }
        } catch (Throwable $e) {
            Log::saveException($e, 'queue');
            new MessageQueueTable()->addWhere(MessageQueueTable::ID, $messageQueueTableDto->id)->update([
                MessageQueueTable::IS_DISCARD => 1,
                MessageQueueTable::ERROR => $e->getMessage(),
                MessageQueueTable::LAST_RUN_TIME => time(),
                MessageQueueTable::RUN_NUM => Db::incr(),
            ]);
        }
    }

    /**
     * 执行代理队列消息
     *
     * @param array $data 代理数据
     *   - class: 类名
     *   - method: 方法名（带 __proxy 后缀）
     *   - arguments: 参数数组
     *   - isStatic: 是否静态方法
     *   - transaction?: 事务配置（如有）
     * @return void
     * @throws Throwable
     */
    private static function execProxy(array $data): void
    {
        $className = $data['class'];
        $method = $data['method'];
        $arguments = $data['arguments'];
        $isStatic = $data['isStatic'] ?? false;
        $txConfig = $data['transaction'] ?? null;

        // 如果有事务配置，在事务内执行
        if ($txConfig !== null) {
            $txArgs = $txConfig['arguments'] ?? [];
            Db::transaction(
                call: static fn() => ProxyDispatcher::invokeMethod($className, $method, $arguments, $isStatic),
                dbName: $txArgs['dbName'] ?? 'default',
                isolationLevel: $txArgs['isolationLevel'] ?? null,
                timeout: $txArgs['timeout'] ?? null,
                enableLog: $txArgs['logTransaction'] ?? false
            );
            return;
        }

        // 直接执行
        ProxyDispatcher::invokeMethod($className, $method, $arguments, $isStatic);
    }


    /**
     * 中止队列执行
     * @param int $msgId 队列ID
     * @throws Throwable
     */
    public static function cancel(int $msgId): bool
    {
        $affectedRows = new MessageQueueTable()
            ->addWhere(MessageQueueTable::ID, $msgId)
            ->addWhere(MessageQueueTable::IS_DISCARD, 0) // 只取消未被丢弃的队列
            ->addWhere(MessageQueueTable::IS_SUCCESS, 0) // 只取消未成功的队列
            ->update([
                MessageQueueTable::IS_DISCARD => 1,
                MessageQueueTable::ERROR => '手动取消',
                MessageQueueTable::LAST_RUN_TIME => time(),
            ]);

        // 清理进度缓存
        PoolRedis::call(function (Redis $redis) use ($msgId) {
            $key = self::getProgressCacheKey($msgId);
            $redis->del($key);
        });

        self::unLock();
        return $affectedRows > 0;
    }

    /**
     * @throws Throwable
     */
    public static function updateProgress(int $msgId, float $progress): void
    {
        PoolRedis::call(function (Redis $redis) use ($msgId, $progress) {
            $key = self::getProgressCacheKey($msgId);
            $redis->set($key, $progress);
            $redis->expire($key, 3);
        });


        if (isset(self::$runTimer[$msgId])) {
            Timer::clear(self::$runTimer[$msgId]);
            unset(self::$runTimer[$msgId]);
        }

        $timerId = Timer::after(1000, function () use ($msgId, $progress) {
            new MessageQueueTable()->addWhere(MessageQueueTable::ID, $msgId)->update([
                MessageQueueTable::PROGRESS => $progress,
                MessageQueueTable::LAST_RUN_TIME => time(),
            ]);
        });
        self::$runTimer[$msgId] = $timerId;
    }

    /**
     * 获取当前的进度
     * @throws Throwable
     */
    public static function getStatus(int $msgId): array
    {
        $find = new MessageQueueTable()->addWhere(MessageQueueTable::ID, $msgId)->selectOne();

        $cacheProgress = PoolRedis::call(function (Redis $redis) use ($msgId) {
            $key = self::getProgressCacheKey($msgId);
            return $redis->get($key);
        });

        return [
            'progress' => max($find->progress, $cacheProgress),
            'error' => $find->error,
            'isSuccess' => $find->isSuccess,
            'isDiscard' => $find->isDiscard,
        ];

    }


}