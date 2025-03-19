<?php
declare(strict_types=1);

namespace Swlib\Table;

use Generate\ConfigEnum;
use Swlib\Exception\AppException;
use Throwable;

/**
 * SQL查询构建器Trait
 * 提供了构建和执行SQL查询的基本功能
 */
trait SqlTrait
{
    protected string $_field = '*';
    protected array $_fieldArray = [];

    private ?int $_limit = null;

    private ?int $_offset = null;

    private string $_order = '';

    private string $_lock = '';

    private string $_join = '';

    private string $_where = '1=1';

    private string $_group = '';

    private array $_bindParams = [];

    private string $_sql = '';

    private bool $distinct = false;

    private int $cacheTime = 0;
    private string $cacheKey = '';
    private Db $iteratorDb;


    /**
     * @throws Throwable
     */
    public function field(string|array $fields): static
    {
        // 不是数组就转换成数组
        if (!is_array($fields)) {
            $fields = explode(',', $fields);
        }
        foreach ($fields as $field) {
            $this->_fieldArray[] = $field;
        }

        return $this;
    }

    public function distinct(): static
    {
        $this->distinct = true;
        return $this;
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
            $this->field(static::FIELD_ALL);
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
            $t = $this->formatField($field);
            $resultFields[] = "$t as $field";
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
     * @return static 返回当前实例以支持链式调用
     */
    public function page(int $page, int $pageSize): static
    {
        // 确保页码至少为1
        $page = max(1, $page);
        $this->_limit = $pageSize;
        $this->_offset = ($page - 1) * $pageSize;

        return $this;
    }


    /**
     * 设置查询结果的限制数量
     *
     * @param int $limit 限制返回的记录数
     * @return static 返回当前实例以支持链式调用
     */
    public function limit(int $limit): static
    {
        $this->_limit = $limit;
        return $this;
    }

    /**
     * 设置查询结果的排序方式
     *
     * @param array $order 排序配置数组，键为字段名，值为排序方式(ASC/DESC)
     * @return static 返回当前实例以支持链式调用
     * @throws Throwable 当字段格式化失败时抛出异常
     */
    public function order(array $order): static
    {
        if (empty($order)) {
            return $this;
        }

        $orderArr = [];
        foreach ($order as $field => $orderType) {
            $field = $this->formatField($field);
            $orderArr[] = " $field $orderType";
        }
        $orderStr = implode(',', $orderArr);
        $this->_order = " ORDER by $orderStr";

        return $this;
    }

    /**
     * 设置查询的WHERE条件
     *
     * @param array $where WHERE条件数组。格式可以是：
     *                     [[field1, operator, value1], [field2, operator, value2]]
     *                     或 [field1 => value1, field2 => value2]
     *
     * 查询条件数组中的每一项中的  value 如果是一个数组，则会对这一项的 field 生成 or 查询
     * 示例1： [[name,like,[%张三%,%李四%]]  会生成 name like '%张三%' or name like '%李四%'
     * 示例2： [[name,<>,[张三,李四]]  会生成 name <> '张三' or name <> '李四'
     *
     *
     * 【推荐】一般的数据库等于查询，如非必要，可以使用 in 查询的方式
     * [[name,in,[张三,李四]] 会生成 name in ('张三','李四')
     *
     * @return static 返回当前实例以支持链式调用
     * @throws Throwable 当条件构建失败时抛出异常
     */
    public function where(array $where): static
    {
        if (empty($where)) {
            return $this;
        }
        $where = $this->_getWhere($where);
        if (empty($where)) {
            return $this;
        }
        $this->_where = implode(' AND ', $where);

        return $this;
    }


    /**
     * 使用OR连接的WHERE条件
     *
     * @param array $where WHERE条件数组，格式同where()方法
     * @return static 返回当前实例以支持链式调用
     * @throws Throwable 当条件构建失败时抛出异常
     */
    public function whereOr(array $where): static
    {
        if (empty($where)) {
            return $this;
        }
        $where = $this->_getWhere($where);
        $this->_where = implode(' OR ', $where);
        return $this;
    }

    /**
     * 组合AND和OR条件的WHERE查询
     *
     * @param array $andWhere AND条件数组
     * @param array $orWhere OR条件数组
     * @param string $connector 连接AND和OR条件的操作符
     * @return static 返回当前实例以支持链式调用
     * @throws Throwable 当条件构建失败时抛出异常
     */
    public function whereAndOr(array $andWhere, array $orWhere, string $connector = 'AND'): static
    {
        $andStr = implode(' AND ', $this->_getWhere($andWhere));
        $orStr = implode(' OR ', $this->_getWhere($orWhere));

        if ($andStr && $orStr) {
            $this->_where = " ($andStr) $connector ($orStr) ";
        } elseif ($andStr && empty($orStr)) {
            $this->_where = " ($andStr) ";
        } elseif (empty($andStr) && $orStr) {
            $this->_where = " 1=1 $connector ($orStr) ";
        }

        return $this;
    }


    /**
     * 添加单个WHERE条件
     *
     * @param string $field 字段名
     * @param string|int|array $value 查询值
     * @param string $operator 操作符(=, >, <, LIKE等)
     * @param string $logic 与其他条件的连接逻辑(AND/OR)
     * @return static 返回当前实例以支持链式调用
     * @throws Throwable 当条件构建失败时抛出异常
     */
    public function addWhere(string $field, string|int|array $value, string $operator = '=', string $logic = "AND"): static
    {
        $retWhere = $this->_getWhere([[$field, $operator, $value]]);
        if (!empty($retWhere)) {
            $this->_where .= " $logic " . $retWhere[0];
        }
        return $this;
    }


    /**
     * 添加原始SQL WHERE条件
     *
     * @param string $sql 原始SQL条件语句
     * @return static 返回当前实例以支持链式调用
     * @throws Throwable 当SQL语句无效时抛出异常
     */
    public function addWhereRaw(string $sql, array $bindParams = []): static
    {
        if (ConfigEnum::APP_DEV === APP_ENV_DEV && empty($bindParams)) {
            echo '警告：使用addWhereRaw方法未提供参数绑定，可能存在SQL注入风险' . PHP_EOL;
        }
        $this->_where .= " $sql ";
        $this->_bindParams = array_merge($this->_bindParams, $bindParams);
        return $this;
    }


    /**
     * 添加FOR UPDATE锁
     *
     * @return static 返回当前实例以支持链式调用
     */
    public function lock(): static
    {
        $this->_lock = ' for update ';
        return $this;
    }


    /**
     * 添加表连接
     *
     * @param string $table 要连接的表名
     * @param string $field 当前表的连接字段
     * @param string $field2 要连接表的连接字段
     * @param JoinEnum $joinType 连接类型(INNER/LEFT/RIGHT)
     * @return static 返回当前实例以支持链式调用
     * @throws Throwable 当字段格式化失败时抛出异常
     */
    public function join(string $table, string $field, string $field2, JoinEnum $joinType = JoinEnum::INNER): static
    {
        $field = $this->formatField($field);
        $field2 = $this->formatField($field2);
        $joinType = $joinType->value;
        $this->_join .= " $joinType $table ON $field = $field2 ";
        return $this;
    }

    /**
     * 设置GROUP BY分组
     *
     * @param string $field 分组字段名
     * @return static 返回当前实例以支持链式调用
     * @throws Throwable 当字段格式化失败时抛出异常
     */
    public function group(string $field = ''): static
    {
        if ($field) {
            $field = $this->formatField($field);
            $this->_group = " GROUP BY $field ";
        }

        return $this;
    }

    /**
     * 执行SELECT查询
     *
     * @return array 查询结果数组
     * @throws Throwable 当查询执行失败时抛出异常
     */
    protected function select(): array
    {
        $this->_buildSql();
        $db = new Db(Db::ACTION_GET_RESULT, $this->_sql, $this->_bindParams, $this->debugSql, self::DATABASES);
        if ($this->cacheTime > 0) {
            return $db->getCacheResult($this->cacheTime, $this->cacheKey);
        }
        return $db->getResult();
    }

    /**
     * 获取查询结果的迭代器
     *
     * @return iterable 查询结果的迭代器
     * @throws Throwable 当查询执行失败时抛出异常
     */
    protected function getIterator(): iterable
    {
        $this->_buildSql();
        $db = new Db(Db::ACTION_GET_ITERATOR, $this->_sql, $this->_bindParams, $this->debugSql, self::DATABASES);
        $this->iteratorDb = $db;
        return $db->getIterator();
    }

    protected function close(): void
    {
        $this->iteratorDb->close();
    }


    /**
     * @throws Throwable
     */
    protected function find(): array
    {
        $this->limit(1);
        $this->_buildSql();
        $db = new Db(Db::ACTION_GET_RESULT, $this->_sql, $this->_bindParams, $this->debugSql, self::DATABASES);
        if ($this->cacheTime > 0) {
            $res = $db->getCacheResult($this->cacheTime, $this->cacheKey);
        } else {
            $res = $db->getResult();
        }
        return $res ? $res[0] : [];
    }


    /**
     * 获取字段的最大值
     *
     * @param string $field 要查询的字段名
     * @return string|int 字段的最大值
     * @throws Throwable 当查询执行失败时抛出异常
     */
    public function max(string $field): string|int
    {
        $field = $this->formatField($field);
        $this->_field = "MAX($field) as num";
        $res = $this->find();
        return $res ? $res['num'] : 0;
    }

    /**
     * 获取字段的最小值
     *
     * @param string $field 要查询的字段名
     * @return string|int 字段的最小值
     * @throws Throwable 当查询执行失败时抛出异常
     */
    public function min(string $field): int|string
    {
        $field = $this->formatField($field);
        $this->_field = "MIN($field) as num";
        $res = $this->find();
        return $res ? ($res['num'] ?: 0) : 0;
    }

    /**
     * 获取字段的总和
     *
     * @param string $field 要求和的字段名
     * @return int 字段值的总和
     * @throws Throwable 当查询执行失败时抛出异常
     */
    public function sum(string $field): int
    {
        $field = $this->formatField($field);
        $this->_field = "SUM($field) as num";
        $res = $this->find();
        return $res && isset($res['num']) ? (int)$res['num'] : 0;
    }

    /**
     * 统计记录数
     *
     * @param string $field 要统计的字段，默认为*
     * @param bool $distinct 是否使用DISTINCT
     * @return int 记录数
     * @throws Throwable 当查询执行失败时抛出异常
     */
    public function count(string $field = '*', bool $distinct = false): int
    {
        $field = $this->formatField($field);
        if ($distinct) {
            $field = "DISTINCT $field";
        }
        $this->_field = "COUNT($field) as num";
        $res = $this->find();
        return $res && isset($res['num']) ? (int)$res['num'] : 0;
    }

    /**
     * 插入单条记录
     *
     * @param array $data 要插入的数据数组，键为字段名，值为字段值
     * @return int 插入记录的ID
     * @throws Throwable 当插入失败或数据格式错误时抛出异常
     */
    public function insert(array $data = []): int
    {
        if (empty($data)) {
            $data = $this->_row;
        }
        // 检查数据格式
        $this->_checkSaveDataStructure($data);

        $values = [];
        $fields = [];
        foreach ($data as $field => $val) {
            $values[] = $val;
            $fields[] = $this->formatField($field);
        }

        $columns = implode(', ', $fields);
        $q = implode(', ', array_fill(0, count($data), '?'));


        $this->_sql = "INSERT INTO " . static::TABLE_NAME . " ($columns) VALUES ($q)";
        $this->_bindParams = $values;

        $db = new Db(Db::ACTION_INSERT, $this->_sql, $this->_bindParams, $this->debugSql, self::DATABASES);
        return $db->getInsertId();
    }

    /**
     * 智能保存数据（自动判断是插入还是更新）
     * @param array $where 更新条件，默认为空，根据主键判断是否更新
     * @return int 受影响的行数或插入的ID
     * @throws Throwable 当操作失败时抛出异常
     */
    public function save(array $where = []): int
    {
        $data = $this->_row;
        if ($where) {
            $findId = (clone $this)->where($where)->selectField(static::PRI_KEY);
            if ($findId) {
                // 更新 table id
                $this->setByField(static::PRI_KEY, $findId);
                return (clone $this)->where([
                    static::PRI_KEY => $findId
                ])->update($data);
            }
        } else if (isset($data[static::PRI_KEY]) && $id = $data[static::PRI_KEY]) {
            return $this->where([static::PRI_KEY => $id])->update($data);
        }
        $id = $this->insert($data);
        // 更新 table id
        $this->setByField(static::PRI_KEY, $id);
        return $id;
    }

    /**
     * 批量插入多条记录
     *
     * @param array $data 要插入的二维数组，每个子数组代表一条记录
     * @return int 成功插入的记录数
     * @throws Throwable 当插入失败或数据格式错误时抛出异常
     */
    public function insertAll(array $data): int
    {
        if (empty($data)) {
            return 0;
        }


        foreach ($data as $value) {
            if (!is_array($value)) {
                throw new AppException('insertAll 需要一个二维数组');
            }
            // 检查数据格式
            $this->_checkSaveDataStructure($value);
        }

        // 获取到字段
        $fields = [];
        foreach ($data[0] as $field => $val) {
            $fields[] = $this->formatField($field);
        }
        $columns = implode(', ', $fields);

        // 获取到每个字段的值
        $values = [];
        foreach ($data as $val) {
            foreach ($val as $value) {
                $values[] = $value;
            }
        }

        // 问号填充
        $qArr = [];
        foreach ($data as $item) {
            $qArr[] = '(' . implode(', ', array_fill(0, count($item), '?')) . ')';
        }
        $q = implode(',', $qArr);

        $this->_sql = "INSERT INTO " . static::TABLE_NAME . " ($columns) VALUES $q";
        $this->_bindParams = $values;

        $db = new Db(Db::ACTION_INSERT_ALL, $this->_sql, $this->_bindParams, $this->debugSql, self::DATABASES);
        return $db->getAffectedRows();
    }

    /**
     * 更新记录
     *
     * @param array $data 要更新的数据数组，键为字段名，值为新的字段值
     * @return int 受影响的行数
     * @throws Throwable 当更新失败或没有WHERE条件时抛出异常
     */
    public function update(array $data = []): int
    {
        if (empty($data)) {
            $data = $this->_row;
        }
        if (empty($this->_where) || $this->_where == '1=1') {
            throw new AppException('update 必须使用 where 条件');
        }

        // 检查数据格式
        $this->_checkSaveDataStructure($data);

        $fields = [];
        $updateParams = [];
        foreach ($data as $field => $value) {
            $sqlField = $this->formatField($field);
            $updateExpression = "$sqlField = ?";
            if (is_string($value) && stripos($value, $field) === 0) {
                // 更新字段 设置的值 包含 需要更新的字段,为自增更新  例如  num = num + 1
                // 按照加减乘除分割
                if (preg_match('/^' . preg_quote($field, '/') . '([+\-*\/])(\d+)$/', $value, $match)) {                    // 拼接sql  num = num + ?
                    $operator = $match[1];
                    $incrementValue = $match[2];
                    $updateExpression = "$sqlField = $sqlField $operator ?";
                    $value = $incrementValue;
                }
            }
            $fields[] = $updateExpression;
            $updateParams[] = $value;
        }

        $updateField = implode(',', $fields);
        $this->_sql = "UPDATE " . static::TABLE_NAME . " SET $updateField  WHERE $this->_where";
        if ($this->_limit) {
            $this->_sql .= " LIMIT $this->_limit ";
        }
        $params = array_merge($updateParams, $this->_bindParams);
        $this->_bindParams = $params;
        $db = new Db(Db::ACTION_UPDATE, $this->_sql, $this->_bindParams, $this->debugSql, self::DATABASES);
        return $db->getAffectedRows();
    }

    /**
     * 删除记录
     *
     * @return int 受影响的行数
     * @throws Throwable 当删除失败或没有WHERE条件时抛出异常
     */
    public function delete(): int
    {
        if (empty($this->_where) || $this->_where == '1=1') {
            throw new AppException('delete 操作时必须使用 where 条件');
        }
        $this->_sql = "DELETE FROM " . static::TABLE_NAME . "  WHERE $this->_where";
        if ($this->_limit) {
            $this->_sql .= " LIMIT $this->_limit ";
        }

        $db = new Db(Db::ACTION_DELETE, $this->_sql, $this->_bindParams, $this->debugSql, self::DATABASES);
        return $db->getAffectedRows();
    }


    /**
     * 设置查询缓存
     *
     * @param int $time 缓存时间（秒），0表示随机300-3600秒
     * @param string $cacheKey 缓存键名，默认使用SQL语句
     * @return static 返回当前实例以支持链式调用
     */
    public function cache(int $time = 0, string $cacheKey = ''): static
    {
        $this->cacheTime = $time === 0 ? mt_rand(300, 3600) : $time;
        $this->cacheKey = $cacheKey;
        return $this;
    }

    /**
     * 拼接SQL 预计，注意查询顺序
     * @return void
     * @throws Throwable
     */
    private function _buildSql(): void
    {
        $this->_buildField();
        $tableName = static::TABLE_NAME;
        $this->_sql = "SELECT $this->_field FROM `$tableName` $this->_join";

        if ($this->_where) {
            $this->_sql .= " WHERE $this->_where";
        }

        if ($this->_group) {
            $this->_sql .= $this->_group;
        }

        if ($this->_order) {
            $this->_sql .= $this->_order;
        }

        if ($this->_limit) {
            if ($this->_offset) {
                $this->_sql .= " LIMIT $this->_offset,$this->_limit  ";
            } else {
                $this->_sql .= " LIMIT $this->_limit ";
            }
        }

        if ($this->_lock) {
            $this->_sql .= $this->_lock;
        }
    }

    /**
     * 格式化字段名
     *
     * @param string $as 字段名或别名
     * @return string 格式化后的字段名
     * @throws Throwable 当字段不存在时抛出异常
     */
    private function formatField(string $as): string
    {
        if ($as === '*') {
            return '*';
        }

        // 写全了 表名和字段名称的  tableName.fieldName
        if (stripos($as, '.') !== false && Db::checkFieldExists($as, self::DATABASES)) {
            return $as;
        }

        // 只是写了字段名称，没有写表名
        $field = static::TABLE_NAME . '.' . $as;
        if (Db::checkFieldExists($field, self::DATABASES)) {
            return $field;
        }

        // 传入的只是别名
        return Db::getFieldNameByAs($as, self::DATABASES);
    }


    /**
     * @throws Throwable
     */
    private function _getWhere(array $where): array
    {
        // where 参数格式化归一
        $where = $this->_whereNormalization($where);

        $retWhere = [];
        foreach ($where as $condition) {
            if (!isset($condition[0])) {
                throw new AppException('WHERE条件格式错误：缺少字段名');
            }
            $field = $this->formatField($condition[0]);
            $operator = strtolower($condition[1]);


            if (in_array($operator, ['is null', 'is not null'])) {
                $value = '';
            } elseif (count($condition) !== 3) {
                throw new AppException('where 条件格式错误 当前传入的是 %s', var_export($where, true));
            } else {
                $value = $condition[2];
            }


            switch ($operator) {
                case 'is null':
                case 'is not null':
                    $retWhere[] = "$field $operator";
                    break;
                case 'in':
                case 'not in':
                    if (!is_array($value)) {
                        throw new AppException('in 查询需要一个数组给的是一个%s', gettype($value));
                    }
                    if (empty($value)) {
                        break;
                    }

                    // 问号生成   假设 $value = [1, 2, 3]  结果为 ?,?,?
                    $q = implode(',', array_fill(0, count($value), '?'));
                    $retWhere[] = "$field $operator ($q)";
                    array_push($this->_bindParams, ...$value);

                    break;

                case 'between':
                    $retWhere[] = "$field $operator ? and ?";
                    $this->_bindParams[] = $value[0];
                    $this->_bindParams[] = $value[1];
                    break;

                // 主要针对 json 字段的查询
                case 'json_contains':
                    // 如果是一个 不为0 的空值 就不加入查询条件
                    if (empty($value) && $value != 0) break;
                    $node = $condition[3] ?? '$';
                    if (!is_array($value)) {
                        $value = [$value];
                    }
                    $tempArr = [];
                    foreach ($value as $v) {
                        $tempArr[] = " JSON_CONTAINS($field, ?, ?) ";
                        $this->_bindParams[] = strval($v);
                        $this->_bindParams[] = $node;
                    }
                    $retWhere[] = '(' . implode(' OR ', $tempArr) . ')';
                    break;
                default:
                    // 如果是一个 不为0 的空值 就不加入查询条件
                    if (empty($value) && $value != 0) break;

                    // value 数据归一化成数组
                    if (!is_array($value)) {
                        $value = [$value];
                    }
                    $tempArr = [];
                    foreach ($value as $v) {
                        $tempArr[] = " $field $operator ? ";

                        if ($operator === 'like') {
                            // like 查询，去掉两边的引号
                            $v = trim($v, "\"'");
                        }

                        $this->_bindParams[] = $v;
                    }

                    if (count($tempArr) === 1) {
                        $retWhere[] = $tempArr[0];
                    } else {
                        $retWhere[] = ' ( ' . implode(' OR ', $tempArr) . ' ) ';
                    }

                    break;
            }

        }

        return $retWhere;
    }


    /**
     * 检查保存的数据结构是否合法
     *
     * @param array $data 要检查的数据数组
     * @throws AppException 当数据结构不合法时抛出异常
     */
    private function _checkSaveDataStructure(array $data): void
    {
        foreach ($data as $field => $value) {
            if (is_array($value)) {
                throw new AppException("%s 是一个数组 不支持直接写入一个数组到数据库", $field);
            }
            if (is_object($value)) {
                throw new AppException("%s 是一个对象 不支持直接写入一个对象到数据库", $field);
            }
            if (is_resource($value)) {
                throw new AppException("%s 是一个资源类型 不支持直接写入到数据库", $field);
            }
        }
    }


    /**
     * WHERE条件参数标准化
     *
     * @param array $where WHERE条件数组
     * @return array 标准化后的WHERE条件数组
     */
    private function _whereNormalization(array $where): array
    {
        $ret = [];
        foreach ($where as $key => $value) {
            if (is_numeric($key)) {
                // 如果 key 是数字，则传入的 [field, operator, value] 直接使用
                $ret[] = $value;
            } elseif (Db::checkAsExists($key, self::DATABASES)) {
                // 如果传入的是一个字段名，则传入的是 [field => value] 需要转换成数组

                // 默认是等于，如果是数组则改成 in
                $operator = is_array($value) ? 'in' : '=';
                $ret[] = [$key, $operator, $value];
            } else {
                $ret[] = $value;
            }
        }
        return $ret;


    }
}

