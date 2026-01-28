<?php
declare(strict_types=1);

namespace Swlib\Admin\Action;

use Swlib\Admin\Action\Attribute\ActionButton;
use Swlib\Admin\Action\Attribute\BatchAction;
use Swlib\Admin\Config\PageConfig;
use Swlib\Admin\Controller\Attribute\DisableAction;
use Swlib\Admin\Controller\Interface\AdminControllerInterface;
use Swlib\Admin\Manager\AdminUserManager;
use Swlib\DataManager\ReflectionManager;
use Swlib\Utils\Url;
use Throwable;

/**
 * 批量操作注解收集器
 *
 * 通过反射扫描控制器类，收集方法上的BatchAction注解
 */
class BatchActionCollector
{

    /**
     * 从控制器实例中收集所有批量操作注解
     *
     * 基于 URL 进行去重，子类的操作优先级更高
     *
     * @param AdminControllerInterface $controllerInstance 控制器实例
     * @return BatchAction[]
     * @throws Throwable
     */
    public static function collect(AdminControllerInterface $controllerInstance): array
    {
        $actions = [];
        $urlMap = []; // 用于去重的 URL 映射，记录每个 URL 对应的操作

        $reflectionClass = ReflectionManager::getClass($controllerInstance);
        $currentClassName = $reflectionClass->getName();


        // 收集控制器类上的 BatchAction 注解
        $classAttributes = $reflectionClass->getAttributes(BatchAction::class);
        foreach ($classAttributes as $attribute) {
            /** @var BatchAction $batchAction */
            $batchAction = $attribute->newInstance();
            if ($batchAction->enable && AdminUserManager::hasPermissions($batchAction->allowRoles)) {
                // 类级别的注解，URL 作为唯一标识，需要格式化 URL 以便比较
                $normalizedUrl = self::normalizeUrl($batchAction->url);
                if (!isset($urlMap[$normalizedUrl])) {
                    $urlMap[$normalizedUrl] = [
                        'action' => $batchAction,
                        'class' => $currentClassName,
                        'isMethod' => false
                    ];
                }
            }
        }


        // 收集方法上的批量操作注解
        $methods = $reflectionClass->getMethods();

        foreach ($methods as $method) {
            // 检查方法是否被 DisableAction 注解标记
            if (DisableAction::checkMethodDisabled($controllerInstance, $method->getName())) {
                continue;
            }

            $attributes = $method->getAttributes(BatchAction::class);

            foreach ($attributes as $attribute) {
                /** @var BatchAction $batchAction */
                $batchAction = $attribute->newInstance();

                // 检查是否启用以及权限
                if ($batchAction->enable && AdminUserManager::hasPermissions($batchAction->allowRoles)) {
                    // 设置URL为方法名
                    if (empty($batchAction->url)) {
                        $batchAction->url = $method->getName();
                    }

                    $normalizedUrl = self::normalizeUrl($batchAction->url);
                    $methodClass = $method->getDeclaringClass()->getName();

                    // 如果 URL 还没有被记录，或者当前方法来自子类（优先级更高），则更新
                    if (!isset($urlMap[$normalizedUrl]) || self::isSubclass($methodClass, $urlMap[$normalizedUrl]['class'])) {
                        $urlMap[$normalizedUrl] = [
                            'action' => $batchAction,
                            'class' => $methodClass,
                            'isMethod' => true
                        ];
                    }
                }
            }
        }

        // 从 urlMap 中提取操作
        foreach ($urlMap as $item) {
            $actions[] = $item['action'];
        }

        // 按照sort字段排序，数字越小越靠前
        usort($actions, function ($a, $b) {
            return $a->sort <=> $b->sort;
        });

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

        // 将相对路径转换为绝对路径，不添加查询参数
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

