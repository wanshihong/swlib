<?php
declare(strict_types=1);

namespace Swlib\Parse;


use Exception;
use Swlib\Utils\Func;


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
        $this->dbName = Func::underscoreToCamelCase($this->database);
        $this->tableName = Func::underscoreToCamelCase($tableName);
        $namespace = "$this->dbName";

        $this->saveStr[] = '<?php';
        $this->saveStr[] = "namespace Generate\Tables\\$namespace;";
        $this->saveStr[] = '';
        $this->saveStr[] = '';
        $this->saveStr[] = 'use Swlib\Table\FuncTrait;';
        $this->saveStr[] = 'use Swlib\Table\SqlTrait;';
        $this->saveStr[] = 'use Swlib\Table\TableInterface;';
        $this->saveStr[] = 'use Throwable;';
        $this->saveStr[] = '';
        $this->saveStr[] = '';
        $this->saveStr[] = 'class ' . $this->tableName . 'Table implements TableInterface {';
        $this->saveStr[] = '';
        $this->saveStr[] = '';
        $this->saveStr[] = '    use FuncTrait;';
        $this->saveStr[] = '    use SqlTrait;';
        $this->saveStr[] = '    const string DATABASES = "' . $this->database . '";';
        $this->saveStr[] = '    const string TABLE_NAME = "' . $tableName . '";';
        $this->getPriKey();
        $this->createFieldConst();
        $this->getAllField();
        $this->createFieldInsertDefaultValue();
        $this->createFieldGetSet();
        $this->saveStr[] = '';


    }

    public function __destruct()
    {
        $this->saveStr[] = '}';
        file_put_contents(self::saveDir . $this->dbName . '/' . "{$this->tableName}Table.php", implode(PHP_EOL, $this->saveStr));
    }

    public static function createDir(): void
    {
        ParseTable::createDir(self::saveDir);
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
        $this->saveStr[] = '    const array INSERT_UPDATE_DEFAULT = [';
        foreach ($this->fields as $item) {
            $field = $item['Field'];
            $default = $item['Default'];
            if ($item['Default'] === null) {
                $default = 'null';
            } elseif ($item['Default'] === '') {
                $default = "''";
            } else {
                if (is_string($default)) {
                    $default = "'$default'";
                }
            }

            $this->saveStr[] = '        self::' . strtoupper($field) . ' => ' . $default . ',';

        }
        $this->saveStr[] = '    ];';
        $this->saveStr[] = '';
        $this->saveStr[] = '';
    }


    /**
     * @throws Exception
     */
    public function createFieldGetSet(): void
    {
        foreach ($this->fields as $item) {
            $field = $item['Field'];
            $dbType = $item['Type'];
            $this->fieldType($dbType, $field);
        }
    }


    private function fieldType(string $dbFieldType, string $field): void
    {
        $upperField = strtoupper($field);

        $conf = [
            [
                'types' => ['tinyint', 'smallint', 'int', 'bigint'],
                'get' => "get => \$this->_getNumValue(self::$upperField);",
                'set' => "set => \$this->_row[self::$upperField] = \$value;",
                'type' => '?int',
                'def' => 'null'
            ],
            [
                'types' => ['float', 'decimal'],
                'get' => "get => \$this->_getNumValue(self::$upperField,'float');",
                'set' => "set => \$this->_row[self::$upperField] = \$value;",
                'type' => '?float',
                'def' => 'null'
            ],
            [
                'types' => ['json'],
                'get' => "get => \$this->_getArrayValue(self::$upperField);",
                'set' => "set => \$this->_row[self::$upperField] = \$value;",
                'type' => 'array',
                'def' => '[]'
            ]
        ];
        $defStr = '""';
        $typeStr = 'string';
        $getStr = "get => \$this->_row[self::$upperField] ?? $defStr;";
        $setStr = "set => \$this->_row[self::$upperField] = \$value;";
        foreach ($conf as $item) {
            foreach ($item['types'] as $confType) {
                if (str_starts_with($dbFieldType, $confType)) {
                    $defStr = $item['def'];
                    $typeStr = $item['type'];
                    $getStr = $item['get'];
                    $setStr = $item['set'];
                    break 2;
                }
            }
        }


        $field = Func::underscoreToCamelCase($field);
        $field = lcfirst($field);
        $this->saveStr[] = "    public $typeStr \$$field = $defStr {";
        $this->saveStr[] = "        $getStr";
        $this->saveStr[] = "        $setStr";
        $this->saveStr[] = '    }';
        $this->saveStr[] = '';
    }

}