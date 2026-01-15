# SWLib 框架完整使用指南

## 目录

- [1. 框架概述](#1-框架概述)
- [2. 快速开始](#2-快速开始)
- [3. 数据库操作 (ORM)](#3-数据库操作-orm)
- [4. 路由系统](#4-路由系统)
- [5. 后台管理系统](#5-后台管理系统)
- [6. AOP 和事务](#6-aop-和事务)
- [7. 事件系统](#7-事件系统)
- [8. 进程管理](#8-进程管理)
- [9. 队列系统](#9-队列系统)
- [10. 连接池管理](#10-连接池管理)
- [11. 中间件系统](#11-中间件系统)
- [12. Protobuf 集成](#12-protobuf-集成)
- [13. 工具类](#13-工具类)
- [14. 最佳实践](#14-最佳实践)

## 1. 框架概述

SWLib 是一个基于 PHP 8.4+ 和 Swoole 的现代化高性能 Web 开发框架，专为构建企业级应用而设计。

### 1.1 核心特性

- **现代 PHP**: 基于 PHP 8.4+ 注解特性，充分利用现代 PHP 语言特性
- **高性能**: 集成 Swoole 协程服务器，支持高并发处理
- **完整的 ORM**: 自动生成 Table 和 DTO 类，支持复杂查询、事务、缓存
- **AOP 支持**: 内置切面编程，支持日志、缓存、性能监控等横切关注点
- **后台管理**: 开箱即用的后台管理系统，支持 RBAC 权限控制
- **事件驱动**: 强大的事件系统，支持异步、队列、延迟执行
- **进程管理**: 自定义进程支持，适合后台任务处理
- **连接池**: MySQL 和 Redis 连接池，提升数据库访问性能
- **Protobuf**: 自动生成 Protobuf 协议文件，支持高效的数据传输
- **中间件**: 灵活的中间件系统，支持认证、权限、日志等
### 1.2 架构特点

- **代码生成**: 基于数据库结构自动生成 Table、DTO、Model、Admin 等代码
- **注解驱动**: 使用 PHP 8.4+ 注解定义路由、事件、进程、AOP 等
- **协程友好**: 全面支持 Swoole 协程，提供协程上下文管理
- **类型安全**: 强类型设计，充分利用 PHP 类型系统

## 2. 快速开始

### 2.1 环境要求

- **PHP**: >= 8.4
- **Swoole**: >= 6.0.0
- **扩展**: mysqli, redis, bcmath, curl, openssl, mbstring, gd, gmagick
- **数据库**: MySQL 5.7+ / MariaDB 10.3+
- **缓存**: Redis 5.0+

### 2.2 安装

#### 使用 Composer 安装

```bash
composer require wansh/swlib
```

#### 配置自动加载

```json
{
  "autoload": {
    "psr-4": {
      "App\\": [
        "runtime/Proxy/App/",
        "App/"
      ],
      "Generate\\": "runtime/Generate",
      "GPBMetadata\\": "runtime/Protobuf/GPBMetadata/",
      "Protobuf\\": "runtime/Protobuf/Protobuf/",
      "Swlib\\": [
        "runtime/Proxy/Swlib/",
        "Swlib/"
      ]
    }
  }
}
```

### 2.3 环境配置

创建 `.env` 文件：

```env
# 应用配置
APP_PROD=false
APP_NAME=all
WORKER_NUM=2
TASK_WORKER_NUM=2
PORT=9501

# 数据库配置
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database
DB_ROOT=root
DB_PWD=your_password
DB_CHARSET=utf8mb4
DB_SLOW_TIME=300
DB_SAVE_SQL=true
DB_POOL_NUM=10
DB_HEART=10

# Redis 配置
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_AUTH=
REDIS_POOL_NUM=10

# 后台管理配置
ADMIN_CONFIG_PATH=App\AdminConfig
```

### 2.4 创建启动文件

```php
<?php
// bin/start.php
declare(strict_types=1);
require_once "./vendor/autoload.php";

use Generate\ConfigEnum;
use Swlib\App;

define('ROOT_DIR', dirname(__DIR__) . DIRECTORY_SEPARATOR);
const APP_DIR = ROOT_DIR . 'App' . DIRECTORY_SEPARATOR;
const PUBLIC_DIR = ROOT_DIR . 'public' . DIRECTORY_SEPARATOR;
const RUNTIME_DIR = ROOT_DIR . 'runtime' . DIRECTORY_SEPARATOR;

$app = new App();

try {
    $config = [
        'hook_flags' => SWOOLE_HOOK_ALL,
        'daemonize' => ConfigEnum::APP_PROD,
        'worker_num' => ConfigEnum::WORKER_NUM,
        'task_worker_num' => ConfigEnum::TASK_WORKER_NUM,
        'task_enable_coroutine' => true,
        'enable_coroutine' => true,
        'reload_async' => true,
        'log_file' => RUNTIME_DIR . '/log/server_error.log'
    ];

    if (!ConfigEnum::APP_PROD) {
        $config['document_root'] = PUBLIC_DIR;
        $config['enable_static_handler'] = true;
    }

    $app->startSwooleServer($config);
} catch (Throwable $e) {
    echo "启动失败: " . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
}
```

### 2.5 启动应用

```bash
# 启动服务器
php bin/start.php

# 后台运行
nohup php bin/start.php > /dev/null 2>&1 &
```

访问 `http://127.0.0.1:9501` 即可看到欢迎页面。

### 2.6 目录结构

```
project/
├── App/                    # 应用代码
│   ├── Controller/         # 控制器
│   ├── Service/           # 服务层
│   ├── Model/             # 模型层
│   └── AdminConfig.php    # 后台配置
├── Swlib/                 # 框架核心
├── bin/                   # 启动脚本
│   └── start.php
├── public/                # 静态资源
├── runtime/               # 运行时文件
│   ├── Generate/          # 自动生成的代码
│   ├── Protobuf/          # Protobuf 文件
│   ├── Proxy/             # AOP 代理类
│   └── log/               # 日志文件
├── .env                   # 环境配置
└── composer.json
```



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

### 3.6 高级功能

#### 事务处理

```php
use Swlib\Table\Db;

$result = Db::transaction(function () use ($userId, $amount) {
    // 减少用户余额
    new UserTable()->where([
        UserTable::ID => $userId
    ])->update([
        UserTable::BALANCE => Db::incr($amount, '-')
    ]);

    // 创建订单
    $orderId = new OrderTable()->insert([
        OrderTable::USER_ID => $userId,
        OrderTable::AMOUNT => $amount
    ]);

    return $orderId;
});
```

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

## 4. 路由系统

SWLib 使用基于注解的路由系统，支持 RESTful API、中间件、参数验证等特性。

### 4.1 基本路由

#### 控制器定义

```php
use Swlib\Controller\Abstract\AbstractController;use Swlib\Router\Router;

#[Router(method: 'POST')]
class UserController extends AbstractController
{
    #[Router(errorTitle: '获取用户信息失败')]
    public function info(UserProto $request): UserProto
    {
        $id = $request->getId();
        if (empty($id)) {
            throw new AppException('用户ID不能为空');
        }

        $user = new UserTable()->where([
            UserTable::ID => $id
        ])->selectOne();

        return UserModel::formatItem($user);
    }

    #[Router(method: 'GET', url: '/user/list')]
    public function list(): UserListsProto
    {
        $users = new UserTable()
            ->where([UserTable::STATUS => 1])
            ->selectAll();

        return UserModel::formatList($users);
    }
}
```

#### 路由参数

```php
#[Router(
    method: ['GET', 'POST'],    // 支持的 HTTP 方法
    url: '/api/user/info',      // 自定义 URL（默认根据类名和方法名生成）
    cache: 300,                 // 客户端缓存时间（秒）
    errorTitle: '操作失败',      // 错误提示标题
    middleware: [AuthMiddleware::class]  // 中间件
)]
```

### 4.2 请求处理

#### 获取请求参数

```php
class UserController extends AbstractController
{
    public function update(): Success
    {
        // GET 参数
        $id = $this->get('id', '参数错误');
        $page = $this->get('page', '', 1);  // 带默认值

        // POST 参数
        $name = $this->post('name', '姓名不能为空');
        $email = $this->post('email', '', 'default@example.com');

        // Header 参数
        $token = $this->getHeader('authorization');

        return new Success();
    }
}
```

#### Protobuf 请求

```php
#[Router(method: 'POST')]
class UserController extends AbstractController
{
    public function create(UserProto $request): UserProto
    {
        // 自动解析 Protobuf 请求体
        $username = $request->getUsername();
        $email = $request->getEmail();

        // 业务逻辑处理
        $id = new UserTable()->insert([
            UserTable::USERNAME => $username,
            UserTable::EMAIL => $email
        ]);

        // 返回 Protobuf 响应
        $response = new UserProto();
        $response->setId($id);
        $response->setUsername($username);
        $response->setEmail($email);

        return $response;
    }
}
```

### 4.3 响应类型

```php
use Swlib\Response\JsonResponse;
use Swlib\Response\TwigResponse;
use Swlib\Response\RedirectResponse;

class IndexController extends AbstractController
{
    // JSON 响应
    public function api(): JsonResponse
    {
        return JsonResponse::success(['message' => 'Hello World']);
    }

    // 模板响应
    #[Router(method: 'GET', url: '/')]
    public function index(): TwigResponse
    {
        return TwigResponse::render('index.twig', [
            'title' => '欢迎页面'
        ]);
    }

    // 重定向响应
    public function redirect(): RedirectResponse
    {
        return RedirectResponse::url('/dashboard');
    }
}
```

## 5. 后台管理系统

SWLib 提供了完整的后台管理系统，支持快速创建 CRUD 界面。后台系统基于模块化设计，包含必要的配置类、基础控制器和丰富的字段类型。

### 5.1 后台系统架构

#### 核心组件

1. **AdminConfig**: 后台配置类，定义菜单、路由和基本设置
2. **Dashboard**: 后台首页控制器
3. **Login**: 登录认证控制器
4. **AbstractAdmin**: 后台管理控制器基类

#### 目录结构

```
App/AdminXsom756/
├── AdminConfig.php          # 后台配置类
├── Dashboard.php           # 后台首页控制器
├── Login.php              # 登录控制器
├── User/                  # 用户管理模块
│   ├── UserAdmin.php
│   ├── UserScoreAdmin.php
│   └── ...
└── System/               # 系统管理模块
    ├── ConfigAdmin.php
    ├── AdminManagerAdmin.php
    └── ...
```

### 5.2 创建后台配置 (AdminConfig)

**必须创建** `AdminConfig` 类来配置后台系统的基本设置：

```php
<?php

namespace App\AdminXsom756;

use App\Apps\Live\LiveConfig;
use Generate\RouterPath;
use Generate\Tables\Wenyuehui\ConfigTable;
use Swlib\Admin\Config\AdminConfigAbstract;
use Swlib\Admin\Manager\AdminManager;
use Swlib\Admin\Menu\Menu;
use Swlib\Admin\Menu\MenuGroup;
use Swlib\Admin\Utils\Func;

class AdminConfig extends AdminConfigAbstract
{
    const int AppId = LiveConfig::AppId;

    public function Init(AdminManager $layout): void
    {
        // 首页地址
        $layout->adminIndexUrl = RouterPath::AdminXsom756DashboardIndex;

        // 登录相关路由
        $layout->logoutUrl = RouterPath::AdminXsom756LoginLogout;
        $layout->loginUrl = RouterPath::AdminXsom756LoginLogin;
        $layout->changePasswordUrl = RouterPath::AdminXsom756LoginChangePassword;
        $layout->noAccessUrl = RouterPath::AdminXsom756DashboardNoAccess;

        // 文件上传路由
        $layout->uploadUrl = Url::generateUrl(RouterPath::AppsCommonFileUpload, [
            'appid' => AdminConfig::AppId
        ]);
    }

    public function configAdminTitle(): string
    {
        return '管理后台';
    }

    public function configMenus(): array
    {
        return [
            new MenuGroup(label: '用户管理', icon: 'bi bi-chevron-double-right')->setMenus(
                new Menu(label: '用户账号', url: RouterPath::AdminXsom756UserUserAdminLists),
                new Menu(label: '登录日志', url: RouterPath::AdminXsom756UserUserLoginHisAdminLists),
                new Menu(label: '账户日志', url: RouterPath::AdminXsom756UserUserScoreLogAdminLists),
                new Menu(label: '提现管理', url: RouterPath::AdminXsom756UserUserWithdrawAdminLists),
            ),

            new MenuGroup(label: '系统管理', icon: 'bi bi-chevron-double-right')->setMenus(
                new Menu(label: '管理员', url: RouterPath::AdminXsom756SystemAdminManagerAdminLists),
                new Menu(label: '系统配置', url: RouterPath::AdminXsom756SystemConfigAdminLists,
                    params: [ConfigTable::APP_ID => AdminConfig::AppId]),
                new Menu(label: '翻译配置', url: RouterPath::AdminXsom756SystemLanguageAdminLists),
                new Menu(label: '页面配置', url: RouterPath::AdminXsom756SystemRouterAdminLists),
            ),
        ];
    }
}
```

**AdminConfig 配置说明**：

- **AppId**: 应用ID常量，用于数据隔离
- **Init()**: 配置后台系统的基本路由和设置
- **configAdminTitle()**: 设置后台标题
- **configMenus()**: 配置左侧菜单结构

### 5.3 创建基础控制器

#### Dashboard 控制器

```php
<?php

namespace App\AdminXsom756;

class Dashboard extends \Swlib\Admin\Controller\Dashboard
{
    // 继承基础 Dashboard 类即可
    // 可以重写 index() 方法自定义首页内容
}
```

#### Login 控制器

```php
<?php

namespace App\AdminXsom756;

class Login extends \Swlib\Admin\Controller\LoginAdmin
{
    // 继承基础 Login 类即可
    // 包含登录、注册、修改密码、退出登录等功能
}
```

**基础控制器功能**：

- **Dashboard**: 提供后台首页和无权限页面
- **Login**: 提供完整的认证功能
  - `login()`: 登录页面和登录处理
  - `register()`: 注册功能
  - `changePassword()`: 修改密码
  - `logout()`: 退出登录

### 5.4 创建后台管理控制器

```php
<?php

namespace App\AdminXsom756\Controller\User;

use App\AdminXsom756\Controller\AdminConfig;use Generate\Models\Wenyuehui\UserModel;use Generate\Tables\Wenyuehui\UserTable;use Generate\TablesDto\Wenyuehui\UserTableDto;use Swlib\Admin\Config\PageConfig;use Swlib\Admin\Config\PageFieldsConfig;use Swlib\Admin\Controller\Abstract\AbstractAdmin;use Swlib\Admin\Fields\HiddenField;use Swlib\Admin\Fields\Int2TimeField;use Swlib\Admin\Fields\NumberField;use Swlib\Admin\Fields\SelectField;use Swlib\Admin\Fields\TextField;use Swlib\Admin\Manager\OptionManager;use Swlib\Table\Interface\TableInterface;

class UserAdmin extends AbstractAdmin
{
    protected function configPage(PageConfig $config): void
    {
        $config->pageName = "登录用户";
        $config->tableName = UserTable::class;
        $config->order = [
            UserTable::ID => 'desc'
        ];
    }

    public function listsQuery(TableInterface $query): void
    {
        // 数据权限控制：只显示当前应用的用户
        $query->addWhere(UserTable::APP_ID, AdminConfig::AppId);
    }

    protected function configField(PageFieldsConfig $fields): void
    {
        $fields->setFields(
            new NumberField(field: UserTable::ID, label: 'ID')->hideOnForm(),
            new HiddenField(field: UserTable::APP_ID, label: 'AppId')
                ->setDefault(AdminConfig::AppId),
            new TextField(field: UserTable::PHONE, label: '手机号码')
                ->setRequired(false),
            new TextField(field: UserTable::USERNAME, label: '用户名')
                ->setRequired(false),
            new TextField(field: UserTable::PASSWORD, label: '密码')
                ->setRequired(false),
            new TextField(field: UserTable::EMAIL, label: '邮箱')
                ->setRequired(false),
            new SelectField(field: UserTable::STATUS, label: '状态')->setOptions(
                new OptionManager(UserModel::StatusActive,
                    UserModel::StatusTextMaps[UserModel::StatusActive]),
                new OptionManager(UserModel::StatusInactive,
                    UserModel::StatusTextMaps[UserModel::StatusInactive]),
                new OptionManager(UserModel::StatusDisabled,
                    UserModel::StatusTextMaps[UserModel::StatusDisabled]),
            ),
            new NumberField(field: UserTable::SHARE_RATIO, label: '分销比例')
                ->setRequired(false)->hideOnFilter(),
            new Int2TimeField(field: UserTable::LAST_LOGIN_TIME, label: '上次登录时间')
                ->hideOnForm()->hideOnFilter(),
            new Int2TimeField(field: UserTable::REG_AT, label: '注册时间')
                ->hideOnForm()->hideOnFilter(),
        );
    }

    public function insertUpdateBefore(UserTableDto $dto): void
    {
        // 密码加密处理
        if ($dto->password && !str_starts_with($dto->password, '$2y$')) {
            $dto->password = password_hash($dto->password, PASSWORD_DEFAULT);
        }
    }
}
```

#### 核心方法说明

- **configPage()**: 配置页面基本信息
  - `pageName`: 页面标题
  - `tableName`: 关联的数据表类
  - `order`: 默认排序规则

- **listsQuery()**: 自定义查询条件
  - 用于数据权限控制
  - 添加额外的查询条件

- **configField()**: 配置字段
  - 定义表单字段和列表显示字段
  - 设置字段属性和验证规则

- **insertUpdateBefore()**: 数据保存前的处理
  - 数据验证和转换
  - 业务逻辑处理

#### 高级配置示例

```php
class ConfigAdmin extends AbstractAdmin
{
    protected function configPage(PageConfig $config): void
    {
        $config->pageName = "系统配置";
        $config->tableName = ConfigTable::class;
        $config->order = [
            ConfigTable::APP_ID => 'desc',
            ConfigTable::KEY => 'asc',
            ConfigTable::ID => 'desc'
        ];
    }

    public function listsQuery(TableInterface $query): void
    {
        $query->addWhere(ConfigTable::APP_ID, AdminConfig::AppId);
    }

    public function configAction(ActionsConfig $actions): void
    {
        // 禁用默认编辑按钮
        $actions->disabledActions = [ActionDefaultButtonEnum::EDIT];

        // 添加自定义操作按钮
        $actions->addActions(
            new Action(label: '编辑', url: 'edit', params: [
                ConfigTable::VALUE_TYPE => '%' . ConfigTable::VALUE_TYPE,
            ])->showList()->showDetail()->setSort(1)
                ->setTemplate('action/action-alink.twig')->setIcon('bi bi-pencil'),
        );
    }

    protected function configField(PageFieldsConfig $fields): void
    {
        // 根据参数动态配置字段
        $valueType = $this->get(ConfigTable::VALUE_TYPE, '', 'txt');

        if ($valueType === 'txt') {
            $valueConfig = new TextareaField(field: ConfigTable::VALUE, label: '配置')->hideOnFilter();
        } elseif ($valueType === 'time') {
            $valueConfig = new Int2TimeField(field: ConfigTable::VALUE, label: '配置')->hideOnFilter();
        } elseif ($valueType === 'number') {
            $valueConfig = new NumberField(field: ConfigTable::VALUE, label: '配置')->hideOnFilter();
        } elseif ($valueType === 'url') {
            $valueConfig = new UrlField(field: ConfigTable::VALUE, label: '配置')->hideOnFilter();
        } else {
            $valueConfig = new ImageField(field: ConfigTable::VALUE, label: '配置')->hideOnFilter();
        }

        $isEdit = $this->pagePos === PagePosEnum::FORM_EDIT;

        $fields->setFields(
            new NumberField(field: ConfigTable::ID, label: 'ID')->hideOnForm()->hideOnList(),
            new TextField(field: ConfigTable::KEY, label: '配置唯一标识')
                ->setListMaxWidth(200)->setDisabled($isEdit),
            new TextField(field: ConfigTable::DESC, label: '配置说明')
                ->hideOnFilter()->setListMaxWidth(200),
            $valueConfig,
            new SwitchField(field: ConfigTable::IS_ENABLE, label: '是否启用')
                ->hideOnForm()->hideOnFilter(),
            new HiddenField(field: ConfigTable::APP_ID, label: '应用ID')
                ->setFilterDefault(AdminConfig::AppId)->setDefault(AdminConfig::AppId),
        );
    }

    public function insertUpdateAfter(ConfigTableDto $table): void
    {
        // 数据保存后清除缓存
        ConfigService::clearCache(AdminConfig::AppId, $table->key);
    }
}
```

### 5.5 字段类型

#### 基础字段

```php
// 文本字段
new TextField(field: UserTable::USERNAME, label: '用户名')
    ->setRequired(true)
    ->setPlaceholder('请输入用户名');

// 数字字段
new NumberField(field: UserTable::AGE, label: '年龄')
    ->setMin(0)
    ->setMax(150);

// 密码字段
new PasswordField(field: UserTable::PASSWORD, label: '密码');

// 文本域
new TextareaField(field: UserTable::DESCRIPTION, label: '描述');

// 开关字段
new SwitchField(field: UserTable::IS_ACTIVE, label: '是否激活');
```

#### 选择字段

```php
// 下拉选择
new SelectField(field: UserTable::GENDER, label: '性别')->setOptions(
    new OptionManager('male', '男'),
    new OptionManager('female', '女')
);

// 关联选择（外键关联）
new SelectField(field: UserTable::CATEGORY_ID, label: '分类')
    ->setRelation(
        CategoryTable::class,    // 关联表
        CategoryTable::ID,       // 关联表主键
        CategoryTable::NAME      // 显示字段
    );

// 多选字段
new CheckboxField(field: UserTable::TAGS, label: '标签')->setOptions(
    new OptionManager('tag1', '标签1'),
    new OptionManager('tag2', '标签2')
);
```

#### 特殊字段

```php
// 图片上传
new ImageField(field: UserTable::AVATAR, label: '头像');

// 文件上传
new FileField(field: UserTable::ATTACHMENT, label: '附件');

// 日期时间
new DateTimeField(field: UserTable::BIRTHDAY, label: '生日');

// 时间戳转换
new Int2TimeField(field: UserTable::CREATED_AT, label: '创建时间');

// 颜色选择
new ColorField(field: UserTable::THEME_COLOR, label: '主题色');
```

### 5.6 字段属性和方法

#### 通用字段属性

```php
// 字段显示控制
new TextField(field: UserTable::USERNAME, label: '用户名')
    ->hideOnForm()        // 在表单中隐藏
    ->hideOnList()        // 在列表中隐藏
    ->hideOnFilter()      // 在筛选中隐藏
    ->onlyOnForm()        // 只在表单中显示
    ->onlyOnList()        // 只在列表中显示
    ->onlyOnFilter();     // 只在筛选中显示

// 字段验证
new TextField(field: UserTable::EMAIL, label: '邮箱')
    ->setRequired(true)           // 必填
    ->setPlaceholder('请输入邮箱')  // 占位符
    ->setDisabled(true)           // 禁用编辑
    ->setReadonly(true);          // 只读

// 列表显示控制
new TextField(field: UserTable::DESCRIPTION, label: '描述')
    ->setListMaxWidth(200)        // 列表最大宽度
    ->setListEllipsis(true);      // 超长省略

// 默认值设置
new NumberField(field: UserTable::STATUS, label: '状态')
    ->setDefault(1)               // 表单默认值
    ->setFilterDefault(1);        // 筛选默认值
```

#### 特殊字段配置

```php
// 图片上传字段
new ImageField(field: UserTable::AVATAR, label: '头像')
    ->setUploadPath('/uploads/avatars/')  // 上传路径
    ->setMaxSize(2048)                    // 最大文件大小(KB)
    ->setAllowedTypes(['jpg', 'png']);    // 允许的文件类型

// 文件上传字段
new FileField(field: UserTable::ATTACHMENT, label: '附件')
    ->setUploadPath('/uploads/files/')
    ->setMaxSize(10240)
    ->setAllowedTypes(['pdf', 'doc', 'docx']);

// URL 字段
new UrlField(field: UserTable::WEBSITE, label: '网站')
    ->setOpenInNewTab(true);              // 在新标签页打开

// 时间戳字段
new Int2TimeField(field: UserTable::CREATED_AT, label: '创建时间')
    ->setFormat('Y-m-d H:i:s');           // 时间格式

// 数字字段
new NumberField(field: UserTable::PRICE, label: '价格')
    ->setMin(0)                           // 最小值
    ->setMax(99999)                       // 最大值
    ->setStep(0.01)                       // 步长
    ->setPrefix('￥')                      // 前缀
    ->setSuffix('元');                     // 后缀
```

### 5.7 权限控制和数据隔离

#### 数据权限控制

```php
class UserAdmin extends AbstractAdmin
{
    public function listsQuery(TableInterface $query): void
    {
        // 应用级数据隔离
        $query->addWhere(UserTable::APP_ID, AdminConfig::AppId);

        // 用户级数据隔离
        if (!$this->hasRole('ROLE_ADMIN')) {
            $query->addWhere(UserTable::CREATED_BY, $this->getCurrentUserId());
        }

        // 状态过滤
        $query->addWhere(UserTable::STATUS, '!=', -1);
    }

    public function editQuery(TableInterface $query): void
    {
        // 编辑权限控制
        $query->addWhere(UserTable::STATUS, 1);
    }

    public function deleteQuery(TableInterface $query): void
    {
        // 删除权限控制
        $query->addWhere(UserTable::STATUS, '!=', 1);
    }
}
```

#### 字段级权限控制

```php
protected function configField(PageFieldsConfig $fields): void
{
    $fields->setFields(
        new TextField(field: UserTable::USERNAME, label: '用户名'),

        // 根据角色显示不同字段
        new SelectField(field: UserTable::STATUS, label: '状态')
            ->setOptions(
                new OptionManager(1, '启用'),
                new OptionManager(0, '禁用')
            )
            ->setVisible($this->hasRole('ROLE_ADMIN')),  // 只有管理员可见

        // 根据条件禁用字段
        new TextField(field: UserTable::EMAIL, label: '邮箱')
            ->setDisabled($this->pagePos === PagePosEnum::FORM_EDIT),
    );
}
```

### 5.8 事件钩子

后台管理系统提供了完整的事件钩子，可以在数据操作的各个阶段进行自定义处理：

```php
class UserAdmin extends AbstractAdmin
{
    // 插入/更新前的统一处理
    public function insertUpdateBefore(UserTableDto $dto): void
    {
        // 密码加密
        if ($dto->password && !str_starts_with($dto->password, '$2y$')) {
            $dto->password = password_hash($dto->password, PASSWORD_DEFAULT);
        }

        // 数据验证
        if (empty($dto->username)) {
            throw new AppException('用户名不能为空');
        }
    }

    // 插入前处理
    public function insertBefore(UserTableDto $dto): void
    {
        $dto->createdBy = $this->getCurrentUserId();
        $dto->createdAt = time();
        $dto->appId = AdminConfig::AppId;
    }

    // 插入后处理
    public function insertAfter(UserTableDto $dto): void
    {
        // 清除相关缓存
        $this->clearUserCache($dto->id);

        // 发送通知
        $this->sendNotification('新用户注册', $dto->username);

        // 记录操作日志
        $this->logOperation('CREATE_USER', $dto->id);
    }

    // 更新前处理
    public function updateBefore(UserTableDto $dto): void
    {
        $dto->updatedBy = $this->getCurrentUserId();
        $dto->updatedAt = time();
    }

    // 更新后处理
    public function updateAfter(UserTableDto $dto): void
    {
        // 清除缓存
        $this->clearUserCache($dto->id);

        // 记录操作日志
        $this->logOperation('UPDATE_USER', $dto->id);
    }

    // 删除前处理
    public function deleteBefore(UserTableDto $dto): void
    {
        // 检查是否可以删除
        if ($dto->status === 1) {
            throw new AppException('激活状态的用户不能删除');
        }

        // 检查关联数据
        $orderCount = new OrderTable()->where([
            OrderTable::USER_ID => $dto->id
        ])->count();

        if ($orderCount > 0) {
            throw new AppException('该用户有关联订单，不能删除');
        }
    }

    // 删除后处理
    public function deleteAfter(UserTableDto $dto): void
    {
        // 清除缓存
        $this->clearUserCache($dto->id);

        // 删除关联数据
        new UserScoreTable()->where([
            UserScoreTable::USER_ID => $dto->id
        ])->delete();

        // 记录操作日志
        $this->logOperation('DELETE_USER', $dto->id);
    }

    // 插入/更新后的统一处理
    public function insertUpdateAfter(UserTableDto $dto): void
    {
        // 更新统计数据
        $this->updateUserStatistics();

        // 同步到其他系统
        $this->syncToExternalSystem($dto);
    }

    private function clearUserCache(int $userId): void
    {
        // 清除用户相关缓存
        Cache::delete("user:{$userId}");
        Cache::delete("user_profile:{$userId}");
    }

    private function logOperation(string $action, int $userId): void
    {
        // 记录操作日志
        new AdminLogTable()->insert([
            AdminLogTable::ADMIN_ID => $this->getCurrentUserId(),
            AdminLogTable::ACTION => $action,
            AdminLogTable::TARGET_ID => $userId,
            AdminLogTable::CREATED_AT => time()
        ]);
    }
}
```

#### 可用的事件钩子

| 钩子方法 | 触发时机 | 参数 | 说明 |
|---------|---------|------|------|
| `insertUpdateBefore()` | 插入/更新前 | `$dto` | 统一的前置处理 |
| `insertBefore()` | 插入前 | `$dto` | 插入特定的前置处理 |
| `insertAfter()` | 插入后 | `$dto` | 插入特定的后置处理 |
| `updateBefore()` | 更新前 | `$dto` | 更新特定的前置处理 |
| `updateAfter()` | 更新后 | `$dto` | 更新特定的后置处理 |
| `deleteBefore()` | 删除前 | `$dto` | 删除前的检查和处理 |
| `deleteAfter()` | 删除后 | `$dto` | 删除后的清理工作 |
| `insertUpdateAfter()` | 插入/更新后 | `$dto` | 统一的后置处理 |

### 5.9 自定义操作按钮

```php
use Swlib\Admin\Config\ActionsConfig;
use Swlib\Admin\Action\Action;
use Swlib\Admin\Enum\ActionDefaultButtonEnum;

class UserAdmin extends AbstractAdmin
{
    public function configAction(ActionsConfig $actions): void
    {
        // 禁用默认按钮
        $actions->disabledActions = [
            ActionDefaultButtonEnum::DELETE,  // 禁用删除按钮
            ActionDefaultButtonEnum::EDIT     // 禁用编辑按钮
        ];

        // 添加自定义操作按钮
        $actions->addActions(
            // 列表页按钮
            new Action(label: '重置密码', url: 'resetPassword')
                ->showList()                    // 在列表页显示
                ->setIcon('bi bi-key')          // 设置图标
                ->setSort(1)                    // 排序
                ->setConfirm('确定要重置密码吗？'), // 确认提示

            // 详情页按钮
            new Action(label: '发送邮件', url: 'sendEmail')
                ->showDetail()                  // 在详情页显示
                ->setIcon('bi bi-envelope')
                ->setSort(2),

            // 批量操作按钮
            new Action(label: '批量激活', url: 'batchActivate')
                ->showBatch()                   // 批量操作
                ->setIcon('bi bi-check-circle')
                ->setConfirm('确定要激活选中的用户吗？'),
        );
    }

    // 自定义操作方法
    public function resetPassword(): JsonResponse
    {
        $id = $this->get('id', '请选择用户');

        $user = new UserTable()->where([
            UserTable::ID => $id
        ])->selectOne();

        if (!$user) {
            throw new AppException('用户不存在');
        }

        $newPassword = Str::random(8);
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        new UserTable()->where([
            UserTable::ID => $id
        ])->update([
            UserTable::PASSWORD => $hashedPassword
        ]);

        // 发送新密码到用户邮箱
        $this->sendPasswordEmail($user->email, $newPassword);

        return JsonResponse::success('密码重置成功，新密码已发送到用户邮箱');
    }

    public function batchActivate(): JsonResponse
    {
        $ids = $this->post('ids', '请选择用户');

        if (!is_array($ids) || empty($ids)) {
            throw new AppException('请选择要激活的用户');
        }

        $affectedRows = new UserTable()->where([
            [UserTable::ID, 'in', $ids]
        ])->update([
            UserTable::STATUS => 1,
            UserTable::UPDATED_AT => time()
        ]);

        return JsonResponse::success("成功激活 {$affectedRows} 个用户");
    }
}
```

### 5.10 后台管理最佳实践

#### 1. 项目结构组织

```
App/AdminXsom756/
├── AdminConfig.php              # 后台配置（必需）
├── Dashboard.php               # 首页控制器（必需）
├── Login.php                  # 登录控制器（必需）
├── User/                      # 用户管理模块
│   ├── UserAdmin.php
│   ├── UserScoreAdmin.php
│   └── UserLoginHisAdmin.php
├── System/                    # 系统管理模块
│   ├── ConfigAdmin.php
│   ├── AdminManagerAdmin.php
│   └── LanguageAdmin.php
└── Apps/                      # 业务模块
    ├── Live/
    │   ├── LiveRoomsAdmin.php
    │   └── LiveGiftItemsAdmin.php
    └── Video/
        ├── VideosAdmin.php
        └── VideoCommentsAdmin.php
```

#### 2. 命名规范

- **控制器命名**: `{模块名}Admin.php`
- **页面标题**: 使用中文，简洁明了
- **字段标签**: 使用中文，与业务术语保持一致
- **菜单组织**: 按业务模块分组，层级不超过3层

#### 3. 数据安全

```php
class UserAdmin extends AbstractAdmin
{
    public function listsQuery(TableInterface $query): void
    {
        // 1. 应用级数据隔离（必须）
        $query->addWhere(UserTable::APP_ID, AdminConfig::AppId);

        // 2. 软删除过滤
        $query->addWhere(UserTable::DELETED_AT, 'is null', '');

        // 3. 权限级数据过滤
        if (!$this->hasRole('ROLE_SUPER_ADMIN')) {
            $query->addWhere(UserTable::CREATED_BY, $this->getCurrentUserId());
        }
    }

    public function insertUpdateBefore(UserTableDto $dto): void
    {
        // 1. 数据验证
        if (empty($dto->username)) {
            throw new AppException('用户名不能为空');
        }

        // 2. 数据清理
        $dto->username = trim($dto->username);
        $dto->email = strtolower(trim($dto->email));

        // 3. 敏感数据处理
        if ($dto->password) {
            $dto->password = password_hash($dto->password, PASSWORD_DEFAULT);
        }

        // 4. 强制设置应用ID
        $dto->appId = AdminConfig::AppId;
    }
}
```

#### 4. 性能优化

```php
class UserAdmin extends AbstractAdmin
{
    protected function configField(PageFieldsConfig $fields): void
    {
        $fields->setFields(
            // 1. 大字段在列表中隐藏
            new TextareaField(field: UserTable::DESCRIPTION, label: '描述')
                ->hideOnList(),

            // 2. 时间字段在筛选中隐藏
            new Int2TimeField(field: UserTable::CREATED_AT, label: '创建时间')
                ->hideOnFilter(),

            // 3. 关联字段使用缓存
            new SelectField(field: UserTable::CATEGORY_ID, label: '分类')
                ->setRelation(CategoryTable::class, CategoryTable::ID, CategoryTable::NAME)
                ->setCacheTime(3600),  // 缓存1小时
        );
    }

    public function listsQuery(TableInterface $query): void
    {
        // 4. 只查询必要字段
        $query->field([
            UserTable::ID,
            UserTable::USERNAME,
            UserTable::EMAIL,
            UserTable::STATUS,
            UserTable::CREATED_AT
        ]);

        // 5. 合理的分页大小
        $this->pageSize = 20;
    }
}
```

#### 5. 用户体验

```php
protected function configField(PageFieldsConfig $fields): void
{
    $fields->setFields(
        // 1. 合理的字段顺序
        new NumberField(field: UserTable::ID, label: 'ID')->hideOnForm(),
        new TextField(field: UserTable::USERNAME, label: '用户名'),
        new TextField(field: UserTable::EMAIL, label: '邮箱'),

        // 2. 清晰的字段标签
        new SelectField(field: UserTable::STATUS, label: '账户状态')->setOptions(
            new OptionManager(1, '正常'),
            new OptionManager(0, '禁用'),
            new OptionManager(-1, '已删除')
        ),

        // 3. 合适的字段类型
        new SwitchField(field: UserTable::IS_VIP, label: 'VIP用户'),
        new Int2TimeField(field: UserTable::LAST_LOGIN_TIME, label: '最后登录'),

        // 4. 隐藏技术字段
        new HiddenField(field: UserTable::APP_ID, label: '应用ID')
            ->setDefault(AdminConfig::AppId),
    );
}
```

#### 6. 错误处理

```php
class UserAdmin extends AbstractAdmin
{
    public function insertUpdateBefore(UserTableDto $dto): void
    {
        try {
            // 业务逻辑处理
            $this->validateUserData($dto);
            $this->processUserData($dto);
        } catch (AppException $e) {
            // 业务异常直接抛出
            throw $e;
        } catch (Throwable $e) {
            // 系统异常记录日志并转换
            Log::error('用户数据处理失败', [
                'dto' => $dto,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new AppException('数据处理失败，请稍后重试');
        }
    }

    private function validateUserData(UserTableDto $dto): void
    {
        if (empty($dto->username)) {
            throw new AppException('用户名不能为空');
        }

        if (!filter_var($dto->email, FILTER_VALIDATE_EMAIL)) {
            throw new AppException('邮箱格式不正确');
        }

        // 检查用户名是否重复
        $exists = new UserTable()->where([
            [UserTable::USERNAME, '=', $dto->username],
            [UserTable::ID, '!=', $dto->id ?? 0]
        ])->exists();

        if ($exists) {
            throw new AppException('用户名已存在');
        }
    }
}
```

#### 7. 菜单配置技巧

```php
public function configMenus(): array
{
    return [
        // 1. 按业务模块分组
        new MenuGroup(label: '用户管理', icon: 'bi bi-people')->setMenus(
            new Menu(label: '用户账号', url: RouterPath::AdminXsom756UserUserAdminLists),
            new Menu(label: '用户积分', url: RouterPath::AdminXsom756UserUserScoreAdminLists),
            new Menu(label: '登录日志', url: RouterPath::AdminXsom756UserUserLoginHisAdminLists),
        ),

        // 2. 系统管理放在最后
        new MenuGroup(label: '系统管理', icon: 'bi bi-gear')->setMenus(
            new Menu(label: '管理员', url: RouterPath::AdminXsom756SystemAdminManagerAdminLists),
            new Menu(label: '系统配置', url: RouterPath::AdminXsom756SystemConfigAdminLists,
                params: [ConfigTable::APP_ID => AdminConfig::AppId]),  // 3. 传递必要参数
            new Menu(label: '操作日志', url: RouterPath::AdminXsom756SystemLogAdminLists),
        ),
    ];
}
```

#### 8. 开发调试

```php
class UserAdmin extends AbstractAdmin
{
    protected function configPage(PageConfig $config): void
    {
        $config->pageName = "用户管理";
        $config->tableName = UserTable::class;

        // 开发环境显示SQL调试
        if (APP_DEBUG) {
            $config->showSqlDebug = true;
        }
    }

    public function listsQuery(TableInterface $query): void
    {
        // 开发环境记录查询
        if (APP_DEBUG) {
            $query->setDebugSql();
        }

        $query->addWhere(UserTable::APP_ID, AdminConfig::AppId);
    }
}
```

通过以上配置和最佳实践，可以快速构建功能完整、安全可靠的后台管理系统。
        if ($dto->status === 1) {
            throw new AppException('激活用户不能删除');
        }
    }
}
```

## 6. AOP 和事务

SWLib 提供了强大的 AOP（面向切面编程）支持，可以实现日志记录、性能监控、缓存、事务管理等横切关注点。

### 6.1 AOP 切面

#### 创建切面

```php
use Swlib\Aop\Abstract\AbstractAspect;
use Swlib\Aop\JoinPoint;

class LoggingAspect extends AbstractAspect
{
    public function before(JoinPoint $joinPoint): void
    {
        $signature = $joinPoint->getSignature();
        Log::info("方法调用开始: {$signature}");
    }

    public function after(JoinPoint $joinPoint, mixed $result): void
    {
        $signature = $joinPoint->getSignature();
        Log::info("方法调用结束: {$signature}");
    }

    public function afterThrowing(JoinPoint $joinPoint, Throwable $exception): void
    {
        $signature = $joinPoint->getSignature();
        Log::error("方法调用异常: {$signature} - {$exception->getMessage()}");
    }
}
```

#### 应用切面

```php
use Swlib\Aop\Aspects\LoggingAspect;
use Swlib\Aop\Aspects\PerformanceAspect;
use Swlib\Aop\Aspects\CachingAspect;

class UserService
{
    #[LoggingAspect]
    #[PerformanceAspect(threshold: 1000)] // 超过1秒记录警告
    public function getUserInfo(int $userId): UserTableDto
    {
        return new UserTable()->where([
            UserTable::ID => $userId
        ])->selectOne();
    }

    #[CachingAspect(ttl: 3600, keyPrefix: 'user_list')]
    public function getUserList(): array
    {
        return new UserTable()->selectAll();
    }
}
```

### 6.2 内置切面

#### 性能监控切面

```php
#[PerformanceAspect(
    threshold: 1000,    // 阈值（毫秒）
    logAll: false       // 是否记录所有调用
)]
public function slowMethod(): mixed
{
    // 耗时操作
    sleep(2);
    return 'result';
}
```

#### 缓存切面

```php
#[CachingAspect(
    ttl: 3600,                    // 缓存时间（秒）
    keyPrefix: 'user_service',    // 缓存键前缀
    includeArgs: true             // 是否包含参数在缓存键中
)]
public function getCachedData(int $id): array
{
    // 复杂的数据查询
    return $this->complexQuery($id);
}
```

### 6.3 事务管理

#### 声明式事务

```php
use Swlib\Table\Attributes\Transaction;

class OrderService
{
    #[Transaction(dbName: 'default')]
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

        // 创建订单项
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

#### 编程式事务

```php
use Swlib\Table\Db;

$result = Db::transaction(function () use ($userId, $amount) {
    // 检查余额
    $user = new UserTable()->where([
        UserTable::ID => $userId
    ])->lock()->selectOne();  // 加锁查询

    if ($user->balance < $amount) {
        throw new AppException('余额不足');
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
        TransactionTable::AMOUNT => $amount,
        TransactionTable::TYPE => 'debit'
    ]);
},
dbName: 'default',           // 数据库名称
isolationLevel: Db::ISOLATION_REPEATABLE_READ,  // 隔离级别
timeout: 30,                 // 超时时间（秒）
enableLog: true             // 启用事务日志
);
```






## 7. 事件系统

SWLib 提供了强大的事件系统，支持同步/异步事件处理、优先级、延迟执行等特性。

### 7.1 定义事件

```php
use Swlib\Event\Abstract\AbstractEvent;
use Swlib\Event\Attribute\Event;

#[Event(name: 'user.registered')]
class UserRegisteredEvent extends AbstractEvent
{
    public function __construct(
        public readonly int $userId,
        public readonly string $username,
        public readonly string $email
    ) {}
}
```

### 7.2 事件监听器

```php
use Swlib\Event\Abstract\AbstractEventListener;
use Swlib\Event\Attribute\EventListener;

#[EventListener(
    event: 'user.registered',
    priority: 100,          // 优先级（数字越大越先执行）
    async: true,            // 是否异步执行
    delay: 0                // 延迟执行（秒）
)]
class SendWelcomeEmailListener extends AbstractEventListener
{
    public function handle(UserRegisteredEvent $event): void
    {
        // 发送欢迎邮件
        EmailService::send(
            $event->email,
            '欢迎注册',
            "欢迎 {$event->username} 注册我们的平台！"
        );
    }
}
```

### 7.3 触发事件

```php
use Swlib\Event\Event;

class UserService
{
    public function register(string $username, string $email): int
    {
        // 创建用户
        $userId = new UserTable()->insert([
            UserTable::USERNAME => $username,
            UserTable::EMAIL => $email
        ]);

        // 触发事件
        Event::emit('user.registered', [
            'userId' => $userId,
            'username' => $username,
            'email' => $email
        ]);

        return $userId;
    }
}
```

## 8. 进程管理

### 8.1 创建自定义进程

```php
use Swlib\Process\Abstract\AbstractProcess;use Swlib\Process\Attribute\ProcessAttribute;

#[ProcessAttribute(interval: 1*1000)]  // 间隔1秒执行移除
class DataSyncProcess extends AbstractProcess
{
    public function run(): void
    {
        while (true) {
            try {
                $this->syncData();
                $this->sleep(60);
            } catch (Throwable $e) {
                Log::error('数据同步失败', ['error' => $e->getMessage()]);
                $this->sleep(300);
            }
        }
    }

    private function syncData(): void
    {
        // 数据同步逻辑
        $data = $this->fetchRemoteData();
        $this->saveToDatabase($data);
    }
}
```

## 9. 队列系统

### 9.1 任务队列

```php
use Swlib\Queue\Queue;

// 添加任务到队列
Queue::push('email', new SendEmailJob($emailData));

// 延迟任务
Queue::delay('notification', new NotificationJob($data), 300); // 5分钟后执行
```

### 9.2 任务处理器

```php
use Swlib\Queue\AbstractJob;

class SendEmailJob extends AbstractJob
{
    public function handle(): void
    {
        // 发送邮件逻辑
        EmailService::send($this->data['to'], $this->data['subject'], $this->data['body']);
    }

    public function failed(Throwable $exception): void
    {
        // 任务失败处理
        Log::error('邮件发送失败', ['error' => $exception->getMessage()]);
    }
}
```

## 10. 连接池管理

### 10.1 MySQL 连接池

```php
use Swlib\Pool\PoolMysql;

// 使用连接池执行查询
PoolMysql::call(function($db) {
    $result = $db->query('SELECT * FROM users WHERE status = 1');
    return $result->fetch_all(MYSQLI_ASSOC);
});
```

### 10.2 Redis 连接池

```php
use Swlib\Pool\PoolRedis;

// 使用连接池操作 Redis
PoolRedis::call(function($redis) {
    $redis->set('user:1', json_encode(['name' => 'John']));
    return $redis->get('user:1');
});

// 缓存操作
$data = PoolRedis::getSet('cache_key', function() {
    return new UserTable()->selectAll();
}, 3600);  // 缓存1小时
```

## 11. 中间件系统

### 11.1 创建中间件

```php
use Swlib\Middleware\AbstractMiddleware;

class AuthMiddleware extends AbstractMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        $token = $request->getHeader('authorization');

        if (empty($token)) {
            return JsonResponse::error('未授权访问', 401);
        }

        $user = $this->validateToken($token);
        if (!$user) {
            return JsonResponse::error('令牌无效', 401);
        }

        $request->setAttribute('user', $user);
        return $next($request);
    }
}
```

### 11.2 应用中间件

```php
// 全局中间件
#[Router(middleware: [AuthMiddleware::class, LoggingMiddleware::class])]
class ApiController extends AbstractController
{
    // 所有方法都会应用中间件
}
```

## 12. Protobuf 集成

### 12.1 自动生成 Protobuf

框架会根据数据库表结构自动生成 Protobuf 协议文件。

#### 扩展字段定义

在数据库表的 ID 字段注释中添加扩展字段定义：

```sql
-- 在 ID 字段注释中添加：
-- protobuf:ext:json:[
--   "item:isFocus:bool",
--   "item:focusCount:int32",
--   "lists:counts:repeated string"
-- ]
```

### 12.2 Proto 文件生成

框架会自动根据表结构生成对应的 .proto 文件：

```protobuf
syntax = "proto3";

package Protobuf.Database.User;
option php_metadata_namespace = "GPBMetadata\\Database";

message UserProto {
    int32 id = 1;
    string name = 2;
    int32 status = 3;
    bool isFocus = 4;
    int32 focusCount = 5;
}

message UserListsProto {
    repeated UserProto lists = 1;
    repeated string counts = 2;
}
```

### 12.3 在控制器中使用

```php
#[Router(method: 'POST')]
class UserController extends AbstractController
{
    public function create(UserProto $request): UserProto
    {
        // 验证请求数据
        if (empty($request->getUsername())) {
            throw new AppException('用户名不能为空');
        }

        // 插入数据
        $id = new UserTable()->insert([
            UserTable::USERNAME => $request->getUsername(),
            UserTable::EMAIL => $request->getEmail(),
            UserTable::STATUS => $request->getStatus()
        ]);

        // 构建响应
        $response = new UserProto();
        $response->setId($id);
        $response->setUsername($request->getUsername());
        $response->setEmail($request->getEmail());
        $response->setStatus($request->getStatus());

        return $response;
    }
}
```

### 12.4 编译 Proto 文件

```bash
# 编译所有 proto 文件
protoc -I protos/ protos/*.proto --php_out=runtime/Protobuf/

# 编译指定数据库的 proto 文件
protoc -I protos/Database/ protos/Database/*.proto --php_out=runtime/Protobuf/
```

## 13. 工具类

### 13.1 文件操作

```php
use Swlib\Utils\File;

// 创建目录
File::createDir('/path/to/dir');

// 复制目录
File::copyDirectory('/source', '/target');

// 删除目录
File::delDirectory('/path/to/dir');
```

### 13.2 字符串处理

```php
use Swlib\Utils\Str;

// 驼峰转下划线
$snake = Str::camelToUnderscore('userName'); // user_name

// 下划线转驼峰
$camel = Str::underscoreToCamel('user_name'); // userName
```

### 13.3 数组操作

```php
use Swlib\Utils\Arr;

// 数组转树形结构
$tree = Arr::toTree($array, 'id', 'parent_id');

// 获取数组指定键的值
$value = Arr::get($array, 'key.nested', 'default');
```

## 14. 最佳实践

### 14.1 代码组织

#### 目录结构建议

```
App/
├── Controller/          # 控制器
│   ├── Api/            # API 控制器
│   └── Admin/          # 后台控制器
├── Service/            # 服务层
├── Model/              # 模型层
├── Event/              # 事件
│   ├── Events/         # 事件定义
│   └── Listeners/      # 事件监听器
├── Process/            # 自定义进程
├── Middleware/         # 中间件
└── Utils/              # 工具类
```

#### 命名规范

- **类名**: 使用 PascalCase（如：`UserController`）
- **方法名**: 使用 camelCase（如：`getUserInfo`）
- **常量**: 使用 UPPER_SNAKE_CASE（如：`MAX_RETRY_COUNT`）
- **变量**: 使用 camelCase（如：`$userId`）

### 14.2 性能优化

#### 数据库查询优化

```php
// ✅ 好的做法
$users = new UserTable()
    ->field([UserTable::ID, UserTable::USERNAME])  // 只查询需要的字段
    ->where([UserTable::STATUS => 1])
    ->cache(300)                                    // 使用缓存
    ->selectAll();

// ❌ 避免的做法
$users = new UserTable()->selectAll();  // 查询所有字段
```

#### 连接池使用

```php
// ✅ 使用连接池
PoolMysql::call(function($db) {
    return $db->query('SELECT * FROM users');
});

// ❌ 直接创建连接
$db = new mysqli($host, $user, $pass, $database);
```

### 14.3 错误处理

#### 统一异常处理

```php
class UserService
{
    public function createUser(array $data): int
    {
        try {
            // 验证数据
            $this->validateUserData($data);

            // 创建用户
            return new UserTable()->insert($data);

        } catch (ValidationException $e) {
            throw new AppException('数据验证失败: ' . $e->getMessage());
        } catch (Throwable $e) {
            Log::error('创建用户失败', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            throw new AppException('创建用户失败');
        }
    }
}
```

### 14.4 安全建议

#### 输入验证

```php
class UserController extends AbstractController
{
    public function update(): Success
    {
        // 验证输入
        $id = $this->get('id', '用户ID不能为空');
        $username = $this->post('username', '用户名不能为空');

        // 验证权限
        if (!$this->canEditUser($id)) {
            throw new AppException('无权限编辑此用户');
        }

        // 过滤输入
        $username = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');

        // 更新数据
        new UserTable()->where([
            UserTable::ID => $id
        ])->update([
            UserTable::USERNAME => $username
        ]);

        return new Success();
    }
}
```

#### SQL 注入防护

```php
// ✅ 使用参数化查询
$users = new UserTable()->where([
    [UserTable::NAME, 'like', "%{$keyword}%"]
])->selectAll();

// ❌ 避免字符串拼接
$sql = "SELECT * FROM users WHERE name LIKE '%{$keyword}%'";
```

### 14.5 测试建议

#### 单元测试

```php
use PHPUnit\Framework\TestCase;

class UserServiceTest extends TestCase
{
    public function testCreateUser(): void
    {
        $service = new UserService();

        $userData = [
            'username' => 'testuser',
            'email' => 'test@example.com'
        ];

        $userId = $service->createUser($userData);

        $this->assertIsInt($userId);
        $this->assertGreaterThan(0, $userId);
    }
}
```

---

## 总结

SWLib 是一个功能完整、性能优异的现代 PHP 框架，提供了：

- **高性能**: 基于 Swoole 协程，支持高并发
- **完整的 ORM**: 自动生成、类型安全、功能丰富
- **现代化设计**: PHP 8.4+ 注解、AOP、事件驱动
- **开箱即用**: 后台管理、权限控制、队列系统
- **企业级特性**: 连接池、缓存、监控、日志

通过本文档，您可以快速上手并充分利用 SWLib 框架的各项功能来构建高质量的 Web 应用。

### 14.6 使用示例项目

基于本框架的实际项目示例可以参考 App 目录中的代码结构和实现方式。

---

**文档版本**: 2.0
**最后更新**: 2025-12-03
**框架版本**: SWLib 2.x
**PHP 版本要求**: >= 8.4



