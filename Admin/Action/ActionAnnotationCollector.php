<?php
declare(strict_types=1);

namespace Swlib\Admin\Action;

use Swlib\Admin\Action\Attribute\ActionButton;
use Swlib\Admin\Action\Enum\ActionPosEnum;
use Swlib\Admin\Config\PageConfig;
use Swlib\Admin\Controller\Attribute\DisableAction;
use Swlib\Admin\Controller\Interface\AdminControllerInterface;
use Swlib\Admin\Manager\AdminUserManager;
use Swlib\DataManager\ReflectionManager;
use Swlib\Enum\CtxEnum;
use Swlib\Request\Request;
use Swlib\Utils\Url;
use Throwable;

/**
 * 操作注解收集器
 *
 * 通过反射扫描控制器类，收集方法上的ActionButton注解，并转换为Action对象
 */
class ActionAnnotationCollector
{

    /**
     * 从控制器实例中收集当前正在执行方法的注解
     *
     * 收集当前类及所有父类的同名方法上的注解
     * 基于 URL 进行去重，子类的按钮优先级更高
     *
     * @param AdminControllerInterface $controllerInstance 控制器实例
     * @param ActionPosEnum $pos 位置
     * @return ActionButton[]
     * @throws Throwable
     */
    public static function collectFromPos(AdminControllerInterface $controllerInstance, ActionPosEnum $pos): array
    {
        $actions = [];
        $urlMap = []; // 用于去重的 URL 映射，记录每个 URL 对应的按钮

        $reflectionClass = ReflectionManager::getClass($controllerInstance);
        $currentClassName = $reflectionClass->getName();

        // 收集控制器类上的 ActionButton 注解
        $classAttributes = $reflectionClass->getAttributes(ActionButton::class);
        foreach ($classAttributes as $attribute) {
            /** @var ActionButton $actionButton */
            $actionButton = $attribute->newInstance();
            if ($actionButton->enable && in_array($pos, $actionButton->showOn) && AdminUserManager::hasPermissions($actionButton->allowRoles)) {
                // 类级别的注解，URL 作为唯一标识，需要格式化 URL 以便比较
                $normalizedUrl = self::normalizeUrl($actionButton->url);
                if (!isset($urlMap[$normalizedUrl])) {
                    $urlMap[$normalizedUrl] = [
                        'button' => $actionButton,
                        'class' => $currentClassName,
                        'isMethod' => false
                    ];
                }
            }
        }

        // 收集方法上的 ActionButton 注解
        $methods = $reflectionClass->getMethods();

        foreach ($methods as $method) {
            // 检查方法是否被 DisableAction 注解标记
            if (DisableAction::checkMethodDisabled($controllerInstance, $method->getName())) {
                continue;
            }

            $attributes = $method->getAttributes(ActionButton::class);

            foreach ($attributes as $attribute) {
                /** @var ActionButton $actionButton */
                $actionButton = $attribute->newInstance();
                if ($actionButton->enable && in_array($pos, $actionButton->showOn) && AdminUserManager::hasPermissions($actionButton->allowRoles)) {
                    if (empty($actionButton->url)) {
                        $actionButton->url = $method->getName();
                    }

                    $normalizedUrl = self::normalizeUrl($actionButton->url);
                    $methodClass = $method->getDeclaringClass()->getName();

                    // 如果 URL 还没有被记录，或者当前方法来自子类（优先级更高），则更新
                    if (!isset($urlMap[$normalizedUrl]) || self::isSubclass($methodClass, $urlMap[$normalizedUrl]['class'])) {
                        $urlMap[$normalizedUrl] = [
                            'button' => $actionButton,
                            'class' => $methodClass,
                            'isMethod' => true
                        ];
                    }
                }
            }
        }

        // 从 urlMap 中提取按钮
        foreach ($urlMap as $item) {
            $actions[] = $item['button'];
        }

        // 按照sort字段排序，数字越大越靠后
        usort($actions, function ($a, $b) {
            return $a->sort <=> $b->sort;
        });


        // 处理占位符
        foreach ($actions as $action) {
            foreach ($action->params as $k => $param) {
                if (str_starts_with($param, 'get:')) {
                    $key = substr($param, 4);
                    $action->params [$k] = Request::get($key);
                }
                if (str_starts_with($param, 'post:')) {
                    $key = substr($param, 5);
                    $action->params [$k] = Request::post($key);
                }
            }
        }


        // 收集静态页面添加到页面
        self::addStaticFilesToPage($controllerInstance->pageConfig, $actions);

        return $actions;
    }

    /**
     * 判断 $childClass 是否是 $parentClass 的子类
     *
     * @param string $childClass 子类名称
     * @param string $parentClass 父类名称
     * @return bool
     */
    private static function isSubclass(string $childClass, string $parentClass): bool
    {
        return $childClass !== $parentClass && is_subclass_of($childClass, $parentClass);
    }

    /**
     * 规范化 URL，将相对路径转换为绝对路径，便于比较
     *
     * @param string $url 原始 URL
     * @return string 规范化后的 URL
     */
    private static function normalizeUrl(string $url): string
    {
        if (empty($url)) {
            return '';
        }

        // 使用 Func::url() 将相对路径转换为绝对路径，不添加查询参数
        return Url::generateUrl($url, [], [], false);
    }


    /**
     * 添加字段，按钮等静态文件 到页面中
     * @param PageConfig $pageConfig
     * @param ActionButton[] $actions
     * @return void
     */
    private static function addStaticFilesToPage(PageConfig $pageConfig, array $actions): void
    {
        foreach ($actions as $field) {
            foreach ($field->cssFiles as $cssFile) {
                $pageConfig->addCssFile($cssFile);
            }

            foreach ($field->jsFiles as $jsFile) {
                $pageConfig->addJsFile($jsFile);
            }
        }
    }


}
