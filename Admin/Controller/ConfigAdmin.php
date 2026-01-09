<?php

namespace Swlib\Admin\Controller;

use App\Service\ConfigService;
use ReflectionException;
use Swlib\Admin\Config\PageConfig;
use Swlib\Admin\Config\PageFieldsConfig;
use Swlib\Admin\Controller\Abstract\AbstractAdmin;
use Swlib\Admin\Controller\Enum\AdminActionEnum;
use Swlib\Admin\Fields\ColorField;
use Swlib\Admin\Fields\ImageField;
use Swlib\Admin\Fields\Int2TimeField;
use Swlib\Admin\Fields\NumberField;
use Swlib\Admin\Fields\SelectField;
use Swlib\Admin\Fields\SwitchField;
use Swlib\Admin\Fields\TextareaField;
use Swlib\Admin\Fields\TextField;
use Swlib\Admin\Fields\UrlField;
use Swlib\Admin\Manager\OptionManager;
use Swlib\Table\Db;
use Swlib\Table\Interface\TableDtoInterface;
use Swlib\Table\Interface\TableInterface;
use Throwable;

class ConfigAdmin extends AbstractAdmin
{


    /**
     * @throws ReflectionException
     */
    protected function configPage(PageConfig $config): void
    {
        $config->pageName = "系统配置";
        $reflection = Db::getTableReflection('ConfigTable');
        $config->tableName = $reflection->getName();
        $config->order = [
            $reflection->getConstant('KEY') => 'asc',
            $reflection->getConstant('ID') => 'desc'
        ];

    }

    public function listsQuery(TableInterface $query): void
    {

    }


    /**
     * @throws Throwable
     */
    protected function configField(PageFieldsConfig $fields): void
    {
        $reflection = Db::getTableReflection('ConfigTable');
        $valueType = $this->get($reflection->getConstant('VALUE_TYPE'), '', 'txt');
        if ($valueType === 'txt') {
            $valueConfig = new TextareaField(field: $reflection->getConstant('VALUE'), label: '配置')->hideOnFilter();
        } elseif ($valueType === 'time') {
            $valueConfig = new Int2TimeField(field: $reflection->getConstant('VALUE'), label: '配置')->hideOnFilter();
        } elseif ($valueType === 'number') {
            $valueConfig = new NumberField(field: $reflection->getConstant('VALUE'), label: '配置')->hideOnFilter();
        } elseif ($valueType === 'url') {
            $valueConfig = new UrlField(field: $reflection->getConstant('VALUE'), label: '配置')->hideOnFilter();
        }elseif ($valueType === 'color') {
            $valueConfig = new ColorField(field: $reflection->getConstant('VALUE'), label: '配置')->hideOnFilter();
        } else {
            $valueConfig = new ImageField(field: $reflection->getConstant('VALUE'), label: '配置')->hideOnFilter();
        }

        $isEdit = $this->getCurrentAction() === AdminActionEnum::EDIT->value;

        $typeField = new SelectField(field: $reflection->getConstant('VALUE_TYPE'), label: '配置类型')->hideOnFilter()->setOptions(
            new OptionManager('txt', '文本'),
            new OptionManager('number', '数字'),
            new OptionManager('url', '链接'),
            new OptionManager('image', '图片'),
            new OptionManager('time', '时间'),
            new OptionManager('color', '色彩'),
        )->hideOnFilter();

        if ($isEdit) {
            $typeField->hideOnForm();
        }

        $fields->setFields(
            new NumberField(field: $reflection->getConstant('ID'), label: 'ID')->hideOnForm()->hideOnList()->hideOnFilter(),
            new TextField(field: $reflection->getConstant('KEY'), label: '配置唯一标识')->setListMaxWidth(200)->setDisabled($isEdit),
            new TextField(field: $reflection->getConstant('DESC'), label: '配置说明')->setListMaxWidth(200),
            $valueConfig,
            new SwitchField(field: $reflection->getConstant('IS_ENABLE'), label: '是否启用')->hideOnForm()->hideOnFilter(),
            new SelectField(field: $reflection->getConstant('IS_ENABLE'), label: '是否启用')->onlyOnFilter()->setOptions(
                new OptionManager(1, '启用'),
                new OptionManager(0, '不启用'),
            ),
            new SwitchField(field: $reflection->getConstant('ALLOW_QUERY'), label: '允许接口查询')->hideOnForm()->hideOnFilter(),
            $typeField,
        );
    }


    /**
     * @throws Throwable
     */
    public function insertUpdateAfter(TableDtoInterface $dto): void
    {
        ConfigService::clearCache($dto->key);
    }


}