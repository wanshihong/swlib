<?php
declare(strict_types=1);

namespace Swlib\Table;

use Swlib\Exception\AppErr;
use Swlib\Exception\AppException;
use Swlib\Table\Interface\TableInterface;
use Throwable;

/**
 * SQL查询构建器Trait
 * 提供了构建和执行SQL查询的基本功能
 */
class QueryBuild
{
    // 查询 field 字符串
    private string $_field = '*';

    // 本次查询用到那些字段
    private array $_fieldArray = [];

    private ?int $_limit = null;

    private ?int $_offset = null;

    private string $_order = '';

    private string $_lock = '';

    private string $_join = '';

    private string $_where = '1=1';
    private(set) array $whereArray = [];

    private string $_group = '';

    private(set) array $bindParams = [];


    private bool $distinct = false;

    private(set) string $sql = '';


    public function __construct(
        // 查询的实例
        private readonly TableInterface $table,
    )
    {
    }

    /**
     * @throws Throwable
     */
    public function field(string|array $fields): void
    {
        // 不是数组就转换成数组
        if (!is_array($fields)) {
            $fields = explode(',', $fields);
        }
        foreach ($fields as $field) {
            if (!in_array($field, $this->_fieldArray)) {
                $this->_fieldArray[] = $field;
            }
        }


    }

    public function distinct(): void
    {
        $this->distinct = true;

    }


    /**
     * @throws Throwable
     */
    private function _buildField(): void
    {
        if ($this->_field !== '*') {
            // 构造过字段了，例如 count() 查询的时候
            return;
        }


        // 如果没有设置字段，则默认查询全部字段
        if (empty($this->_fieldArray)) {
            $this->field($this->table::FIELD_ALL);
        }

        // 查询字段转换成一维数组
        // 直接写 Table::FIELD_ALL 就是一个全部字段的数组
        // 传入的字段有可能是 [['a','b','c'],'d','e','f']
        // 输出 ['a','b','c','d','e','f']
        $resultFields = [];

        /**
         * array_walk_recursive 会递归地遍历 _fieldArray 中的每个元素
         */
        array_walk_recursive($this->_fieldArray, function ($field) use (&$resultFields) {
            $t = $this->table->formatField($field);
            if (stripos($t, ' as ') === false) {
                $resultFields[] = "$t as $field";
            } else {
                $resultFields[] = $t;
            }
        });
        $resultFields = array_unique($resultFields);
        $distinctStr = $this->distinct ? ' DISTINCT ' : '';
        $this->_field = $distinctStr . implode(',', $resultFields);
    }

    /**
     * 设置分页查询
     *
     * @param int $page 页码，从1开始
     * @param int $pageSize 每页记录数
     * @return void 返回当前实例以支持链式调用
     */
    public function page(int $page, int $pageSize): void
    {
        // 确保页码至少为1
        $page = max(1, $page);
        $this->_limit = $pageSize;
        $this->_offset = ($page - 1) * $pageSize;


    }


    /**
     * 设置查询结果的限制数量
     *
     * @param int $limit 限制返回的记录数
     * @return void 返回当前实例以支持链式调用
     */
    public function limit(int $limit): void
    {
        $this->_limit = $limit;

    }

    /**
     * 设置查询结果的排序方式
     *
     * @param array|Expression $order 排序配置数组，键为字段名，值为排序方式(ASC/DESC)或Expression对象
     *                     支持以下格式：
     *                     - ['field1' => 'ASC', 'field2' => 'DESC'] 普通字段排序
     *                     - ['field1' => new Expression('FIELD(id, 1, 2, 3)')] 使用Expression对象
     *                     - ['field1' => new Expression('`FIELD` ASC')] 使用Expression对象 FIELD` 会替换成  field1
     *                     - [new Expression('RAND()')] 直接使用Expression对象作为排序条件
     * @return void 返回当前实例以支持链式调用
     * @throws AppException
     */
    public function order(array|Expression $order): void
    {
        if (empty($order)) {
            return;
        }

        if ($order instanceof Expression) {
            $this->_order = " ORDER by " . $order->value;
            return;
        }

        $orderArr = [];
        foreach ($order as $field => $orderType) {
            if ($orderType instanceof Expression) {
                if (is_numeric($field)) {
                    // 如果值是Expression对象，直接使用其表达式
                    $orderArr[] = " " . $orderType->value;
                } else {
                    // 传入的 key 如果是 字段别名
                    if (Db::checkAsExists($field)) {
                        $field = $this->table->formatField($field);
                    }
                    // 完成占位符的替换
                    $expression = str_replace('`FIELD`', $field, $orderType->value);
                    $orderArr[] = " " . $expression;
                }
            } else {
                // 普通的字段排序
                // 验证排序方向
                if (!in_array(strtoupper($orderType), ['ASC', 'DESC'])) {
                    throw new AppException(AppErr::DB_ORDER_TYPE_INVALID_WITH_NAME, $orderType);
                }
                $field = $this->table->formatField($field);
                $orderArr[] = " $field $orderType";
            }
        }
        $orderStr = implode(',', $orderArr);
        $this->_order = " ORDER by $orderStr";


    }

    /**
     * 设置查询的WHERE条件
     *
     * @param array $where WHERE条件数组。支持复杂的嵌套条件：
     *                     - 简单条件：[[field1, operator, value1], [field2, operator, value2]]
     *                     - 键值对：[field1 => value1, field2 => value2]
     *                     - 嵌套条件：[[[field1, '=', value1], 'OR', [field2, '=', value2]], 'AND', [field3, '=', value3]]
     *                     - 逻辑连接符：支持 'AND', 'OR' 连接符
     *
     * 支持的操作符：
     * - 基本比较：=, !=, <>, >, <, >=, <=, like
     * - 空值检查：is null, is not null
     * - 范围查询：in, not in, between
     * - JSON查询：json_contains
     *
     * 使用示例：
     * 1. 简单条件：[['name', '=', 'John'], ['age', '>', 18]]
     * 2. 嵌套条件：[['name', '=', 'John'], 'OR', ['name', '=', 'Jane']]
     * 3. 复杂嵌套：[[['status', '=', 1], 'AND', ['type', '=', 'user']], 'OR', [['status', '=', 2]]]
     *
     * @return void 返回当前实例以支持链式调用
     */
    public function where(array $where): void
    {
        if (empty($where)) {
            return;
        }

        // 记录 where 条件
        if (empty($this->whereArray)) {
            // 首次使用  where
            $this->whereArray = $where;
        } else {
            // 多次使用 where, 追加
            $tempWhere = $this->whereArray;
            $this->whereArray = [];
            $this->whereArray[] = $tempWhere;
            $this->whereArray[] = $where;
        }

        // 使用 QueryWhereBuild 构建复杂的 WHERE 条件
        $whereBuild = new QueryWhereBuild();
        $whereBuild->setTable($this->table);

        // 使用 QueryWhereBuild 构建 WHERE 条件
        $result = $whereBuild->buildWhere($where);

        if (!empty($result['sql'])) {
            // 支持多次调用：如果已经有 WHERE 条件，则用 AND 连接
            if ($this->_where !== '1=1') {
                $this->_where = "($this->_where) AND ({$result['sql']})";
            } else {
                $this->_where = $result['sql'];
            }
            $this->bindParams = array_merge($this->bindParams, $result['params']);
        }
    }


    /**
     * 添加FOR UPDATE锁
     *
     * @return void 返回当前实例以支持链式调用
     */
    public function lock(): void
    {
        $this->_lock = ' for update ';

    }


    /**
     * 添加表连接
     *
     * @param string $table 要连接的表名
     * @param string $field 当前表的连接字段
     * @param string $field2 要连接表的连接字段
     * @param JoinEnum $joinType 连接类型(INNER/LEFT/RIGHT)
     * @param string $alias 表别名，如果为空则使用表名
     * @return void 返回当前实例以支持链式调用
     */
    public function join(string $table, string $field, string $field2, JoinEnum $joinType = JoinEnum::INNER, string $alias = ''): void
    {
        $field = $this->table->formatField($field);
        $field2 = $this->table->formatField($field2);
        $joinType = $joinType->value;

        $table = "`$table`";
        if ($alias) {
            $table .= " as $alias ";
        }

        $this->_join .= " $joinType $table ON $field = $field2 ";

    }

    /**
     * 设置GROUP BY分组
     *
     * @param string|Expression $field 分组字段名
     * @return void 返回当前实例以支持链式调用
     */
    public function group(string|Expression $field = ''): void
    {
        if (empty($field)) {
            return;
        }
        if ($field instanceof Expression) {
            $this->_group = " GROUP BY " . $field->value;
        } else {
            $field = $this->table->formatField($field);
            $this->_group = " GROUP BY $field ";
        }
    }


    /**
     * @throws Throwable
     */
    public function find(): string
    {
        $this->limit(1);
        return $this->select();
    }


    /**
     * 获取字段的最大值
     *
     * @param string $field 要查询的字段名
     * @param string $alias 字段别名，默认为 'num'
     * @return void 字段的最大值
     * @throws Throwable 当查询执行失败时抛出异常
     */
    public function max(string $field, string $alias = 'num'): void
    {
        $field = $this->table->formatField($field);
        $this->_field = "MAX($field) as $alias";
        $this->find();

    }

    /**
     * 获取字段的最小值
     *
     * @param string $field 要查询的字段名
     * @param string $alias 字段别名，默认为 'num'
     * @return void 字段的最小值
     * @throws Throwable 当查询执行失败时抛出异常
     */
    public function min(string $field, string $alias = 'num'): void
    {
        $field = $this->table->formatField($field);
        $this->_field = "MIN($field) as $alias";
        $this->find();

    }

    /**
     * 获取字段的总和
     *
     * @param string $field 要求和的字段名或表达式，支持多个字段运算，例如：
     *                     - 'price' 单个字段求和
     *                     - 'price * quantity' 多个字段运算后求和
     *                     - 'price + tax' 多个字段相加后求和
     *                     - 'price - discount' 字段相减后求和
     * @param string $alias 字段别名，默认为 'num'
     * @return void 字段值的总和
     * @throws Throwable 当查询执行失败时抛出异常
     */
    public function sum(string $field, string $alias = 'num'): void
    {
        // 匹配字段名的正则表达式
        $pattern = '/\b([a-zA-Z_][a-zA-Z0-9_]*)\b/';

        // 替换表达式中的字段名为格式化后的字段名
        $processedField = preg_replace_callback($pattern, function ($matches) {
            return $this->table->formatField($matches[1]);
        }, $field);

        $this->_field = "SUM($processedField) as $alias";
        $this->find();

    }

    /**
     * 获取字段的平均值
     *
     * @param string $field 要计算平均值的字段名或表达式，支持多个字段运算，例如：
     *                     - 'price' 单个字段求平均值
     *                     - 'price * quantity' 多个字段运算后求平均值
     *                     - 'price + tax' 多个字段相加后求平均值
     * @param string $alias 字段别名，默认为 'num'
     * @return void 字段的平均值
     * @throws Throwable 当查询执行失败时抛出异常
     */
    public function avg(string $field, string $alias = 'num'): void
    {
        // 匹配字段名的正则表达式
        $pattern = '/\b([a-zA-Z_][a-zA-Z0-9_]*)\b/';

        // 替换表达式中的字段名为格式化后的字段名
        $processedField = preg_replace_callback($pattern, function ($matches) {
            return $this->table->formatField($matches[1]);
        }, $field);

        $this->_field = "AVG($processedField) as $alias";
        $this->find();

    }


    /**
     * 统计记录数
     *
     * @param string $field 要统计的字段，默认为*
     * @param bool $distinct 是否使用DISTINCT
     * @param string $alias 字段别名，默认为 'num'
     * @return void 记录数
     * @throws Throwable 当查询执行失败时抛出异常
     */
    public function count(string $field = '*', bool $distinct = false, string $alias = 'num'): void
    {
        $field = $this->table->formatField($field);
        if ($distinct) {
            $field = "DISTINCT $field";
        }
        $this->_field = "COUNT($field) as $alias";
        $this->find();

    }

    /**
     * 插入单条记录
     *
     * @param array $data 要插入的数据数组，键为字段名，值为字段值
     * @return string 执行SQL
     * @throws AppException
     */
    public function insert(array $data = []): string
    {

        // 检查数据格式
        $data = $this->_checkSaveDataStructure($data);

        $values = [];
        $fields = [];
        foreach ($data as $field => $val) {
            if (is_string($val)) {
                $val = trim($val);
            }
            $values[] = $val;
            $fields[] = $this->table->formatField($field);
        }

        $columns = implode(', ', $fields);
        $q = implode(', ', array_fill(0, count($data), '?'));

        $tableName = $this->table::TABLE_NAME;
        $sql = "INSERT INTO `$tableName` ($columns) VALUES ($q)";
        $this->bindParams = $values;

        $this->sql = $sql;
        return $sql;

    }


    /**
     * 批量插入多条记录
     *
     * @param array $data 要插入的二维数组，每个子数组代表一条记录
     * @return string 执行SQL
     * @throws AppException
     */
    public function insertAll(array $data): string
    {
        foreach ($data as $key => $value) {
            if (!is_array($value)) {
                throw new AppException(AppErr::DB_INSERTALL_DATA_TYPE_INVALID);
            }
            // 检查数据格式
            $data[$key] = $this->_checkSaveDataStructure($value, 'insert-all');
        }

        // 第一步：验证所有记录的字段集合一致性
        $firstRowFields = array_keys($data[0]);
        foreach ($data as $index => $row) {
            $currentFields = array_keys($row);

            // 校验字段集合是否一致（不考虑顺序）
            $firstRowFieldsSet = array_flip($firstRowFields);
            $currentFieldsSet = array_flip($currentFields);
            if (array_keys($firstRowFieldsSet) !== array_keys($currentFieldsSet)) {
                $missingFields = array_diff($firstRowFields, $currentFields);
                $extraFields = array_diff($currentFields, $firstRowFields);
                $errorMsg = "第 $index 条记录的字段集合与第一条不一致";
                if (!empty($missingFields)) {
                    $errorMsg .= "，缺少字段：" . implode(', ', $missingFields);
                }
                if (!empty($extraFields)) {
                    $errorMsg .= "，多余字段：" . implode(', ', $extraFields);
                }
                throw new AppException($errorMsg);
            }
        }

        // 第二步：按首行字段顺序重排每一行数据
        foreach ($data as $index => $row) {
            $reorderedRow = [];
            foreach ($firstRowFields as $field) {
                $reorderedRow[$field] = $row[$field];
            }
            $data[$index] = $reorderedRow;
        }

        // 获取到字段
        $fields = [];
        foreach ($data[0] as $field => $val) {
            $fields[] = $this->table->formatField($field);
        }
        $columns = implode(', ', $fields);

        // 获取到每个字段的值
        $values = [];
        foreach ($data as $val) {
            foreach ($val as $value) {
                if (is_string($value)) {
                    $value = trim($value);
                }
                $values[] = $value;
            }
        }

        // 问号填充
        $qArr = [];
        foreach ($data as $item) {
            $qArr[] = '(' . implode(', ', array_fill(0, count($item), '?')) . ')';
        }
        $q = implode(',', $qArr);

        $tableName = $this->table::TABLE_NAME;
        $sql = "INSERT INTO `$tableName` ($columns) VALUES $q";
        $this->bindParams = $values;
        $this->sql = $sql;
        return $sql;

    }

    /**
     * 更新记录
     *
     * @param array $data 要更新的数据数组，键为字段名，值为新的字段值
     * @return string
     * @throws AppException
     */
    public function update(array $data = []): string
    {

        if (empty($this->_where) || $this->_where == '1=1') {
            throw new AppException(AppErr::DB_WHERE_REQUIRED);
        }

        // 检查数据格式
        $data = $this->_checkSaveDataStructure($data, 'update');

        $fields = [];
        $updateParams = [];
        foreach ($data as $field => $value) {
            if (is_string($value)) {
                $value = trim($value);
            }
            $sqlField = $this->table->formatField($field);

            if ($value instanceof Expression) {
                // 处理表达式，例如原子更新
                $expression = str_replace('`FIELD`', $sqlField, $value->value);
                $fields[] = "$sqlField = $expression";
            } else {
                $fields[] = "$sqlField = ?";
                $updateParams[] = $value;
            }
        }

        $updateField = implode(',', $fields);
        $tableName = $this->table::TABLE_NAME;
        $sql = "UPDATE `$tableName` SET $updateField  WHERE $this->_where";
        if ($this->_limit) {
            $sql .= " LIMIT $this->_limit ";
        }
        $params = array_merge($updateParams, $this->bindParams);
        $this->bindParams = $params;
        $this->sql = $sql;
        return $sql;

    }

    /**
     * 删除记录
     *
     * @return string 受影响的行数
     * @throws AppException
     */
    public function delete(): string
    {
        if (empty($this->_where) || $this->_where == '1=1') {
            throw new AppException(AppErr::DB_WHERE_REQUIRED);
        }
        $tableName = $this->table::TABLE_NAME;
        $sql = "DELETE FROM `$tableName` WHERE $this->_where";
        if ($this->_limit) {
            $sql .= " LIMIT $this->_limit ";
        }
        $this->sql = $sql;
        return $sql;
    }


    /**
     * 拼接SQL 预计，注意查询顺序
     * @return string
     * @throws Throwable
     */
    public function select(): string
    {
        $this->_buildField();
        $tableName = $this->table::TABLE_NAME;
        $sql = "SELECT $this->_field FROM `$tableName` $this->_join";

        if ($this->_where) {
            $sql .= " WHERE $this->_where";
        }

        if ($this->_group) {
            $sql .= $this->_group;
        }

        if ($this->_order) {
            $sql .= $this->_order;
        }

        if ($this->_limit) {
            if ($this->_offset) {
                $sql .= " LIMIT $this->_offset,$this->_limit  ";
            } else {
                $sql .= " LIMIT $this->_limit ";
            }
        }

        if ($this->_lock) {
            $sql .= $this->_lock;
        }

        $this->sql = $sql;
        return $sql;
    }


    /**
     * 检查保存的数据结构是否合法
     *
     * @param array $data 要检查的数据数组
     * @param string $operation 操作类型：'insert' 或 'update'
     * @throws AppException 当数据结构不合法时抛出异常
     */
    private function _checkSaveDataStructure(array $data, string $operation = 'insert'): array
    {
        $ret = [];
        foreach ($data as $field => $value) {
            // 1. 处理空值且数据库允许NULL的情况 (排除 0/"0")
            if (!is_numeric($value) && empty($value) && ($this->table::DB_ALLOW_NULL[$field] ?? false)) {
                if ($operation === 'insert') {
                    continue; // insert 时直接跳过，交由数据库默认处理
                }
                // update 或 insert-all 时，强制使用数据库默认值以保持字段完整性
                $ret[$field] = $this->table::DB_DEFAULT[$field];
                continue;
            }

            // 2. 类型安全检查
            if (is_array($value)) {
                throw new AppException(AppErr::DB_FIELD_TYPE_INVALID_WITH_NAME, $field);
            }
            if (is_resource($value)) {
                throw new AppException(AppErr::DB_FIELD_TYPE_INVALID_WITH_NAME, $field);
            }
            if (is_object($value) && !($value instanceof Expression)) {
                throw new AppException(AppErr::DB_FIELD_TYPE_INVALID_WITH_NAME, $field);
            }

            $ret[$field] = $value;
        }

        return $ret;
    }


}
