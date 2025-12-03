<?php

namespace Swlib\Admin\Utils;

use Swlib\Admin\Config\PageConfig;
use Swlib\Admin\Fields\AbstractField;
use Swlib\Admin\Fields\SelectField;
use Swlib\Admin\Manager\OptionManager;
use Swlib\Enum\CtxEnum;
use Swlib\Table\Interface\TableDtoInterface;
use Throwable;


class Func
{

    /**
     * 添加字段，按钮等静态文件 到页面中
     * @param PageConfig $pageConfig
     * @param array $fields
     * @return void
     */
    public static function addStaticFilesToPage(PageConfig $pageConfig, array $fields): void
    {
        foreach ($fields as $field) {
            foreach ($field->cssFiles as $cssFile) {
                $pageConfig->addCssFile($cssFile);
            }

            foreach ($field->jsFiles as $jsFile) {
                $pageConfig->addJsFile($jsFile);
            }
        }
    }


    /**
     * 把 selectField 的列表查询提前，不然会导致 selectField 的每行一个查询
     * @param AbstractField[] $fields
     * @param TableDtoInterface $dbAll
     * @throws Throwable
     */
    public static function selectFieldBeforeQuery(array $fields, TableDtoInterface $dbAll): void
    {
        $selectFields = [];
        // 收集到所有下拉选择字段
        foreach ($fields as $field) {
            if ($field instanceof SelectField && $field->fieldShowList && $field->table) {
                $selectFields[$field->field] = [];
            }
        }

        // 收集到这些字段的值，并且存储为 ['field1'=>[1,2,3],'field2'=>[1,2,3]]
        foreach ($dbAll as $dto) {
            foreach ($selectFields as $field => $v) {
                $fieldValue = $dto->getByField($field);
                if (empty($fieldValue)) {
                    continue;
                }

                // 如果是JSON数组 则直接去掉两边的 []
                if (is_string($fieldValue) && str_starts_with($fieldValue, '[')) {
                    $fieldValue = substr($fieldValue, 1, -1);
                }

                // 解析成数组
                $arr = explode(',', $fieldValue);
                foreach ($arr as $a) {
                    if (!in_array($a, $selectFields[$field])) {
                        $selectFields[$field][] = $a;
                    }
                }
            }
        }

        // 查询，并回填数据
        foreach ($fields as $field) {
            if (!($field instanceof SelectField)) {
                continue;
            }
            foreach ($selectFields as $fieldName => $fieldValues) {
                if ($field->field !== $fieldName || empty($fieldValues)) {
                    continue;
                }
                $table = new $field->table;

                $findAll = $table->field([$field->idField, $field->textField])->setDebugSql()->addWhere($field->idField, $fieldValues, 'in')->selectAll();
                $options = [];
                foreach ($findAll as $t) {
                    $id = $t->getByField($field->idField);
                    $options[] = new OptionManager($id, $t->getByField($field->textField, "#$id"));
                }
                $field->setOptions(...$options);
            }
        }

    }


    /**
     * 生成基于当前控制器的路由
     * @param string $url
     * @param array $params
     * @param array $delParams 需要删除的参数列表
     * @param bool $hasAddParam 是否添加参数，不添加就直接返回
     * @return string
     */
    public static function url(string $url, array $params = [], array $delParams = [], bool $hasAddParam = true): string
    {
        if (
            str_starts_with($url, "http://")
            || str_starts_with($url, 'https://')
            || str_starts_with($url, 'javascript:')
        ) {
            if (empty($params)) {
                return $url;
            }
            return self::formatUrl($url, $params);
        }
        $request = CtxEnum::Request->get();

        if (str_starts_with($url, '/')) {
            // 是绝对路径
            $retUrl = $url;
        } else {
            // 相对路径
            $path_info = $request->server['path_info'];

            $arr = explode('/', $path_info);
            array_pop($arr);
            $arr[] = $url;
            $retUrl = implode('/', $arr);
        }


        if ($hasAddParam === false) {
            return self::formatUrl($retUrl, $params);
        }

        $queryParams = array_merge($request->get ?: [], $params);
        if (empty($queryParams)) {
            return self::formatUrl($retUrl, $queryParams);
        }

        // 删除参数
        if (!empty($delParams)) {
            foreach ($delParams as $param) {
                unset($queryParams[$param]);
            }
        }


        return self::formatUrl($retUrl, $queryParams);
    }


    public static function formatUrl($url, $params): string
    {
        if (empty($params)) {
            return $url;
        }
        if (stripos($url, '?') === false) {
            return $url . '?' . http_build_query($params);
        } else {
            return $url . '&' . http_build_query($params);
        }
    }

}