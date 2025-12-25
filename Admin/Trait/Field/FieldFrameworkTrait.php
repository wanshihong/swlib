<?php

namespace Swlib\Admin\Trait\Field;

use Swlib\Enum\CtxEnum;
use Swlib\Table\Interface\TableDtoInterface;
use Swlib\Table\Interface\TableInterface;
use Swoole\Http\Request;
use Throwable;

trait FieldFrameworkTrait
{

    /**
     * 表单编辑的时候，设置字段的值
     * 请勿手动调用，由框架自动调用
     * 请勿手动调用，由框架自动调用
     * 请勿手动调用，由框架自动调用
     * @param TableDtoInterface $dto
     * @return $this
     * @throws Throwable
     */
    public function frameworkEditFill(TableDtoInterface $dto): static
    {
        $value = $dto->getByField($this->field, $this->default);
        if ($this->formFormat) {
            $value = call_user_func($this->formFormat, $value, $dto);
        }

        $this->value = $value;
        return $this;
    }

    /**
     * 获取页面查询的字段
     * 部分字段配置可能需要查询多个字段，所有这里返回数组
     * 请勿手动调用，由框架自动调用
     * 请勿手动调用，由框架自动调用
     * 请勿手动调用，由框架自动调用
     * @return string[]
     */
    public function frameworkGetQueryField(): array
    {
        return [$this->field];
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
        /** @var Request $request */
        $request = CtxEnum::Request->get();
        $value = $request->get[$this->field] ?? null;

        if ($this->filterRequestAfter) {
            $value = call_user_func($this->filterRequestAfter, $value, $request->get ?: []);
        }
        if ($value && is_string($value)) {
            $value = trim($value);
        }
        $this->value = $value;
        return $value;
    }


    /**
     * 列表页面过滤器接收到值以后，设置查询条件
     * 请勿手动调用，由框架自动调用
     * 请勿手动调用，由框架自动调用
     * 请勿手动调用，由框架自动调用
     * @throws Throwable
     */
    public function frameworkFilterAddQueryWhere(TableInterface $query): void
    {
        $query->addWhere($this->field, $this->value);
    }

    /**
     * 表单接收 post 数据
     * 请勿手动调用，由框架自动调用
     * 请勿手动调用，由框架自动调用
     * 请勿手动调用，由框架自动调用
     * @throws Throwable
     */
    public function frameworkFormRequest()
    {
        /** @var Request $request */
        $request = CtxEnum::Request->get();
        $value = $this->default ?: null;
        if (isset($request->post[$this->field])) {
            $value = $request->post[$this->field];
        }

        if ($this->formRequestAfter) {
            $value = call_user_func($this->formRequestAfter, $value, $request->post ?: []);
        }

        $this->value = $value;

        return $this->value;
    }

    /**
     * 列表 设置字段的值
     * 请勿手动调用，由框架自动调用
     * 请勿手动调用，由框架自动调用
     * 请勿手动调用，由框架自动调用
     * @param mixed $value
     * @param TableInterface $table
     * @return $this
     */
    public function frameworkSetValue(mixed $value, TableInterface $table): static
    {
        if ($this->listFormat && is_callable($this->listFormat)) {
            $value = call_user_func($this->listFormat, $value, $table);
        }

        $value = $value == null && $this->default !== null ? $this->default : $value;

        $this->value = $value;
        return $this;
    }

}