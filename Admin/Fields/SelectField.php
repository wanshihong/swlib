<?php

namespace Swlib\Admin\Fields;

use Generate\RouterPath;
use Swlib\Admin\Manager\OptionManager;
use Swlib\Controller\Language\Enum\LanguageEnum;
use Swlib\Enum\CtxEnum;
use Swlib\Exception\AppException;
use Swlib\Table\Db;
use Swlib\Table\Interface\TableDtoInterface;
use Swlib\Table\Interface\TableInterface;
use Throwable;

class SelectField extends AbstractField
{

    // 列表页面自定义模板
    public string $templateList = "fields/lists/select.twig";

    // 列表页面自定义模板
    public string $templateForm = "fields/form/select.twig";
    public string $templateFilter = "fields/filter/select.twig";

    // 选择字段显示的文本，视图显示的时候展示  类似于  select option 中的 text，value
    public string $showText = '';

    /**
     * 关联选择数据，查询和列表二选一
     * @var OptionManager[]
     */
    public array $options = [];

    /**
     * 关联数据库表格信息，查询和列表二选一
     */
    public ?string $table = null; // 表格名称
    public string $idField = ''; // 关联ID
    public string $idFieldOriginalName;// 关联ID 的原始字段名称
    public string $textField = ''; // 显示字段

    /**
     * 关联字段详情URL
     * 如果配置了,可以点击跳转到这个链接
     * @var string
     */
    public string $relationUrl = '';

    /**
     * @var callable 增加查询条件
     */
    public mixed $addQuery = null;


    public function __construct(string $field, string $label)
    {
        parent::__construct($field, $label);
        $this->addCssFile('/admin/css/field-select.css');
        $this->addJsFile('/admin/js/field-select.js');

        $this->setFormCreate(function (self $field) {
            $field->showText = $this->_getShowText($field->value);
        });
    }


    /**
     * 关联选择数据，查询和列表二选一
     * 设置了选择， query 就失效
     *
     * @param OptionManager[] $options
     * @return $this
     */
    public function setOptions(...$options): SelectField
    {
        $this->options = $options;
        return $this;
    }

    /**
     * 设置关联表格的主要信息，查询和列表二选一
     * @param string $tableName 关联的表格名称  UserTable::class  不是  UserTable::TABLE_NAME
     * @param string $idField 关联的ID字段
     * @param string $textField 关联的文本字段
     * @param string $url 点击关联字段，跳转到对应的详情页面
     * @param string $dbName 使用的那个数据库
     * @return $this
     * @throws Throwable
     */
    public function setRelation(string $tableName, string $idField, string $textField, string $url = '', string $dbName = 'default'): static
    {
        if (!class_exists($tableName)) {
            throw new AppException(LanguageEnum::FORM_CLASS_NOT_EXIST_WITH_NAME, $tableName);
        }
        $this->table = $tableName;
        $this->idField = $idField;
        $this->idFieldOriginalName = explode('.', Db::getFieldNameByAs($idField, $dbName))[1];
        $this->textField = $textField;

        if ($url === '') {
            // 如果没有指定URL 尝试自动识别
            $request = CtxEnum::Request->get();
            $tableName2Url = "/" . str_replace('_', '-', $tableName::TABLE_NAME);
            $adminUrlStart = explode('/', $request->server['path_info'])[1];
            foreach (RouterPath::PATHS as $path => $v) {
                if (
                    str_starts_with($path, "/$adminUrlStart") &&
                    (stripos($path, $tableName2Url . '/detail') || stripos($path, $tableName2Url . '-admin/detail'))
                ) {
                    $this->relationUrl = $path;
                }
            }
        } else {
            // 指定了URL
            $this->relationUrl = $url;
        }


        return $this;
    }


    /**
     * @throws Throwable
     */
    protected function _getShowText(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        if ($this->table) {
            /**@var TableInterface $table */
            $table = new $this->table;
            $ret = $table->addWhere($this->idField, $value)->selectField($this->textField);
            return $value ? $value . '#' . $ret : '';
        } else {
            foreach ($this->options as $option) {
                if ($option->id == $value) {
                    return $option->text;
                }
            }
        }
        return '';
    }

    /**
     * 筛选器接收 get 数据
     * 请勿手动调用，由框架自动调用
     * 请勿手动调用，由框架自动调用
     * 请勿手动调用，由框架自动调用
     * @throws Throwable
     */
    public function frameworkFilterRequest()
    {
        $value = parent::frameworkFilterRequest();
        $this->showText = $this->_getShowText($value);
        return $value;
    }

    /**
     * 表单编辑的时候，设置字段的值
     * 请勿手动调用，由框架自动调用
     * @param TableDtoInterface $dto
     * @return $this
     * @throws Throwable
     */
    public function frameworkEditFill(TableDtoInterface $dto): static
    {
        // 读取字段的值
        $value = $dto->getByField($this->field);
        $this->value = $value;

        // 自定义了 格式化
        if ($this->formFormat) {
            list($value, $text) = call_user_func($this->formFormat, $value, $dto);
            $this->showText = $text;
            $this->value = $value;
            return $this;
        }

        if ($this->textField) {
            // 显示的文本
            $this->showText = $this->_getShowText($value);
        } elseif ($this->options) {
            //关联选择
            foreach ($this->options as $option) {
                if ($option->id == $value) {
                    $this->showText = $option->text;
                }
            }
        }

        return $this;
    }


    /**
     * 列表 设置字段的值
     * 请勿手动调用，由框架自动调用
     * 请勿手动调用，由框架自动调用
     * 请勿手动调用，由框架自动调用
     * @param mixed $value
     * @param TableInterface $table
     * @return $this
     * @throws Throwable
     */
    public function frameworkSetValue(mixed $value, TableInterface $table): static
    {
        if ($this->listFormat && is_callable($this->listFormat)) {
            $value = call_user_func($this->listFormat, $value, $table);
        }
        $value = $value == null && $this->default !== null ? $this->default : $value;

        if ($this->options) {
            foreach ($this->options as $option) {
                if ($option->id == $value) {
                    if ($this->table) {
                        // 如果是关联表格，则增加 ID 显示在前面
                        $this->showText = $option->text ? $value . '#' . $option->text : "ID:$value";
                    } else {
                        $this->showText = $option->text;
                    }

                }
            }
        } else if ($this->table && $value) {
            $text = $this->_getShowText($value);
            $this->showText = $text ?: '';
        }

        $this->value = $value;
        return $this;
    }

    public function setAddQuery(mixed $addQuery): SelectField
    {
        $this->addQuery = $addQuery;
        return $this;
    }

}