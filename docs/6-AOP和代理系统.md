## 6. AOP 和代理系统

SWLib 提供了强大的 AOP（面向切面编程）支持，通过代理调度系统实现了日志记录、性能监控、缓存、事务管理等横切关注点。

### 6.1 AOP 切面编程

#### 切面类型

框架提供了四种通知类型：

| 通知类型 | 执行时机 | 用途 |
|---------|---------|------|
| `before()` | 目标方法执行前 | 参数验证、权限检查、日志记录 |
| `after()` | 目标方法成功执行后 | 结果缓存、性能统计、发送通知 |
| `around()` | 环绕目标方法执行 | 缓存、短路、方法替代 |
| `afterThrowing()` | 目标方法抛出异常时 | 异常日志、告警、资源清理 |

#### 创建切面

```php
use Swlib\Aop\Abstract\AbstractAspect;
use Swlib\Aop\JoinPoint;

class LoggingAspect extends AbstractAspect
{
    public function before(JoinPoint $joinPoint): void
    {
        $args = $joinPoint->getArguments();
        Log::info("方法调用开始: " . $joinPoint->getMethodName(), [
            'args' => $args
        ]);
    }

    public function after(JoinPoint $joinPoint, mixed $result): void
    {
        Log::info("方法调用结束: " . $joinPoint->getMethodName(), [
            'result' => $result
        ]);
    }

    public function afterThrowing(JoinPoint $joinPoint, Throwable $exception): void
    {
        Log::error("方法调用异常: " . $joinPoint->getMethodName(), [
            'error' => $exception->getMessage()
        ]);
    }
}
```

#### 应用切面

```php
use Swlib\Aop\Aspects\LoggingAspect;
use Swlib\Aop\Aspects\PerformanceAspect;

class UserService
{
    #[LoggingAspect]
    #[PerformanceAspect(threshold: 1000)]
    public function getUserInfo(int $userId): UserTableDto
    {
        return new UserTable()->where([
            UserTable::ID => $userId
        ])->selectOne();
    }
}
```

#### 内置切面

框架提供了以下内置切面：

| 切面类 | 功能 | 参数 |
|--------|------|------|
| `LoggingAspect` | 方法调用日志 | - |
| `PerformanceAspect` | 性能监控 | `threshold`: 超时阈值(毫秒) |
| `CachingAspect` | 结果缓存 | `ttl`: 缓存时间, `keyPrefix`: 键前缀 |
| `ValidationAspect` | 参数验证 | `rules`: 验证规则 |
| `QueryCleanAspect` | 查询清理 | 清理查询构建器状态 |

### 6.2 代理调度系统

#### 执行流程

代理调度系统按以下阶段执行：

```
┌─────────────────────────────────────────────────────────────┐
│ Phase 1: 执行所有 AOP 的 before()                            │
│ [按 priority 降序]                                           │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│ Phase 2: 构建并执行 handle() pipeline                        │
│ [按 priority 降序，形成责任链]                               │
│   Stage1::handle() → Stage2::handle() → ... → 原方法        │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│ Phase 3: 执行所有 AOP 的 after()                             │
│ [按 priority 降序]                                           │
└─────────────────────────────────────────────────────────────┘
                            ↓ (异常时)
┌─────────────────────────────────────────────────────────────┐
│ Exception: 执行所有 AOP 的 afterThrowing()                   │
│ [按 priority 降序]                                           │
└─────────────────────────────────────────────────────────────┘
```

#### 链路信息

```php
use Swlib\Proxy\ProxyContext;

// 获取最近一次调度的链路信息
$result = ProxyContext::pop();

// 获取调度结果
$originalResult = $result->getResult();        // 原方法返回值
$proxyResults = $result->getProxyResults();   // 各注解执行结果
$isShortCircuited = $result->isShortCircuited(); // 是否短路
```

### 6.3 事务管理

`#[Transaction]` 注解是 AOP 在事务管理中的应用实例。

#### 基本用法

```php
use Swlib\Table\Attributes\Transaction;

#[Transaction(
    dbName: 'default',
    isolationLevel: Db::ISOLATION_READ_COMMITTED,
    timeout: 30,
    logTransaction: true
)]
public function createOrder(int $userId, array $items): int
{
    // 方法内的所有数据库操作都会在同一事务中执行
    // 任何异常都会自动回滚
}
```

#### 工作原理

`#[Transaction]` 注解实现了 `ProxyAttributeInterface` 接口：

```php
public function handle(array $ctx, callable $next): mixed
{
    return Db::transaction(
        call: static fn() => $next($ctx),
        dbName: $this->dbName,
        isolationLevel: $this->isolationLevel,
        timeout: $this->timeout,
        enableLog: $this->logTransaction
    );
}
```

**详细的事务管理内容**（隔离级别、嵌套事务、跨库限制、事件监控等）请参阅 [3.6 事务管理](docs/3-数据库操作-ORM.md#36-事务管理)。
