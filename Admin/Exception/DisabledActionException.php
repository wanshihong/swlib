<?php
declare(strict_types=1);

namespace Swlib\Admin\Exception;

use Exception;
use Swlib\Admin\Manager\AdminManager;
use Swlib\Controller\Language\Service\Language;
use Swlib\Response\TwigResponse;
use Throwable;

/**
 * 禁用操作异常
 *
 * 当用户尝试访问被 DisableAction 注解标记的方法时抛出此异常
 * 该异常会直接渲染错误页面并结束响应
 */
class DisabledActionException extends Exception
{
    /**
     * @param string $message 错误提示消息
     * @param string|null $backUrl 返回链接，默认为后台首页
     * @throws Throwable
     */
    public function __construct(string $message = "该操作已被禁用", ?string $backUrl = null)
    {
        $backUrl = $backUrl ?? AdminManager::getInstance()->adminIndexUrl;
        $translatedMessage = Language::get($message);
        parent::__construct($translatedMessage);

        // 直接渲染错误页面并输出
        TwigResponse::render("disabledAction.twig", [
            'msg' => $translatedMessage,
            'backText' => Language::get('返回'),
            'backUrl' => $backUrl
        ])->output();
    }
}

