<?php
declare(strict_types=1);

namespace Swlib\Parse;


use Exception;
use Swlib\Utils\File;


class ParseTableMap
{


    private array $fieldMaps = [];


    public function __destruct()
    {
        // 写入 表的字段别名
        File::save(RUNTIME_DIR . 'Generate/TableFieldMap.php', $this->_gen(var_export($this->fieldMaps, true)));
    }

    private function _gen(string $str): string
    {
        return <<<STR
<?php

declare(strict_types=1);

namespace Generate;

class TableFieldMap
{
    const array maps = $str;
}
STR;

    }

    /**
     * @throws Exception
     */
    public function createMap(string $database, string $tableName, array $fields, int $tableIndex): void
    {
        if (!isset($this->fieldMaps[$database])) {
            $this->fieldMaps[$database] = [];
        }
        foreach ($fields as $fieldIndex => $item) {
            $field = $item['Field'];
            $as = ParseTable::createAs($tableIndex, $fieldIndex);
            $this->fieldMaps[$database][$as] = "$tableName.$field";
        }
    }


}