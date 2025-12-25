<?php
declare(strict_types=1);

namespace Swlib\ServerEvents;

use Swlib\Connect\MysqlHeart;
use Swlib\Connect\PoolMysql;
use Swlib\Connect\PoolRedis;
use Swlib\Connect\RedisHeart;
use Swlib\DataManager\WorkerManager;
use Swlib\Event\Attribute\Event;
use Swlib\Event\EventEnum;
use Swlib\Utils\Log;
use Swoole\Server;
use Throwable;

class OnShutdownEvent
{
    public function handle(Server $server): void
    {
        echo "Server shutdown, pid: " . $server->master_pid . PHP_EOL;

        try {
            // 触发自定义事件
            EventEnum::ServerShutdownEvent->emit([
                'server' => $server,
            ]);
        } catch (Throwable) {
            // 忽略事件发送异常
        }

        // 停止心跳检测
        try {
            MysqlHeart::stop();
        } catch (Throwable) {
            // 忽略异常
        }

        try {
            RedisHeart::stop();
        } catch (Throwable) {
            // 忽略异常
        }

        // 关闭所有连接池
        try {
            PoolRedis::close();
        } catch (Throwable) {
            // 忽略异常
        }

        try {
            PoolMysql::close();
        } catch (Throwable) {
            // 忽略异常
        }

        try {
            WorkerManager::clear();
        } catch (Throwable) {
            // 忽略异常
        }

        try {
            // 移除监听自定义事件
            Event::offMaps();
        } catch (Throwable) {
            // 忽略异常
        }

        try {
            // 输出性能报告
            $performanceReport = EventPerformanceMonitor::getReport();
            echo "Performance Report:\n$performanceReport\n";
            Log::info('Server shutdown performance report generated', [
                'report' => $performanceReport
            ]);
        } catch (Throwable) {
            // 忽略异常
        }

        try {
            // 清理事件处理器实例缓存
            ServerEventManager::clearInstanceCache();
        } catch (Throwable) {
            // 忽略异常
        }

    }
} 