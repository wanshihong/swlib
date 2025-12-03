<?php

namespace Swlib\Table\Trait;


use Swlib\Table\Interface\TableDtoInterface;
use Throwable;

/**
 * 查询结果 数组列表 的相关操作方法
 */
trait TableDataListsTrait
{
    /**
     * @var TableDtoInterface[]
     */
    private array $__rows = [];


    /**
     * @throws Throwable
     */
    public function __setRows(array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        foreach ($rows as $row) {
            $this->__addRow($row);
        }
    }

    /**
     * @return TableDtoInterface[]
     */
    public function __getRows(): array
    {
        return $this->__rows;
    }


    /**
     * @throws Throwable
     */
    public function __addRow(array $row): void
    {
        $newDto = new $this();
        $newDto->__fromArray($row);
        $this->__rows[] = $newDto;
    }


    /**
     * 返回关联数组 二维数组
     * key 是 数据库字段名
     * @return array
     * @throws Throwable
     */
    public function getDbFieldNameKeyRows(): array
    {
        $ret = [];
        foreach ($this->__getRows() as $tableRow) {
            $ret[] = $tableRow->getDbFieldNameKeyRow();
        }
        return $ret;
    }


    /**
     * 获取一个格式化成 key => value 的数组
     * @param string $fieldId id字段
     * @param string $fieldName name字段
     * @return  integer[]
     * @throws Throwable
     */
    public function formatId2Name(string $fieldId, string $fieldName): array
    {
        $ret = [];
        foreach ($this->__getRows() as $list) {
            $key = $list->getByField($fieldId);
            $value = $list->getByField($fieldName);
            $ret[$key] = $value;
        }
        return $ret;
    }

    /**
     * 获取一个格式化成 key => value 的数组
     * @param string $fieldId id字段
     * @return  static[]
     * @throws Throwable
     */
    public function formatId2Array(string $fieldId): array
    {
        $ret = [];
        foreach ($this->__getRows() as $list) {
            $key = $list->getByField($fieldId);
            $ret[$key] = $list;
        }
        return $ret;
    }

    /**
     * 获取本次查询，某个字段的值
     * @param string $field
     * @return array
     * @throws Throwable
     */
    public function getArrayByField(string $field): array
    {

        $ret = [];
        foreach ($this->__getRows() as $item) {
            $val = $item->getByField($field);
            $ret[$val] = true; // 使用关联数组来去重
        }
        return array_keys($ret); // 转换为值数组;
    }


}