<?php
declare(strict_types=1);

namespace Swlib\Parse\Table;


use Exception;
use Generate\DatabaseConnect;
use Swlib\Parse\Helper\FieldDefaultValueHelper;
use Swlib\Utils\File;
use Swlib\Utils\StringConverter;


class ParseTableTableDto
{
    const  string saveDir = ROOT_DIR . 'runtime/Generate/TablesDto/';
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
        $this->saveStr[] = "namespace Generate\TablesDto\\$this->namespace;";
        $this->saveStr[] = '';
        $this->saveStr[] = '';
        $this->saveStr[] = 'use Countable;';
        $this->saveStr[] = 'use IteratorAggregate;';
        $this->saveStr[] = 'use Swlib\Table\Trait\TableDtoTrait;';
        $this->saveStr[] = 'use Swlib\Table\Trait\TableDataListsTrait;';
        $this->saveStr[] = 'use Swlib\Table\Interface\TableDtoInterface;';
        $this->saveStr[] = 'use Generator;';
        $this->saveStr[] = "use Generate\Tables\\$this->namespace\\{$this->tableName}Table;";
        $this->saveStr[] = '';
        $this->saveStr[] = '';
        $this->saveStr[] = 'class ' . $this->tableName . 'TableDto implements TableDtoInterface, IteratorAggregate, Countable {';
        $this->saveStr[] = '';
        $this->saveStr[] = '';
        $this->saveStr[] = '    use TableDtoTrait;';
        $this->saveStr[] = '    use TableDataListsTrait;';
        $this->saveStr[] = '    const string TABLE_CLASS = ' . $this->tableName . 'Table::class;';
        $this->createFieldGetSet();
        $this->saveStr[] = '';


    }

    public function __destruct()
    {
        $this->saveStr[] = '}';
        File::save(self::saveDir . $this->namespace . '/' . "{$this->tableName}TableDto.php", implode(PHP_EOL, $this->saveStr));
    }

    public static function createDir(): void
    {
        ParseTable::createDir(self::saveDir, false);
    }


    /**
     * @throws Exception
     */
    public function createFieldGetSet(): void
    {
        foreach ($this->fields as $item) {
            $field = $item['Field'];
            $dbType = $item['Type'];
            $default = $item['Default'];
            $allowNull = $item['Null'] === 'YES';
            $this->fieldType($dbType, $field, $item['Comment'], $default, $allowNull);
        }
    }


    private function fieldType(string $dbFieldType, string $field, string $comment, mixed $dbDefault, bool $allowNull): void
    {
        $fieldCamelCase = StringConverter::underscoreToCamelCase($field,'_',false);
        $fieldConstName = strtoupper($field); // 字段别名常量名，例如：APP_ID

        // 使用统一的默认值处理方法
        $config = FieldDefaultValueHelper::getFieldDefaultConfig($dbFieldType, $dbDefault, $allowNull);
        $accessors = FieldDefaultValueHelper::getDtoFieldAccessors($fieldCamelCase, $config, $allowNull);

        $this->saveStr[] = '    /**';
        $this->saveStr[] = "    * $comment";
        $this->saveStr[] = '    */';
        $this->saveStr[] = "    public {$config['type']} \$$fieldCamelCase = {$config['php_default']} {";

        // 生成 getter
        if (!empty($accessors['get'])) {
            $this->saveStr[] = "        {$accessors['get']}";
        }

        // 生成 set hook，调用 __trackModification
        $this->saveStr[] = "        set {";
        $this->saveStr[] = "            \$this->$fieldCamelCase = {$accessors['set_value']};";
        $this->saveStr[] = "            \$this->__trackModification({$this->tableName}Table::$fieldConstName, \$this->$fieldCamelCase);";
        $this->saveStr[] = "        }";

        $this->saveStr[] = '    }';
        $this->saveStr[] = '';
    }

}