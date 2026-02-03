<?php

namespace Swlib\Admin\Fields;

use Swlib\Admin\Interface\PermissionInterface;
use Swlib\Admin\Trait\AttrTrait;
use Swlib\Admin\Trait\Field\FieldFrameworkTrait;
use Swlib\Admin\Trait\Field\FieldShowControlTrait;
use Swlib\Admin\Trait\Field\FieldTemplateTrait;
use Swlib\Admin\Trait\PermissionTrait;
use Swlib\Admin\Trait\StaticTrait;
use Swlib\Controller\Language\Service\Language;
use Swlib\Table\Db;
use Throwable;

abstract class AbstractField implements PermissionInterface
{
    // 静态资源
    use StaticTrait;

    // 模板自定义
    use FieldTemplateTrait;

    // 框架调用相关，无需手动设置的
    use FieldFrameworkTrait;

    // 显示控制
    use FieldShowControlTrait;

    // 权限
    use PermissionTrait;

    // 自定义 html 属性
    use AttrTrait;

    /**
     * @var mixed|null 默认值
     */
    public mixed $default = null;

    // 过滤器默认值
    public mixed $filterDefault = null;

    /**
     * @var mixed|null 字段的值
     */
    public mixed $value = null;

    // 列表查询出来的一行数据
    public array $row = [];// 数据库别名作为key

    // 列表查询出来的一行数据
    public array $rowVar = [];// 数据库字段作为key

    // 这一行的主键
    public string $priFieldName = "";

    // 这一行主键的值
    public int $priFieldValue = 0;
    /**
     * 这个字段使用的变量名称
     * field 是数据库别名 例如 t0f0
     * fieldVar 是代码中使用的语义化变量 例如 userName
     * @var string
     */
    public string $fieldVar = "";

    /**
     * 过滤器默认都是等于查询，可以通过回调函数自定义查询
     * function (TableInterface $query, mixed $value):mixed
     * @var callable|null
     */
    public mixed $filterQuery = null;


    /**
     * 列表页面 字段值格式化方法
     * function (mixed $value,TableInterface $table):mixed
     * @var callable|null
     */
    public mixed $listFormat = null;

    /**
     * 新增页面
     * function (AbstractField $field):mixed
     * @var callable|null
     */
    public mixed $formCreate = null;


    /**
     * 编辑页面 字段值格式化方法
     * 在表单创建的时候填充值到表单
     * function (?mixed $value, ?TableInterface $table):mixed
     * @var callable|null
     */
    public mixed $formFormat = null;

    /**
     * @var mixed|null 表单接收到值以后 字段值格式化方法
     * function (mixed $value):mixed
     */
    public mixed $formRequestAfter = null;

    /**
     * @var mixed|null  过滤器接收到值以后 字段值格式化方法
     * function (mixed $value,array $getAll):mixed
     */
    public mixed $filterRequestAfter = null;

    /**
     * 字段帮助说明
     * @var string
     */
    public string $helpText = '';


    /**
     * 设置列表页面 字段值的格式化方法
     * function ($value, TableInterface $rowTable){}
     * @param callable $callback
     * @return static
     */
    public function setListFormat(callable $callback): static
    {
        $this->listFormat = $callback;
        return $this;
    }

    /**
     * 设置表单页面 字段值的格式化方法
     * 表单查询出来数据以后 数据回填到表单
     *
     * 一般情况 返回格式化后的值就可以了
     * function ($value, TableInterface $table):string|int{}
     *
     * SelectField  需要返回一个数组， 第一项是 value，第二项是 text
     * function ($value, TableInterface $table):array{}
     * @param callable $callback
     * @return static
     */
    public function setFormFormat(callable $callback): static
    {
        $this->formFormat = $callback;
        return $this;
    }


    /**
     * 从表单接收到值以后，对数据格式化的方法
     * function ($value, $postAll){}
     * @param callable $callback
     * @return static
     */
    public function setFormRequestAfter(callable $callback): static
    {
        $this->formRequestAfter = $callback;
        return $this;
    }

    /**
     * 过滤器接收到的值以后，对数据格式化的方法
     * function ($value, $getAll){}
     * @param callable $filterRequestAfter
     * @return static
     */
    public function setFilterRequestAfter(callable $filterRequestAfter): static
    {
        $this->filterRequestAfter = $filterRequestAfter;
        return $this;
    }


    /**
     * @throws Throwable
     */
    public function __construct(
        // 数据库字段,使用的是别名
        public string $field,
        // 页面显示名称
        public string $label,
        // 使用的那个数据库
        public string $dbName = 'default',
    )
    {
        $this->label = Language::get($label);
        $fieldVar = Db::getFieldNameByAs($this->field, $this->dbName);
        $this->elemId = str_replace(['.', '_'], '-', $fieldVar);
        $this->fieldVar = $fieldVar;
    }


    /**
     * 设置字段的默认值,
     * 以下地方用到
     * 1. 列表页面 如果数据查询出来的值是null，则使用默认值
     * 2. 表单页面创建页面，
     * @param mixed $default
     * @return $this
     */
    public function setDefault(mixed $default): static
    {
        $this->default = $default;
        return $this;
    }


    /**
     * 过滤器默认都是等于查询，可以通过回调函数自定义查询
     */
    public function setFilterQuery(callable $filterQuery): static
    {
        $this->filterQuery = $filterQuery;
        return $this;
    }

    /**
     * 新增页面 可以用来初始化
     * @param mixed $formCreate
     * @return $this
     */
    public function setFormCreate(mixed $formCreate): AbstractField
    {
        $this->formCreate = $formCreate;
        return $this;
    }

    public function setFilterDefault(mixed $filterDefault): AbstractField
    {
        $this->filterDefault = $filterDefault;
        return $this;
    }

    public function setHelpText(string $helpText): static
    {
        $this->helpText = $helpText;
        return $this;
    }


}