# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## 项目概述

SWLib 是一个基于 PHP 8.4+ 和 Swoole 6.0+ 的高性能 Web 框架库，专为构建企业级应用而设计。这是一个 Composer 库包 (`wansh/swlib`)，不是独立应用。

## 环境要求

- PHP >= 8.4
- Swoole >= 6.0.0
- 扩展: mysqli, redis, bcmath, curl, openssl, mbstring, gd, gmagick
- MySQL 5.7+ / MariaDB 10.3+
- Redis 5.0+

## 核心架构

### 注解驱动开发

框架大量使用 PHP 8.4+ 属性(Attributes)实现声明式编程：

```php
#[Router(method: 'POST')]           // 路由定义
#[Event(name: 'user.registered')]   // 事件监听
#[LoggingAspect]                     // AOP 切面
#[Transaction(dbName: 'default')]   // 数据库事务
#[QueueAttribute(delay: 120)]        // 队列任务
#[CrontabAttribute]                  // 定时任务
#[ProcessAttribute]                  // 自定义进程
```

### 代码生成系统

框架在启动时自动从数据库表结构生成代码（位于 `runtime/` 目录）：

| 类别 | 说明 | 目录 |
|------|------|------|
| Table | 数据表映射类，包含字段常量 | runtime/Generate/Tables/ |
| TableDto | 类型安全的 DTO 对象 | runtime/Generate/TablesDto/ |
| Model | 业务模型类 | runtime/Generate/Models/ |
| CRUD | 基础 CRUD 服务 | runtime/Generate/Cruds/ |
| Admin | 后台管理控制器 | runtime/Generate/Admins/ |
| Proto | Protobuf 协议文件 | protos/ |

**重要**: `runtime/` 和 `protos/` 目录下的文件由框架自动生成，不要手动修改。

### 目录结构

```
Swlib/
├── Admin/           # 后台管理系统（控制器、字段、菜单、模板）
├── Aop/             # AOP 切面编程
├── Connect/         # 连接池管理（MySQL/Redis）
├── Controller/      # 基础控制器
├── Crontab/         # 定时任务
├── Event/           # 事件系统
├── Exception/       # 异常类
├── Parse/           # 代码生成/解析系统
├── Queue/           # 消息队列
├── Router/          # 路由系统
├── Table/           # ORM 核心
├── TaskProcess/     # 任务工作进程
├── Utils/           # 工具类
├── App.php          # 应用入口类
└── docs/            # 中文文档（20 个章节）
```

## 核心组件使用

### ORM 查询

```php
use Generate\Tables\UserTable;

// 使用字段常量，避免字符串
$user = new UserTable()
    ->field([UserTable::ID, UserTable::USERNAME])
    ->where([UserTable::ID => $id])
    ->selectOne();

// DTO 对象访问
echo $user->username;

// 分页查询
$users = new UserTable()
    ->where([UserTable::STATUS => 1])
    ->order([UserTable::ID => 'desc'])
    ->page(1, 10)
    ->selectAll();

// 使用缓存
$users = new UserTable()
    ->where([UserTable::STATUS => 1])
    ->cache(300)  // 缓存 5 分钟
    ->selectAll();
```

### 控制器

```php
use Swlib\Controller\Abstract\AbstractController;
use Swlib\Router\Router;

#[Router(method: 'POST')]
class UserController extends AbstractController
{
    public function info(): UserProto
    {
        $id = $this->get('id', 'ID不能为空');
        $name = $this->post('name', '名称不能为空');
        // ...
    }
}
```

### 异常处理

```php
use Swlib\Exception\AppException;
use Swlib\Exception\UnauthorizedException;
use Swlib\Exception\TokenExpiredException;
use Swlib\Exception\RedirectException;

// 使用 AppErr 常量，不要硬编码消息
throw new AppException(AppErr::PARAM_ERROR);

// 特定场景使用对应异常
throw new UnauthorizedException();    // 用户未登录
throw new TokenExpiredException();    // Token 过期
```

### AOP 切面

```php
use Swlib\Aop\Abstract\AbstractAspect;
use Swlib\Aop\JoinPoint;

class LoggingAspect extends AbstractAspect
{
    public function before(JoinPoint $joinPoint): void { }
    public function after(JoinPoint $joinPoint, mixed $result): void { }
    public function around(JoinPoint $joinPoint): mixed { }
    public function afterThrowing(JoinPoint $joinPoint, Throwable $e): void { }
}
```

### 连接池

```php
use Swlib\Connect\PoolRedis;

// 执行 Redis 操作
PoolRedis::call(function (Redis $redis) {
    return $redis->get('key');
});

// 缓存辅助方法
PoolRedis::getSet(
    key: 'cache_key',
    call: fn() => new UserTable()->selectAll(),
    expire: 3600
);
```

## 代码风格规范

### 命名规范

| 类型 | 规范 | 示例 |
|------|------|------|
| 类名 | PascalCase | `UserController` |
| 方法名 | camelCase | `getUserInfo` |
| 常量 | UPPER_SNAKE_CASE | `MAX_RETRY_COUNT` |
| 变量 | camelCase | `$userId` |

### 代码风格要点

```php
// ✅ 推荐
echo "welcome $name";
$user = new UserTable();
new UserTable()->field([UserTable::ID, UserTable::USERNAME]);

// ❌ 避免
echo "welcome {$name}";
$user = (new UserTable());
new UserTable()->field(['id', 'username']);
```

## 项目规范

- **API 设计**: 每个 API 独立一个类，私有方法放在 `run` 方法下方
- **服务层**: 只有多个地方用到的通用服务才设计服务层 Service
- **异常处理**: 使用 `AppErr` 常量，不要硬编码消息
- **ORM**: 优先使用 DTO 对象而非数组，只查询需要的字段
- **禁止修改**: 不要修改 `runtime/` 和 `protos/` 下的自动生成文件

## 文档

详细中文文档位于 `docs/` 目录，共 20 个章节，涵盖框架的所有功能：
- 框架概述、快速开始
- ORM、路由、后台管理
- AOP、事件、进程管理
- 定时任务、队列、锁机制
- 连接池、中间件、Protobuf 等
