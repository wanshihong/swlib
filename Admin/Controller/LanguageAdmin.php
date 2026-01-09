<?php

namespace Swlib\Admin\Controller;

use ReflectionException;
use Swlib\Admin\Config\PageConfig;
use Swlib\Admin\Config\PageFieldsConfig;
use Swlib\Admin\Controller\Abstract\AbstractAdmin;
use Swlib\Admin\Controller\Enum\AdminActionEnum;
use Swlib\Admin\Fields\Int2TimeField;
use Swlib\Admin\Fields\TextField;
use Swlib\Admin\Manager\AdminManager;
use Swlib\Admin\Middleware\AdminInitMiddleware;
use Swlib\Enum\CtxEnum;
use Swlib\Exception\AppException;
use Swlib\Response\RedirectResponse;
use Swlib\Router\Router;
use Swlib\Table\Db;
use Swlib\Utils\Cookie;
use Throwable;

#[Router(middleware: AdminInitMiddleware::class)]
class LanguageAdmin extends AbstractAdmin
{

    /**
     * @throws ReflectionException
     */
    protected function configPage(PageConfig $config): void
    {
        $config->pageName = "翻译配置";
        $reflection = Db::getTableReflection('LanguageTable');
        $config->tableName = $reflection->getName();
    }


    /**
     * 后台设置当前的语言文件
     * @throws AppException
     */
    #[Router(method: 'GET')]
    public function setLanguage(): RedirectResponse
    {
        $language = $this->get('language');
        Cookie::set('lang', $language, 86400 * 365);
        $request = CtxEnum::Request->get();
        $url = $request->header['referer'] ?? AdminManager::getInstance()->adminIndexUrl;
        return RedirectResponse::url($url);
    }


    /**
     * @throws Throwable
     */
    protected function configField(PageFieldsConfig $fields): void
    {


        $isEdit = $this->getCurrentAction() === AdminActionEnum::EDIT->value;
        $reflection = Db::getTableReflection('LanguageTable');
        $fields->setFields(
            new TextField(field: $reflection->getConstant('ID'), label: "ID")->hideOnForm(),
            new Int2TimeField(field: $reflection->getConstant('USE_TIME'), label: '上次使用时间')->hideOnForm(),
            new TextField(field: $reflection->getConstant('ZH'), label: '简体中文'),
            new TextField(field: $reflection->getConstant('VI'), label: '越南语')->hideOnFilter()->setRequired(false),
            new TextField(field: $reflection->getConstant('EN'), label: '英语'),
            new TextField(field: $reflection->getConstant('JA'), label: '日语')->hideOnList()->hideOnFilter()->setRequired(false),
            new TextField(field: $reflection->getConstant('KO'), label: '韩语')->hideOnList()->hideOnFilter()->setRequired(false),
            new TextField(field: $reflection->getConstant('FR'), label: '法语')->hideOnList()->hideOnFilter()->setRequired(false),
            new TextField(field: $reflection->getConstant('ES'), label: '西班牙语')->hideOnList()->hideOnFilter()->setRequired(false),
            new TextField(field: $reflection->getConstant('IT'), label: '意大利语')->hideOnList()->hideOnFilter()->setRequired(false),
            new TextField(field: $reflection->getConstant('DE'), label: '德语')->hideOnList()->hideOnFilter()->setRequired(false),
            new TextField(field: $reflection->getConstant('TR'), label: '土耳其语')->hideOnList()->hideOnFilter()->setRequired(false),
            new TextField(field: $reflection->getConstant('RU'), label: '俄语')->hideOnList()->hideOnFilter()->setRequired(false),
            new TextField(field: $reflection->getConstant('PT'), label: '葡萄牙语')->hideOnList()->hideOnFilter()->setRequired(false),
            new TextField(field: $reflection->getConstant('INA'), label: '印尼语')->hideOnList()->hideOnFilter()->setRequired(false),
            new TextField(field: $reflection->getConstant('TH'), label: '泰语')->hideOnList()->hideOnFilter()->setRequired(false),
            new TextField(field: $reflection->getConstant('MS'), label: '马来西亚语')->hideOnList()->hideOnFilter()->setRequired(false),
            new TextField(field: $reflection->getConstant('AR'), label: '阿拉伯语')->hideOnList()->hideOnFilter()->setRequired(false),
            new TextField(field: $reflection->getConstant('HI'), label: '印地语')->hideOnList()->hideOnFilter()->setRequired(false),
        );

    }


}