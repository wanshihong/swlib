<?php

namespace Swlib\Admin\Trait;

use Exception;
use Swlib\Admin\Fields\AbstractField;
use Swlib\Admin\Manager\AdminUserManager;
use Swlib\Table\TableInterface;
use Throwable;

trait PageFrameworkTrait
{

    /**
     * 返回列表页面数据查询的字段
     *
     *  框架会自动调用这个方法，请不要手动调用
     *  框架会自动调用这个方法，请不要手动调用
     *  框架会自动调用这个方法，请不要手动调用
     */
    public function frameworkGetListsFields($priFieldName): array
    {
        $queryFields = [];
        $fields = [];
        foreach ($this->fields as $item) {
            if (!$item->fieldShowList) continue;
            $queryFields[] = $item->field;
            $fields[] = $item;
        }

        // 添加主键 到查询
        if (!in_array($priFieldName, $queryFields)) {
            $queryFields[] = $priFieldName;
        }

        return [$fields, $queryFields];
    }

    /**
     * 返回详情页面数据查询的字段
     *
     *  框架会自动调用这个方法，请不要手动调用
     *  框架会自动调用这个方法，请不要手动调用
     *  框架会自动调用这个方法，请不要手动调用
     */
    public function frameworkGetDetailFields($priFieldName): array
    {
        $fields = [];
        $queryFields = [];
        foreach ($this->fields as $item) {
            if (!$item->fieldShowDetail) continue;
            // 详情页面的展示宽度可以设置大一点
            $item->setListMaxWidth(800);
            $queryFields[] = $item->field;
            $fields[] = $item;
        }

        // 添加主键 到查询
        if (!in_array($priFieldName, $fields)) {
            $queryFields[] = $priFieldName;
        }

        return [$fields, $queryFields];
    }

    /**
     * 返回过滤器需要展示的 数据字段
     *
     *  框架会自动调用这个方法，请不要手动调用
     *  框架会自动调用这个方法，请不要手动调用
     *  框架会自动调用这个方法，请不要手动调用
     * @return array
     */
    public function frameworkGetFilterFields(): array
    {

        $fields = [];
        foreach ($this->fields as $item) {
            if (!$item->fieldShowFilter) continue;
            // 克隆一个字段，不影响原字段
            $fields[] = clone $item;
        }
        return $fields;
    }


    /**
     * 获取表单页面的字段列表
     *
     *  框架会自动调用这个方法，请不要手动调用
     *  框架会自动调用这个方法，请不要手动调用
     *  框架会自动调用这个方法，请不要手动调用
     * @return array [AbstractField[], string[]]
     */
    public function frameworkGetFormFields(): array
    {
        $fields = [];
        $queryFields = [];
        foreach ($this->fields as $field) {
            // 如果不显示在表单上，则返回
            if ($field->fieldShowForm === false) continue;
            // 获取查询字段
            array_push($queryFields, ...$field->frameworkGetQueryField());
            // 返回字段配置
            $fields[] = $field;
        }

        return [$fields, $queryFields];
    }


    /**
     *  框架会自动调用这个方法，请不要手动调用
     *  框架会自动调用这个方法，请不要手动调用
     *  框架会自动调用这个方法，请不要手动调用
     * @throws Throwable
     */
    public function frameworkFilterRequest(TableInterface $query, array $filterFields): void
    {

        /** @var AbstractField $field */
        foreach ($filterFields as $field) {
            $value = $field->frameworkFilterRequest();
            if (empty($value)) {
                if ($field->filterDefault != null) {
                    if (is_array($field->filterDefault)) {
                        $field->value = implode(',', $field->filterDefault);
                    } else {
                        $field->value = $field->filterDefault;
                    }
                }
                continue;
            }

            if ($field->filterQuery) {
                call_user_func($field->filterQuery, $query, $value);
            } else {
                $field->frameworkFilterAddQueryWhere($query);
            }
        }
    }


    /**
     * 表单接收 post 数据
     *
     *  框架会自动调用这个方法，请不要手动调用
     *  框架会自动调用这个方法，请不要手动调用
     *  框架会自动调用这个方法，请不要手动调用
     * @param AbstractField[] $fields
     * @return TableInterface
     * @throws Throwable
     */
    public function frameworkFormRequest(array $fields): TableInterface
    {
        /** @var TableInterface $table */
        $table = new $this->admin->pageConfig->tableName;

        foreach ($fields as $field) {
            $value = $field->frameworkFormRequest();
            if ($value === null) {
                continue;
            }
            // 是否在表单字段中
            if (!$table->inField($field->field)) continue;

            if (is_string($value)) {
                $value = trim($value);
            }

            $table->setByField($field->field, $value);
        }

        return $table;
    }

    /**
     * 编辑页面，根据ID 查询出来数据，并且回填到表单字段
     *
     *  框架会自动调用这个方法，请不要手动调用
     *  框架会自动调用这个方法，请不要手动调用
     *  框架会自动调用这个方法，请不要手动调用
     * @param AbstractField[] $fields
     * @param TableInterface $table
     * @throws Throwable
     */
    public function frameworkFormEditFill(array $fields, TableInterface $table): void
    {
        foreach ($fields as $field) {
            $field->frameworkEditFill($table);
        }
    }

    /**
     * 根据字段名，获取字段配置
     *
     *  框架会自动调用这个方法，请不要手动调用
     *  框架会自动调用这个方法，请不要手动调用
     *  框架会自动调用这个方法，请不要手动调用
     * @throws Throwable
     */
    public function frameworkGetField(string $fieldName): AbstractField
    {
        foreach ($this->fields as $field) {
            if ($field->field == $fieldName) return $field;
        }
        throw new Exception("field: $fieldName not config");
    }


    /**
     *
     *  框架会自动调用这个方法，请不要手动调用
     *  框架会自动调用这个方法，请不要手动调用
     *  框架会自动调用这个方法，请不要手动调用
     * @throws Throwable
     */
    public function checkFieldsPermissions(): void
    {
        $ret = [];
        foreach ($this->fields as $field) {
            if (AdminUserManager::checkPermissionsByConfig($field) === false) {
                continue;
            }
            $ret[] = $field;
        }
        $this->fields = $ret;
    }

}