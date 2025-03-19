<?php

namespace Swlib\Admin\Trait\Field;

use Throwable;

trait FieldArrayTrait
{

    const string ARRAY_FIELD_TYPE_STRING = 'string';
    const string ARRAY_FIELD_TYPE_ARRAY = 'array';

    public function arrayFieldInit(string $listsType = self::ARRAY_FIELD_TYPE_STRING): void
    {

        // 表单页面接收值后，数据格式化
        $this->setFormRequestAfter(function ($value) {
            if (empty($value)) {
                return '[]';
            }
            foreach ($value as $k => $v) {
                $value[$k] = trim($v);
            }
            return json_encode(array_unique($value), JSON_UNESCAPED_UNICODE);
        });

        // 设置字段的默认值，添加的时候需要用到
        $this->setDefault([""]);

        // 表单编辑 回填数据到表单的时候数据格式化
        $this->setFormFormat(function ($value) {
            if (empty($value)) return [""];
            try {
                return json_decode($value, true) ? json_decode($value, true) : [$value];
            } catch (Throwable) {
                return [""];
            }
        });


        // 列表页面数据格式化
        $this->setListFormat(function (string|null $value) use ($listsType) {
            if (empty($value)){
                return ' ';
            }
            try {
                $arr = json_decode($value, true);
                if (empty($arr)) return '';
                if ($listsType === self::ARRAY_FIELD_TYPE_STRING) {
                    return implode(',', $arr);
                }
                return $arr;
            } catch (Throwable) {
                return $value ? implode(',', $arr) :'' ;
            }
        });
    }

}