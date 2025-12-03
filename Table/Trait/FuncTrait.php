<?php

namespace Swlib\Table\Trait;


use Exception;
use Swlib\Exception\AppException;
use Swlib\Table\Db;
use Throwable;

trait FuncTrait
{





    public function inField(string $field): bool
    {
        return in_array($field, static::FIELD_ALL);
    }


    /**
     * 获取主键字段
     * @return string
     */
    public function getPrimaryKey(): string
    {
        return static::PRI_KEY;
    }

    /**
     * 获取主键字段原始名称
     * @return string
     * @throws Exception
     */
    public function getPrimaryKeyOriginal(): string
    {
        return explode('.', Db::getFieldNameByAs(static::PRI_KEY, self::DATABASES))[1];
    }

    /**
     * 返回一个迭代器，外部可以逐行处理数据而不需要全部加载到内存
     * 查询结果只能执行一次循环,
     *
     * 如果还需要对结果多次操作,请使用 selectAll 或者  在外部循环过程中 对需要操作的数据做好记录保存
     *
     *
     * @return iterable
     * @throws Throwable
     */
    public function selectIterator(): iterable
    {
        if ($this->cacheTime > 0) {
            throw new AppException('迭代器获取查询结果,不支持查询缓存');
        }
        foreach ($this->queryIterator() as $item) {
            $dto = $this->getDto();
            $dto->__fromArray($item);
            yield $dto;
        }
    }


    /**
     * @return mixed
     * @throws Throwable
     */
    private function _selectAll(): mixed
    {
        $dto = $this->getDto();
        $rows = $this->select();
        $dto->__setRows($rows);
        return $dto;
    }


    /**
     * @throws Throwable
     */
    private function _selectOne(): mixed
    {
        $data = $this->find();
        if (empty($data)) {
            return null;
        }
        $dto = $this->getDto();
        $dto->__fromArray($data);
        return $dto;
    }

    /**
     * 获取某个字段的值
     * @throws Throwable
     */
    public function selectField(string $field, $default = null): mixed
    {
        $this->field($field);
        $res = $this->selectOne();
        return $res ? $res->getByField($field) : $default;
    }

    /**
     * 判断某个条件下是否存在
     * @return bool
     * @throws Throwable
     */
    public function exists(): bool
    {
        $this->field(static::PRI_KEY);
        $res = $this->find();
        return (bool)$res;
    }




}