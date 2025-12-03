<?php
declare(strict_types=1);

namespace Swlib\Parse;


use Swlib\Connect\PoolMysql;
use Swlib\Utils\File;
use Swlib\Utils\Func;
use Throwable;
use function Swoole\Coroutine\parallel;

class ParseTable
{


    private array $tableNames = [];

    /**
     * @throws Throwable
     */
    public function __construct()
    {

        ParseTableCRUD::createDir();
        ParseTableAdmin::createDir();
        ParseTableModel::createDir();
        ParseTableProtoc::createDir();
        ParseTableTable::createDir();
        ParseTableTableDto::createDir();

        PoolMysql::eachDbName(function ($dbName) {
            $tempDbName = Func::underscoreToCamelCase($dbName);
            if (isset($this->tableNames[$tempDbName])) {
                $this->tableNames[$tempDbName] = [];
            }

            $parseTableMap = new ParseTableMap();
            $this->selectTables($dbName, $parseTableMap);
        });

        PoolMysql::eachDbName(function ($dbName) {
            $tempDbName = Func::underscoreToCamelCase($dbName);
            $this->clearFile(RUNTIME_DIR . "Generate/Tables/$tempDbName");
            $this->clearFile(ROOT_DIR . "protos/$tempDbName", '.proto', ['BaseExt.proto']);
        });


        // 编译 proto
        ParseTableProtoc::compileProto();
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
                $tableComment = PoolMysql::query("SELECT TABLE_COMMENT FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = '$tableName';", $dbName)->fetch_column();

                foreach ($fields as $key => $item) {
                    $fields[$key]['Field'] = $this->renameReservedFields($item['Field']);
                }

                $database = Func::underscoreToCamelCase($dbName);
                new ParseTableProtoc($database, $tableName, $fields, $tableComment);

                // map 和 table  不要改变 数据库名称的 大小写 配置中是啥就是啥，否则容易混淆，
                new ParseTableTable($dbName, $tableName, $fields, $lastCount);
                new ParseTableTableDto($dbName, $tableName, $fields, $lastCount);
                $parseTableMap->createMap($dbName, $tableName, $fields, $lastCount);


                new ParseTableCRUD($database, $tableName, $fields, $tableComment);
                new ParseTableAdmin($database, $tableName, $fields, $tableComment);
                new ParseTableModel($database, $tableName, $fields, $tableComment);

                $this->tableNames[$dbName][] = Func::underscoreToCamelCase($tableName);
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

        PoolMysql::eachDbName(function ($dbName) use ($baseDir, $delBase) {
            $dbName = Func::underscoreToCamelCase($dbName);
            if ($delBase) {
                File::delDirectory($baseDir . $dbName);
            }

            if (!is_dir($baseDir)) {
                mkdir($baseDir, 0777, true);
            }
        });
    }

    private function clearFile($dir, $ext = 'Table.php', $ignore = []): void
    {
        File::eachDir($dir, function ($path) use ($ext, $ignore) {
            $basename = basename($path);
            if (in_array($basename, $ignore)) {
                return;
            }

            $fileName = str_replace($ext, '', $basename);

            $find = false;
            foreach ($this->tableNames as $tables) {
                if (in_array($fileName, $tables)) {
                    $find = true;
                    break;
                }
            }

            if ($find === false) {
                unlink($path);
            }

        });
    }

}