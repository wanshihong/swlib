<?php

namespace Swlib\Event;


use Generate\Tables\Main\RouterHisTable;
use Generate\Tables\Main\RouterTable;
use ReflectionException;
use Swlib\Connect\PoolRedis;
use Swlib\Coroutine\Attribute\CoroutineAttribute;
use Swlib\Event\Abstract\AbstractEvent;
use Swlib\Event\Attribute\Event;
use Swlib\Table\Db;
use Swlib\Utils\Url;
use Throwable;

/**
 * 路由访问事件，访问所有的路由都会触发这个事件
 */
#[Event(EventEnum::HttpRouteEnterEvent->name)]
class HttpRouteEnterEvent extends AbstractEvent
{
    /**
     * 静态数组存储访问历史
     * 格式: [uri => ['his' => [['time' => xxx, 'ip' => 'xxx']], 'last_write_time' => xxx, 'router_id' => xxx]]
     */
    private static array $historyBuffer = [];

    /**
     * 批量写入的阈值
     */
    private const int BATCH_WRITE_THRESHOLD = 50;

    /**
     * 超时写入时间（秒）- 10分钟
     */
    private const int TIMEOUT_WRITE_SECONDS = 600;

    /**
     * 清理历史数据的最后执行时间
     */
    private static int $lastCleanupTime = 0;

    /**
     * @throws Throwable
     */
    public function handle(array $args): void
    {

        // 后台的路由不记录
        if (Url::isAdmin($args['uri'])) {
            return;
        }
        // 到 task 进程去执行
        $this->saveHistory([
            'uri' => $args['uri'],
            'ip' => $args['ip']
        ]);
    }


    /**
     * 异步存储路由的访问历史记录
     * @throws Throwable
     */
    #[CoroutineAttribute]
    public function saveHistory(array $data): void
    {
        $uri = $data['uri'];
        if (!str_starts_with($uri, '/')) {
            $uri = '/' . $uri;
        }
        $ip = $data['ip'];


        // 获取或创建router_id
        $routerId = PoolRedis::getSet("saveRouterHistory:$uri", function () use ($uri) {
            $router = new RouterTable()->addWhere(RouterTable::URI, "$uri")->selectOne();
            return $router->id;
        });

        if ($routerId) {

            // 将访问记录添加到缓存数组
            $this->addToHistoryBuffer($uri, $routerId, $ip);


            // 检查是否需要批量写入
            $this->checkAndBatchWrite();

            // 检查超时写入
            $this->checkTimeoutWrite();

            // 优化的清理历史数据逻辑
            $this->checkAndCleanupHistory();
        }
    }

    /**
     * 添加访问记录到缓存数组
     */
    private function addToHistoryBuffer(string $uri, int $routerId, string $ip): void
    {
        $time = time();
        if (!isset(self::$historyBuffer[$uri])) {
            self::$historyBuffer[$uri] = [
                'his' => [],
                'last_write_time' => $time,
                'router_id' => $routerId
            ];
        }

        self::$historyBuffer[$uri]['his'][] = [
            'time' => $time,
            'ip' => $ip
        ];
    }

    /**
     * 检查并执行批量写入
     * @throws Throwable
     */
    private function checkAndBatchWrite(): void
    {
        foreach (self::$historyBuffer as $uri => $data) {
            if (count($data['his']) >= self::BATCH_WRITE_THRESHOLD) {
                $this->batchWriteHistory($uri, $data);
            }
        }
    }

    /**
     * 检查超时写入
     * @throws Throwable
     */
    private function checkTimeoutWrite(): void
    {
        $currentTime = time();
        foreach (self::$historyBuffer as $uri => $data) {
            $timeDiff = $currentTime - $data['last_write_time'];
            if ($timeDiff >= self::TIMEOUT_WRITE_SECONDS && !empty($data['his'])) {
                $this->batchWriteHistory($uri, $data);
            }
        }
    }

    /**
     * 检查并清理历史数据（优化版）
     * @throws ReflectionException|Throwable
     */
    private function checkAndCleanupHistory(): void
    {
        $currentTime = time();
        $currentDate = (int)date('Ymd');
        $lastCleanupDate = (int)date('Ymd', self::$lastCleanupTime);

        // 只在跨天时执行清理，避免频繁查询
        if ($currentDate > $lastCleanupDate) {
            // 更新最后清理时间
            self::$lastCleanupTime = $currentTime;

            // 使用Redis缓存避免同一天重复执行
            PoolRedis::getSet("deleteRouterHistory:$currentDate", function () {
                $timeField = RouterHisTable::TIME;
                $minSaveTime = time() - 86400 * 90; // 90天前

                $minTime = new RouterHisTable()->where([
                    [$timeField, '<', $minSaveTime]
                ])->order([
                    $timeField => 'asc'
                ])->min($timeField);

                if ($minTime) {
                    $this->deleteHistory($minSaveTime);
                }
                return (bool)$minTime;
            });
        }
    }

    /**
     * 批量写入历史记录
     * @throws Throwable
     */
    private function batchWriteHistory(string $uri, array $data): void
    {
        if (empty($data['his'])) {
            return;
        }

        // 创建临时变量存储当前需要处理的数据
        $tempHistoryData = $data['his'];
        $routerId = $data['router_id'];
        $recordCount = count($tempHistoryData);

        // 立即清空当前URI的历史记录
        unset(self::$historyBuffer[$uri]);

        $insertData = [];
        foreach ($tempHistoryData as $record) {
            $insertData[] = [
                RouterHisTable::ROUTER_ID => $routerId,
                RouterHisTable::URI => $uri,
                RouterHisTable::TIME => $record['time'],
                RouterHisTable::IP => $record['ip'],
            ];
        }

        // 批量插入
        new RouterHisTable()->insertAll($insertData);

        // 更新router表的统计信息
        new RouterTable()->addWhere(RouterTable::ID, $routerId)->update([
            RouterTable::LAST_TIME => time(),
            RouterTable::NUM => Db::incr($recordCount)
        ]);
    }

    /**
     * 删除路由
     * @throws Throwable
     */
    public function deleteHistory(int $minSaveTime): void
    {
        while (true) {
            $res = new RouterHisTable()->where([
                [RouterHisTable::TIME, '<', $minSaveTime]
            ])->limit(3000)->delete();
            if (empty($res)) {
                break;
            }
        }
    }
}