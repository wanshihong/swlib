<?php
declare(strict_types=1);

namespace Swlib\Parse\Table;


use Exception;
use Generate\DatabaseConnect;
use Swlib\Parse\Helper\FieldDefaultValueHelper;
use Swlib\Utils\File;
use Swlib\Utils\StringConverter;


class ParseTableTable
{
    const  string saveDir = ROOT_DIR . 'runtime/Generate/Tables/';
    private array $saveStr = [];
    private string $namespace;

    /**
     * @throws Exception
     */
    public function __construct(
        public string $database,
        public string $tableName,
        public array  $fields,
        public int    $tableIndex
    )
    {
        $this->tableName = StringConverter::underscoreToCamelCase($tableName);
        $this->namespace = DatabaseConnect::getNamespace($this->database);

        $this->saveStr[] = '<?php';
        $this->saveStr[] = "namespace Generate\Tables\\$this->namespace;";
        $this->saveStr[] = '';
        $this->saveStr[] = '';
        $this->saveStr[] = "use Generate\\TablesDto\\$this->namespace\\{$this->tableName}TableDto;";
        $this->saveStr[] = 'use Swlib\\Table\\Trait\\FuncTrait;';
        $this->saveStr[] = 'use Swlib\\Table\\Trait\\RawQueryTrait;';
        $this->saveStr[] = 'use Swlib\\Table\\Trait\\SqlTrait;';
        $this->saveStr[] = 'use Swlib\\Table\\Trait\\TableEventTrait;';
        $this->saveStr[] = 'use Swlib\\Table\\Interface\\TableInterface;';
        $this->saveStr[] = 'use Swlib\Table\Attributes\FieldMetadata;';
        $this->saveStr[] = 'use ReflectionClass;';
        $this->saveStr[] = 'use ReflectionException;';
        $this->saveStr[] = 'use Throwable;';
        $this->saveStr[] = '';
        $this->saveStr[] = '';
        $this->saveStr[] = 'class ' . $this->tableName . 'Table implements TableInterface {';
        $this->saveStr[] = '';
        $this->saveStr[] = '';
        $this->saveStr[] = '    use FuncTrait;';
        $this->saveStr[] = '    use RawQueryTrait;';
        $this->saveStr[] = '    use SqlTrait;';
        $this->saveStr[] = '    use TableEventTrait;';
        $this->saveStr[] = '    const string DATABASES = "' . $this->database . '";';
        $this->saveStr[] = '    const string TABLE_NAME = "' . $tableName . '";';
        $this->saveStr[] = '    const string DTO_CLASS = ' . $this->tableName . 'TableDto::class;';

        // 表级 ORM 生命周期事件常量（每张表独一份）
        $eventPrefix = sprintf('table.%s.%s', $this->database, $tableName);
        $this->saveStr[] = '    // ORM 生命周期事件：当前表的表级事件名';
        $this->saveStr[] = '    const string SelectBefore = "' . $eventPrefix . '.select.before";';
        $this->saveStr[] = '    const string SelectAfter  = "' . $eventPrefix . '.select.after";';
        $this->saveStr[] = '    const string InsertBefore = "' . $eventPrefix . '.insert.before";';
        $this->saveStr[] = '    const string InsertAfter  = "' . $eventPrefix . '.insert.after";';
        $this->saveStr[] = '    const string UpdateBefore = "' . $eventPrefix . '.update.before";';
        $this->saveStr[] = '    const string UpdateAfter  = "' . $eventPrefix . '.update.after";';
        $this->saveStr[] = '    const string DeleteBefore = "' . $eventPrefix . '.delete.before";';
        $this->saveStr[] = '    const string DeleteAfter  = "' . $eventPrefix . '.delete.after";';
        $this->getPriKey();
        $this->createFieldConst();
        $this->getAllField();
        $this->createFieldInsertDefaultValue();
        $this->createDto();
        $this->saveStr[] = '';


    }

    public function __destruct()
    {
        $this->saveStr[] = '}';
        File::save(self::saveDir . $this->namespace . '/' . "{$this->tableName}Table.php", implode(PHP_EOL, $this->saveStr));
    }

    public static function createDir(): void
    {
        ParseTable::createDir(self::saveDir, false);
    }

    private function getPriKey(): void
    {
        foreach ($this->fields as $fieldIndex => $item) {
            $as = ParseTable::createAs($this->tableIndex, $fieldIndex);
            // 主键
            if ($item['Key'] == 'PRI') {
                $priKey = $as;
                $this->saveStr[] = '    const string PRI_KEY = "' . $priKey . '";';
                break;
            }
        }
    }

    private function getAllField(): void
    {
        $this->saveStr[] = '';
        $this->saveStr[] = '';
        $this->saveStr[] = '    const array FIELD_ALL = [';
        foreach ($this->fields as $item) {
            $field = $item['Field'];
            $this->saveStr[] = '        self::' . strtoupper($field) . ',';
        }
        $this->saveStr[] = '    ];';
    }


    /**
     * @throws Exception
     */
    public function createFieldConst(): void
    {
        $this->saveStr[] = '';
        $this->saveStr[] = '';
        $tableIndex = $this->tableIndex;

        // 生成字段常量
        foreach ($this->fields as $fieldIndex => $item) {
            $field = $item['Field'];
            $as = ParseTable::createAs($tableIndex, $fieldIndex);

            $this->saveStr[] = '    /**';
            $this->saveStr[] = "     * {$item['Comment']}";
            $this->saveStr[] = '     */';
            $this->saveStr[] = '    const string ' . strtoupper($field) . ' = "' . $as . '";';
        }
        $this->saveStr[] = '';
        $this->saveStr[] = '';

        // 生成字段元数据注解和方法

        $this->saveStr[] = '';
        $this->saveStr[] = '';

        $fieldAttributeStr = '';
        // 为每个字段生成注解
        foreach ($this->fields as $fieldIndex=> $item) {
            $field = $item['Field'];
            $fieldName = StringConverter::underscoreToCamelCase($field, '_', false);
            $typeInfo = $this->parseFieldType($item['Type']);
            $as = ParseTable::createAs($tableIndex, $fieldIndex);

            $fieldAttributeStr .= $this->generateFieldMetadataAnnotation($field, $fieldName, $item, $typeInfo,$as) . PHP_EOL;
        }

        $fieldAttributeStr = rtrim($fieldAttributeStr);

        $this->saveStr[] = '';
        $this->saveStr[] = '';

        $getFieldMetadata = <<<PHPCODE
    /**
     * 获取字段元数据
     * @param string \$fieldAlias 字段别名常量，如 self::ID
     * @return FieldMetadata|null
     * @throws ReflectionException
     */
    $fieldAttributeStr
    public static function getFieldMetadata(string \$fieldAlias): ?FieldMetadata
    {
        \$reflection = new ReflectionClass(__CLASS__);
        \$method = \$reflection->getMethod(__FUNCTION__);
        foreach (\$method->getAttributes(FieldMetadata::class) as \$attribute) {
            /** @var FieldMetadata \$metadata */
            \$metadata = \$attribute->newInstance();
            if (\$metadata->field === \$fieldAlias) {
                return \$metadata;
            }
        }
        return null;
    }
PHPCODE;


        $this->saveStr[] = $getFieldMetadata;
        $this->saveStr[] = '';
        $this->saveStr[] = '';
    }

    /**
     * @throws Exception
     */
    public function createFieldInsertDefaultValue(): void
    {
        $this->saveStr[] = '';
        $this->saveStr[] = '';
        $this->saveStr[] = '    // dto 对象属性的默认值';
        $this->saveStr[] = '    const array DTO_DEFAULT = [';
        foreach ($this->fields as $item) {
            $field = $item['Field'];
            $dbFieldType = $item['Type'];
            $dbDefault = $item['Default'];
            $allowNull = $item['Null'] === 'YES';

            // 使用统一的默认值处理方法
            $config = FieldDefaultValueHelper::getFieldDefaultConfig($dbFieldType, $dbDefault, $allowNull);
            $sqlDefault = $config['php_default'];

            $this->saveStr[] = '        self::' . strtoupper($field) . ' => ' . $sqlDefault . ',';
        }
        $this->saveStr[] = '    ];';
        $this->saveStr[] = '';
        $this->saveStr[] = '';

        $this->saveStr[] = '';
        $this->saveStr[] = '';
        $this->saveStr[] = '    // 写入数据库的默认值';
        $this->saveStr[] = '    const array DB_DEFAULT = [';
        foreach ($this->fields as $item) {
            $field = $item['Field'];
            $dbFieldType = $item['Type'];
            $dbDefault = $item['Default'];
            $allowNull = $item['Null'] === 'YES';

            // 使用统一的默认值处理方法
            $config = FieldDefaultValueHelper::getFieldDefaultConfig($dbFieldType, $dbDefault, $allowNull);
            $sqlDefault = $config['sql_default'];
            $this->saveStr[] = '        self::' . strtoupper($field) . ' => ' . $sqlDefault . ',';
        }
        $this->saveStr[] = '    ];';
        $this->saveStr[] = '';
        $this->saveStr[] = '';


        $this->saveStr[] = '';
        $this->saveStr[] = '';
        $this->saveStr[] = '    // 数据库是否允许是 null';
        $this->saveStr[] = '    const array DB_ALLOW_NULL = [';
        foreach ($this->fields as $item) {
            $field = $item['Field'];
            $allowNull = $item['Null'] === 'YES';

            $this->saveStr[] = '        self::' . strtoupper($field) . ' => ' . var_export($allowNull, true) . ',';
        }
        $this->saveStr[] = '    ];';
        $this->saveStr[] = '';
        $this->saveStr[] = '';


        $this->saveStr[] = '';
        $this->saveStr[] = '';
        $this->saveStr[] = '    // 别名对应的 dto 属性';
        $this->saveStr[] = '    const array AS_FIELD = [';
        foreach ($this->fields as $item) {
            $field = $item['Field'];
            $fieldName = StringConverter::underscoreToCamelCase($field, '_', false);
            $this->saveStr[] = '        self::' . strtoupper($field) . ' => "' . $fieldName . '",';
        }
        $this->saveStr[] = '    ];';
        $this->saveStr[] = '';
        $this->saveStr[] = '';


    }


    public function createDto(): void
    {
        $this->saveStr[] = '';
        $this->saveStr[] = '';
        $this->saveStr[] = "    public function getDto():{$this->tableName}TableDto";
        $this->saveStr[] = '    {';
        $this->saveStr[] = "        return new {$this->tableName}TableDto();";
        $this->saveStr[] = '    }';
        $this->saveStr[] = '';
        $this->saveStr[] = '';

        $this->saveStr[] = '    /**';
        $this->saveStr[] = "    * @return {$this->tableName}TableDto|null";
        $this->saveStr[] = '    * @throws Throwable';
        $this->saveStr[] = '    */';
        $this->saveStr[] = "    public function selectOne():?{$this->tableName}TableDto";
        $this->saveStr[] = '    {';
        $this->saveStr[] = "        return \$this->_selectOne();";
        $this->saveStr[] = '    }';
        $this->saveStr[] = '';
        $this->saveStr[] = '';

        $this->saveStr[] = '    /**';
        $this->saveStr[] = "    * @return {$this->tableName}TableDto";
        $this->saveStr[] = '    * @throws Throwable';
        $this->saveStr[] = '    */';
        $this->saveStr[] = "    public function selectAll():{$this->tableName}TableDto";
        $this->saveStr[] = '    {';
        $this->saveStr[] = "        return \$this->_selectAll();";
        $this->saveStr[] = '    }';
        $this->saveStr[] = '';
        $this->saveStr[] = '';
    }


    /**
     * 生成字段元数据注解
     */
    private function generateFieldMetadataAnnotation(
        string $field,
        string $fieldName,
        array $item,
        array $typeInfo,
        string $as
    ): string {
        $nullable = $item['Null'] === 'YES';
        $isPrimary = $item['Key'] === 'PRI';
        $isUnique = $item['Key'] === 'UNI';
        $isIndex = $item['Key'] === 'MUL';

        $parts = [
            sprintf('field: "%s"', $as),
            sprintf('name: "%s"', $field),
            sprintf('alias: "%s"', $fieldName),
            sprintf('description: "%s"', addslashes(str_replace("\n", ";", $item['Comment']))),
            sprintf('dbName: "%s"', $this->database),
            sprintf('tableName: "%s"', $this->tableName),
            sprintf('type: "%s"', $typeInfo['type']),
            sprintf('nullable: %s', $nullable ? 'true' : 'false'),
            sprintf('default: %s', $this->formatAnnotationDefaultValue($item['Default'], $nullable)),
            sprintf('length: %s', $typeInfo['length'] ?? 'null'),
        ];

        // 枚举值
        if (isset($typeInfo['values'])) {
            $values = array_map(fn($v) => "'$v'", $typeInfo['values']);
            $parts[] = sprintf('enumValues: [%s]', implode(', ', $values));
        }

        // 扩展属性
        if ($isPrimary) {
            $parts[] = 'isPrimary: true';
        }
        if ($isUnique) {
            $parts[] = 'isUnique: true';
        }
        if ($isIndex) {
            $parts[] = 'isIndex: true';
        }
        if ($item['Extra'] === 'auto_increment') {
            $parts[] = 'isAutoIncrement: true';
        }

        $phpType = $this->mapPhpType($typeInfo['type']);
        if ($phpType) {
            $parts[] = sprintf('phpType: "%s"', $phpType);
        }

        $protoType = $this->mapProtoType($typeInfo['type']);
        if ($protoType) {
            $parts[] = sprintf('protoType: "%s"', $protoType);
        }

        return '    #[FieldMetadata(' . implode(', ', $parts) . ')]';
    }

    /**
     * 解析字段类型
     * @return array{type: string, length?: int|null, values?: array<string>}
     */
    private function parseFieldType(string $type): array
    {
        // enum('a','b','c') -> ['type' => 'enum', 'length' => null, 'values' => ['a','b','c']]
        if (preg_match('/^enum\((.*?)\)$/i', $type, $matches)) {
            $values = array_map(fn($v) => trim($v, "'"), explode(',', $matches[1]));
            return ['type' => 'enum', 'length' => null, 'values' => $values];
        }

        // varchar(255) -> ['type' => 'varchar', 'length' => 255]
        // bigint(20) -> ['type' => 'bigint', 'length' => 20]
        if (preg_match('/^(\w+)(?:\((\d+)\))?$/', $type, $matches)) {
            return [
                'type' => $matches[1],
                'length' => $matches[2] ?? null,
            ];
        }

        return ['type' => $type, 'length' => null];
    }

    /**
     * 格式化注解用的默认值
     */
    private function formatAnnotationDefaultValue(mixed $default, bool $nullable): string
    {
        if ($default === null) {
            return $nullable ? 'null' : 'null';
        }

        if (is_numeric($default)) {
            return (string)$default;
        }

        // CURRENT_TIMESTAMP 等函数直接返回字符串
        if (preg_match('/^[A-Z_]+\(\)$/', (string)$default)) {
            return "'$default'";
        }

        return '"' . addslashes((string)$default) . '"';
    }

    /**
     * 映射数据库类型到 PHP 类型
     */
    private function mapPhpType(string $dbType): ?string
    {
        $map = [
            'tinyint' => 'int',
            'smallint' => 'int',
            'mediumint' => 'int',
            'int' => 'int',
            'bigint' => 'int',
            'float' => 'float',
            'double' => 'float',
            'decimal' => 'float',
            'varchar' => 'string',
            'char' => 'string',
            'text' => 'string',
            'longtext' => 'string',
            'json' => 'array',
            'datetime' => 'string',
            'timestamp' => 'string',
            'date' => 'string',
            'time' => 'string',
            'enum' => 'string',
        ];

        return $map[$dbType] ?? null;
    }

    /**
     * 映射数据库类型到 Proto 类型
     */
    private function mapProtoType(string $dbType): ?string
    {
        $map = [
            'tinyint' => 'int32',
            'smallint' => 'int32',
            'mediumint' => 'int32',
            'int' => 'int32',
            'bigint' => 'int64',
            'float' => 'double',
            'double' => 'double',
            'decimal' => 'double',
            'varchar' => 'string',
            'char' => 'string',
            'text' => 'string',
            'longtext' => 'string',
            'json' => 'string',
            'datetime' => 'int64',
            'timestamp' => 'int64',
            'date' => 'int64',
            'enum' => 'string',
        ];

        return $map[$dbType] ?? null;
    }


}