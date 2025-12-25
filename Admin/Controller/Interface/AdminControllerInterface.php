<?php

declare(strict_types=1);

namespace Swlib\Admin\Controller\Interface;

use Swlib\Response\JsonResponse;
use Swlib\Response\RedirectResponse;
use Swlib\Response\TwigResponse;
use Throwable;

/**
 * Admin 控制器接口
 *
 * 定义了后台管理控制器的所有公共方法签名，
 * 用于 IDE 代码补全和类型检查支持。
 */
interface AdminControllerInterface
{
    /**
     * 列表页面
     *
     * 显示数据列表，支持分页、排序、过滤等功能
     *
     * @return TwigResponse 返回渲染后的列表页面
     * @throws Throwable
     */
    public function lists(): TwigResponse;

    /**
     * 新建页面
     *
     * GET 请求显示新建表单，POST 请求处理表单提交
     *
     * @return TwigResponse|RedirectResponse GET 返回表单页面，POST 成功后重定向到列表页
     * @throws Throwable
     */
    public function new(): TwigResponse|RedirectResponse;

    /**
     * 编辑页面
     *
     * GET 请求显示编辑表单，POST 请求处理表单提交
     *
     * @return TwigResponse|RedirectResponse GET 返回表单页面，POST 成功后重定向
     * @throws Throwable
     */
    public function edit(): TwigResponse|RedirectResponse;

    /**
     * 删除操作
     *
     * 删除指定的数据记录
     *
     * @return RedirectResponse 删除成功后重定向到列表页
     * @throws Throwable
     */
    public function delete(): RedirectResponse;

    /**
     * 详情页面
     *
     * 显示单条数据的详细信息
     *
     * @return TwigResponse 返回渲染后的详情页面
     * @throws Throwable
     */
    public function detail(): TwigResponse;

    /**
     * 开关切换
     *
     * 用于处理列表页面中的开关字段切换操作
     *
     * @return JsonResponse 返回操作结果的 JSON 响应
     * @throws Throwable
     */
    public function switch(): JsonResponse;

    /**
     * 获取选择列表
     *
     * 通过下拉输入框输入关键字时，获取匹配的选择列表数据
     *
     * @return JsonResponse 返回匹配的选项列表
     * @throws Throwable
     */
    public function getSelectList(): JsonResponse;

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
    public function getCurrentAction(): string;
}