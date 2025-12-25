<?php

namespace Swlib\Table\Interface;

use Swlib\Table\Expression;
use Swlib\Table\JoinEnum;

interface TableInterface
{
    /**
     * 获取对应的 DTO 实例
     */
    public function getDto(): TableDtoInterface;

    /**
     * 查询单条记录并返回 DTO
     */
    public function selectOne(): ?TableDtoInterface;

    /**
     * 查询多条记录并返回 DTO（内部包含 __rows 数组）
     */
    public function selectAll(): TableDtoInterface;

    /**
     * 判断字段是否在当前表字段列表中
     */
    public function inField(string $field): bool;

    /**
     * 获取主键字段（别名）
     */
    public function getPrimaryKey(): string;

    /**
     * 获取主键字段原始名称（库名.表名.字段名 => 表名.字段名）
     */
    public function getPrimaryKeyOriginal(): string;

    /**
     * 返回一个迭代器，逐行处理查询结果
     */
    public function selectIterator(): iterable;

    /**
     * 查询单个字段的值
     */
    public function selectField(string $field, mixed $default = null): mixed;

    /**
     * 判断是否存在满足条件的记录
     */
    public function exists(): bool;

    /**
     * 开启 SQL 调试输出
     */
    public function setDebugSql(): static;

    /**
     * 设置查询字段
     * @param string|array<int,string> $fields
     */
    public function field(string|array $fields): static;

    /**
     * 设置 DISTINCT 查询
     */
    public function distinct(): static;

    /**
     * 设置分页
     */
    public function page(int $page, int $pageSize): static;

    /**
     * 限制返回条数
     */
    public function limit(int $limit): static;

    /**
     * 设置排序
     * @param array<string,string> $order
     */
    public function order(array $order): static;

    /**
     * 设置 WHERE 条件
     * @param array $where
     */
    public function where(array $where): static;

    /**
     * 动态追加单个 WHERE 条件
     */
    public function addWhere(string $field, string|int|array $value, string $operator = '='): static;

    /**
     * 添加 FOR UPDATE 锁
     */
    public function lock(): static;

    /**
     * 表连接
     */
    public function join(string $table, string $field, string $field2, JoinEnum $joinType = JoinEnum::INNER, string $alias = ''): static;

    /**
     * GROUP BY 分组
     */
    public function group(string $field = ''): static;

    /**
     * 获取字段最大值
     */
    public function max(string $field, string $alias = 'num'): string|int;

    /**
     * 获取字段最小值
     */
    public function min(string $field, string $alias = 'num'): string|int;

    /**
     * 获取字段总和
     */
    public function sum(string $field, string $alias = 'num'): int;

    /**
     * 获取字段平均值
     */
    public function avg(string $field, string $alias = 'num'): int;

    /**
     * 统计记录数
     */
    public function count(string $field = '*', bool $distinct = false, string $alias = 'num'): int;

    /**
     * 插入单条记录
     * @param array $data
     */
    public function insert(array $data = []): int;

    /**
     * 批量插入记录
     * @param array<int,array<string,mixed>> $data
     */
    public function insertAll(array $data): int;

    /**
     * 更新记录
     * @param array $data
     */
    public function update(array $data = []): int;

    /**
     * 删除记录
     */
    public function delete(): int;

    /**
     * 设置查询缓存
     */
    public function cache(int $time = 0, string $cacheKey = ''): static;

    /**
     * 格式化字段名或表达式
     */
    public function formatField(string|Expression $as): string;

    /**
     * 克隆当前查询对象
     */
    public function __clone(): void;

    /**
     * 清理内部查询构造器状态
     */
    public function queryClean(): void;

    /**
     * 执行原生 SQL 并返回单个 DTO 对象
     */
    public function queryToObject(string $sql, array $params = []): ?TableDtoInterface;

    /**
     * 执行原生 SQL 并返回 DTO 对象数组
     * @return TableDtoInterface[]
     */
    public function queryToObjects(string $sql, array $params = []): array;
}