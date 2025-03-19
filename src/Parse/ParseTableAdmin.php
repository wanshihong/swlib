<?php
declare(strict_types=1);

namespace Swlib\Parse;


use Exception;
use Swlib\Utils\Func;


class ParseTableAdmin
{

    const string saveDir = RUNTIME_DIR . "codes/admin/";

    private array $saveStr = [];

    /**
     * @throws Exception
     */
    public function __construct(public string $database, public string $tableName, public array $fields)
    {
        $this->tableName = Func::underscoreToCamelCase($this->tableName);

        $this->saveStr[] = '<?php';
        $this->saveStr[] = 'namespace App\\' . $this->database . '\Admin;';
        $this->saveStr[] = '';
        $this->saveStr[] = '';
        $this->saveStr[] = "use Generate\Tables\\$this->database\\{$this->tableName}Table;";
        $this->saveStr[] = 'use Swlib\Admin\Config\PageConfig;';
        $this->saveStr[] = 'use Swlib\Admin\Config\PageFieldsConfig;';
        $this->saveStr[] = 'use Swlib\Admin\Controller\AbstractAdmin;';
        $this->saveStr[] = 'use Swlib\Admin\Fields\Int2TimeField;';
        $this->saveStr[] = 'use Swlib\Admin\Fields\NumberField;';
        $this->saveStr[] = 'use Swlib\Admin\Fields\TextField;';
        $this->saveStr[] = 'use Throwable;';
        $this->saveStr[] = '';
        $this->saveStr[] = '';
        $this->saveStr[] = '';
        $this->saveStr[] = "class {$this->tableName}Admin extends AbstractAdmin{";


        $this->configPage();
        $this->configField();


    }

    public static function createDir(): void
    {
        ParseTable::createDir(self::saveDir);
    }

    private function configPage(): void
    {
        $this->saveStr[] = '    protected function configPage(PageConfig $config): void';
        $this->saveStr[] = '    {';
        $this->saveStr[] = '        $config->pageName = "xxxxx";';
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
            $comment = $item['Comment'] ?: $field;
            $upField = strtoupper($field);
            if ($field == 'id') {
                $this->saveStr[] = "            new NumberField(field: {$this->tableName}Table::$upField, label: 'ID')->hideOnForm(),";
            } elseif (str_contains($field, 'time')) {
                $this->saveStr[] = "            new Int2TimeField(field: {$this->tableName}Table::$upField, label: '$comment'),";
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

        file_put_contents(self::saveDir . $this->database . "/{$this->tableName}Admin.php", implode(PHP_EOL, $this->saveStr));
    }


}