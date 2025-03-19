<?php

namespace Swlib\Admin\Manager;

use Swlib\Admin\Action\Action;
use Swlib\Admin\Fields\AbstractField;
use Swlib\Exception\AppException;
use Swlib\Table\TableInterface;
use Throwable;

class ListRowManager
{

    /**
     * 这一行里面有那些字段
     * @var AbstractField[]
     */
    public array $fields = [];


    /**
     * 直接展示的操作
     * @var ?Action
     */
    public ?Action $firstAction = null;

    /**
     * 通过下拉菜单暂时的操作
     * @var Action[]
     */
    public array $lastActions = [];


    public string $priFieldOriginalName;// 主键原始字段名称
    public string $priFieldName; // 主键别名
    public mixed $priFieldValue;// 主键值

    /**
     * @throws Throwable
     */
    public function __construct(
        // 查询出来的一行数据表格, 包含所有字段
        public TableInterface $table,

        /**
         * 这一行有那些配置字段
         * @var AbstractField[] $fieldsConfig
         */
        public array          $configFields,


        /**
         * 这一行有那些配置的操作
         * @var Action[]
         */
        public array          $configActions
    )
    {
        // 取到主键的值
        $this->priFieldOriginalName = $this->table->getPrimaryKeyOriginal();
        $this->priFieldName = $this->table->getPrimaryKey();
        $this->priFieldValue = $this->table->getPrimaryValue();
        // 获取这一行的数据
        $data = $this->table->__toArray();

        // 循环一行的数据，遍历所有的字段，进行字段配置
        foreach ($configFields as $field) {
            if (!array_key_exists($field->field, $data)) {
                throw new AppException("字段 %s 未配置", $field->field);
            }
            // 克隆一个字段,不然会影响其他字段
            $newField = clone $field;
            $newField->row = $data; // 赋值一行的数据
            $newField->priFieldName = $this->priFieldName;// 赋值主键
            $newField->priFieldValue = $this->priFieldValue;// 赋值主键的值
            $newField->elemId = $newField->elemId . '-' . $this->priFieldValue;// 赋值元素节点ID
            $this->fields[] = $newField->frameworkSetValue($data[$newField->field], $this->table);
        }


        // 设置 操作
        foreach ($configActions as $action) {
            $newAction = clone $action;

            // 初始化操作按钮的参数
            if (!$newAction->params) {
                $newAction->params = [];
            }
            // 添加主键操作参数
            $newAction->params[$this->priFieldOriginalName] = $this->priFieldValue;

            // 如果是有占位符的参数，那么就替换成真实的值
            foreach ($newAction->params as $key => $value) {
                if (!str_starts_with($value, '%')) {
                    continue;
                }
                $field = str_replace('%', '', $value);
                $newAction->params[$key] = $this->table->getByField($field);
            }

            if (!$this->firstAction) {
                // 第一个操作
                $this->firstAction = $newAction;
            } else {
                // 剩下的操作
                $this->lastActions[] = $newAction;
            }
        }
    }




}