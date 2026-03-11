# Swlib 文档校对记录（2026-03-11）

## 校对范围

- `backed/Swlib/README.md`
- `backed/Swlib/docs/*.md`
- 关键实现源码：`App.php`、`Router.php`、`OnRequestEvent.php`、`Db.php`、`TransactionTrait.php`、`MessageQueue.php`、`TaskDispatcher.php`

## 本轮补强点

1. README 改为任务导向入口，突出“快速定位与排障索引”。
2. 每篇文档新增统一入口块：快速入口、常见误用、关联源码、排障索引。
3. 文档组织强调“主题可扩展”，不固定绑定为 20 篇。
4. 新增单页 HTML 生成方案，支持从 Markdown 重复构建。

## 代码一致性重点

- 路由链路补充了 PathInfo 冲突与 422 行为提示（`OnRequestEvent`）。
- ORM 事务补充了跨库事务限制提示（`TransactionTrait` + `Db`）。
- 队列补充了锁与重试语义（`MessageQueue`）。
- Task 补充了可序列化参数约束（`TaskDispatcher`）。

## 后续可继续拆分的文档

- 后台管理：可拆为字段系统、生命周期、模板扩展、性能专题。
- ORM：可拆为查询 DSL、事务专题、缓存与慢 SQL 专题。
- Protobuf：可拆为字段扩展规则与前端对接专题。
