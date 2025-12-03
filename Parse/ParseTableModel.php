<?php
declare(strict_types=1);

namespace Swlib\Parse;


use Exception;
use Swlib\Utils\File;
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

        $this->saveModelStr[] = "<?php //$this->tableName";
        $this->saveModelStr[] = 'namespace Generate\Models\\' . $this->database . ';';
        $this->saveModelStr[] = '';
        $this->saveModelStr[] = '';
        $this->saveModelStr[] = 'use Throwable;';
        $this->saveModelStr[] = 'use Swlib\Exception\AppException;';
        $this->saveModelStr[] = 'use Swlib\Enum\CtxEnum;';
        $this->saveModelStr[] = 'use Google\Protobuf\Internal\RepeatedField;';
        $this->saveModelStr[] = "use Generate\TablesDto\\$this->database\\{$this->tableName}TableDto;";
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

    }

    public function __destruct()
    {
        $this->saveModelStr[] = '}';

        File::save(self::saveDir . $this->database . '/' . $this->tableName . "Model.php", implode(PHP_EOL, $this->saveModelStr));
    }


    public static function createDir(): void
    {
        ParseTable::createDir(self::saveDir, false);
    }

    private function createRequestData(): void
    {

        $tableName = "{$this->tableName}Table";

        $this->saveModelStr[] = '';
        $this->saveModelStr[] = '    /**';
        $this->saveModelStr[] = '    * @throws Throwable';
        $this->saveModelStr[] = '    */';
        $this->saveModelStr[] = "    public static function request({$this->tableName}Proto \$request): {$tableName}Dto";
        $this->saveModelStr[] = '    {';

        // 接受参数
        foreach ($this->fields as $item) {
            $field = $item['Field'];
            $fieldName = Func::underscoreToCamelCase($field);
            $lcFieldName = lcfirst($fieldName);
            $this->saveModelStr[] = "        \$$lcFieldName = \$request->get$fieldName();";
        }

        // 记录到数组
        $this->saveModelStr[] = "        \$dto = new {$tableName}Dto();";
        foreach ($this->fields as $item) {
            $field = $item['Field'];
            $type = $item['Type'];
            $fieldName = Func::underscoreToCamelCase($field);
            $lcFieldName = lcfirst($fieldName);
            $this->saveModelStr[] = "        if (\$$lcFieldName) {";
            if (str_contains($type, 'json')) {
                $this->saveModelStr[] = "           \$dto->$lcFieldName= \$$lcFieldName instanceof RepeatedField ? iterator_to_array(\$$lcFieldName) : \$$lcFieldName;";
            } else if (str_contains($type, 'enum')) {
                $enumClass = "$this->tableName{$fieldName}Enum";
                $this->saveModelStr[] = "           \$$lcFieldName = strtolower($enumClass::name(\$$lcFieldName));";
                $this->saveModelStr[] = "           \$dto->$lcFieldName = \$$lcFieldName;";
                $this->saveModelStr[] = "           if (!isset(self::{$fieldName}TextMaps[\$$lcFieldName])) {";
                $this->saveModelStr[] = "               throw new AppException('{$lcFieldName}参数错误');";
                $this->saveModelStr[] = "           }";
            } else {
                $this->saveModelStr[] = "           \$dto->$lcFieldName = \$$lcFieldName;";
            }

            $this->saveModelStr[] = "        }";
        }

        $this->saveModelStr[] = '        return $dto;';
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
        $this->saveModelStr[] = '    public static function formatItem(' . $this->tableName . 'TableDto $dto):' . $this->tableName . 'Proto';
        $this->saveModelStr[] = '    {';
        $this->saveModelStr[] = '        $proto = new ' . $this->tableName . 'Proto();';
        foreach ($this->fields as $item) {
            $field = $item['Field'];
            $type = $item['Type'];
            $fieldName = Func::underscoreToCamelCase($field);
            $lcField = lcfirst($fieldName);
            if (str_starts_with($type, 'enum')) {
                $this->saveModelStr[] = "        if (\$dto->$lcField) {";
                $enumClass = "$this->tableName{$fieldName}Enum";
                $this->saveModelStr[] = "           \$proto->set$fieldName($enumClass::value(\$dto->$lcField));";
                $this->saveModelStr[] = "        }";

            } else if (
                str_starts_with($type, 'int')
                || str_starts_with($type, 'tinyint')
                || str_starts_with($type, 'smallint')
                || str_starts_with($type, 'bigint')
            ) {
                $this->saveModelStr[] = "        if (\$dto->$lcField !== null) {";
                $this->saveModelStr[] = "           \$proto->set$fieldName(\$dto->$lcField);";
                $this->saveModelStr[] = "        }";
            } else {
                $this->saveModelStr[] = "        \$proto->set$fieldName(\$dto->$lcField);";
            }


        }
        $this->saveModelStr[] = '        return $proto;';
        $this->saveModelStr[] = '    }';
    }


}