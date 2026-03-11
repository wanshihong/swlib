# SWLib 使用指南

SWLib 是基于 PHP 8.4+ 与 Swoole 的业务框架，覆盖路由、ORM、后台、事件、队列、任务、连接池、Protobuf、AOP 与代码生成能力。本 README 只做“快速定位入口”，详细内容都在 `docs/`。

## 我现在要做什么

| 目标 | 先看哪里 | 再看哪里 |
| --- | --- | --- |
| 新人 5 分钟了解框架 | `docs/1-框架概述.md` | `docs/2-快速开始.md` |
| 写 API（Router + Protobuf） | `docs/4-路由系统.md` | `docs/17-Protobuf集成.md` |
| 写数据库查询/事务 | `docs/3-数据库操作-ORM.md` | `docs/19-异常处理.md` |
| 写后台 CRUD 页 | `docs/5-后台管理系统.md` | `docs/20-最佳实践.md` |
| 写异步任务 | `docs/9-定时任务.md` `docs/10-队列系统.md` `docs/11-任务处理.md` | `docs/8-进程管理.md` |
| 排查连接与性能问题 | `docs/13-连接池管理.md` | `docs/14-服务器事件.md` |

## 文档主题地图（可扩展，不固定篇数）

| 主题簇 | 主要文档 |
| --- | --- |
| 入门与工程结构 | `docs/1-框架概述.md` `docs/2-快速开始.md` |
| Web 请求链路 | `docs/4-路由系统.md` `docs/16-中间件系统.md` `docs/14-服务器事件.md` |
| 数据访问与事务 | `docs/3-数据库操作-ORM.md` `docs/13-连接池管理.md` `docs/12-锁机制.md` |
| 后台系统 | `docs/5-后台管理系统.md` |
| 异步与调度 | `docs/8-进程管理.md` `docs/9-定时任务.md` `docs/10-队列系统.md` `docs/11-任务处理.md` `docs/7-事件系统.md` |
| 协议与生成 | `docs/17-Protobuf集成.md` `docs/15-代码生成系统.md` |
| 横切能力与规范 | `docs/6-AOP和代理系统.md` `docs/18-工具类.md` `docs/19-异常处理.md` `docs/20-最佳实践.md` |

## 常见排障入口

| 现象 | 快速跳转 |
| --- | --- |
| 404 / 405 / 路由不匹配 | `docs/4-路由系统.md` |
| Protobuf 请求解析异常 | `docs/17-Protobuf集成.md` |
| 事务跨库报错 | `docs/3-数据库操作-ORM.md` |
| 队列消息重复或不执行 | `docs/10-队列系统.md` |
| Task 参数不可序列化 | `docs/11-任务处理.md` |
| 连接池耗尽或慢查询 | `docs/13-连接池管理.md` |

## 单页手册（HTML）

`docs/swlib-manual.html` 是给开发者快速阅读的单页文档产物，内容由 `docs/*.md` 生成。

```bash
bash backed/Swlib/docs/build-single-page.sh
```

生成后可以直接浏览器打开，无需 PHP 服务，无需外部 CDN。

## 维护规则

1. Markdown 是唯一事实源，禁止手改生成后的章节正文。
2. 文档新增、拆分、重排时只更新 `docs/_meta/order.json`。
3. 每篇文档优先包含：快速入口、最小示例、排障和源码定位。
