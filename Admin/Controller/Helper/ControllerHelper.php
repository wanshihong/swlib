<?php

namespace Swlib\Admin\Controller\Helper;

use Swlib\Admin\Config\PageConfig;
use Swlib\Admin\Controller\Attribute\DisableAction;
use Swlib\Admin\Controller\Enum\AdminActionEnum;
use Swlib\Admin\Controller\Interface\AdminControllerInterface;
use Swlib\Admin\Exception\DisabledActionException;
use Swlib\Admin\Fields\AbstractField;
use Swlib\Admin\Fields\SelectField;
use Swlib\Admin\Manager\OptionManager;
use Swlib\Controller\Language\Enum\LanguageEnum;
use Swlib\Enum\CtxEnum;
use Swlib\Router\Router;
use Swlib\Table\Interface\TableDtoInterface;
use Throwable;

class ControllerHelper
{


    /**
     * lists 列表页面
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
     * 检查当前访问的方法是否被 DisableAction 注解标记
     *
     * @param AdminControllerInterface $controller
     * @throws DisabledActionException 如果方法被禁用则抛出异常
     * @throws Throwable
     */
    public static function checkDisabledAction(AdminControllerInterface $controller): void
    {
        $currentAction = self::getCurrentAction();
        if (empty($currentAction)) {
            return;
        }

        $disabled = DisableAction::checkMethodDisabled($controller, $currentAction);

        if ($disabled) {
            try {
                $enum = AdminActionEnum::from($currentAction);
                throw new DisabledActionException($enum->getDefaultMessage());
            } catch (Throwable) {
                throw new DisabledActionException(LanguageEnum::ADMIN_ACCESS_FORBIDDEN . ": $currentAction");
            }
        }
    }

    /**
     * 获取当前路由的方法名称
     *
     * 根据当前访问的页面返回对应的方法名，例如：
     * - 访问列表页面返回 'lists'
     * - 访问新建页面返回 'new'
     * - 访问编辑页面返回 'edit'
     * - 访问详情页面返回 'detail'
     * - 访问删除操作返回 'delete'
     *
     * @return string 当前路由的方法名称
     */
    public static function getCurrentAction(): string
    {
        $request = CtxEnum::Request->get();
        $pathInfo = $request->server['path_info'] ?? '';
        [,$pathInfo,] = Router::parse($pathInfo);
        $pathInfo = trim($pathInfo, '/');
        $parts = explode('/', $pathInfo);
        return end($parts) ?: '';
    }

}