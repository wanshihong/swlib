<?php
declare(strict_types=1);

namespace Swlib\ProjectInit;

use Generate\ProjectInitMap;
use RuntimeException;
use Swlib\Parse\Helper\ConsoleColor;

class ProjectInitRunner
{
    public static function run(): void
    {
        $entries = ProjectInitMap::ITEMS;
        if ($entries === []) {
            return;
        }

        ConsoleColor::writeInfo('正在执行项目启动初始化...');
        self::runEntries($entries);
        ConsoleColor::writeSuccess('项目启动初始化完成');
    }

    /**
     * @param array<int, array{run: array{0:string,1:string}, desc:string}> $entries
     */
    public static function runEntries(array $entries): void
    {
        foreach ($entries as $entry) {
            [$className, $method] = $entry['run'];
            $desc = $entry['desc'] ?: "$className::$method";

            $instance = new $className();
            if (!method_exists($instance, $method)) {
                throw new RuntimeException("项目初始化执行失败：{$desc}，方法 $className::$method 不存在");
            }

            ConsoleColor::writeStep("执行项目初始化 $desc");
            $instance->{$method}();
            ConsoleColor::writeStep("执行项目初始化 $desc", 'success', 0.0);
        }
    }
}
