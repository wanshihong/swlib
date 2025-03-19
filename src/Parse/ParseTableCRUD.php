<?php
declare(strict_types=1);

namespace Swlib\Parse;


use Exception;
use Swlib\Utils\Func;


class ParseTableCRUD
{

    const string saveDir = RUNTIME_DIR . "codes/crud/";

    private array $saveStr = [];

    /**
     * @throws Exception
     */
    public function __construct(public $database, public string $tableName, public array $fields)
    {


        $this->tableName = Func::underscoreToCamelCase($this->tableName);

        $this->saveStr[] = '<?php';
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
        $this->saveStr[] = '';
        $this->saveStr[] = '#[Router(method: \'POST\')]';
        $this->saveStr[] = "class $this->tableName extends AbstractController{
                ";


        $this->createSave();
        $this->createLists();
        $this->createDetail();
        $this->createRemove();

    }

    public static function createDir(): void
    {
        ParseTable::createDir(self::saveDir);
    }

    public function __destruct()
    {
        $this->saveStr[] = '}';
        file_put_contents(self::saveDir . "$this->database/$this->tableName.php", implode(PHP_EOL, $this->saveStr));
    }


    private function createSave(): void
    {

        $this->saveStr[] = '';
        $this->saveStr[] = '    /**';
        $this->saveStr[] = '    * @throws Throwable';
        $this->saveStr[] = '    */';
        $this->saveStr[] = '    #[Router(errorTitle: \'保存数据失败\')]';
        $this->saveStr[] = '    public function save(' . $this->tableName . 'Proto $request): Success';
        $this->saveStr[] = '    {';
        $this->saveStr[] = '        $table = ' . $this->tableName . 'Model::request($request);';
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

            $fieldName = Func::underscoreToCamelCase($field);
            $lcFieldName = lcfirst($fieldName);
            $this->saveStr[] = "        if (empty(\$table->$lcFieldName)){";
            $this->saveStr[] = '            throw new AppException(\'请输入' . ($comment ?: $lcFieldName) . '\');';
            $this->saveStr[] = '        }';
        }
        $this->saveStr[] = '';
        $this->saveStr[] = "        \$res = \$table->save();";
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
        $this->saveStr[] = '    * @throws Throwable';
        $this->saveStr[] = '    */';
        $this->saveStr[] = '    #[Router(errorTitle: \'获取列表数据失败\')]';
        $this->saveStr[] = '    public function lists(' . $this->tableName . 'Proto $request): ' . $this->tableName . 'ListsProto';
        $this->saveStr[] = '    {';
        $this->saveStr[] = '        $page = $request->getQueryPageNo() ?: 1;';
        $this->saveStr[] = '        $size = $request->getQueryPageSize() ?: 10;';
        $this->saveStr[] = '';
        $this->saveStr[] = '        $where = [];';
        $this->saveStr[] = '        $order = [' . $this->tableName . 'Table::ID=>"desc"];';
        $this->saveStr[] = '        $' . lcfirst($this->tableName) . 'Table = new ' . $this->tableName . 'Table();';
        $this->saveStr[] = '        $lists = $' . lcfirst($this->tableName) . 'Table->order($order)->where($where)->page($page, $size)->selectAll();';
        $this->saveStr[] = '';
        $this->saveStr[] = '        $protoLists = [];';
        $this->saveStr[] = '        foreach ($lists as $table) {';
        $this->saveStr[] = '            $proto = ' . $this->tableName . 'Model::formatItem($table);';
        $this->saveStr[] = '            // 其他自定义字段格式化';
        $this->saveStr[] = '            $protoLists[] = $proto;';
        $this->saveStr[] = '        }';
        $this->saveStr[] = '';
        $this->saveStr[] = '        $ret = new ' . $this->tableName . 'ListsProto();';
        $this->saveStr[] = '        $ret->setLists($protoLists);';
        $this->saveStr[] = '        return $ret;';
        $this->saveStr[] = '    }';
    }

    private function createDetail(): void
    {
        $this->saveStr[] = '';
        $this->saveStr[] = '    /**';
        $this->saveStr[] = '    * @throws Throwable';
        $this->saveStr[] = '    */';
        $this->saveStr[] = '    #[Router(errorTitle: \'查看详情失败\')]';
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
        $this->saveStr[] = '    * @throws Throwable';
        $this->saveStr[] = '    */';
        $this->saveStr[] = '    #[Router(errorTitle: \'删除数据失败\')]';
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