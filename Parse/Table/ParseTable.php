<?php
declare(strict_types=1);

namespace Swlib\Parse\Table;


use Generate\DatabaseConnect;
use mysqli;
use RuntimeException;
use Swlib\Exception\AppErr;
use Swlib\Exception\AppException;
use Swlib\Parse\Helper\ConsoleColor;
use Swlib\Parse\Helper\FieldConflictDetector;
use Swlib\Utils\File;
use Swlib\Utils\StringConverter;
use Swoole\Database\MysqliProxy;
use Throwable;
use function Swoole\Coroutine\parallel;

class ParseTable
{


    private array $tableNames = [];

    /**
     * 执行数据库表创建，如果还没有执行过的话
     * @throws Throwable
     */
    private function executeCreateTablesIfNeeded(): void
    {
        $lockFile = RUNTIME_DIR . '/lock/create_tables.lock';

        // 如果锁文件存在，说明已经执行过了
        if (file_exists($lockFile)) {
            return;
        }

        // 执行 SQL 文件
        $this->executeSqlFile();

        // 创建锁文件
        file_put_contents($lockFile, 1);
    }

    /**
     * 执行 create_tab.sql 中的 SQL 语句
     * @throws Throwable
     */
    private function executeSqlFile(): void
    {
        $sqlFile = ROOT_DIR . 'Swlib' . DIRECTORY_SEPARATOR . 'create_tab.sql';

        if (!file_exists($sqlFile)) {
            throw new AppException(AppErr::PARSE_SQL_FILE_NOT_FOUND_WITH_PATH, $sqlFile);
        }

        $sql = file_get_contents($sqlFile);

        // 执行 SQL 语句
        DatabaseConnect::call(function (MysqliProxy|mysqli $mysqli) use ($sql) {
            $mysqli->multi_query($sql);
        });
    }

    /**
     * @throws Throwable
     */
    public function __construct()
    {
        // 执行数据库表创建
        $this->executeCreateTablesIfNeeded();

        ParseTableCRUD::createDir();
        ParseTableAdmin::createDir();
        ParseTableModel::createDir();
        ParseTableProtoc::createDir();
        ParseTableTable::createDir();
        ParseTableTableDto::createDir();

        DatabaseConnect::eachDbName(function ($dbName) {
            $tempDbName = StringConverter::underscoreToCamelCase($dbName);
            if (isset($this->tableNames[$tempDbName])) {
                $this->tableNames[$tempDbName] = [];
            }

            $parseTableMap = new ParseTableMap();
            $this->selectTables($dbName, $parseTableMap);
        });

        DatabaseConnect::eachDbName(function ($dbName) {
            $tempDbName = StringConverter::underscoreToCamelCase($dbName);
            $this->clearFile(RUNTIME_DIR . "Generate/Tables/$tempDbName");
            $this->clearFile(ROOT_DIR . "protos/$tempDbName", '.proto', [
                'BaseExt.proto',
                $tempDbName . 'BaseExt.proto'
            ]);
        });


        // 编译 proto
        ParseTableProtoc::compileProto();
    }

    /**
     * @throws Throwable
     */
    private function selectTables(string $dbName, ParseTableMap $parseTableMap): void
    {
        $tables = DatabaseConnect::query("SHOW TABLES", $dbName)->fetch_all();
        if (empty($tables)) {
            ConsoleColor::writeWarning("数据库 '$dbName' 中没有找到任何表");
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
                $fields = DatabaseConnect::query("SHOW FULL COLUMNS FROM `" . $tableName . "`", $dbName)->fetch_all(MYSQLI_ASSOC);
                $tableComment = DatabaseConnect::query("SELECT TABLE_COMMENT FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = '$tableName';", $dbName)->fetch_column();

                // 检测字段冲突
                $conflicts = FieldConflictDetector::detect($fields);
                if (!empty($conflicts['table']) || !empty($conflicts['dto'])) {
                    $this->reportConflictsAndExit($dbName, $tableName, $conflicts);
                }

                foreach ($fields as $key => $item) {
                    $fields[$key]['Field'] = $this->renameReservedFields($item['Field']);
                }

                $database = StringConverter::underscoreToCamelCase($dbName);
                new ParseTableProtoc($dbName, $tableName, $fields, $tableComment);

                // map 和 table  不要改变 数据库名称的 大小写 配置中是啥就是啥，否则容易混淆，
                new ParseTableTable($dbName, $tableName, $fields, $lastCount);
                new ParseTableTableDto($dbName, $tableName, $fields, $lastCount);
                new ParseTableModel($dbName, $tableName, $fields, $tableComment);
                $parseTableMap->createMap($dbName, $tableName, $fields, $lastCount);


                new ParseTableCRUD($dbName, $tableName, $fields, $tableComment);
                new ParseTableAdmin($dbName, $tableName, $fields, $tableComment);


                $this->tableNames[$dbName][] = StringConverter::underscoreToCamelCase($tableName);
            }
        });
    }


    /**
     * 报告字段冲突并退出程序
     *
     * @param string $dbName 数据库名
     * @param string $tableName 表名
     * @param array $conflicts 冲突列表
     * @return never
     */
    private function reportConflictsAndExit(string $dbName, string $tableName, array $conflicts): never
    {
        ConsoleColor::writeErrorHighlight("========================================");
        ConsoleColor::writeErrorHighlight("严重错误：检测到字段名冲突！");
        ConsoleColor::writeErrorHighlight("========================================");
        ConsoleColor::writeError("");
        ConsoleColor::writeError("数据库: $dbName");
        ConsoleColor::writeError("表名: $tableName");
        ConsoleColor::writeError("");

        // 报告 Table 类冲突
        if (!empty($conflicts['table'])) {
            ConsoleColor::writeError("【Table 类冲突】");
            foreach ($conflicts['table'] as $fieldName => $conflictItems) {
                ConsoleColor::writeError("  字段: $fieldName");
                foreach ($conflictItems as $item) {
                    ConsoleColor::writeError("    - 与 $item 冲突");
                }
            }
            ConsoleColor::writeError("");
        }

        // 报告 DTO 类冲突
        if (!empty($conflicts['dto'])) {
            ConsoleColor::writeError("【DTO 类冲突】");
            foreach ($conflicts['dto'] as $fieldName => $conflictItems) {
                ConsoleColor::writeError("  字段: $fieldName");
                foreach ($conflictItems as $item) {
                    ConsoleColor::writeError("    - 与 $item 冲突");
                }
            }
            ConsoleColor::writeError("");
        }

        ConsoleColor::writeErrorHighlight("========================================");
        ConsoleColor::writeErrorHighlight("请修改数据库字段名后重新运行解析！");
        ConsoleColor::writeErrorHighlight("========================================");

        exit(1);
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

        DatabaseConnect::eachDbName(function ($dbName) use ($baseDir, $delBase) {
            $dbName = StringConverter::underscoreToCamelCase($dbName);
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