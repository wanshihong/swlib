<?php

namespace Swlib\Admin\Controller;

use Swlib\Admin\Config\PageConfig;
use Swlib\Admin\Config\PageFieldsConfig;
use Swlib\Admin\Enum\PagePosEnum;
use Swlib\Admin\Fields\Int2TimeField;
use Swlib\Admin\Fields\TextField;
use Swlib\Admin\Manager\AdminManager;
use Swlib\Admin\Middleware\AdminInitMiddleware;
use Swlib\Enum\CtxEnum;
use Swlib\Exception\AppException;
use Swlib\Response\RedirectResponse;
use Swlib\Router\Router;
use Swlib\Table\Db;
use Swlib\Utils\Func;
use ReflectionException;
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
        // 这里的 path 不要去掉，否则前台请求不携带 cookie
        $this->response->cookie(Func::getCookieKey('lang'), $language, time() + 86400 * 365, '/');

        $request = CtxEnum::Request->get();
        $url = $request->header['referer'] ?? AdminManager::getInstance()->adminIndexUrl;
        return RedirectResponse::url($url);
    }


    /**
     * @throws Throwable
     */
    protected function configField(PageFieldsConfig $fields): void
    {

        $reflection = Db::getTableReflection('LanguageTable');
        $fields->setFields(
            new TextField(field: $reflection->getConstant('ID'), label: "ID")->hideOnForm(),
            new TextField(field: $reflection->getConstant('KEY'), label: '标识')->setReadonly($this->pagePos === PagePosEnum::FORM_EDIT),
            new Int2TimeField(field: $reflection->getConstant('USE_TIME'), label: '上次使用时间')->hideOnForm(),
            new TextField(field: $reflection->getConstant('ZH'), label: '简体中文'),
            new TextField(field: $reflection->getConstant('EN'), label: 'English'),
        );

    }


}