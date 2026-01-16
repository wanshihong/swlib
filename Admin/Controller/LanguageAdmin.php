<?php

namespace Swlib\Admin\Controller;

use Generate\Tables\Main\LanguageTable;
use Swlib\Admin\Config\PageConfig;
use Swlib\Admin\Config\PageFieldsConfig;
use Swlib\Admin\Controller\Abstract\AbstractAdmin;
use Swlib\Admin\Fields\Int2TimeField;
use Swlib\Admin\Fields\TextField;
use Swlib\Admin\Manager\AdminManager;
use Swlib\Admin\Middleware\AdminInitMiddleware;
use Swlib\Enum\CtxEnum;
use Swlib\Exception\AppException;
use Swlib\Response\RedirectResponse;
use Swlib\Router\Router;
use Swlib\Utils\Cookie;
use Throwable;

#[Router(middleware: AdminInitMiddleware::class)]
class LanguageAdmin extends AbstractAdmin
{

    protected function configPage(PageConfig $config): void
    {
        $config->pageName = "翻译配置";
        $config->tableName = LanguageTable::class;
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

        $fields->setFields(
            new TextField(field: LanguageTable::ID, label: "ID")->hideOnForm(),
            new Int2TimeField(field: LanguageTable::USE_TIME, label: '上次使用时间')->hideOnForm(),
            new TextField(field: LanguageTable::ZH, label: '简体中文'),
            new TextField(field: LanguageTable::VI, label: '越南语')->hideOnFilter()->setRequired(false),
            new TextField(field: LanguageTable::EN, label: '英语'),
            new TextField(field: LanguageTable::JA, label: '日语')->hideOnList()->hideOnFilter()->setRequired(false),
            new TextField(field: LanguageTable::KO, label: '韩语')->hideOnList()->hideOnFilter()->setRequired(false),
            new TextField(field: LanguageTable::FR, label: '法语')->hideOnList()->hideOnFilter()->setRequired(false),
            new TextField(field: LanguageTable::ES, label: '西班牙语')->hideOnList()->hideOnFilter()->setRequired(false),
            new TextField(field: LanguageTable::IT, label: '意大利语')->hideOnList()->hideOnFilter()->setRequired(false),
            new TextField(field: LanguageTable::DE, label: '德语')->hideOnList()->hideOnFilter()->setRequired(false),
            new TextField(field: LanguageTable::TR, label: '土耳其语')->hideOnList()->hideOnFilter()->setRequired(false),
            new TextField(field: LanguageTable::RU, label: '俄语')->hideOnList()->hideOnFilter()->setRequired(false),
            new TextField(field: LanguageTable::PT, label: '葡萄牙语')->hideOnList()->hideOnFilter()->setRequired(false),
            new TextField(field: LanguageTable::INA, label: '印尼语')->hideOnList()->hideOnFilter()->setRequired(false),
            new TextField(field: LanguageTable::TH, label: '泰语')->hideOnList()->hideOnFilter()->setRequired(false),
            new TextField(field: LanguageTable::MS, label: '马来西亚语')->hideOnList()->hideOnFilter()->setRequired(false),
            new TextField(field: LanguageTable::AR, label: '阿拉伯语')->hideOnList()->hideOnFilter()->setRequired(false),
            new TextField(field: LanguageTable::HI, label: '印地语')->hideOnList()->hideOnFilter()->setRequired(false),
        );

    }


}