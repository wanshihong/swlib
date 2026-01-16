<?php

namespace Swlib\Parse\Table;

use DateTime;
use Generate\DatabaseConnect;
use Swlib\Parse\Helper\ConsoleColor;
use Swlib\Utils\File;
use Swlib\Utils\Log;
use Throwable;
use function Swoole\Coroutine\parallel;

/**
 * 对数据库结构进行备份
 */
class TableBack
{

    private string $backupDir = RUNTIME_DIR . '/database_backup';
    private string $schemaHashFile;
    private array $ignoredOptions = ['AUTO_INCREMENT', 'ROW_FORMAT', 'COMMENT'];

    public function __construct()
    {
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0777, true);
        }

        $this->schemaHashFile = $this->backupDir . '/schema_hash.json';
        $this->checkAndBackup();
    }

    /**
     * 获取当前数据库的所有表结构
     * @return array
     * @throws Throwable
     */
    private function getCurrentSchema(): array
    {
        $tables = DatabaseConnect::query("SHOW TABLES");
        $schema = [];

        parallel(64, function () use ($tables, &$schema) {
            while ($table = $tables->fetch_row()) {
                $tableName = $table[0];
                $createTable = DatabaseConnect::query("SHOW CREATE TABLE `$tableName`")->fetch_row()[1];
                $schema[$tableName] = $createTable;
            }
        });

        return $schema;
    }

    /**
     * 获取当前数据库的所有视图
     * @return array
     * @throws Throwable
     */
    private function getCurrentViews(): array
    {
        $views = [];

        // 获取所有视图名称
        $viewList = DatabaseConnect::query("SHOW FULL TABLES WHERE Table_type = 'VIEW'");

        parallel(64, function () use ($viewList, &$views) {
            while ($view = $viewList->fetch_row()) {
                $viewName = $view[0];
                // 获取视图的创建语句
                $createViewResult = DatabaseConnect::query("SHOW CREATE VIEW `$viewName`")->fetch_row();
                $createView = $createViewResult[1]; // 视图定义通常在第二列
                $views[$viewName] = $createView;
            }
        });


        return $views;
    }

    /**
     * 顺序标准化处理所有表结构
     * @param array $schema
     * @return array
     */
    private function normalizeSchema(array $schema): array
    {

        return array_map(function ($createStatement) {
            return $this->normalizeCreateStatement($createStatement);
        }, $schema);
    }

    /**
     * 检查是否有新的备份需要创建
     */
    public function checkAndBackup(): void
    {
        try {
            // 顺序获取当前数据库结构和视图
            $currentSchema = $this->getCurrentSchema();
            $currentViews = $this->getCurrentViews();
            $allCurrentSchema = array_merge($currentSchema, $currentViews);

            // 顺序标准化处理所有表结构
            $normalizedSchema = $this->normalizeSchema($allCurrentSchema);

            // 计算当前结构的哈希值
            ksort($normalizedSchema);
            $currentSchemaHash = md5(serialize($normalizedSchema));

            // 检查是否有变更
            $previousHash = $this->getPreviousSchemaHash();

            if ($previousHash !== $currentSchemaHash) {
                // 获取上一次的结构
                $previousSchema = $this->getPreviousSchema();

                if (!empty($previousSchema)) {
                    // 标准化处理上一次的结构
                    $normalizedPreviousSchema = $this->normalizeSchema($previousSchema);

                    // 找出变更
                    $changes = $this->findSchemaChanges($normalizedPreviousSchema, $normalizedSchema);

                    // 只有当有实际变更时才创建备份
                    if (!empty($changes['added']) || !empty($changes['modified']) || !empty($changes['removed'])) {
                        // 获取详细变更
                        $detailedChanges = $this->getDetailedChanges($previousSchema, $allCurrentSchema);

                        // 创建备份
                        $this->createBackup($allCurrentSchema, $changes, $detailedChanges);

                        // 更新哈希值
                        $this->saveSchemaHash($currentSchemaHash, $allCurrentSchema);

                        ConsoleColor::writeWarning("数据库结构已变更，备份已创建");
                    } else {
                        ConsoleColor::writeInfo("检测到变更但无实质性改变，无需备份");
                    }
                } else {
                    // 首次备份
                    $changes = ['added' => array_keys($allCurrentSchema), 'modified' => [], 'removed' => []];
                    $this->createBackup($allCurrentSchema, $changes);
                    $this->saveSchemaHash($currentSchemaHash, $allCurrentSchema);
                    ConsoleColor::writeInfo("首次备份已创建");
                }
            } else {
                ConsoleColor::writeInfo("数据库结构未变更，无需备份");
            }
        } catch (Throwable $e) {
            ConsoleColor::writeSuccessHighlight("数据库备份严重错误: " . $e->getMessage());
            ConsoleColor::writeErrorToStderr("数据库备份失败详情", $e);
            Log::saveException($e, 'backup');
        }
    }

    /**
     * 获取上一次保存的结构哈希值
     * @return string|null
     */
    private function getPreviousSchemaHash(): ?string
    {
        if (!file_exists($this->schemaHashFile)) {
            return null;
        }

        $data = json_decode(file_get_contents($this->schemaHashFile), true);
        return $data['hash'] ?? null;
    }

    /**
     * 获取上一次保存的数据库结构
     * @return array
     */
    private function getPreviousSchema(): array
    {
        if (!file_exists($this->schemaHashFile)) {
            return [];
        }

        $data = json_decode(file_get_contents($this->schemaHashFile), true);
        return $data['schema'] ?? [];
    }

    /**
     * 保存当前结构哈希值和结构定义
     * @param string $hash
     * @param array $schema
     */
    private function saveSchemaHash(string $hash, array $schema): void
    {
        $data = [
            'hash' => $hash,
            'schema' => $schema,
            'timestamp' => time()
        ];

        File::save($this->schemaHashFile, json_encode($data, JSON_PRETTY_PRINT));
    }

    /**
     * 创建一个新的备份文件
     * @param array $schema
     * @param array $changes 变更记录
     * @param array $detailedChanges 详细的字段变更记录
     */
    private function createBackup(array $schema, array $changes, array $detailedChanges = []): void
    {
        $timestamp = new DateTime();
        $filename = $this->backupDir . '/backup_' . $timestamp->format('YmdHis') . '.sql';

        // 生成变更记录
        $changeLog = "-- 数据库结构变更记录 " . $timestamp->format('Y-m-d H:i:s') . "\n";

        if (!empty($changes['added'])) {
            $changeLog .= "-- 新增表/视图: " . implode(', ', $changes['added']) . "\n";
        }

        if (!empty($changes['modified'])) {
            $changeLog .= "-- 修改表/视图: " . implode(', ', $changes['modified']) . "\n";
        }

        if (!empty($changes['removed'])) {
            $changeLog .= "-- 删除表/视图: " . implode(', ', $changes['removed']) . "\n";
        }

        // 添加详细的字段变更记录
        if (!empty($detailedChanges)) {
            $changeLog .= "\n-- 详细变更记录:\n";
            foreach ($detailedChanges as $tableName => $tableChanges) {
                $changeLog .= "-- 表 `$tableName` 的变更:\n";

                if (!empty($tableChanges['added_columns'])) {
                    foreach ($tableChanges['added_columns'] as $column => $definition) {
                        $changeLog .= "--   新增字段: `$column` $definition\n";
                    }
                }

                if (!empty($tableChanges['modified_columns'])) {
                    foreach ($tableChanges['modified_columns'] as $column => $changes) {
                        $changeLog .= "--   修改字段: `$column` 从 {$changes['from']} 变更为 {$changes['to']}\n";
                    }
                }

                if (!empty($tableChanges['removed_columns'])) {
                    foreach ($tableChanges['removed_columns'] as $column => $definition) {
                        $changeLog .= "--   删除字段: `$column` $definition\n";
                    }
                }

                if (!empty($tableChanges['added_indexes'])) {
                    foreach ($tableChanges['added_indexes'] as $index => $definition) {
                        $changeLog .= "--   新增索引: $index $definition\n";
                    }
                }

                if (!empty($tableChanges['removed_indexes'])) {
                    foreach ($tableChanges['removed_indexes'] as $index => $definition) {
                        $changeLog .= "--   删除索引: $index $definition\n";
                    }
                }

                if (!empty($tableChanges['other_changes'])) {
                    $changeLog .= "--   其他变更: {$tableChanges['other_changes']}\n";
                }
            }
        }

        $changeLog .= "\n";

        // 保存完整的SQL和变更记录
        $sql = $changeLog;
        foreach ($schema as $createTable) {
            $sql .= "$createTable;\n\n";
        }

        File::save($filename, $sql);
        ConsoleColor::writeInfo("备份已创建: $filename");
    }

    /**
     * 查找两个数据库结构之间的差异
     * @param array $oldSchema 已标准化的旧结构
     * @param array $newSchema 已标准化的新结构
     * @return array 包含added, modified, removed三个数组的关联数组
     */
    private function findSchemaChanges(array $oldSchema, array $newSchema): array
    {
        $changes = [
            'added' => [],
            'modified' => [],
            'removed' => []
        ];

        // 查找新增和修改的表/视图
        foreach ($newSchema as $name => $normalizedStatement) {
            if (!isset($oldSchema[$name])) {
                $changes['added'][] = $name;
            } else if ($oldSchema[$name] !== $normalizedStatement) {
                $changes['modified'][] = $name;
            }
        }

        // 查找删除的表/视图
        foreach ($oldSchema as $name => $normalizedStatement) {
            if (!isset($newSchema[$name])) {
                $changes['removed'][] = $name;
            }
        }

        return $changes;
    }

    /**
     * 顺序获取详细的字段变更信息
     * @param array $oldSchema
     * @param array $newSchema
     * @return array
     */
    private function getDetailedChanges(array $oldSchema, array $newSchema): array
    {
        $detailedChanges = [];

        // 找出所有需要比较的表
        foreach ($newSchema as $tableName => $createTable) {
            // 跳过视图，只处理表
            if (stripos($createTable, 'CREATE TABLE') === false) {
                continue;
            }

            if (isset($oldSchema[$tableName])) {
                // 使用相同的规范化逻辑来确保一致性
                $oldNormalized = $this->normalizeCreateStatement($oldSchema[$tableName]);
                $newNormalized = $this->normalizeCreateStatement($createTable);

                if ($oldNormalized !== $newNormalized) {
                    $tableChanges = $this->analyzeTableChanges($oldSchema[$tableName], $createTable);

                    // 只有当有实际变更时才添加到详细变更列表
                    if ($this->hasActualChanges($tableChanges)) {
                        $detailedChanges[$tableName] = $tableChanges;
                    }
                }
            }
        }

        return $detailedChanges;
    }

    /**
     * 检查表变更是否包含实际的变化
     * @param array $tableChanges
     * @return bool
     */
    private function hasActualChanges(array $tableChanges): bool
    {
        return !empty($tableChanges['added_columns']) ||
            !empty($tableChanges['modified_columns']) ||
            !empty($tableChanges['removed_columns']) ||
            !empty($tableChanges['added_indexes']) ||
            !empty($tableChanges['removed_indexes']) ||
            !empty($tableChanges['other_changes']);
    }

    /**
     * 分析单个表的结构变更
     * @param string $oldCreateTable
     * @param string $newCreateTable
     * @return array
     */
    private function analyzeTableChanges(string $oldCreateTable, string $newCreateTable): array
    {
        $changes = [
            'added_columns' => [],
            'modified_columns' => [],
            'removed_columns' => [],
            'added_indexes' => [],
            'removed_indexes' => [],
            'other_changes' => ''
        ];

        // 解析表结构
        $oldColumns = $this->parseColumns($oldCreateTable);
        $newColumns = $this->parseColumns($newCreateTable);
        $oldIndexes = $this->parseIndexes($oldCreateTable);
        $newIndexes = $this->parseIndexes($newCreateTable);

        // 比较列
        foreach ($newColumns as $column => $definition) {
            if (!isset($oldColumns[$column])) {
                $changes['added_columns'][$column] = $definition;
            } elseif ($this->normalizeColumnDefinition($oldColumns[$column]) !== $this->normalizeColumnDefinition($definition)) {
                $changes['modified_columns'][$column] = [
                    'from' => $oldColumns[$column],
                    'to' => $definition
                ];
            }
        }

        foreach ($oldColumns as $column => $definition) {
            if (!isset($newColumns[$column])) {
                $changes['removed_columns'][$column] = $definition;
            }
        }

        // 比较索引
        foreach ($newIndexes as $index => $definition) {
            if (!isset($oldIndexes[$index])) {
                $changes['added_indexes'][$index] = $definition;
            } elseif ($this->normalizeIndexDefinition($oldIndexes[$index]) !== $this->normalizeIndexDefinition($definition)) {
                // 索引定义变更也算作新增和删除
                $changes['removed_indexes'][$index] = $oldIndexes[$index];
                $changes['added_indexes'][$index] = $definition;
            }
        }

        foreach ($oldIndexes as $index => $definition) {
            if (!isset($newIndexes[$index])) {
                $changes['removed_indexes'][$index] = $definition;
            }
        }

        // 检查其他变更（如表引擎、字符集等）
        $oldOptions = $this->parseTableOptions($oldCreateTable);
        $newOptions = $this->parseTableOptions($newCreateTable);

        foreach ($newOptions as $option => $value) {
            // 忽略不重要的选项变化
            if (in_array($option, $this->ignoredOptions)) {
                continue;
            }

            if (!isset($oldOptions[$option]) || $oldOptions[$option] !== $value) {
                $changes['other_changes'] .= "$option 从 " . ($oldOptions[$option] ?? '无') . " 变更为 $value; ";
            }
        }

        return $changes;
    }

    /**
     * 标准化列定义
     * @param string $definition
     * @return string
     */
    private function normalizeColumnDefinition(string $definition): string
    {
        // 移除注释
        $normalized = preg_replace("/COMMENT\s+'[^']*'/i", "", $definition);
        // 标准化空格
        return $this->standardizeSpaces($normalized);
    }

    /**
     * 标准化索引定义
     * @param string $definition
     * @return string
     */
    private function normalizeIndexDefinition(string $definition): string
    {
        // 标准化空格
        return $this->standardizeSpaces($definition);
    }

    /**
     * 优化的标准化CREATE语句方法
     * @param string $createStatement
     * @return string
     */
    private function normalizeCreateStatement(string $createStatement): string
    {
        // 判断是表还是视图
        $isView = stripos($createStatement, 'CREATE VIEW') !== false ||
            stripos($createStatement, 'CREATE ALGORITHM') !== false;

        $normalized = $createStatement;

        // 使用更高效的正则表达式替换
        if ($isView) {
            // 对视图的标准化处理 - 一次性替换多个模式
            $patterns = [
                '/DEFINER\s*=\s*`[^`]+`@`[^`]+`/i',
                '/SQL SECURITY \w+/i',
                '/ALGORITHM\s*=\s*\w+/i',
                '/CHARACTER SET \S+/i'
            ];
            $normalized = preg_replace($patterns, '', $normalized);
        } else {
            // 对表的标准化处理 - 使用单一正则表达式匹配多个选项
            $optionsPattern = '/(' . implode('|', $this->ignoredOptions) . ')\s*=\s*[\'"]?[^\'"\\s,)]*[\'"]?/i';
            $normalized = preg_replace($optionsPattern, '', $normalized);

            // 移除表注释
            $normalized = preg_replace("/COMMENT\s*=\s*'[^']*'/i", '', $normalized);
        }

        // 标准化空格和其他格式
        return $this->standardizeSpaces($normalized);
    }

    /**
     * 标准化空格和换行，使比较更准确
     * @param string $sql
     * @return string
     */
    private function standardizeSpaces(string $sql): string
    {
        // 将多个空格替换为单个空格
        $sql = preg_replace('/\s+/', ' ', $sql);
        // 移除逗号后的空格
        $sql = str_replace(', ', ',', $sql);
        // 移除括号周围的空格
        $sql = str_replace('( ', '(', $sql);
        $sql = str_replace(' )', ')', $sql);
        // 移除引号周围的空格
        $sql = str_replace(' \'', '\'', $sql);
        $sql = str_replace('\' ', '\'', $sql);
        // 移除等号周围的空格
        $sql = str_replace(' = ', '=', $sql);
        // 移除多余的空格
        return trim($sql);
    }

    /**
     * 解析CREATE TABLE语句中的列定义
     * @param string $createTable
     * @return array
     */
    private function parseColumns(string $createTable): array
    {
        $columns = [];

        // 提取列定义部分
        if (preg_match('/CREATE TABLE[^(]*\((.*)\)[^)]*$/s', $createTable, $matches)) {
            $columnsPart = $matches[1];

            // 分割各个定义（列、索引等）
            $definitions = preg_split('/,\s*(?=`|PRIMARY|UNIQUE|KEY|CONSTRAINT)/', $columnsPart);

            foreach ($definitions as $definition) {
                $definition = trim($definition);

                // 匹配列定义
                if (preg_match('/^`([^`]+)`\s+(.+)$/s', $definition, $colMatches)) {
                    $columnName = $colMatches[1];
                    $columnDef = trim($colMatches[2]);
                    $columns[$columnName] = $columnDef;
                }
            }
        }

        return $columns;
    }

    /**
     * 解析CREATE TABLE语句中的索引定义
     * @param string $createTable
     * @return array
     */
    private function parseIndexes(string $createTable): array
    {
        $indexes = [];

        // 提取索引定义部分
        if (preg_match('/CREATE TABLE[^(]*\((.*)\)[^)]*$/s', $createTable, $matches)) {
            $columnsPart = $matches[1];

            // 分割各个定义
            $definitions = preg_split('/,\s*(?=`|PRIMARY|UNIQUE|KEY|CONSTRAINT)/', $columnsPart);

            foreach ($definitions as $definition) {
                $definition = trim($definition);

                // 匹配主键
                if (preg_match('/^PRIMARY KEY\s+(.+)$/i', $definition, $pkMatches)) {
                    $indexes['PRIMARY KEY'] = trim($pkMatches[1]);
                } // 匹配唯一索引
                elseif (preg_match('/^UNIQUE KEY\s+`([^`]+)`\s+(.+)$/i', $definition, $ukMatches)) {
                    $indexName = $ukMatches[1];
                    $indexDef = trim($ukMatches[2]);
                    $indexes["UNIQUE KEY `$indexName`"] = $indexDef;
                } // 匹配普通索引
                elseif (preg_match('/^KEY\s+`([^`]+)`\s+(.+)$/i', $definition, $kMatches)) {
                    $indexName = $kMatches[1];
                    $indexDef = trim($kMatches[2]);
                    $indexes["KEY `$indexName`"] = $indexDef;
                } // 匹配外键约束
                elseif (preg_match('/^CONSTRAINT\s+`([^`]+)`\s+(.+)$/i', $definition, $fkMatches)) {
                    $constraintName = $fkMatches[1];
                    $constraintDef = trim($fkMatches[2]);
                    $indexes["CONSTRAINT `$constraintName`"] = $constraintDef;
                }
            }
        }

        return $indexes;
    }

    /**
     * 解析CREATE TABLE语句中的表选项
     * @param string $createTable
     * @return array
     */
    private function parseTableOptions(string $createTable): array
    {
        $options = [];

        // 提取表选项部分
        if (preg_match('/\)\s*(.+)$/s', $createTable, $matches)) {
            $optionsPart = $matches[1];

            // 提取各种选项
            $optionPatterns = [
                'ENGINE' => '/ENGINE\s*=\s*(\w+)/i',
                'CHARACTER SET' => '/CHARACTER SET\s*=\s*(\w+)/i',
                'COLLATE' => '/COLLATE\s*=\s*(\w+)/i',
                'AUTO_INCREMENT' => '/AUTO_INCREMENT\s*=\s*(\d+)/i',
                'ROW_FORMAT' => '/ROW_FORMAT\s*=\s*(\w+)/i',
                'DEFAULT CHARSET' => '/DEFAULT CHARSET\s*=\s*(\w+)/i'
            ];

            foreach ($optionPatterns as $option => $pattern) {
                if (preg_match($pattern, $optionsPart, $matches)) {
                    $options[$option] = $matches[1];
                }
            }
        }

        return $options;
    }
}
