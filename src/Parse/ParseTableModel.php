<?php
declare(strict_types=1);

namespace Swlib\Parse;


use Exception;
use Swlib\Utils\Func;

class ParseTableModel
{


    const string saveDir = RUNTIME_DIR . "Generate/Models/";


    private array $saveModelStr = [];

    /**
     * @throws Exception
     */
    public function __construct(public string $database, public string $tableName, public array $fields, public string $tableComment)
    {
        $this->tableName = Func::underscoreToCamelCase($this->tableName);


        $this->saveModelStr[] = '<?php';
        $this->saveModelStr[] = 'namespace Generate\Models\\' . $this->database . ';';
        $this->saveModelStr[] = '';
        $this->saveModelStr[] = '';
        $this->saveModelStr[] = 'use Throwable;';
        $this->saveModelStr[] = 'use Swlib\Exception\AppException;';
        $this->saveModelStr[] = 'use Swlib\Enum\CtxEnum;';
        $this->saveModelStr[] = 'use Google\Protobuf\Internal\RepeatedField;';
        $this->saveModelStr[] = "use Generate\Tables\\$this->database\\{$this->tableName}Table;";
        $this->saveModelStr[] = 'use Protobuf\\' . $this->database . '\\' . $this->tableName . '\\' . $this->tableName . 'Proto;';
        foreach ($this->fields as $item) {
            // 引入枚举 protobuf 类
            if (str_contains($item['Type'], 'enum')) {
                $this->saveModelStr[] = "use Protobuf\\" . $this->database . "\\" . $this->tableName . "\\" . $this->tableName . Func::underscoreToCamelCase($item['Field']) . "Enum;";
            }
        }


        $this->saveModelStr[] = '';
        $this->saveModelStr[] = "/*";
        $this->saveModelStr[] = "* $tableComment";
        $this->saveModelStr[] = "*/";
        $this->saveModelStr[] = 'class ' . $this->tableName . 'Model{';
        $this->createModelEnumMap();
        $this->createRequestData();
        $this->createFormatItem();
        $this->createCountByField();

    }

    public function __destruct()
    {
        $this->saveModelStr[] = '}';

        file_put_contents(self::saveDir . $this->database . '/' . $this->tableName . "Model.php", implode(PHP_EOL, $this->saveModelStr));
    }


    public static function createDir(): void
    {
        ParseTable::createDir(self::saveDir);
    }

    private function createRequestData(): void
    {

        $tableName = "{$this->tableName}Table";

        $this->saveModelStr[] = '';
        $this->saveModelStr[] = '    /**';
        $this->saveModelStr[] = '    * @throws Throwable';
        $this->saveModelStr[] = '    */';
        $this->saveModelStr[] = "    public static function request({$this->tableName}Proto \$request): $tableName";
        $this->saveModelStr[] = '    {';

        // 接受参数
        foreach ($this->fields as $item) {
            $field = $item['Field'];
            $fieldName = Func::underscoreToCamelCase($field);
            $lcFieldName = lcfirst($fieldName);
            $this->saveModelStr[] = "        \$$lcFieldName = \$request->get$fieldName();";
        }

        // 记录到数组
        $this->saveModelStr[] = "        \$table = new $tableName();";
        foreach ($this->fields as $item) {
            $field = $item['Field'];
            $type = $item['Type'];
            $fieldName = Func::underscoreToCamelCase($field);
            $lcFieldName = lcfirst($fieldName);
            $this->saveModelStr[] = "        if (\$$lcFieldName) {";
            if (str_contains($type, 'json')) {
                $this->saveModelStr[] = "           \$table->$lcFieldName= \$$lcFieldName instanceof RepeatedField ? iterator_to_array(\$$lcFieldName) : \$$lcFieldName;";
            } else if (str_contains($type, 'enum')) {
                $enumClass = "$this->tableName{$fieldName}Enum";
                $this->saveModelStr[] = "           \$$lcFieldName = strtolower($enumClass::name(\$$lcFieldName));";
                $this->saveModelStr[] = "           \$table->$lcFieldName = \$$lcFieldName;";
                $this->saveModelStr[] = "           if (!isset(self::{$fieldName}TextMaps[\$$lcFieldName])) {";
                $this->saveModelStr[] = "               throw new AppException('{$lcFieldName}参数错误');";
                $this->saveModelStr[] = "           }";
            } else {
                $this->saveModelStr[] = "           \$table->$lcFieldName = \$$lcFieldName;";
            }

            $this->saveModelStr[] = "        }";
        }

        $this->saveModelStr[] = '        return $table;';
        $this->saveModelStr[] = '    }';
    }

    private function createModelEnumMap(): void
    {
        foreach ($this->fields as $item) {
            $type = $item['Type'];
            if (!str_contains($type, 'enum')) {
                continue;
            }
            $field = $item['Field'];
            $fieldName = Func::underscoreToCamelCase($field);
            $comment = trim($item['Comment']);
            $arr = explode("\n", $comment);
            if (empty($arr) || count($arr) === 1) {
                $arr = explode(";", $comment);
            }

            $ret = [];

            foreach ($arr as $row) {
                $rowArr = explode(':', $row);
                if (empty($rowArr) || count($rowArr) === 1) {
                    $rowArr = explode('：', $row);
                }
                if (empty($rowArr) || count($rowArr) === 1) {
                    $rowArr = [];
                    foreach (explode(' ', $row) as $value) {
                        $value = trim($value);
                        if ($value) {
                            $rowArr[] = $value;
                        }
                    }
                }
                if (count($rowArr) < 2) continue;
                $status = trim($rowArr[0]);
                $text = trim($rowArr[1]);
                $ucStatus = ucfirst($status);

                $ret[] = [
                    'const' => "self::$fieldName$ucStatus",
                    'text' => $text,
                    'status' => $status,
                ];
                $this->saveModelStr[] = "   const string $fieldName$ucStatus='$status';";
            }


            $this->saveModelStr[] = '   const array ' . $fieldName . 'TextMaps = [';
            foreach ($ret as $r) {
                $k = $r['const'];
                $text = $r['text'];
                $this->saveModelStr[] = "       $k => '$text',";
            }
            $this->saveModelStr[] = '   ];';


        }
    }


    private function createFormatItem(): void
    {
        $this->saveModelStr[] = '';
        $this->saveModelStr[] = '    /**';
        $this->saveModelStr[] = '    * @throws Throwable';
        $this->saveModelStr[] = '    */';
        $this->saveModelStr[] = '    public static function formatItem(' . $this->tableName . 'Table $table):' . $this->tableName . 'Proto';
        $this->saveModelStr[] = '    {';
        $this->saveModelStr[] = '        $proto = new ' . $this->tableName . 'Proto();';
        foreach ($this->fields as $item) {
            $field = $item['Field'];
            $type = $item['Type'];
            $fieldName = Func::underscoreToCamelCase($field);
            $lcField = lcfirst($fieldName);
            if (str_starts_with($type, 'enum')) {
                $this->saveModelStr[] = "        if (\$table->$lcField) {";
                $enumClass = "$this->tableName{$fieldName}Enum";
                $this->saveModelStr[] = "           \$proto->set$fieldName($enumClass::value(\$table->$lcField));";
                $this->saveModelStr[] = "        }";

            } else if (str_starts_with($type, 'int')) {
                $this->saveModelStr[] = "        if (\$table->$lcField !== null) {";
                $this->saveModelStr[] = "           \$proto->set$fieldName(\$table->$lcField);";
                $this->saveModelStr[] = "        }";
            } else {
                $this->saveModelStr[] = "        \$proto->set$fieldName(\$table->$lcField);";
            }


        }
        $this->saveModelStr[] = '        return $proto;';
        $this->saveModelStr[] = '    }';
    }

    private function createCountByField(): void
    {
        $this->saveModelStr[] = '';
        $this->saveModelStr[] = '    /**';
        $this->saveModelStr[] = '    * 统计某个字段下的具体数量';
        $this->saveModelStr[] = '    * @throws Throwable';
        $this->saveModelStr[] = '    */';
        $this->saveModelStr[] = '    public static function countByField(int $id, string $field, array $ids, array $where = []): int';
        $this->saveModelStr[] = '    {';
        $this->saveModelStr[] = '         if (!in_array($id, $ids)) {';
        $this->saveModelStr[] = '             throw new AppException("id 需要包含在 ids 中");';
        $this->saveModelStr[] = '         }';
        $this->saveModelStr[] = '         $key = md5($field . json_encode($ids));';
        $this->saveModelStr[] = '         $ret =  CtxEnum::Data->getSetData($key, function () use ($field, $ids, $where) {';
        $this->saveModelStr[] = '             $where[] = [$field, \'in\', $ids];';
        $this->saveModelStr[] = '             $all = new ' . $this->tableName . 'Table()->field([$field, ' . $this->tableName . 'Table::PRI_KEY])->where($where)->selectAll();';
        $this->saveModelStr[] = '             $ret = [];';
        $this->saveModelStr[] = '             foreach ($all as $table) {';
        $this->saveModelStr[] = '                 $fieldValue = $table->getByField($field);';
        $this->saveModelStr[] = '                 if (!array_key_exists($fieldValue, $ret)) {';
        $this->saveModelStr[] = '                     $ret[$fieldValue] = 0;';
        $this->saveModelStr[] = '                 }';
        $this->saveModelStr[] = '                 $ret[$fieldValue]++;';
        $this->saveModelStr[] = '             }';
        $this->saveModelStr[] = '             return $ret;';
        $this->saveModelStr[] = '         });';
        $this->saveModelStr[] = '         return $ret[$id] ?? 0;';
        $this->saveModelStr[] = '    }';
    }


}