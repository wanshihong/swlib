## 3. 数据库操作 (ORM)

SWLib 提供了强大的 ORM 系统，支持自动代码生成、类型安全、复杂查询等特性。

### 3.1 核心概念

#### Table 类
自动生成的数据表映射类，提供查询构建和执行功能：

```php
use Generate\Tables\Wenyuehui\UserTable;

// 所有字段都有对应的常量
UserTable::ID          // 主键
UserTable::USERNAME    // 用户名
UserTable::EMAIL       // 邮箱
UserTable::CREATED_AT  // 创建时间
```

#### TableDto 类
数据传输对象，表示查询结果，提供类型安全的属性访问：

```php
use Generate\TablesDto\Wenyuehui\UserTableDto;

$user = new UserTable()->selectOne();
echo $user->username;  // 类型安全的属性访问
echo $user->email;
```

### 3.2 基本查询

#### 查询单条记录

```php
// 返回 UserTableDto 或 null
$user = new UserTable()->where([
    UserTable::ID => 1
])->selectOne();

if ($user) {
    echo $user->username;
    echo $user->email;
}
```

#### 查询多条记录

```php
// 返回包含 __rows 数组的 UserTableDto
$users = new UserTable()
    ->where([UserTable::STATUS => 1])
    ->order([UserTable::ID => 'desc'])
    ->page(1, 10)
    ->selectAll();

// 遍历结果
foreach ($users as $user) {
    echo $user->username;
}
```

#### 查询特定字段

```php
// 只查询需要的字段，提升性能
$user = new UserTable()
    ->field([UserTable::ID, UserTable::USERNAME, UserTable::EMAIL])
    ->where([UserTable::ID => 1])
    ->selectOne();
```

#### 查询单个字段值

```php
// 获取单个字段的值
$username = new UserTable()
    ->where([UserTable::ID => 1])
    ->selectField(UserTable::USERNAME);

// 带默认值
$email = new UserTable()
    ->where([UserTable::ID => 999])
    ->selectField(UserTable::EMAIL, 'default@example.com');
```

### 3.3 WHERE 条件

SWLib 的 WHERE 条件支持复杂的嵌套查询，可以构建任意复杂度的查询条件。

#### 基本格式

WHERE 条件支持以下几种格式：

1. **关联数组格式**：`[field => value]` （默认使用 = 操作符）
2. **标准数组格式**：`[field, operator, value]`
3. **嵌套条件格式**：支持无限层嵌套的复杂条件组合

#### 简单条件

```php
// 关联数组格式（默认使用 = 操作符）
$users = new UserTable()->where([
    UserTable::STATUS => 1,
    UserTable::APP_ID => 10
])->selectAll();

// 标准数组格式
$users = new UserTable()->where([
    [UserTable::STATUS, '=', 1],
    [UserTable::AGE, '>', 18],
    [UserTable::NAME, 'like', '%张三%'],
    [UserTable::CITY, 'in', ['北京', '上海', '深圳']],
    [UserTable::DELETED_AT, 'is null', '']
])->selectAll();
```

#### 支持的操作符

| 操作符 | 说明 | 示例 |
|--------|------|------|
| `=` | 等于 | `[UserTable::ID, '=', 1]` |
| `!=`, `<>` | 不等于 | `[UserTable::STATUS, '!=', 0]` |
| `>`, `<`, `>=`, `<=` | 比较运算 | `[UserTable::AGE, '>', 18]` |
| `like` | 模糊查询 | `[UserTable::NAME, 'like', '%张%']` |
| `in` | 在范围内 | `[UserTable::ID, 'in', [1,2,3]]` |
| `not in` | 不在范围内 | `[UserTable::STATUS, 'not in', [0,2]]` |
| `between` | 区间查询 | `[UserTable::AGE, 'between', [18, 60]]` |
| `is null` | 为空 | `[UserTable::DELETED_AT, 'is null', '']` |
| `is not null` | 不为空 | `[UserTable::EMAIL, 'is not null', '']` |
| `json_contains` | JSON包含 | `[UserTable::TAGS, 'json_contains', 'tag1']` |

#### 嵌套条件（AND/OR 逻辑）

```php
// 简单 OR 条件：(name = 'John' OR name = 'Jane') AND status = 1
$users = new UserTable()->where([
    [UserTable::NAME, '=', 'John'],
    'OR',
    [UserTable::NAME, '=', 'Jane'],
    'AND',
    [UserTable::STATUS, '=', 1]
])->selectAll();

// 复杂嵌套：((status = 1 AND age > 18) OR (vip_level > 3)) AND deleted_at IS NULL
$users = new UserTable()->where([
    [
        [UserTable::STATUS, '=', 1],
        'AND',
        [UserTable::AGE, '>', 18]
    ],
    'OR',
    [
        [UserTable::VIP_LEVEL, '>', 3]
    ],
    'AND',
    [UserTable::DELETED_AT, 'is null', '']
])->selectAll();

// 更复杂的嵌套：(((name LIKE '%admin%' OR email LIKE '%admin%') AND status = 1) OR role = 'admin') AND deleted_at IS NULL
$users = new UserTable()->where([
    [
        [
            [UserTable::NAME, 'like', '%admin%'],
            'OR',
            [UserTable::EMAIL, 'like', '%admin%']
        ],
        'AND',
        [UserTable::STATUS, '=', 1]
    ],
    'OR',
    [UserTable::ROLE, '=', 'admin'],
    'AND',
    [UserTable::DELETED_AT, 'is null', '']
])->selectAll();
```

#### 特殊操作符详解

##### BETWEEN 查询

```php
// 年龄在 18-60 之间
$users = new UserTable()->where([
    [UserTable::AGE, 'between', [18, 60]]
])->selectAll();

// 创建时间在指定范围内
$users = new UserTable()->where([
    [UserTable::CREATED_AT, 'between', [strtotime('2024-01-01'), strtotime('2024-12-31')]]
])->selectAll();
```

##### IN 和 NOT IN 查询

```php
// ID 在指定列表中
$users = new UserTable()->where([
    [UserTable::ID, 'in', [1, 2, 3, 4, 5]]
])->selectAll();

// 状态不在指定列表中
$users = new UserTable()->where([
    [UserTable::STATUS, 'not in', [0, -1, 99]]
])->selectAll();

// 城市在指定列表中
$users = new UserTable()->where([
    [UserTable::CITY, 'in', ['北京', '上海', '深圳', '广州']]
])->selectAll();
```

##### JSON_CONTAINS 查询

JSON_CONTAINS 是 MySQL 的强大功能，支持在 JSON 字段中搜索特定值：

```php
// 基础用法：搜索 tags 字段中包含 'important' 的记录
$posts = new LivePostsTable()->where([
    [LivePostsTable::TAGS, 'json_contains', 'important']
])->selectAll();
// 生成 SQL: JSON_CONTAINS(`tags`, '"important"', '$')

// 指定 JSON 路径：搜索 user_info.role 为 'admin' 的记录
$posts = new LivePostsTable()->where([
    [LivePostsTable::USER_INFO, 'json_contains', 'admin', '$.role']
])->selectAll();
// 生成 SQL: JSON_CONTAINS(`user_info`, '"admin"', '$.role')

// 多个值搜索（OR 条件）：搜索包含 'tag1' 或 'tag2' 的记录
$posts = new LivePostsTable()->where([
    [LivePostsTable::TAGS, 'json_contains', ['tag1', 'tag2']]
])->selectAll();
// 生成 SQL: (JSON_CONTAINS(`tags`, '"tag1"', '$') OR JSON_CONTAINS(`tags`, '"tag2"', '$'))

// 复杂对象搜索：搜索 config.features.enabled 为 true 的记录
$posts = new LivePostsTable()->where([
    [LivePostsTable::CONFIG, 'json_contains', ['enabled' => true], '$.features']
])->selectAll();
// 生成 SQL: JSON_CONTAINS(`config`, '{"enabled":true}', '$.features')

// 数组索引路径：搜索 items 数组第一个元素的 type 为 'product'
$orders = new OrderTable()->where([
    [OrderTable::ITEMS, 'json_contains', 'product', '$.items[0].type']
])->selectAll();

// 数组中所有元素：搜索 permissions 数组中任意元素包含 'read'
$users = new UserTable()->where([
    [UserTable::PERMISSIONS, 'json_contains', 'read', '$.permissions[*]']
])->selectAll();
```

**JSON 路径语法说明**：
- `$`：根路径（默认）
- `$.key`：指定键的路径
- `$.array[0]`：数组索引路径
- `$.key.subkey`：嵌套对象路径
- `$.array[*]`：数组中的所有元素

#### 动态添加条件

使用 `addWhere()` 方法可以动态添加单个条件：

```php
$query = new UserTable();

// 根据条件动态添加 WHERE 子句
if ($status !== null) {
    $query->addWhere(UserTable::STATUS, $status);
}

if ($minAge > 0) {
    $query->addWhere(UserTable::AGE, $minAge, '>=');
}

if (!empty($keyword)) {
    $query->addWhere(UserTable::NAME, "%{$keyword}%", 'like');
}

if (!empty($cityList)) {
    $query->addWhere(UserTable::CITY, $cityList, 'in');
}

$users = $query->selectAll();
```

#### 多次调用 WHERE

多次调用 `where()` 方法时，条件会使用 AND 连接：

```php
$query = new UserTable();

// 第一次调用
$query->where([
    UserTable::STATUS => 1
]);

// 第二次调用，会与第一次的条件用 AND 连接
$query->where([
    [UserTable::AGE, '>', 18]
]);

// 等价于：WHERE (status = 1) AND (age > 18)
$users = $query->selectAll();
```

#### 空值和零值处理

框架会智能处理空值和零值：

```php
// 空字符串和 null 会被忽略（除了 0）
$users = new UserTable()->where([
    [UserTable::NAME, '=', ''],      // 被忽略
    [UserTable::AGE, '=', null],     // 被忽略
    [UserTable::STATUS, '=', 0],     // 不会被忽略
    [UserTable::SCORE, '>', 0]       // 不会被忽略
])->selectAll();

// 显式检查空值
$users = new UserTable()->where([
    [UserTable::EMAIL, 'is not null', ''],
    [UserTable::EMAIL, '!=', '']
])->selectAll();
```

#### 实际应用示例

##### 用户搜索功能

```php
class UserService
{
    public static function searchUsers(array $filters): UserTableDto
    {
        $query = new UserTable();

        // 基础条件：只查询未删除的用户
        $conditions = [
            [UserTable::DELETED_AT, 'is null', '']
        ];

        // 状态筛选
        if (isset($filters['status'])) {
            $conditions[] = [UserTable::STATUS, '=', $filters['status']];
        }

        // 关键词搜索（用户名或邮箱）
        if (!empty($filters['keyword'])) {
            $keyword = $filters['keyword'];
            $conditions[] = [
                [UserTable::USERNAME, 'like', "%{$keyword}%"],
                'OR',
                [UserTable::EMAIL, 'like', "%{$keyword}%"]
            ];
        }

        // 年龄范围
        if (isset($filters['min_age']) || isset($filters['max_age'])) {
            if (isset($filters['min_age']) && isset($filters['max_age'])) {
                $conditions[] = [UserTable::AGE, 'between', [$filters['min_age'], $filters['max_age']]];
            } elseif (isset($filters['min_age'])) {
                $conditions[] = [UserTable::AGE, '>=', $filters['min_age']];
            } else {
                $conditions[] = [UserTable::AGE, '<=', $filters['max_age']];
            }
        }

        // 城市筛选
        if (!empty($filters['cities'])) {
            $conditions[] = [UserTable::CITY, 'in', $filters['cities']];
        }

        // VIP 用户筛选
        if (isset($filters['is_vip']) && $filters['is_vip']) {
            $conditions[] = [UserTable::VIP_LEVEL, '>', 0];
        }

        // 注册时间范围
        if (isset($filters['start_date']) || isset($filters['end_date'])) {
            if (isset($filters['start_date']) && isset($filters['end_date'])) {
                $conditions[] = [UserTable::CREATED_AT, 'between', [
                    strtotime($filters['start_date']),
                    strtotime($filters['end_date'] . ' 23:59:59')
                ]];
            } elseif (isset($filters['start_date'])) {
                $conditions[] = [UserTable::CREATED_AT, '>=', strtotime($filters['start_date'])];
            } else {
                $conditions[] = [UserTable::CREATED_AT, '<=', strtotime($filters['end_date'] . ' 23:59:59')];
            }
        }

        return $query->where($conditions)
            ->order([UserTable::CREATED_AT => 'desc'])
            ->page($filters['page'] ?? 1, $filters['size'] ?? 20)
            ->selectAll();
    }
}
```

##### 商品筛选功能

```php
class ProductService
{
    public static function filterProducts(array $filters): ProductTableDto
    {
        $conditions = [
            [ProductTable::STATUS, '=', 1],  // 只查询上架商品
            [ProductTable::STOCK, '>', 0]    // 只查询有库存商品
        ];

        // 分类筛选（支持多级分类）
        if (!empty($filters['category_ids'])) {
            $conditions[] = [ProductTable::CATEGORY_ID, 'in', $filters['category_ids']];
        }

        // 价格范围
        if (isset($filters['min_price']) || isset($filters['max_price'])) {
            if (isset($filters['min_price']) && isset($filters['max_price'])) {
                $conditions[] = [ProductTable::PRICE, 'between', [$filters['min_price'], $filters['max_price']]];
            } elseif (isset($filters['min_price'])) {
                $conditions[] = [ProductTable::PRICE, '>=', $filters['min_price']];
            } else {
                $conditions[] = [ProductTable::PRICE, '<=', $filters['max_price']];
            }
        }

        // 品牌筛选
        if (!empty($filters['brand_ids'])) {
            $conditions[] = [ProductTable::BRAND_ID, 'in', $filters['brand_ids']];
        }

        // 标签筛选（JSON 字段）
        if (!empty($filters['tags'])) {
            $tagConditions = [];
            foreach ($filters['tags'] as $tag) {
                $tagConditions[] = [ProductTable::TAGS, 'json_contains', $tag];
                if (count($tagConditions) > 1) {
                    $tagConditions[] = 'OR';
                }
            }
            $conditions[] = $tagConditions;
        }

        // 属性筛选（JSON 字段）
        if (!empty($filters['attributes'])) {
            foreach ($filters['attributes'] as $key => $value) {
                $conditions[] = [ProductTable::ATTRIBUTES, 'json_contains', $value, "$.{$key}"];
            }
        }

        // 关键词搜索
        if (!empty($filters['keyword'])) {
            $keyword = $filters['keyword'];
            $conditions[] = [
                [ProductTable::NAME, 'like', "%{$keyword}%"],
                'OR',
                [ProductTable::DESCRIPTION, 'like', "%{$keyword}%"],
                'OR',
                [ProductTable::SKU, 'like', "%{$keyword}%"]
            ];
        }

        // 排序
        $orderBy = match($filters['sort'] ?? 'default') {
            'price_asc' => [ProductTable::PRICE => 'asc'],
            'price_desc' => [ProductTable::PRICE => 'desc'],
            'sales_desc' => [ProductTable::SALES_COUNT => 'desc'],
            'newest' => [ProductTable::CREATED_AT => 'desc'],
            default => [ProductTable::SORT_ORDER => 'asc', ProductTable::ID => 'desc']
        };

        return new ProductTable()
            ->where($conditions)
            ->order($orderBy)
            ->page($filters['page'] ?? 1, $filters['size'] ?? 20)
            ->selectAll();
    }
}
```

### 3.4 聚合查询

#### 统计数量

```php
// 统计总数
$count = new UserTable()->where([
    UserTable::STATUS => 1
])->count();

// 统计指定字段
$count = new UserTable()->count(UserTable::ID);

// 去重统计
$count = new UserTable()->count(UserTable::CITY, true);
```

#### 求和、最值、平均值

```php
// 求和
$totalScore = new UserScoreTable()->where([
    UserScoreTable::USER_ID => 1
])->sum(UserScoreTable::SCORE);

// 最大值/最小值
$maxAge = new UserTable()->max(UserTable::AGE);
$minAge = new UserTable()->min(UserTable::AGE);

// 平均值
$avgScore = new UserScoreTable()->avg(UserScoreTable::SCORE);
```

### 3.5 数据操作

#### 插入数据

```php
// 方式 1: 使用 Table 直接插入
$id = new UserTable()->insert([
    UserTable::USERNAME => 'test',
    UserTable::EMAIL => 'test@example.com',
    UserTable::STATUS => 1
]);

// 方式 2: 使用 DTO 的 __save() 方法
$dto = new UserTableDto();
$dto->username = 'test';
$dto->email = 'test@example.com';
$dto->status = 1;
$id = $dto->__save();

// 批量插入
$data = [
    [UserTable::USERNAME => 'user1', UserTable::EMAIL => 'user1@example.com'],
    [UserTable::USERNAME => 'user2', UserTable::EMAIL => 'user2@example.com']
];
$affectedRows = new UserTable()->insertAll($data);
```

#### 更新数据

```php
// 基本更新（必须有 WHERE 条件）
$affectedRows = new UserTable()->where([
    UserTable::ID => 1
])->update([
    UserTable::STATUS => 2,
    UserTable::UPDATED_AT => time()
]);

// 增量更新
use Swlib\Table\Db;

new UserTable()->where([
    UserTable::ID => 1
])->update([
    UserTable::LOGIN_COUNT => Db::incr(1),      // +1
    UserTable::BALANCE => Db::incr(10, '-')     // -10
]);

// 使用 DTO 更新
$user = new UserTable()->where([UserTable::ID => 1])->selectOne();
if ($user) {
    $user->status = 2;
    $user->updatedAt = time();
    $user->__save();  // 自动判断是插入还是更新
}
```

#### 删除数据

```php
// 必须使用 WHERE 条件
$affectedRows = new UserTable()->where([
    UserTable::ID => 1
])->delete();

// 批量删除
$affectedRows = new UserTable()->where([
    [UserTable::STATUS, '=', 0],
    [UserTable::CREATED_AT, '<', strtotime('-1 year')]
])->delete();
```

### 3.6 事务管理

框架提供了完整的事务管理功能，支持声明式和编程式两种方式。

#### 3.6.1 声明式事务

使用 `#[Transaction]` 注解声明事务：

```php
use Swlib\Table\Attributes\Transaction;
use Swlib\Table\Db;

class OrderService
{
    #[Transaction(
        dbName: 'default',              // 数据库名称
        isolationLevel: Db::ISOLATION_READ_COMMITTED,  // 隔离级别
        timeout: 30,                    // 锁等待超时（秒）
        logTransaction: true            // 记录事务日志
    )]
    public function createOrder(int $userId, array $items): int
    {
        // 减少库存
        foreach ($items as $item) {
            new ProductTable()->where([
                ProductTable::ID => $item['product_id']
            ])->update([
                ProductTable::STOCK => Db::incr($item['quantity'], '-')
            ]);
        }

        // 创建订单
        $orderId = new OrderTable()->insert([
            OrderTable::USER_ID => $userId,
            OrderTable::TOTAL_AMOUNT => $this->calculateTotal($items)
        ]);

        // 创建订单明细
        foreach ($items as $item) {
            new OrderItemTable()->insert([
                OrderItemTable::ORDER_ID => $orderId,
                OrderItemTable::PRODUCT_ID => $item['product_id'],
                OrderItemTable::QUANTITY => $item['quantity']
            ]);
        }

        return $orderId;
    }
}
```

#### 3.6.2 编程式事务

使用 `Db::transaction()` 方法手动控制事务：

```php
use Swlib\Table\Db;

$result = Db::transaction(
    call: function () use ($userId, $amount) {
        // 检查余额（加锁）
        $user = new UserTable()->where([
            UserTable::ID => $userId
        ])->lock()->selectOne();

        if ($user->balance < $amount) {
            throw new AppException(AppErr::BALANCE_NOT_ENOUGH);
        }

        // 扣减余额
        new UserTable()->where([
            UserTable::ID => $userId
        ])->update([
            UserTable::BALANCE => Db::incr($amount, '-')
        ]);

        // 创建交易记录
        return new TransactionTable()->insert([
            TransactionTable::USER_ID => $userId,
            TransactionTable::AMOUNT => $amount
        ]);
    },
    dbName: 'default',
    isolationLevel: Db::ISOLATION_REPEATABLE_READ,
    timeout: 30,
    enableLog: true
);
```

#### 3.6.3 事务隔离级别

框架支持 MySQL 四种标准隔离级别：

```php
use Swlib\Table\Db;

class Db
{
    const int ISOLATION_READ_UNCOMMITTED = 1;  // 读未提交
    const int ISOLATION_READ_COMMITTED = 2;    // 读已提交
    const int ISOLATION_REPEATABLE_READ = 3;   // 可重复读（默认）
    const int ISOLATION_SERIALIZABLE = 4;      // 串行化
}
```

| 隔离级别 | 脏读 | 不可重复读 | 幻读 | 说明 |
|---------|------|-----------|------|------|
| READ UNCOMMITTED | ✓ | ✓ | ✓ | 最低隔离，性能最好 |
| READ COMMITTED | ✗ | ✓ | ✓ | Oracle 默认级别 |
| REPEATABLE READ | ✗ | ✗ | ✓ | MySQL 默认级别 |
| SERIALIZABLE | ✗ | ✗ | ✗ | 最高隔离，性能最差 |

#### 3.6.4 事务嵌套和跨库限制

**嵌套事务**：如果已在事务中调用 `Db::transaction()`，会复用当前事务连接：

```php
// 外层事务
Db::transaction(function () {
    // 内层会复用外层事务
    Db::transaction(function () {
        // 不会开启新事务，直接使用当前连接
    });
});
```

**跨库限制**：事务不支持跨数据库操作：

```php
// ❌ 错误：跨库事务会抛出异常
Db::transaction(function () {
    // 操作 db1 数据库
    new UserTable()->insert([...]);  // db1

    // 尝试操作 db2 数据库 - 会抛出异常！
    new ProductTable()->insert([...]);  // db2
});
```

错误信息：`db.transaction_cross_db: 事务数据库为 db1，本次请求的数据库为 db2`

#### 3.6.5 事务锁等待超时

设置 `innodb_lock_wait_timeout` 避免长时间等待锁：

```php
use Swlib\Table\Db;

Db::transaction(
    call: fn() => $this->criticalOperation(),
    timeout: 5,  // 最多等待 5 秒获取锁
    dbName: 'default'
);
```

#### 3.6.6 事务事件监控

框架在事务生命周期的各个阶段触发事件，可用于监控和日志：

```php
use Swlib\Event\EventEnum;

// 监听事务事件
EventEnum::DatabaseTransactionEvent->addListener(function ($data) {
    $event = $data['event'];

    // $event->stage: begin/commit/rollback/begin_error/rollback_error 等
    // $event->database: 数据库名称
    // $event->duration: 耗时（毫秒）
    // $event->isolationLevel: 隔离级别
    // $event->timeout: 锁等待超时设置
    // $event->error: 错误信息（如果有）

    Log::info("事务 {$event->stage}", [
        'database' => $event->database,
        'duration' => $event->duration,
        'error' => $event->error
    ]);
});
```

| 事件阶段 | 说明 |
|---------|------|
| `begin` | 事务开始 |
| `commit` | 事务提交成功 |
| `rollback` | 事务回滚成功 |
| `begin_error` | 开启事务失败 |
| `rollback_error` | 回滚操作失败 |
| `restore_timeout_error` | 恢复超时设置失败 |

#### 3.6.7 行锁

在事务中使用悲观锁：

```php
Db::transaction(function () use ($userId) {
    // 加行锁查询
    $user = new UserTable()->where([
        UserTable::ID => $userId
    ])->lock()->selectOne();

    // 其他事务会等待当前事务提交后才能获取这行数据
    $user->balance -= 100;
    $user->__save();
});
```

### 3.7 高级功能

#### 查询缓存

```php
// 缓存 600 秒
$users = new UserTable()
    ->where([UserTable::STATUS => 1])
    ->cache(600)
    ->selectAll();

// 自定义缓存键
$users = new UserTable()
    ->where([UserTable::STATUS => 1])
    ->cache(600, 'active_users_list')
    ->selectAll();
```

#### 原生 SQL 查询

```php
// 执行原生 SQL 并转换为对象
$sql = "SELECT * FROM users WHERE age > ? AND city = ?";
$user = new UserTable()->queryToObject($sql, [18, '北京']);

// 查询多条
$users = new UserTable()->queryToObjects($sql, [18, '北京']);
```
