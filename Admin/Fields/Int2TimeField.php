<?php

namespace Swlib\Admin\Fields;


use DateTime;
use Swlib\Exception\AppErr;
use Swlib\Exception\AppException;
use InvalidArgumentException;
use Swlib\Table\Interface\TableInterface;
use Throwable;

class Int2TimeField extends AbstractField
{

    // 列表页面自定义模板
    public string $templateList = "fields/lists/int2time.twig";

    // 列表页面自定义模板
    public string $templateForm = "fields/form/int2time.twig";
    public string $templateFilter = "fields/filter/int2time.twig";

    // 列表页面过滤器是否支持范围查询
    public bool $filterRange = false;

    public function __construct(string $field, string $label)
    {
        parent::__construct($field, $label);


        // 表单接收到值以后转化成时间戳
        $this->setFormRequestAfter(function ($value) {
            if (empty($value)) {
                return 0;
            }

            $dateTime = new DateTime($value);
            return $dateTime->getTimestamp();
        });

        $this->setFormFormat(function ($value) {
            return date('Y-m-d\TH:i:s', $value);
        });

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
        if (is_array($this->value)) {
            $start = null;
            $end = null;

            // 验证并转换开始时间
            if (!empty($this->value[0])) {
                try {
                    $start = new DateTime($this->value[0]);
                } catch (Throwable) {
                    throw new AppException(AppErr::FORM_FIELD_START_DATE_FORMAT_INVALID);
                }
            }

            // 验证并转换结束时间
            if (!empty($this->value[1])) {
                try {
                    $end = new DateTime($this->value[1]);
                    $end->modify('+1 day'); // 加一天以包含结束日的全部时间
                } catch (Throwable) {
                    throw new AppException(AppErr::FORM_FIELD_END_DATE_FORMAT_INVALID);
                }
            }

            // 设置查询条件
            if ($start !== null && $end !== null) {
                $query->addWhere($this->field, [$start->getTimestamp(), $end->getTimestamp()], 'between');
            } elseif ($start !== null) {
                $query->addWhere($this->field, $start->getTimestamp(), '>=');
            } elseif ($end !== null) {
                $query->addWhere($this->field, $end->getTimestamp(), '<=');
            }
        } else {
            // 验证并转换单个时间值
            if (empty($this->value) || !is_string($this->value) || strtotime($this->value) === false) {
                throw new AppException(AppErr::FORM_FIELD_DATE_FORMAT_INVALID);
            }

            $timestamp = strtotime($this->value);
            $nextDay = strtotime('+1 day', $timestamp);

            $query->addWhere($this->field, [$timestamp, $nextDay], 'between');
        }
    }


    public function setFilterRange(bool $range): Int2TimeField
    {
        $this->filterRange = $range;
        return $this;
    }


}