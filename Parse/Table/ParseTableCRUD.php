<?php
declare(strict_types=1);

namespace Swlib\Parse\Table;


use Exception;
use Generate\DatabaseConnect;
use Swlib\Utils\File;
use Swlib\Utils\StringConverter;

class ParseTableCRUD
{

    const string saveDir = RUNTIME_DIR . "codes/crud/";

    /**
     * @throws Exception
     */
    public function __construct(public $database, public string $tableName, public array $fields, public string $tableComment)
    {
        $this->saveFile('Save', $this->createSave());
        $this->saveFile('Lists', $this->createLists());
        $this->saveFile('Detail', $this->createDetail());
        $this->saveFile('Remove', $this->createRemove());
    }

    public static function createDir(): void
    {
        ParseTable::createDir(self::saveDir, false);
    }

    public function saveFile($actionName, array $ctx): void
    {

        $this->tableName = StringConverter::underscoreToCamelCase($this->tableName);
        $namespace = DatabaseConnect::getNamespace($this->database);
        $saveStr[] = "<?php //$this->tableName";
        $saveStr[] = 'namespace App\Curd\\' . $namespace . ';';
        $saveStr[] = '';
        $saveStr[] = '';
        $saveStr[] = 'use Swlib\Controller\Abstract\AbstractController;';
        $saveStr[] = 'use Swlib\Exception\AppException;';
        $saveStr[] = 'use Protobuf\Common\Success;';
        $saveStr[] = 'use Swlib\Router\Router;';
        $saveStr[] = "use Generate\Models\\$namespace\\{$this->tableName}Model;";
        $saveStr[] = "use Generate\Tables\\$namespace\\{$this->tableName}Table;";
        $saveStr[] = 'use Protobuf\\' . $namespace . '\\' . $this->tableName . '\\' . $this->tableName . 'Proto;';
        $saveStr[] = 'use Protobuf\\' . $namespace . '\\' . $this->tableName . '\\' . $this->tableName . 'ListsProto;';
        $saveStr[] = 'use Throwable;';
        $saveStr[] = '';
        $saveStr[] = '';
        $saveStr[] = "/*";
        $saveStr[] = "* $this->tableComment";
        $saveStr[] = "*/";
        $saveStr[] = '#[Router(method: \'POST\')]';
        $saveStr[] = "class {$this->tableName}Api extends AbstractController{\n";

        foreach ($ctx as $ctxItem) {
            $saveStr[] = $ctxItem;
        }

        $saveStr[] = '}';

        $file = self::saveDir . "$this->database/$this->tableName/$actionName.php";
        File::save($file, implode(PHP_EOL, $saveStr));
    }


    private function createSave(): array
    {

        $saveStr[] = '';
        $saveStr[] = '    /**';
        $saveStr[] = '    /* 更新和创建';
        $saveStr[] = '    * @throws Throwable';
        $saveStr[] = '    */';
        $saveStr[] = '    #[Router(errorTitle: \'保存' . $this->tableComment . '失败\')]';
        $saveStr[] = '    public function run(' . $this->tableName . 'Proto $request): Success';
        $saveStr[] = '    {';
        $saveStr[] = '        $dto = ' . $this->tableName . 'Model::request($request);';
        $saveStr[] = '';
        foreach ($this->fields as $item) {
            $field = $item['Field'];
            if ($field === 'id') {
                $comment = '';
            } else {
                $comment = trim($item['Comment']);
                $comment = explode("\n", $comment);
                $comment = trim($comment[0]);
            }

            $fieldName = StringConverter::underscoreToCamelCase($field);
            $lcFieldName = lcfirst($fieldName);
            $saveStr[] = "        if (empty(\$dto->$lcFieldName)){";
            $saveStr[] = '            throw new AppException(\'请输入' . ($comment ?: $lcFieldName) . '\');';
            $saveStr[] = '        }';
        }
        $saveStr[] = '';
        $saveStr[] = "        \$res = \$dto->save();";
        $saveStr[] = '';
        $saveStr[] = '        $msg = new Success();';
        $saveStr[] = '        $msg->setSuccess((bool)$res);';
        $saveStr[] = '        return $msg;';
        $saveStr[] = '    }';
        return $saveStr;

    }


    private function createLists(): array
    {
        $saveStr[] = '';
        $saveStr[] = '    /**';
        $saveStr[] = '    /* 查询列表';
        $saveStr[] = '    * @throws Throwable';
        $saveStr[] = '    */';
        $saveStr[] = '    #[Router(errorTitle: \'获取' . $this->tableComment . '列表数据失败\')]';
        $saveStr[] = '    public function run(' . $this->tableName . 'Proto $request): ' . $this->tableName . 'ListsProto';
        $saveStr[] = '    {';
        $saveStr[] = '        $pageNumber = $request->getPageNumber() ?: 1;';
        $saveStr[] = '        $pageSize = $request->getPageSize() ?: 10;';
        $saveStr[] = '';
        $saveStr[] = '        $where = [];';
        $saveStr[] = '        $order = [' . $this->tableName . 'Table::PRI_KEY=>"desc"];';
        $saveStr[] = '        $' . lcfirst($this->tableName) . 'Table = new ' . $this->tableName . 'Table();';
        $saveStr[] = '        $dto = $' . lcfirst($this->tableName) . 'Table->order($order)->where($where)->page($pageNumber, $pageSize)->selectAll();';
        $saveStr[] = '        $total = $' . lcfirst($this->tableName) . 'Table->where($where)->count();';
        $saveStr[] = '        $totalPage = (int)ceil($total / $pageSize);;';
        $saveStr[] = '';
        $saveStr[] = '        $protoLists = [];';
        $name = '$' . lcfirst($this->tableName) . 'Dto';
        $saveStr[] = "        foreach (\$dto as $name) {";
        $saveStr[] = "            \$proto = {$this->tableName}Model::formatItem($name);";
        $saveStr[] = '            // 其他自定义字段格式化';
        $saveStr[] = '            $protoLists[] = $proto;';
        $saveStr[] = '        }';
        $saveStr[] = '';
        $saveStr[] = '        $ret = new ' . $this->tableName . 'ListsProto();';
        $saveStr[] = '        $ret->setTotal($total);';
        $saveStr[] = '        $ret->setTotal($total);';
        $saveStr[] = '        $ret->setCurrPage($pageNumber);';
        $saveStr[] = '        $ret->setTotalPage($totalPage);';
        $saveStr[] = '        $ret->setLists($protoLists);';
        $saveStr[] = '        return $ret;';
        $saveStr[] = '    }';
        return $saveStr;
    }

    private function createDetail(): array
    {
        $saveStr[] = '';
        $saveStr[] = '    /**';
        $saveStr[] = '    /* 查询详情';
        $saveStr[] = '    * @throws Throwable';
        $saveStr[] = '    */';
        $saveStr[] = '    #[Router(errorTitle: \'查看' . $this->tableComment . '详情失败\')]';
        $saveStr[] = '    public function run(' . $this->tableName . 'Proto $request): ' . $this->tableName . 'Proto';
        $saveStr[] = '    {';
        $saveStr[] = '        $id = $request->getId();';
        $saveStr[] = '        if(empty($id)){';
        $saveStr[] = '            throw new AppException("缺少参数");';
        $saveStr[] = '        }';
        $saveStr[] = '';
        $saveStr[] = '        $dto = new ' . $this->tableName . 'Table()->where([';
        $saveStr[] = '            ' . $this->tableName . 'Table::ID=>$id,';
        $saveStr[] = '        ])->selectOne();';
        $saveStr[] = '        if(empty($dto)){';
        $saveStr[] = '            throw new AppException("参数错误");';
        $saveStr[] = '        }';
        $saveStr[] = '';
        $saveStr[] = '        return ' . $this->tableName . 'Model::formatItem($dto);';
        $saveStr[] = '    }';
        return $saveStr;
    }

    private function createRemove(): array
    {
        $saveStr[] = '';
        $saveStr[] = '    /**';
        $saveStr[] = '    /* 删除';
        $saveStr[] = '    * @throws Throwable';
        $saveStr[] = '    */';
        $saveStr[] = '    #[Router(errorTitle: \'删除' . $this->tableComment . '失败\')]';
        $saveStr[] = '    public function run(' . $this->tableName . 'Proto $request): Success';
        $saveStr[] = '    {';
        $saveStr[] = '        $id = $request->getId();';
        $saveStr[] = '        if(empty($id)){';
        $saveStr[] = '            throw new AppException("参数错误");';
        $saveStr[] = '        }';
        $saveStr[] = '';
        $saveStr[] = '        $res = new ' . $this->tableName . 'Table()->where([';
        $saveStr[] = '            ' . $this->tableName . 'Table::ID=>$id,';
        $saveStr[] = '        ])->delete();';
        $saveStr[] = '';
        $saveStr[] = '        $msg = new Success();';
        $saveStr[] = '        $msg->setSuccess((bool)$res);';
        $saveStr[] = '        return $msg;';
        $saveStr[] = '    }';
        return $saveStr;
    }


}