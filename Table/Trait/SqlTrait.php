<?php
declare(strict_types=1);

namespace Swlib\Table\Trait;


use Swlib\Exception\AppException;
use Swlib\Table\Aspects\DatabaseOperationEventAspect;
use Swlib\Table\Aspects\QueryCleanAspect;
use Swlib\Table\Db;
use Swlib\Table\Expression;
use Swlib\Table\JoinEnum;
use Swlib\Table\QueryBuild;
use Throwable;

/**
 * SQL查询构建器Trait
 * 提供了构建和执行SQL查询的基本功能
 */
trait SqlTrait
{


    protected(set) int $cacheTime = 0;
    private(set) string $cacheKey = '';

    public bool $debugSql = false;


    private(set) ?QueryBuild $queryBuild = null {
        get {
            if ($this->queryBuild === null) {
                $this->queryBuild = new QueryBuild($this);
            }
            return $this->queryBuild;
        }
    }

    public function setDebugSql(): static
    {
        $this->debugSql = true;
        return $this;
    }


    /**
     * @throws Throwable
     */
    public function field(string|array $fields): static
    {
        $this->queryBuild->field($fields);
        return $this;
    }

    public function distinct(): static
    {
        $this->queryBuild->distinct();
        return $this;
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
        $this->queryBuild->page($page, $pageSize);
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
        $this->queryBuild->limit($limit);
        return $this;
    }

    /**
     * 设置查询结果的排序方式
     *
     * @param array|Expression $order 排序配置数组，键为字段名，值为排序方式(ASC/DESC)
     * @return static 返回当前实例以支持链式调用
     * @throws AppException
     */
    public function order(array|Expression $order): static
    {
        $this->queryBuild->order($order);
        return $this;
    }

    /**
     * @throws Throwable
     */
    public function where(array $where): static
    {
        $this->queryBuild->where($where);
        return $this;
    }


    /**
     * 添加单个WHERE条件
     *
     * @param string $field 字段名
     * @param string|int|array $value 查询值
     * @param string $operator 操作符(=, >, <, LIKE等)
     * @return static 返回当前实例以支持链式调用
     * @throws Throwable 当条件构建失败时抛出异常
     */
    public function addWhere(string $field, string|int|array $value, string $operator = '='): static
    {

        $where = [
            [$field, $operator, $value]
        ];

        $this->queryBuild->where($where);
        return $this;
    }


    /**
     * 添加FOR UPDATE锁
     *
     * @return static 返回当前实例以支持链式调用
     */
    public function lock(): static
    {
        $this->queryBuild->lock();
        return $this;
    }


    /**
     * 添加表连接
     *
     * @param string $table 要连接的表名
     * @param string $field 当前表的连接字段
     * @param string $field2 要连接表的连接字段
     * @param JoinEnum $joinType 连接类型(INNER/LEFT/RIGHT)
     * @param string $alias 表别名，如果为空则使用表名 , 如果自己关联自己查询,则需要定义表别名; 然后查询的字段,使用表别名.数据库字段名称
     * @return static 返回当前实例以支持链式调用
     * @throws Throwable 当字段格式化失败时抛出异常
     */
    public function join(string $table, string $field, string $field2, JoinEnum $joinType = JoinEnum::INNER, string $alias = ''): static
    {
        $this->queryBuild->join($table, $field, $field2, $joinType, $alias);
        return $this;
    }

    /**
     * 设置GROUP BY分组
     *
     * @param string|Expression $field 分组字段名
     * @return static 返回当前实例以支持链式调用
     */
    public function group(string|Expression $field = ''): static
    {
        $this->queryBuild->group($field);
        return $this;
    }


    /**
     * 执行SELECT查询
     *
     * @return array 查询结果数组
     * @throws Throwable 当查询执行失败时抛出异常
     */
    #[DatabaseOperationEventAspect(priority: 2)]
    #[QueryCleanAspect(priority: 1)]
    protected function select(): array
    {
        $sql = $this->queryBuild->select();
        $db = new Db(Db::ACTION_GET_RESULT, $sql, $this->queryBuild->bindParams, $this->debugSql, self::DATABASES);
        if ($this->cacheTime > 0) {
            $result = $db->getCacheResult($this->cacheTime, $this->cacheKey);
        } else {
            $result = $db->getResult();
        }

        return $result;
    }

    /**
     * 获取查询结果的迭代器
     *
     * @return iterable 查询结果的迭代器
     * @throws Throwable 当查询执行失败时抛出异常
     */
    #[DatabaseOperationEventAspect(priority: 2)]
    #[QueryCleanAspect(priority: 1)]
    protected function queryIterator(): iterable
    {
        $sql = $this->queryBuild->select();

        $db = new Db(Db::ACTION_GET_ITERATOR, $sql, $this->queryBuild->bindParams, $this->debugSql, self::DATABASES);

        return $db->getIterator();
    }


    /**
     * @throws Throwable
     */
    #[DatabaseOperationEventAspect(priority: 2)]
    #[QueryCleanAspect(priority: 1)]
    protected function find(): array
    {
        $sql = $this->queryBuild->find();

        $db = new Db(Db::ACTION_GET_RESULT, $sql, $this->queryBuild->bindParams, $this->debugSql, self::DATABASES);

        if ($this->cacheTime > 0) {
            $res = $db->getCacheResult($this->cacheTime, $this->cacheKey);
        } else {
            $res = $db->getResult();
        }

        return $res ? $res[0] : [];
    }


    /**
     * @throws Throwable
     */
    public function dumpSql(): string
    {
        $sql = $this->queryBuild->select();
        echo $sql . PHP_EOL;
        var_dump($this->queryBuild->bindParams);
        return $sql;
    }


    /**
     * 获取字段的最大值
     *
     * @param string $field 要查询的字段名
     * @param string $alias 字段别名，默认为 'num'
     * @return string|int 字段的最大值
     * @throws Throwable 当查询执行失败时抛出异常
     */
    public function max(string $field, string $alias = 'num'): string|int
    {
        $this->queryBuild->max($field, $alias);
        $res = $this->find();
        return $res ? ($res[$alias] ?: 0) : 0;
    }

    /**
     * 获取字段的最小值
     *
     * @param string $field 要查询的字段名
     * @param string $alias 字段别名，默认为 'num'
     * @return string|int 字段的最小值
     * @throws Throwable 当查询执行失败时抛出异常
     */
    public function min(string $field, string $alias = 'num'): string|int
    {
        $this->queryBuild->min($field, $alias);
        $res = $this->find();
        return $res ? ($res[$alias] ?: 0) : 0;
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
     * @return int 字段值的总和
     * @throws Throwable 当查询执行失败时抛出异常
     */
    public function sum(string $field, string $alias = 'num'): int
    {
        $this->queryBuild->sum($field, $alias);
        $res = $this->find();
        return $res && isset($res[$alias]) ? (int)$res[$alias] : 0;
    }

    /**
     * 获取字段的平均值
     *
     * @param string $field 要计算平均值的字段名或表达式
     * @param string $alias 字段别名，默认为 'num'
     * @return int 字段的平均值
     * @throws Throwable 当查询执行失败时抛出异常
     */
    public function avg(string $field, string $alias = 'num'): int
    {
        $this->queryBuild->avg($field, $alias);
        $res = $this->find();
        return $res && isset($res[$alias]) ? (int)$res[$alias] : 0;
    }

    /**
     * 统计记录数
     *
     * @param string $field 要统计的字段，默认为*
     * @param bool $distinct 是否使用DISTINCT
     * @param string $alias 字段别名，默认为 'num'
     * @return int 记录数
     * @throws Throwable 当查询执行失败时抛出异常
     */
    public function count(string $field = '*', bool $distinct = false, string $alias = 'num'): int
    {
        $this->queryBuild->count($field, $distinct, $alias);
        $res = $this->find();
        return $res && isset($res[$alias]) ? (int)$res[$alias] : 0;
    }

    /**
     * 插入单条记录
     *
     * @param array $data 要插入的数据数组，键为字段名，值为字段值
     * @return int 插入记录的ID
     * @throws Throwable 当插入失败或数据格式错误时抛出异常
     */
    #[DatabaseOperationEventAspect(priority: 2)]
    #[QueryCleanAspect(priority: 1)]
    public function insert(array $data = []): int
    {
        $sql = $this->queryBuild->insert($data);
        $db = new Db(Db::ACTION_INSERT, $sql, $this->queryBuild->bindParams, $this->debugSql, self::DATABASES);
        return $db->getInsertId();
    }


    /**
     * 批量插入多条记录
     *
     * @param array $data 要插入的二维数组，每个子数组代表一条记录
     * @return int 成功插入的记录数
     * @throws Throwable 当插入失败或数据格式错误时抛出异常
     */
    #[DatabaseOperationEventAspect(priority: 2)]
    #[QueryCleanAspect(priority: 1)]
    public function insertAll(array $data): int
    {
        $sql = $this->queryBuild->insertAll($data);
        $db = new Db(Db::ACTION_INSERT_ALL, $sql, $this->queryBuild->bindParams, $this->debugSql, self::DATABASES);
        return $db->getAffectedRows();
    }

    /**
     * 更新记录
     *
     * @param array $data 要更新的数据数组，键为字段名，值为新的字段值
     * @return int 受影响的行数
     * @throws Throwable 当更新失败或没有WHERE条件时抛出异常
     */
    #[DatabaseOperationEventAspect(priority: 2)]
    #[QueryCleanAspect(priority: 1)]
    public function update(array $data = []): int
    {
        $sql = $this->queryBuild->update($data);
        $db = new Db(Db::ACTION_UPDATE, $sql, $this->queryBuild->bindParams, $this->debugSql, self::DATABASES);
        return $db->getAffectedRows();
    }

    /**
     * 删除记录
     *
     * @return int 受影响的行数
     * @throws Throwable 当删除失败或没有WHERE条件时抛出异常
     */
    #[DatabaseOperationEventAspect(priority: 2)]
    #[QueryCleanAspect(priority: 1)]
    public function delete(): int
    {
        $sql = $this->queryBuild->delete();
        $db = new Db(Db::ACTION_DELETE, $sql, $this->queryBuild->bindParams, $this->debugSql, self::DATABASES);
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
     * 格式化字段名
     *
     * @param string|Expression $as 字段名或别名
     * @return string 格式化后的字段名
     * @throws Throwable 当字段不存在时抛出异常
     */
    public function formatField(string|Expression $as): string
    {
        if ($as instanceof Expression) {
            return $as->value;
        }
        if ($as === '*') {
            return '*';
        }

        // 写全了 表名和字段名称的  tableName.fieldName
        if (stripos($as, '.') !== false) {
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

    public function __clone(): void
    {
        // 查询对象也需要 clone ; 不然查询会使用用一个 queryBuild
        $this->queryBuild = clone $this->queryBuild;
    }

    public function queryClean(): void
    {
        $this->queryBuild = null;
    }
}
