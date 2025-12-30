<?php
declare(strict_types=1);

namespace Swlib\Parse\Table;


use Exception;
use Swlib\Parse\Helper\FieldDefaultValueHelper;
use Swlib\Utils\File;
use Swlib\Utils\Func;
use Swlib\Utils\StringConverter;


class ParseTableTable
{
    const  string saveDir = ROOT_DIR . 'runtime/Generate/Tables/';
    private array $saveStr = [];
    private string $dbName;

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
        $this->dbName = StringConverter::underscoreToCamelCase($this->database);
        $this->tableName = StringConverter::underscoreToCamelCase($tableName);
        $namespace = "$this->dbName";

        $this->saveStr[] = '<?php';
        $this->saveStr[] = "namespace Generate\Tables\\$namespace;";
        $this->saveStr[] = '';
        $this->saveStr[] = '';
        $this->saveStr[] = "use Generate\\TablesDto\\$this->dbName\\{$this->tableName}TableDto;";
        $this->saveStr[] = 'use Swlib\\Table\\Trait\\FuncTrait;';
        $this->saveStr[] = 'use Swlib\\Table\\Trait\\RawQueryTrait;';
        $this->saveStr[] = 'use Swlib\\Table\\Trait\\SqlTrait;';
        $this->saveStr[] = 'use Swlib\\Table\\Trait\\TableEventTrait;';
        $this->saveStr[] = 'use Swlib\\Table\\Interface\\TableInterface;';
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
        File::save(self::saveDir . $this->dbName . '/' . "{$this->tableName}Table.php", implode(PHP_EOL, $this->saveStr));
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
        foreach ($this->fields as $fieldIndex => $item) {
            $field = $item['Field'];
            $as = ParseTable::createAs($tableIndex, $fieldIndex);
            $this->saveStr[] = '    /**';
            $this->saveStr[] = "    * {$item['Comment']}";
            $this->saveStr[] = '    */';
            $this->saveStr[] = '    const string ' . strtoupper($field) . ' = "' . $as . '";';
        }
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
        foreach ($this->fields as $fieldIndex => $item) {
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


}