<?php

namespace Swlib\Crontab\Attribute;

use Attribute;

/**
 * Crontab 定时任务注解
 *
 * 支持在类或方法上使用，实现类似 Linux Crontab 的定时任务功能。
 *
 * Cron 表达式格式：分(0-59) 时(0-23) 日(1-31) 月(1-12) 周(0-6)
 *
 * ============ 常用业务场景示例 ============
 *
 * 1. 每小时执行一次（如：数据统计、缓存更新）
 *    #[CrontabAttribute(cron: "0 * * * *")]
 *    public function hourlyStatistics(): void { }
 *
 * 2. 每天午夜执行（如：日志清理、数据备份）
 *    #[CrontabAttribute(cron: "0 0 * * *")]
 *    public function dailyCleanup(): void { }
 *
 * 3. 每天 9 点执行（如：发送早报、定时推送）
 *    #[CrontabAttribute(cron: "0 9 * * *")]
 *    public function sendMorningReport(): void { }
 *
 * 4. 每 5 分钟执行一次（如：监控检查、实时同步）
 *    #[CrontabAttribute(cron: "0,5,10,15,20,25,30,35,40,45,50,55 * * * *")]
 *    public function monitoringCheck(): void { }
 *
 * 5. 每周一 10 点执行（如：周报生成、周期性任务）
 *    #[CrontabAttribute(cron: "0 10 * * 1")]
 *    public function weeklyReport(): void { }
 *
 * 6. 每月 1 号 0 点执行（如：月度统计、月度清理）
 *    #[CrontabAttribute(cron: "0 0 1 * *")]
 *    public function monthlyStatistics(): void { }
 *
 * 7. 工作日 9-17 点每小时执行（如：工作时间监控）
 *    #[CrontabAttribute(cron: "0 9-17 * * 1-5")]
 *    public function workingHoursMonitor(): void { }
 *
 * 8. 每 30 分钟执行一次（如：定期检查、心跳检测）
 *    #[CrontabAttribute(cron: "0,30 * * * *")]
 *    public function heartbeat(): void { }
 *
 * 9. 每天 12 点和 18 点执行（如：定时推送、定时提醒）
 *    #[CrontabAttribute(cron: "0 12,18 * * *")]
 *    public function scheduledNotification(): void { }
 *
 * 10. 每个工作日 18 点执行（如：下班提醒、日报汇总）
 *     #[CrontabAttribute(cron: "0 18 * * 1-5")]
 *     public function endOfDayReport(): void { }
 *
 * ============ 配置参数说明 ============
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class CrontabAttribute
{
    public function __construct(
        // Cron 表达式（必需）
        // 格式：分(0-59) 时(0-23) 日(1-31) 月(1-12) 周(0-6)
        // 支持特殊字符：* (任意) , (列表) - (范围) / (步长)
        public string $cron,

        // 任务超时时间（秒），0 表示无限制，默认 300 秒
        public int $timeout = 300,

        // 是否启用协程，默认 true
        public bool $enable_coroutine = true,

        // 任务名称（可选），用于日志和监控，默认使用类名::方法名
        public string $name = '',
    )
    {
    }
}

