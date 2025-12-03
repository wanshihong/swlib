<?php
declare(strict_types=1);

namespace Swlib\Queue;

use Generate\ConfigEnum;
use ReflectionException;
use Swlib\Connect\PoolRedis;
use Swlib\Exception\AppException;
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
     * 创建消息
     * @param array $runArray 执行的具体控制器 例如 [Test::class,'t1'] ,执行函数返回 true 则不在继续执行，否则延迟执行直到最大次数
     * @param array $data 需要传递的数据
     * @param int $delayTime 延迟执行的时间，单位秒，从当前时间直接增加  也就是  time() + $delayTime
     * @param int $maxNum 最大执行次数
     * @param int[] $delayTimes 重试时间间隔
     * @param bool $clear 是否清理之前的消息 类似于 clearTimeout 这种
     * @return int
     * @throws AppException
     * @throws Throwable
     * @throws ReflectionException
     */
    public static function push(array $runArray, array $data, int $delayTime = 0, int $maxNum = 30, array $delayTimes = [5, 10, 20, 30, 60, 90, 120, 180, 240, 300, 600, 1200, 1800, 3600], bool $clear = false): int
    {
        $reflection = Db::getTableReflection('MessageQueueTable');

        $key = md5($runArray[0] . $runArray[1] . json_encode($data));
        if ($clear) {
            // 丢弃之前的 消息队列
            $reflection->newInstance()
                ->addWhere($reflection->getConstant("MSG_KEY"), $key)
                ->update([
                    $reflection->getConstant("IS_DISCARD") => 1,
                ]);
        }

        $id = $reflection->newInstance()->insert([
            $reflection->getConstant("CLASS_NAME") => $runArray[0],
            $reflection->getConstant("METHOD") => $runArray[1],
            $reflection->getConstant("NEXT_RUN_TIME") => time() + $delayTime,
            $reflection->getConstant("MAX_NUM") => $maxNum,
            $reflection->getConstant("DELAY_TIMES") => json_encode($delayTimes, JSON_UNESCAPED_UNICODE),
            $reflection->getConstant("DATA") => json_encode($data, JSON_UNESCAPED_UNICODE),
            $reflection->getConstant("CONSUMER") => ConfigEnum::SERVER_ID,
            $reflection->getConstant("MSG_KEY") => $key,
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

        $reflection = Db::getTableReflection('MessageQueueTable');
        self::lock(function () use ($reflection) {
            $lists = $reflection->newInstance()->where([
                [$reflection->getConstant("IS_DISCARD"), '=', 0],
                [$reflection->getConstant("IS_SUCCESS"), '=', 0],
                [$reflection->getConstant("CONSUMER"), '=', ConfigEnum::SERVER_ID],
                [$reflection->getConstant("NEXT_RUN_TIME"), '<', time()],
            ])->order([
                $reflection->getConstant("LAST_RUN_TIME") => 'asc',
            ])->limit(96)->selectAll();

            if (empty($lists)) {
                return;
            }

            // 更新最后一次执行时间
            self::updateMinLastRunTime();


            foreach ($lists as $table) {
                $msgId = $table->id;
                $cacheProgress = PoolRedis::call(function (Redis $redis) use ($msgId) {
                    $key = self::getProgressCacheKey($msgId);
                    return $redis->get($key);
                });

                // 已经有 没在执行中了 ; 有进度表示这个消息还在执行中
                if (empty($cacheProgress)) {
                    self::exec($table);
                }
            }
        });


        //清理, 没必要每次都执行,有需要的时候才执行
        PoolRedis::call(function (Redis $redis) use ($reflection) {
            $minLastRunTime = $redis->get(self::MIN_LAST_RUN_TIME);
            $delMinTime = time() - 86400 * 7;
            if ($minLastRunTime > 0 && $minLastRunTime < $delMinTime) {
                $reflection->newInstance()
                    ->addWhere($reflection->getConstant("CONSUMER"), ConfigEnum::SERVER_ID)
                    ->addWhere($reflection->getConstant("LAST_RUN_TIME"), $delMinTime, '<')
                    ->addWhere($reflection->getConstant("IS_DISCARD"), 1, '=', 'OR')
                    ->addWhere($reflection->getConstant("IS_SUCCESS"), 1, '=', 'OR')
                    ->limit(3000)
                    ->delete();
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
            return;
        }
        //执行
        $call();

        $reflection = Db::getTableReflection('MessageQueueTable');
        $nextRunTime = $reflection->newInstance()->where([
            [$reflection->getConstant("IS_DISCARD"), '=', 0],
            [$reflection->getConstant("IS_SUCCESS"), '=', 0],
            [$reflection->getConstant("CONSUMER"), '=', ConfigEnum::SERVER_ID],
        ])->min($reflection->getConstant("NEXT_RUN_TIME"));
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
    private static function unLock(): void
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
    private static function exec($table): void
    {
        $className = $table->className;
        $methodName = $table->method;
        $data = $table->data;
        $data = json_decode($data, true);
        $data['_msgId'] = $table->id;
        $data['_progress'] = $table->progress;
        $reflection = Db::getTableReflection('MessageQueueTable');
        try {
            if ($table->runNum >= $table->maxNum) {

                $reflection->newInstance()->addWhere($reflection->getConstant("ID"), $table->id)->update([
                    $reflection->getConstant("IS_DISCARD") => 1,
                    $reflection->getConstant("ERROR") => '执行次数超出边界',
                ]);
                return;
            }

            $ret = new $className()->$methodName($data);
            if ($ret === true) {
                $reflection->newInstance()->addWhere($reflection->getConstant("ID"), $table->id)->update([
                    $reflection->getConstant("IS_SUCCESS") => 1,
                ]);
            } else {
                $delayTimes = $table->delayTimes;
                $delayTimes = json_decode($delayTimes, true);
                $runNum = $table->runNum;
                if (isset($delayTimes[$runNum])) {
                    $nextRunTime = $table->nextRunTime + $delayTimes[$runNum];
                } else {
                    $nextRunTime = $table->nextRunTime + end($delayTimes);
                }

                $reflection->newInstance()->addWhere($reflection->getConstant("ID"), $table->id)->update([
                    $reflection->getConstant("RUN_NUM") => $reflection->getConstant("RUN_NUM") . '+1',
                    $reflection->getConstant("NEXT_RUN_TIME") => $nextRunTime,
                    $reflection->getConstant("LAST_RUN_TIME") => time(),
                ]);
            }
        } catch (Throwable $e) {
            Log::saveException($e, 'queue');
            $reflection->newInstance()->addWhere($reflection->getConstant("ID"), $table->id)->update([
                $reflection->getConstant("IS_DISCARD") => 1,
                $reflection->getConstant("ERROR") => $e->getMessage(),
                $reflection->getConstant("LAST_RUN_TIME") => time(),
                $reflection->getConstant("RUN_NUM") => $reflection->getConstant("RUN_NUM") . '+1',
            ]);
        }
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
            $reflection = Db::getTableReflection('MessageQueueTable');
            $reflection->newInstance()->addWhere($reflection->getConstant("ID"), $msgId)->update([
                $reflection->getConstant("PROGRESS") => $progress,
                $reflection->getConstant("LAST_RUN_TIME") => time(),
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
        $reflection = Db::getTableReflection('MessageQueueTable');
        $find = $reflection->newInstance()->addWhere($reflection->getConstant("ID"), $msgId)->selectOne();

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