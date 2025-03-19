<?php
declare(strict_types=1);

namespace Swlib\Parse;


use Exception;
use Swlib\Connect\PoolMysql;
use Swlib\Utils\File;
use Swlib\Utils\Func;
use Throwable;
use function Swoole\Coroutine\parallel;
use function Swoole\Coroutine\run;

class ParseTable
{


    const array CREATE_TABLES = [
        // 创建路由表格
        "CREATE TABLE IF NOT EXISTS  `router` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `uri` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '路由',
  `last_time` int unsigned DEFAULT '0' COMMENT '最后访问时间',
  `num` int unsigned DEFAULT '0' COMMENT '访问次数',
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '页面名称',
  `info` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '页面简介',
  `keyword` json DEFAULT NULL COMMENT '页面关键字',
  `desc` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '页面详细功能介绍',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin COMMENT='路由访问历史记录';",

        // 创建路由表格
        "CREATE TABLE IF NOT EXISTS `router_his` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `router_id` int unsigned DEFAULT NULL,
  `uri` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '路由',
  `time` int unsigned DEFAULT '0' COMMENT '访问时间',
  `ip` varchar(40) COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'ip，ipv6最长39',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin COMMENT='路由访问历史记录';",


        // 创建翻译表格
        "CREATE TABLE IF NOT EXISTS `language` (
          `id` int NOT NULL AUTO_INCREMENT COMMENT 'protobuf:ext:json:[\r\n\"item:value:string\"\r\n]',
          `use_time` int DEFAULT NULL COMMENT '上次使用时间，太久没使用可以删除',
          `key` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT '' COMMENT '唯一标识',
          `zh` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT '' COMMENT '中文',
          `en` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT '' COMMENT '英文',
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB AUTO_INCREMENT=1174 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;",


        // 创建消息队列表
        "CREATE TABLE IF NOT EXISTS  `message_queue` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `class_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '执行的 类名称',
  `method` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '执行的  类方法',
  `run_num` int unsigned DEFAULT '0' COMMENT '执行了次数',
  `next_run_time` int unsigned DEFAULT '0' COMMENT '下次执行时间',
  `last_run_time` int unsigned DEFAULT NULL COMMENT '上次执行时间',
  `progress` float unsigned DEFAULT '0' COMMENT '长队列的话，显示一下执行进度',
  `error` text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT '执行错误的话，记录错误信息',
  `is_discard` tinyint unsigned DEFAULT '0' COMMENT '是否丢弃了本条消息',
  `is_success` tinyint unsigned DEFAULT '0' COMMENT '是否执行成功了',
  `delay_times` text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT '延迟执行的时间列表，是一个数组',
  `max_num` int unsigned DEFAULT '0' COMMENT '最大执行次数，0不限制',
  `data` text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT '本次执行需要传递的数据',
  `consumer` varchar(50) COLLATE utf8mb4_bin DEFAULT NULL COMMENT '消费者',
  PRIMARY KEY (`id`),
  KEY `next_run_time` (`next_run_time`,`is_discard`,`consumer`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin COMMENT='消息队列';",


        // 创建管理员表格
        "CREATE TABLE IF NOT EXISTS `admin_manager` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(180) NOT NULL,
  `roles` json NOT NULL,
  `password` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_IDENTIFIER_USERNAME` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;"


    ];

    /**
     * @throws Exception
     */
    public function __construct()
    {

        run(function () {

            $sql = self::CREATE_TABLES;
            parallel(64, function () use (&$sql) {
                while ($sqlItem = array_pop($sql)) {
                    PoolMysql::query($sqlItem);
                }
            });


            ParseTableCRUD::createDir();
            ParseTableAdmin::createDir();
            ParseTableModel::createDir();
            ParseTableProtoc::createDir();
            ParseTableTable::createDir();
            $parseTableMap = new ParseTableMap();

            PoolMysql::eachDbName(function ($dbName) use ($parseTableMap) {
                $this->selectTables($dbName, $parseTableMap);
            });

            // 编译 proto
            ParseTableProtoc::compileProto();
        });
    }

    /**
     * @throws Throwable
     */
    private function selectTables(string $dbName, ParseTableMap $parseTableMap): void
    {
        $tables = PoolMysql::query("SHOW TABLES", $dbName)->fetch_all();
        if (empty($tables)) {
            return;
        }
        $this->createTables($tables, $parseTableMap, $dbName);
    }

    private function createTables(array $tables, ParseTableMap $parseTableMap, string $dbName): void
    {
        parallel(64, function () use (&$tables, $parseTableMap, $dbName) {
            while ($item = array_pop($tables)) {
                $tableName = $item[0];
                $lastCount = count($tables);
                $fields = PoolMysql::query("SHOW FULL COLUMNS FROM `" . $tableName . "`", $dbName)->fetch_all(MYSQLI_ASSOC);

                foreach ($fields as $key => $item) {
                    $fields[$key]['Field'] = $this->renameReservedFields($item['Field']);
                }

                $database = Func::underscoreToCamelCase($dbName);
                new ParseTableProtoc($database, $tableName, $fields);

                // map 和 table  不要改变 数据库名称的 大小写 配置中是啥就是啥，否则容易混淆，
                new ParseTableTable($dbName, $tableName, $fields, $lastCount);
                $parseTableMap->createMap($dbName, $tableName, $fields, $lastCount);


                new ParseTableCRUD($database, $tableName, $fields);
                new ParseTableAdmin($database, $tableName, $fields);
                new ParseTableModel($database, $tableName, $fields);
            }
        });
    }


    /**
     * 重命名保留字段
     * @param string $fieldName
     * @return string
     */
    private function renameReservedFields(string $fieldName): string
    {
        /**
         * 保留字段
         * class 是 php 用来取类名称的
         * DATABASES 是数据库名称，
         * TABLE_NAME 是表名称，
         * PRI_KEY 是主键名称
         */
        if (in_array($fieldName, ['class', 'DATABASES', 'TABLE_NAME', 'PRI_KEY'])) {
            // 重命名字段，例如在字段名前加上 'field_' 前缀
            return 'field_' . $fieldName;
        }
        return $fieldName;
    }


    public static function createAs(int $tableIndex, int $fieldIndex): string
    {
        return "t{$tableIndex}f$fieldIndex";
    }


    public static function createDir(string $baseDir, $delBase = true): void
    {
        if ($delBase) {
            File::delDirectory($baseDir);
        }
        if (!is_dir($baseDir)) {
            mkdir($baseDir, 0777, true);
        }

        PoolMysql::eachDbName(function ($dbName) use ($baseDir) {
            $dbName = Func::underscoreToCamelCase($dbName);
            File::delDirectory($baseDir . $dbName);
            mkdir($baseDir . $dbName, 0777, true);
        });
    }

}