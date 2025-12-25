<?php
declare(strict_types=1);

namespace Swlib\Parse;


use Exception;
use Swlib\Utils\File;
use Swlib\Utils\StringConverter;


class ParseTableAdmin
{

    const string saveDir = RUNTIME_DIR . "codes/admin/";

    private string $pathPrefix;
    private array $saveStr = [];

    /**
     * @throws Exception
     */
    public function __construct(public string $database, public string $tableName, public array $fields, public string $tableComment)
    {
        $this->pathPrefix = StringConverter::getPrefixBeforeUnderscore($this->tableName);
        $this->tableName = StringConverter::underscoreToCamelCase($this->tableName);

        $this->saveStr[] = "<?php //$this->tableName";
        $this->saveStr[] = 'namespace App\\' . $this->database . '\Admin;';
        $this->saveStr[] = '';
        $this->saveStr[] = '';
        $this->saveStr[] = "use Generate\Tables\\$this->database\\{$this->tableName}Table;";
        $this->saveStr[] = 'use Swlib\Admin\Config\PageConfig;';
        $this->saveStr[] = 'use Swlib\Admin\Config\PageFieldsConfig;';
        $this->saveStr[] = 'use Swlib\Admin\Controller\AbstractAdmin;';
        $this->saveStr[] = 'use Swlib\Admin\Fields\Int2TimeField;';
        $this->saveStr[] = 'use Swlib\Admin\Fields\NumberField;';
        $this->saveStr[] = 'use Swlib\Admin\Fields\SelectField;';
        $this->saveStr[] = 'use Swlib\Admin\Manager\OptionManager;';
        $this->saveStr[] = 'use Swlib\Admin\Fields\TextField;';
        $this->saveStr[] = 'use Throwable;';
        $this->saveStr[] = '';
        $this->saveStr[] = '';
        $this->saveStr[] = "/*";
        $this->saveStr[] = "* $tableComment";
        $this->saveStr[] = "*/";
        $this->saveStr[] = "class {$this->tableName}Admin extends AbstractAdmin{";


        $this->configPage();
        $this->configField();


    }

    public static function createDir(): void
    {
        ParseTable::createDir(self::saveDir, false);
    }

    private function configPage(): void
    {
        $this->saveStr[] = '    protected function configPage(PageConfig $config): void';
        $this->saveStr[] = '    {';
        $this->saveStr[] = "        \$config->pageName = '$this->tableComment';";
        $this->saveStr[] = "        \$config->tableName = {$this->tableName}Table::class;";
        $this->saveStr[] = '    }';
    }

    private function configField(): void
    {
        $this->saveStr[] = '    protected function configField(PageFieldsConfig $fields): void';
        $this->saveStr[] = '    {';
        $this->saveStr[] = '        $fields->setFields(';
        foreach ($this->fields as $item) {
            $field = $item['Field'];
            $type = $item['Type'];
            $comment = $item['Comment'] ?: $field;
            $upField = strtoupper($field);
            if ($field == 'id') {
                $this->saveStr[] = "            new NumberField(field: {$this->tableName}Table::$upField, label: 'ID')->hideOnForm(),";
            } elseif (str_contains($field, 'time')) {
                $this->saveStr[] = "            new Int2TimeField(field: {$this->tableName}Table::$upField, label: '$comment'),";
            } elseif (str_starts_with($type, 'enum(')) {
                $arr = array_filter(explode("\n", $comment));
                $label = trim($arr[0]);
                $this->saveStr[] = "            new SelectField(field: {$this->tableName}Table::$upField, label: '$label')->setOptions(";
                foreach ($arr as $index => $value) {
                    if ($index === 0) continue;
                    $optionArr = array_filter(explode(':', $value));
                    if (count($optionArr) !== 2) continue;
                    $optionId = trim($optionArr[0]);
                    $optionText = trim($optionArr[1]);
                    $this->saveStr[] = "                 new OptionManager('$optionId', '$optionText'),";
                }
                $this->saveStr[] = "            ),";
            } else {
                $this->saveStr[] = "            new TextField(field: {$this->tableName}Table::$upField, label: '$comment'),";
            }

        }
        $this->saveStr[] = '        );';
        $this->saveStr[] = '    }';
    }

    public function __destruct()
    {
        $this->saveStr[] = '}';
        File::save(self::saveDir . "$this->database/$this->pathPrefix/{$this->tableName}Admin.php", implode(PHP_EOL, $this->saveStr));
    }


}