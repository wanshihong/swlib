<?php

namespace Swlib\Crontab;

use DateTime;
use InvalidArgumentException;

/**
 * Cron 表达式解析器
 *
 * 支持标准 5 字段 Cron 格式：分(0-59) 时(0-23) 日(1-31) 月(1-12) 周(0-6)
 */
class CronExpression
{
    private array $minute = [];
    private array $hour = [];
    private array $day = [];
    private array $month = [];
    private array $weekday = [];

    public function __construct(string $expression)
    {
        $this->parse($expression);
    }

    /**
     * 解析 Cron 表达式
     */
    private function parse(string $expression): void
    {
        $parts = preg_split('/\s+/', trim($expression));

        if (count($parts) !== 5) {
            throw new InvalidArgumentException("Invalid cron expression: $expression");
        }

        $this->minute = $this->parseField($parts[0], 0, 59);
        $this->hour = $this->parseField($parts[1], 0, 23);
        $this->day = $this->parseField($parts[2], 1, 31);
        $this->month = $this->parseField($parts[3], 1, 12);
        $this->weekday = $this->parseField($parts[4], 0, 6);
    }

    /**
     * 解析单个字段
     */
    private function parseField(string $field, int $min, int $max): array
    {
        if ($field === '*') {
            return range($min, $max);
        }

        $values = [];

        // 处理逗号分隔的列表
        foreach (explode(',', $field) as $part) {
            if (str_contains($part, '/')) {
                // 处理步长 */5 或 1-10/2
                [$range, $step] = explode('/', $part);
                $step = (int)$step;

                if ($range === '*') {
                    for ($i = $min; $i <= $max; $i += $step) {
                        $values[] = $i;
                    }
                } else {
                    // 处理范围内的步长
                    [$start, $end] = explode('-', $range);
                    $start = (int)$start;
                    $end = (int)$end;
                    for ($i = $start; $i <= $end; $i += $step) {
                        $values[] = $i;
                    }
                }
            } elseif (str_contains($part, '-')) {
                // 处理范围 1-5
                [$start, $end] = explode('-', $part);
                $values = array_merge($values, range((int)$start, (int)$end));
            } else {
                // 处理单个值
                $values[] = (int)$part;
            }
        }

        return array_unique(array_filter($values, fn($v) => $v >= $min && $v <= $max));
    }

    /**
     * 判断给定时间是否应该执行
     */
    public function isDue(DateTime $now): bool
    {
        $minute = (int)$now->format('i');
        $hour = (int)$now->format('H');
        $day = (int)$now->format('d');
        $month = (int)$now->format('m');
        $weekday = (int)$now->format('w');


        // 检查分钟、小时、月份
        if (!in_array($minute, $this->minute) ||
            !in_array($hour, $this->hour) ||
            !in_array($month, $this->month)) {
            return false;
        }

        // 日期和周几的逻辑：如果两者都不是 *，则满足其中一个即可
        $dayMatch = in_array($day, $this->day);
        $weekdayMatch = in_array($weekday, $this->weekday);

        // 如果日期和周几都被指定（不是全部），则满足其中一个即可
        $dayIsRestricted = count($this->day) < 31;
        $weekdayIsRestricted = count($this->weekday) < 7;

        if ($dayIsRestricted && $weekdayIsRestricted) {
            return $dayMatch || $weekdayMatch;
        } elseif ($dayIsRestricted) {
            return $dayMatch;
        } elseif ($weekdayIsRestricted) {
            return $weekdayMatch;
        }

        // 都是 * 的情况
        return true;
    }
}

