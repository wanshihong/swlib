<?php
declare(strict_types=1);

namespace Swlib\Parse\Table;


use Exception;
use Swlib\Utils\File;
use Swlib\Utils\StringConverter;

class ParseTableCRUD
{

    const string saveDir = RUNTIME_DIR . "codes/crud/";

    private array $saveStr = [];
    private string $pathPrefix;

    /**
     * @throws Exception
     */
    public function __construct(public $database, public string $tableName, public array $fields, public string $tableComment)
    {

        $this->pathPrefix = StringConverter::getPrefixBeforeUnderscore($this->tableName);
        $this->tableName = StringConverter::underscoreToCamelCase($this->tableName);

        $this->saveStr[] = "<?php //$tableName";
        $this->saveStr[] = 'namespace App\Curd\\' . $this->database . ';';
        $this->saveStr[] = '';
        $this->saveStr[] = '';
        $this->saveStr[] = 'use Swlib\Controller\AbstractController;';
        $this->saveStr[] = 'use Swlib\Exception\AppException;';
        $this->saveStr[] = 'use Protobuf\Common\Success;';
        $this->saveStr[] = 'use Swlib\Router\Router;';
        $this->saveStr[] = "use Generate\Models\\$this->database\\{$this->tableName}Model;";
        $this->saveStr[] = "use Generate\Tables\\$database\\{$this->tableName}Table;";
        $this->saveStr[] = 'use Protobuf\\' . $this->database . '\\' . $this->tableName . '\\' . $this->tableName . 'Proto;';
        $this->saveStr[] = 'use Protobuf\\' . $this->database . '\\' . $this->tableName . '\\' . $this->tableName . 'ListsProto;';
        $this->saveStr[] = 'use Throwable;';
        $this->saveStr[] = '';
        $this->saveStr[] = '';
        $this->saveStr[] = "/*";
        $this->saveStr[] = "* $tableComment";
        $this->saveStr[] = "*/";
        $this->saveStr[] = '#[Router(method: \'POST\')]';
        $this->saveStr[] = "class {$this->tableName}Api extends AbstractController{\n";


        $this->createSave();
        $this->createLists();
        $this->createDetail();
        $this->createRemove();

    }

    public static function createDir(): void
    {
        ParseTable::createDir(self::saveDir, false);
    }

    public function __destruct()
    {
        $this->saveStr[] = '}';
        $file = self::saveDir . "$this->database/$this->pathPrefix/{$this->tableName}Api.php";
        File::save($file, implode(PHP_EOL, $this->saveStr));
    }


    private function createSave(): void
    {

        $this->saveStr[] = '';
        $this->saveStr[] = '    /**';
        $this->saveStr[] = '    /* 更新和创建';
        $this->saveStr[] = '    * @throws Throwable';
        $this->saveStr[] = '    */';
        $this->saveStr[] = '    #[Router(errorTitle: \'保存' . $this->tableComment . '失败\')]';
        $this->saveStr[] = '    public function save(' . $this->tableName . 'Proto $request): Success';
        $this->saveStr[] = '    {';
        $this->saveStr[] = '        $dto = ' . $this->tableName . 'Model::request($request);';
        $this->saveStr[] = '';
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
            $this->saveStr[] = "        if (empty(\$dto->$lcFieldName)){";
            $this->saveStr[] = '            throw new AppException(\'请输入' . ($comment ?: $lcFieldName) . '\');';
            $this->saveStr[] = '        }';
        }
        $this->saveStr[] = '';
        $this->saveStr[] = "        \$res = \$dto->save();";
        $this->saveStr[] = '';
        $this->saveStr[] = '        $msg = new Success();';
        $this->saveStr[] = '        $msg->setSuccess((bool)$res);';
        $this->saveStr[] = '        return $msg;';
        $this->saveStr[] = '    }';

    }


    private function createLists(): void
    {
        $this->saveStr[] = '';
        $this->saveStr[] = '    /**';
        $this->saveStr[] = '    /* 查询列表';
        $this->saveStr[] = '    * @throws Throwable';
        $this->saveStr[] = '    */';
        $this->saveStr[] = '    #[Router(errorTitle: \'获取' . $this->tableComment . '列表数据失败\')]';
        $this->saveStr[] = '    public function lists(' . $this->tableName . 'Proto $request): ' . $this->tableName . 'ListsProto';
        $this->saveStr[] = '    {';
        $this->saveStr[] = '        $pageNumber = $request->getExt()?->getPage() ?: 1;';
        $this->saveStr[] = '        $pageSize = $request->getExt()?->getSize() ?: 10;';
        $this->saveStr[] = '';
        $this->saveStr[] = '        $where = [];';
        $this->saveStr[] = '        $order = [' . $this->tableName . 'Table::PRI_KEY=>"desc"];';
        $this->saveStr[] = '        $' . lcfirst($this->tableName) . 'Table = new ' . $this->tableName . 'Table();';
        $this->saveStr[] = '        $dto = $' . lcfirst($this->tableName) . 'Table->order($order)->where($where)->page($pageNumber, $pageSize)->selectAll();';
        $this->saveStr[] = '        $total = $' . lcfirst($this->tableName) . 'Table->where($where)->count();';
        $this->saveStr[] = '        $totalPage = (int)ceil($total / $pageSize);;';
        $this->saveStr[] = '';
        $this->saveStr[] = '        $protoLists = [];';
        $name = '$' . lcfirst($this->tableName) . 'Dto';
        $this->saveStr[] = "        foreach (\$dto as $name) {";
        $this->saveStr[] = "            \$proto = {$this->tableName}Model::formatItem($name);";
        $this->saveStr[] = '            // 其他自定义字段格式化';
        $this->saveStr[] = '            $protoLists[] = $proto;';
        $this->saveStr[] = '        }';
        $this->saveStr[] = '';
        $this->saveStr[] = '        $ret = new ' . $this->tableName . 'ListsProto();';
        $this->saveStr[] = '        $ret->setTotal($total);';
        $this->saveStr[] = '        $ret->setTotal($total);';
        $this->saveStr[] = '        $ret->setCurrPage($pageNumber);';
        $this->saveStr[] = '        $ret->setTotalPage($totalPage);';
        $this->saveStr[] = '        $ret->setLists($protoLists);';
        $this->saveStr[] = '        return $ret;';
        $this->saveStr[] = '    }';
    }

    private function createDetail(): void
    {
        $this->saveStr[] = '';
        $this->saveStr[] = '    /**';
        $this->saveStr[] = '    /* 查询详情';
        $this->saveStr[] = '    * @throws Throwable';
        $this->saveStr[] = '    */';
        $this->saveStr[] = '    #[Router(errorTitle: \'查看' . $this->tableComment . '详情失败\')]';
        $this->saveStr[] = '    public function detail(' . $this->tableName . 'Proto $request): ' . $this->tableName . 'Proto';
        $this->saveStr[] = '    {';
        $this->saveStr[] = '        $id = $request->getId();';
        $this->saveStr[] = '        if(empty($id)){';
        $this->saveStr[] = '            throw new AppException("缺少参数");';
        $this->saveStr[] = '        }';
        $this->saveStr[] = '';
        $this->saveStr[] = '        $table = new ' . $this->tableName . 'Table()->where([';
        $this->saveStr[] = '            ' . $this->tableName . 'Table::ID=>$id,';
        $this->saveStr[] = '        ])->selectOne();';
        $this->saveStr[] = '        if(empty($table)){';
        $this->saveStr[] = '            throw new AppException("参数错误");';
        $this->saveStr[] = '        }';
        $this->saveStr[] = '';
        $this->saveStr[] = '        return ' . $this->tableName . 'Model::formatItem($table);';
        $this->saveStr[] = '    }';
    }

    private function createRemove(): void
    {
        $this->saveStr[] = '';
        $this->saveStr[] = '    /**';
        $this->saveStr[] = '    /* 删除';
        $this->saveStr[] = '    * @throws Throwable';
        $this->saveStr[] = '    */';
        $this->saveStr[] = '    #[Router(errorTitle: \'删除' . $this->tableComment . '失败\')]';
        $this->saveStr[] = '    public function delete(' . $this->tableName . 'Proto $request): Success';
        $this->saveStr[] = '    {';
        $this->saveStr[] = '        $id = $request->getId();';
        $this->saveStr[] = '        if(empty($id)){';
        $this->saveStr[] = '            throw new AppException("参数错误");';
        $this->saveStr[] = '        }';
        $this->saveStr[] = '';
        $this->saveStr[] = '        $res = new ' . $this->tableName . 'Table()->where([';
        $this->saveStr[] = '            ' . $this->tableName . 'Table::ID=>$id,';
        $this->saveStr[] = '        ])->delete();';
        $this->saveStr[] = '';
        $this->saveStr[] = '        $msg = new Success();';
        $this->saveStr[] = '        $msg->setSuccess((bool)$res);';
        $this->saveStr[] = '        return $msg;';
        $this->saveStr[] = '    }';
    }


}