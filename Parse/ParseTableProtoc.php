<?php
declare(strict_types=1);

namespace Swlib\Parse;


use Exception;
use Swlib\Connect\PoolMysql;
use Swlib\Utils\ConsoleColor;
use Swlib\Utils\File;
use Swlib\Utils\StringConverter;

/**
 *
 *  ID 字段注释中有  protobuf:ext:json:[]  json 中的内容表示需要生成的额外字段
 *      protobuf:ext:json:[
 *           "位置:字段:字段类型",
 *           "item:isFocus:bool",
 *           "item:isFocus:bool",
 *           "item:focusCount:int32",
 *           "lists:counts:repeated string"
 *            // $self 指向自己
 *           "lists:counts:$self"
 *     ]
 *
 *
 *  字段注释中有     g-str-field 表示额外生成一个字符串 protobuf 字段,常用于表示数字的字符串对于的结果显示
 *
 *  字段注释中有     protobuf:item:xxx   定义字段生成的 protobuf 字段类型
 *                 例如1： protobuf:item:string   定义字段生成的 protobuf 字段类型
 *                 例如2： protobuf:item:bool     定义字段生成的 protobuf 字段类型
 *
 *  字段注释中有     protobuf:lists:string 表示在列表中生成 字符串格式 string protobuf 字段
 *
 */
class ParseTableProtoc
{

    const string saveDir = ROOT_DIR . "protos/";
    const string saveDirMaps = ROOT_DIR . "protos/field_maps";

    private array $protobufMessage;
    private array $importFiles = [];

    private string $dbName;
    private string $tableComment;

    /**
     * @throws Exception
     */
    public function __construct(string $database, string $tableName, array $fields, string $tableComment = '')
    {
        $this->dbName = $database;
        $this->tableComment = $tableComment ?? '';
        $this->protobufMessage = [];
        $upperTableName = StringConverter::underscoreToCamelCase($tableName);

        $this->createNamespace($upperTableName);
        $this->createItemMessage($fields, $upperTableName);

        $this->createListsMessage($upperTableName, $fields);


        $str = implode(PHP_EOL, $this->protobufMessage);

        $str = str_replace("// import files", implode(PHP_EOL, $this->importFiles), $str);

        File::save(self::saveDir . $this->dbName . "/$upperTableName.proto", $str . PHP_EOL);
    }


    /**
     * 编译 proto 文件
     * @return void
     */
    public static function compileProto(): void
    {
//        File::delDirectory(RUNTIME_DIR . "Protobuf/Protobuf/");
//        File::delDirectory(RUNTIME_DIR . "Protobuf/GPBMetadata/");

        $dirs = [""];
        PoolMysql::eachDbName(function ($dbName) use (&$dirs) {
            $dbName = StringConverter::underscoreToCamelCase($dbName);
            $dirs[] = $dbName;
        });

        $outDir = RUNTIME_DIR . "Protobuf/";
        if (!is_dir($outDir)) {
            mkdir($outDir, 0777, true);
        }
        foreach ($dirs as $dir) {
            $inDir = rtrim(self::saveDir . "/$dir", '/');
            $inDir = str_replace('//', '/', $inDir);
            $sh = "protoc -I $inDir $inDir/*.proto --php_out=$outDir 2>&1";
            ConsoleColor::writeInfo("执行Proto编译: $sh");

            $output = [];
            exec($sh, $output);

            // 如果有输出内容，检查是否包含错误信息
            if (!empty($output)) {
                foreach ($output as $line) {
                    // 检查是否是错误信息（通常包含 .proto: 或 error 等关键字）
                    if (str_contains($line, '.proto:') ||
                        str_contains($line, 'error') ||
                        str_contains($line, 'Error') ||
                        str_contains($line, 'already defined') ||
                        str_contains($line, 'already been used')) {
                        ConsoleColor::writeErrorHighlight($line);
                    } else {
                        echo $line . PHP_EOL;
                    }
                }
            }
        }
    }


    public static function createDir(): void
    {
        ParseTable::createDir(self::saveDir, false);
    }


    private function createNamespace(string $upperTableName): void
    {
        $str = '';
        $comment = str_replace(["\r", "\n"], ' ', trim($this->tableComment));
        if (!empty($comment)) {
            $str .= "/** 表注释: " . PHP_EOL;
            $str .= "* $comment " . PHP_EOL;
            $str .= "*/" . PHP_EOL . PHP_EOL . PHP_EOL;
        }
        $str .= "// 数据库: $this->dbName, 表: $upperTableName" . PHP_EOL;
        $str .= 'syntax = "proto3";' . PHP_EOL . PHP_EOL;
        $str .= "// import files" . PHP_EOL . PHP_EOL;
        $str .= '// protoc  --php_out=../../   *.proto ' . PHP_EOL . PHP_EOL;
        $str .= "package Protobuf.$this->dbName.$upperTableName;" . PHP_EOL;
        $str .= "option php_metadata_namespace = \"GPBMetadata\\\\$this->dbName\";" . PHP_EOL;

        $this->protobufMessage[] = $str;

    }

    private function createItemMessage(array $fields, string $tableName): void
    {
        $fieldMapsFileDir = self::saveDirMaps . "/" . $this->dbName;
        $fieldMapsFileName = $fieldMapsFileDir . "/$tableName.json";
        if (!is_dir($fieldMapsFileDir)) {
            mkdir($fieldMapsFileDir, 0777, true);
        }
        $fieldMaps = [];
        if (is_file($fieldMapsFileName)) {
            $fieldMaps = json_decode(file_get_contents($fieldMapsFileName), true);
        }


        $fields[] = ['Field' => 'pageNumber', 'Type' => 'int32', 'Comment' => '当前分页'];
        $fields[] = ['Field' => 'pageSize', 'Type' => 'int32', 'Comment' => '每页大小'];

        // 查询是否需要生成额外的字段
        $ext = $this->getExtField($fields, 'item', $tableName);
        $fields = array_merge($fields, $ext);

        $str = "message {$tableName}Proto {" . PHP_EOL;


        foreach ($fields as $item) {
            $field = $item['Field'];
            $dbType = $item['Type'];
            $comment = $item['Comment'] ?? '';

            $fieldName = StringConverter::underscoreToCamelCase($field);
            $arr = $this->genItem($dbType, $fieldName, $comment, $tableName);

            $commentLine = '';
            if (!empty($comment)) {
                $commentLine .= PHP_EOL . PHP_EOL . "    /** " . PHP_EOL;
                $commentLine .= "    * " . $comment . PHP_EOL;
                $commentLine .= "    */ " . PHP_EOL;
            }

            foreach ($arr as $value) {
                $index = $fieldMaps[$value] ?? ($fieldMaps ? count($fieldMaps) : 0) + 1;
                if ($commentLine) {
                    $str .= $commentLine;
                }
                $str .= "    $value = $index;" . PHP_EOL;
                $fieldMaps[$value] = $index;
            }
        }
        $str .= "}";
        $this->protobufMessage[] = $str;
        File::save($fieldMapsFileName, json_encode($fieldMaps));
    }

    private function getExtField(array $fields, string $pos, string $tableName): array
    {
        $ret = [];
        foreach ($fields as $field) {
            // 扩展信息是配置在ID 的注释中的，不是ID 字段就跳过
            if ($field['Field'] != 'id') continue;
            // 没有配置扩展信息就跳过
            $comment = $field['Comment'] ?? '';
            if (empty($comment)) break;

            if (!str_contains($comment, 'protobuf:ext:json:')) break;
            $json = str_replace('protobuf:ext:json:', '', $comment);
            $json = trim($json);
            // 转换成数组
            $extConfig = json_decode($json, true);
            if (empty($extConfig)) break;

            foreach ($extConfig as $item) {
                $tempArr = explode(':', $item);
                $configPos = trim($tempArr[0]);
                $filedName = trim($tempArr[1]);
                $type = trim($tempArr[2]);
                if ($configPos != $pos) continue;
                $type = str_replace('$self', $tableName, $type);

                $this->importFile($type, $tableName);
                $ret[] = ['Field' => $filedName, 'Type' => $type];
            }
        }

        return $ret;
    }


    /**
     * 判断是否需要引入 .proto 文件
     * @param $type
     * @param $tableName
     * @return void
     */
    private function importFile($type, $tableName): void
    {
        $tempTableName = str_replace('ListsProto', '', $tableName);

        $typeArr = explode('.', $type);

        /**
         * 定义成这样的 类型
         * 第一项是 Protobuf
         * 第二项是 数据库名称
         * 才需要引入第三项
         * "lists:userAddress:Protobuf.Wenyuehui.UserAddresses.UserAddressesProto",
         */
        if (count($typeArr) < 3 || !in_array($typeArr[0], ['Protobuf', 'repeated Protobuf']) || $typeArr[1] !== $this->dbName) {
            return;
        }

        if ($typeArr[2] == $tempTableName) {
            return;
        }
        $fileName = "$typeArr[2].proto";
        $str = "import \"$fileName\";";
        if (!in_array($str, $this->importFiles)) {
            $this->importFiles[] = $str;
        }
    }

    private function createListsMessage(string $tableName, array $fields): void
    {
        $ret = [];
        $ret[] = ['Field' => 'lists', 'Type' => "repeated {$tableName}Proto", 'Comment' => '列表数据'];
        $ret[] = ['Field' => 'total', 'Type' => "int32", 'Comment' => '总数'];
        $ret[] = ['Field' => 'curr_page', 'Type' => "int32", 'Comment' => '当前页码'];
        $ret[] = ['Field' => 'total_page', 'Type' => "int32", 'Comment' => '总页数'];

        // 循环字段 查看注释中是否有定义 protobuf:lists: 配置，如果有就添加到列表中
        foreach ($fields as $item) {
            $field = $item['Field'];
            $comment = $item['Comment'] ?? '';

            $type = $this->getFieldType($comment, 'protobuf:lists:');
            if ($type === false) continue;
            $ret[] = ['Field' => $field, 'Type' => $type, 'Comment' => $comment];
        }
        // 判断ID 字段中是否有扩展信息
        $ext = $this->getExtField($fields, 'lists', "{$tableName}ListsProto");
        foreach ($ext as $e) {
            $ret[] = ['Field' => $e['Field'], 'Type' => $e['Type'], 'Comment' => ''];
        }

        $str = "message {$tableName}ListsProto {" . PHP_EOL;

        $index = 1;
        foreach ($ret as $field) {
            $type = $field['Type'];
            $name = $field['Field'];
            $cmt = isset($field['Comment']) ? trim((string)$field['Comment']) : '';
            if ($cmt !== '') {
                $str .= "    // " . str_replace(["\r", "\n"], ' ', $cmt) . PHP_EOL;
            }
            $str .= "    $type $name = $index;" . PHP_EOL;
            $index++;
        }

        $str .= "}";
        $this->protobufMessage[] = $str;

    }

    private function createEnumProtoc(string $tableName, string $fieldName, string $str): void
    {
        $str = substr($str, 5, -1);
        $str = str_replace(["'", '"'], "", $str);
        $arr = explode(",", $str);

        $msgName = "$tableName$fieldName";

        $upper = strtoupper($fieldName);

        $ret = "enum {$msgName}Enum {" . PHP_EOL;
        $ret .= "    UNDEFINED_$upper = 0;" . PHP_EOL;
        foreach ($arr as $index => $v) {
            $key = strtoupper("$v");
            $value = $index + 1;
            $ret .= "    $key = $value;" . PHP_EOL;
        }
        $ret .= "}" . PHP_EOL;

        $this->protobufMessage[] = $ret;
    }


    private function genItem(string $dbType, string $fieldName, string $comment, string $tableName): array
    {
        $field = lcfirst($fieldName);
        // 是否在注释中定义了 类型
        $type = $this->getFieldType($comment, 'protobuf:item:');
        $ret = [];

        if (stripos($comment, 'g-str-field') !== false) {
            $ret[] = "string {$field}Str";
        }

        switch (true) {
            case str_starts_with($dbType, 'int'):
                if (stripos($field, 'time') !== false && stripos($comment, 'g-str-field') === false) {
                    $ret[] = "string {$field}Str";
                }
                $type = $type ?: 'int32';
                $ret[] = "$type $field";
                break;

            case str_starts_with($dbType, 'varchar'):
            case str_starts_with($dbType, 'char'):
            case str_starts_with($dbType, 'text'):
            case str_starts_with($dbType, 'datetime'):
            case str_starts_with($dbType, 'longtext'):
            case str_starts_with($dbType, 'binary'):
            case str_starts_with($dbType, 'date'):
            case str_starts_with($dbType, 'timestamp'):
            case str_starts_with($dbType, 'time'):
            case str_starts_with($dbType, 'blob'):
            case str_starts_with($dbType, 'longblob'):
            case str_starts_with($dbType, 'varbinary'):
                $type = $type ?: 'string';
                $ret[] = "$type $field";
                break;
            case str_starts_with($dbType, 'bool'):
                $type = $type ?: 'bool'; // 通常用于布尔值
                $ret[] = "$type $field";
                break;
            case str_starts_with($dbType, 'mediumint'):
            case str_starts_with($dbType, 'smallint'):
            case str_starts_with($dbType, 'tinyint'):
                $type = $type ?: 'int32';
                $ret[] = "$type $field";
                break;

            case str_starts_with($dbType, 'bigint'):
                $type = $type ?: 'int64';
                $ret[] = "$type $field";
                break;
            case str_starts_with($dbType, 'decimal'):
            case str_starts_with($dbType, 'float'):
                $type = $type ?: 'float';
                $ret[] = "$type $field";
                break;
            case str_starts_with($dbType, 'double'):
                $type = $type ?: 'double';
                $ret[] = "$type $field";
                break;

            case str_starts_with($dbType, 'set'):
                $ret[] = "repeated string $field";
                break;

            case str_starts_with($dbType, 'enum'):
                $ret[] = "$tableName{$fieldName}Enum $field";
                $this->createEnumProtoc($tableName, $fieldName, $dbType);
                break;


            case str_starts_with($dbType, 'json'):
//                $type = $type ?: 'int32';
//                $ret[] = "repeated $type $field";
//                if ($type == "int32") {
//                    $ret[] = "repeated string {$field}Str";
//                }
                $type = $type ?: 'string';
                $ret[] = "$type $field";
                break;
            case str_starts_with($dbType, 'repeated'):
                $ret[] = "$dbType $field";
                break;
            default:
                $type = $type ?: ($dbType ?: 'string');
                $ret[] = "$type $field";
                break;
        }


        return $ret;
    }

    function getFieldType(string $comment, $find): string|false
    {
        $index = stripos($comment, $find);
        if ($index !== false) {
            preg_match("/$find([a-z0-9]+)\s?/", $comment, $matches);

            if ($matches[1]) {
                return $matches[1];
            }
        }

        return false;
    }

}