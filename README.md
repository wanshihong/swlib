# SWLib 框架使用指南

SWLib 是一个基于 PHP 8.4+ 与 Swoole 的业务开发框架，覆盖路由、ORM、后台管理、事件、队列、多语言与代码生成等常见能力。本文档负责快速建立整体认知，并指引你进入对应的专项手册。

## 文档导航

| 主题          | 文档                                   | 适合谁看          | 说明                               |
|-------------|--------------------------------------|---------------|----------------------------------|
| 框架总览        | `README.md`                          | 所有人           | 快速建立目录、能力与文档地图                   |
| 框架概述        | `docs/1-框架概述.md`                     | 新接手项目的开发者     | 框架组成、启动流程、目录结构                   |
| 快速开始        | `docs/2-快速开始.md`                     | 初次搭建环境的开发者    | 安装、配置、启动                         |
| ORM / Table | `docs/3-数据库操作-ORM.md`                | 后端开发者         | Table、DTO、查询、事务                  |
| 路由系统        | `docs/4-路由系统.md`                     | 后端开发者         | 路由、控制器、参数读取                      |
| 后台管理        | `docs/5-后台管理系统.md`                   | 后台开发者         | 页面生命周期、字段系统、VirtualField、模板与性能规范 |
| AOP 与代理     | `docs/6-AOP和代理系统.md`                 | 需要理解切面与代理的开发者 | 事务、代理、横切逻辑                       |
| 事件系统        | `docs/7-事件系统.md`                     | 后端开发者         | 事件定义、监听器、异步触发                    |
| 队列/任务/定时    | `docs/9-定时任务.md` ~ `docs/11-任务处理.md` | 任务开发者         | 定时任务、队列、Task                     |
| 代码生成        | `docs/15-代码生成系统.md`                  | 框架维护者         | 生成器与模板                           |
| 最佳实践        | `docs/20-最佳实践.md`                    | 所有人           | 性能、规范、安全建议                       |

## 核心能力概览

| 能力          | 作用               | 常用入口                                       |
|-------------|------------------|--------------------------------------------|
| 路由          | 定义接口与页面入口        | `#[Router]`、控制器                            |
| ORM / Table | 数据库访问、DTO、查询构建   | `Generate\Tables\*`、`Generate\TablesDto\*` |
| 后台管理        | 快速构建 CRUD 与运营后台  | `AbstractAdmin`、`PageConfig`、字段类           |
| 多语言         | 服务端文本多语言         | `Language::get()`                          |
| 事件系统        | 业务解耦与异步触发        | Event / Listener                           |
| 定时任务        | 周期任务执行           | Crontab                                    |
| 队列 / Task   | 异步任务处理           | Queue / Task                               |
| 代码生成        | 生成 Table、DTO、路由等 | `runtime/codes/*`                          |

## 后台管理快速概览

后台系统由 `AbstractAdmin` 驱动，一个后台页面通常由三部分构成：

1. `configPage()` 配置页面名称、表名、排序和分页
2. `configField()` 配置列表、表单、筛选与详情字段
3. `join()` / `listsQuery()` / 保存钩子补充查询与业务逻辑

当前后台字段分为两类：

| 字段类别           | 数据来源        | 是否参与基础 SQL 查询 | 适合场景                |
|----------------|-------------|--------------:|---------------------|
| 普通字段           | 基础列表 SQL    |             是 | 原表字段、简单关联字段、轻量格式化   |
| `VirtualField` | 基础列表查询后批量回填 |             否 | 跨表状态、聚合统计、当前页批量派生字段 |

## 字段快速选型

| 需求         | 推荐方案                | 是否参与基础 SQL 查询 | 备注            |
|------------|---------------------|--------------:|---------------|
| 原表文本展示     | `TextField`         |             是 | 最常见           |
| 数字展示 / 输入  | `NumberField`       |             是 | 可配置最小值、最大值    |
| 枚举状态       | `SelectField`       |             是 | 静态选项优先        |
| 开关状态       | `SwitchField`       |             是 | 布尔状态          |
| 图片展示 / 上传  | `ImageField` 或自定义模板 |             是 | 图片类字段         |
| 富文本        | `EditorField`       |             是 | 商品详情、文章详情     |
| 跨表状态 / 聚合列 | `VirtualField`      |             否 | 必须批量处理，禁止 N+1 |

## 后台开发最小示例

```php
<?php

namespace App\AdminDemo\User;

use Generate\Tables\Main\UserTable;
use Swlib\Admin\Config\PageConfig;
use Swlib\Admin\Config\PageFieldsConfig;
use Swlib\Admin\Controller\Abstract\AbstractAdmin;
use Swlib\Admin\Fields\NumberField;
use Swlib\Admin\Fields\SelectField;
use Swlib\Admin\Fields\TextField;
use Swlib\Admin\Fields\VirtualField;
use Swlib\Admin\Manager\OptionManager;
use Swlib\Table\Interface\TableInterface;

class UserAdmin extends AbstractAdmin
{
    protected function configPage(PageConfig $config): void
    {
        $config->pageName = '用户管理';
        $config->tableName = UserTable::class;
        $config->order = [UserTable::ID => 'desc'];
    }

    public function listsQuery(TableInterface $query): void
    {
        $query->addWhere(UserTable::STATUS, 1);
    }

    protected function configField(PageFieldsConfig $fields): void
    {
        $fields->setFields(
            new NumberField(UserTable::ID, 'ID')->hideOnForm(),
            new TextField(UserTable::USERNAME, '用户名'),
            new SelectField(UserTable::STATUS, '状态')->setOptions(
                new OptionManager(1, '启用'),
                new OptionManager(0, '禁用'),
            ),
            (new VirtualField('profile_summary', '资料摘要'))
                ->setBatchValueResolver(function (array $rows): array {
                    return [
                        1 => '已完善',
                        2 => '待补充',
                    ];
                }),
        );
    }
}
```

## 后台最佳实践摘要

| 推荐做法                                      | 不推荐做法                     |
|-------------------------------------------|---------------------------|
| 列表跨表派生值使用 `VirtualField` 批量处理             | 在 `setListFormat()` 中逐行查库 |
| 多个虚拟字段共享同一批数据时使用 `batchKey + batchLoader` | 每个字段各查一次同一张表              |
| 模板只负责展示                                   | 在 Twig 模板中拼复杂业务逻辑         |
| 原表字段用普通字段类直接展示                            | 借用真实字段承载虚拟值               |
| 先看后台手册再看源码                                | 直接翻源码猜生命周期与扩展点            |

## 环境要求

| 项目     | 要求                                                                   |
|--------|----------------------------------------------------------------------|
| PHP    | `>= 8.4`                                                             |
| Swoole | `>= 6.0.0`                                                           |
| 数据库    | MySQL 5.7+ / MariaDB 10.3+                                           |
| 缓存     | Redis 5.0+                                                           |
| 常用扩展   | `mysqli`、`redis`、`bcmath`、`curl`、`openssl`、`mbstring`、`gd`、`gmagick` |

## 下一步阅读建议

1. 首次接触项目：先看 `docs/1-框架概述.md`
2. 需要写后台页面：直接看 `docs/5-后台管理系统.md`
3. 需要查库或改查询：看 `docs/3-数据库操作-ORM.md`
4. 需要理解路由与控制器：看 `docs/4-路由系统.md`
