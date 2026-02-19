## 17. Protobuf 集成

SWLib 框架深度集成了 Google Protocol Buffers (Protobuf)，用于高效的 API 数据序列化。框架会根据数据库表结构自动生成 Protobuf 定义文件（.proto）并编译为 PHP 类。

### 17.1 概述

#### 什么是 Protobuf

Protocol Buffers 是 Google 开发的一种轻量级、高效的结构化数据序列化格式，相比 JSON/XML 具有以下优势：
- **更小的体积**：二进制格式，数据量减少 50%-80%
- **更快的序列化/反序列化**：性能提升 3-10 倍
- **类型安全**：编译时类型检查，减少运行时错误
- **跨语言支持**：支持 PHP、Go、Java、Python 等多种语言

#### 框架中的 Protobuf 架构

```
┌─────────────────┐     自动生成      ┌─────────────────┐     编译      ┌─────────────────┐
│   MySQL 数据表   │ ───────────────> │   .proto 文件    │ ───────────> │   PHP 类文件     │
└─────────────────┘                   └─────────────────┘               └─────────────────┘
        │                                     │                                 │
        │                                     │                                 │
        v                                     v                                 v
   protos/DatabaseName/              runtime/Protobuf/DatabaseName/    控制器返回类型
      TableName.proto                   TableName/TableNameProto.php
```

#### 目录结构

```
project/
├── protos/                        # Protobuf 定义文件（框架自动生成）
│   ├── Wenyuehui/                 # 按数据库名称分组
│   │   ├── User.proto             # 用户表对应的 proto 文件
│   │   ├── Order.proto            # 订单表对应的 proto 文件
│   │   └── ...
│   └── field_maps/                # 字段映射缓存（保持字段编号稳定）
│       └── Wenyuehui/
│           ├── User.json
│           └── ...
└── runtime/
    ├── Protobuf/                  # 编译后的 PHP 类
    │   ├── Wenyuehui/
    │   │   ├── User/
    │   │   │   ├── UserProto.php           # 单条记录消息类
    │   │   │   └── UserListsProto.php      # 列表消息类
    │   │   └── ...
    │   └── GPBMetadata/           # Protobuf 元数据
    └── codes/proto/               # 部署用的 proto 文件副本
```

---

### 17.2 自动生成机制

框架在启动时会自动扫描数据库表结构，生成对应的 Protobuf 文件。

#### 生成时机

1. 应用首次启动时
2. 数据库表结构变更后重启服务时

#### 生成的消息类型

每个数据表会生成两个 Protobuf 消息类型：

```protobuf
// 单条记录消息
message UserProto {
    int32 id = 1;
    string username = 2;
    string email = 3;
    int32 status = 4;
    int32 created_at = 5;
    string created_atStr = 6;  // 自动生成的字符串格式时间
    int32 page_number = 7;     // 分页参数
    int32 page_size = 8;       // 分页参数
}

// 列表消息
message UserListsProto {
    repeated UserProto lists = 1;  // 列表数据
    int32 total = 2;               // 总数
    int32 curr_page = 3;           // 当前页码
    int32 total_page = 4;          // 总页数
}
```

---

### 17.3 字段类型映射

框架会自动将 MySQL 字段类型映射到 Protobuf 类型：

| MySQL 类型 | Protobuf 类型 | 说明 |
|-----------|--------------|------|
| TINYINT, SMALLINT, MEDIUMINT | int32 | 整数 |
| INT | int32 | 整数 |
| BIGINT | int64 | 大整数 |
| FLOAT | float | 单精度浮点 |
| DOUBLE, DECIMAL | double | 双精度浮点 |
| BOOL | bool | 布尔值 |
| VARCHAR, CHAR, TEXT, LONGTEXT | string | 字符串 |
| DATETIME, TIMESTAMP, DATE, TIME | string | 时间（自动生成 Str 后缀字段） |
| ENUM | Enum | 生成枚举类型 |
| SET | repeated string | 字符串数组 |
| JSON | string | JSON 字符串 |
| BLOB, LONGBLOB, BINARY | string | 二进制数据 |

#### 自动生成的辅助字段

对于时间类型字段，框架会自动生成一个带 `Str` 后缀的字符串字段：

```protobuf
int32 created_at = 5;
string created_atStr = 6;  // 格式化后的时间字符串
```

---

### 17.4 字段注释配置

通过在数据库字段注释中添加特殊标记，可以自定义 Protobuf 字段的生成。

#### 自定义字段类型

使用 `protobuf:item:type` 格式指定字段类型：

```sql
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    status TINYINT COMMENT '状态 protobuf:item:int64',  -- 强制使用 int64
    balance DECIMAL(10,2) COMMENT '余额 protobuf:item:string'  -- 使用字符串存储精度
);
```

生成的 proto：
```protobuf
int64 status = 2;
string balance = 3;
```

#### 生成字符串辅助字段

使用 `g-str-field` 标记为数值字段生成字符串格式：

```sql
CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    price DECIMAL(10,2) COMMENT '价格 g-str-field'  -- 同时生成数值和字符串格式
);
```

生成的 proto：
```protobuf
double price = 2;
string priceStr = 3;  // 用于前端精确显示
```

#### 列表消息中的额外字段

使用 `protobuf:lists:type` 在列表消息中添加额外字段：

```sql
CREATE TABLE orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT COMMENT '用户ID protobuf:lists:int32'  -- 在 ListsProto 中也包含此字段
);
```

生成的 ListsProto：
```protobuf
message OrderListsProto {
    repeated OrderProto lists = 1;
    int32 total = 2;
    int32 curr_page = 3;
    int32 total_page = 4;
    int32 user_id = 5;  // 额外添加的字段
}
```

---

### 17.5 扩展字段定义

在 ID 字段注释中可以定义扩展字段，用于在消息中添加数据库表中不存在的字段。

#### 基本语法

```sql
CREATE TABLE posts (
    id INT PRIMARY KEY AUTO_INCREMENT
        COMMENT 'protobuf:ext:json:[
            "item:isFocus:bool",
            "item:focusCount:int32",
            "lists:counts:repeated string"
        ]',
    title VARCHAR(255),
    content TEXT
);
```

#### 扩展字段格式

```
"位置:字段名:类型"
```

- **位置**：`item` 表示单条记录，`lists` 表示列表消息
- **字段名**：生成的 Protobuf 字段名称
- **类型**：Protobuf 数据类型

#### 支持的类型

| 类型 | 说明 | 示例 |
|------|------|------|
| bool | 布尔值 | `item:isActive:bool` |
| int32 | 32位整数 | `item:count:int32` |
| int64 | 64位整数 | `item:timestamp:int64` |
| string | 字符串 | `item:name:string` |
| float | 浮点数 | `item:score:float` |
| double | 双精度浮点 | `item:amount:double` |
| repeated string | 字符串数组 | `lists:tags:repeated string` |
| repeated int32 | 整数数组 | `lists:ids:repeated int32` |

#### 引用其他消息类型

可以在扩展字段中引用其他表生成的消息类型：

```sql
CREATE TABLE orders (
    id INT PRIMARY KEY AUTO_INCREMENT
        COMMENT 'protobuf:ext:json:[
            "item:user:Protobuf.Wenyuehui.User.UserProto",
            "lists:products:repeated Protobuf.Wenyuehui.Product.ProductProto"
        ]',
    -- ...
);
```

这会自动在 proto 文件中添加 import 语句：

```protobuf
import "User.proto";
import "Product.proto";

message OrderProto {
    // ... 原有字段
    UserProto user = 10;
}

message OrderListsProto {
    // ... 原有字段
    repeated ProductProto products = 10;
}
```

#### 使用 $self 引用自身

```sql
CREATE TABLE categories (
    id INT PRIMARY KEY AUTO_INCREMENT
        COMMENT 'protobuf:ext:json:[
            "item:children:repeated $self"  -- 引用自身的列表类型
        ]',
    name VARCHAR(100),
    parent_id INT
);
```

生成：
```protobuf
message CategoryProto {
    int32 id = 1;
    string name = 2;
    int32 parent_id = 3;
    repeated CategoryProto children = 4;  // 自引用
}
```

---

### 17.6 在控制器中使用

#### 返回 Protobuf 响应

控制器方法返回 Protobuf 消息对象，框架会自动序列化响应：

```php
use Protobuf\Wenyuehui\User\UserProto;
use Protobuf\Wenyuehui\User\UserListsProto;
use Generate\Tables\UserTable;

class UserController extends AbstractController
{
    #[Router(method: 'POST')]
    public function info(): UserProto
    {
        $id = $this->get('id', 'ID不能为空');

        $user = new UserTable()
            ->where([UserTable::ID => $id])
            ->selectOne();

        $proto = new UserProto();
        $proto->setId($user->id);
        $proto->setUsername($user->username);
        $proto->setEmail($user->email);
        $proto->setStatus($user->status);

        return $proto;
    }

    #[Router(method: 'POST')]
    public function list(): UserListsProto
    {
        $page = $this->get('page', 1);
        $size = $this->get('size', 10);

        $users = new UserTable()
            ->where([UserTable::STATUS => 1])
            ->order([UserTable::ID => 'desc'])
            ->page($page, $size)
            ->selectAll();

        $proto = new UserListsProto();
        $proto->setTotal($users->__total);
        $proto->setCurrPage($page);
        $proto->setTotalPage(ceil($users->__total / $size));

        $list = [];
        foreach ($users as $user) {
            $item = new UserProto();
            $item->setId($user->id);
            $item->setUsername($user->username);
            $list[] = $item;
        }
        $proto->setLists($list);

        return $proto;
    }
}
```

#### 使用 DTO 快速填充

框架提供了便捷方法将 DTO 数据填充到 Protobuf 对象：

```php
use Protobuf\Wenyuehui\User\UserProto;

public function info(): UserProto
{
    $user = new UserTable()
        ->where([UserTable::ID => $this->get('id')])
        ->selectOne();

    $proto = new UserProto();

    // 自动映射同名属性
    $proto->setId($user->id);
    $proto->setUsername($user->username);
    $proto->setEmail($user->email);
    $proto->setStatus($user->status);
    $proto->setCreatedAt($user->createdAt);
    $proto->setCreatedAtStr(date('Y-m-d H:i:s', $user->createdAt));

    return $proto;
}
```

---

### 17.7 Proto 文件编译

#### 自动编译

框架在生成 proto 文件后会自动调用 protoc 编译器：

```php
// 框架内部调用
ParseTableProtoc::compileProto();
```

执行的命令：
```bash
protoc -I protos/Wenyuehui protos/Wenyuehui/*.proto --php_out=runtime/Protobuf/
```

#### 手动编译

如果需要手动重新编译 proto 文件：

```bash
# 编译单个文件
protoc -I protos protos/Wenyuehui/User.proto --php_out=runtime/Protobuf/

# 编译整个目录
protoc -I protos/Wenyuehui protos/Wenyuehui/*.proto --php_out=runtime/Protobuf/
```

#### 编译要求

确保系统已安装 protoc 编译器：

```bash
# macOS
brew install protobuf

# Ubuntu/Debian
apt-get install protobuf-compiler

# 验证安装
protoc --version
```

---

### 17.8 Proto 文件示例

#### 基本表示例

SQL：
```sql
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) COMMENT '用户名',
    email VARCHAR(100) COMMENT '邮箱',
    status TINYINT DEFAULT 1 COMMENT '状态：1正常 0禁用',
    created_at INT COMMENT '创建时间',
    updated_at INT COMMENT '更新时间'
) COMMENT='用户表';
```

生成的 `protos/Wenyuehui/User.proto`：
```protobuf
// 数据库: Wenyuehui, 表: User
syntax = "proto3";

// protoc  --php_out=../../   *.proto

package Protobuf.Wenyuehui.User;
option php_metadata_namespace = "GPBMetadata\\Wenyuehui";

message UserProto {
    int32 id = 1;
    string username = 2;
    string email = 3;
    int32 status = 4;
    int32 created_at = 5;
    string created_atStr = 6;
    int32 updated_at = 7;
    string updated_atStr = 8;
    int32 page_number = 9;
    int32 page_size = 10;
}

message UserListsProto {
    repeated UserProto lists = 1;
    int32 total = 2;
    int32 curr_page = 3;
    int32 total_page = 4;
}
```

#### 带扩展字段的示例

SQL：
```sql
CREATE TABLE posts (
    id INT PRIMARY KEY AUTO_INCREMENT
        COMMENT 'protobuf:ext:json:[
            "item:isLiked:bool",
            "item:likeCount:int32",
            "item:author:Protobuf.Wenyuehui.User.UserProto"
        ]',
    title VARCHAR(255) COMMENT '标题',
    content TEXT COMMENT '内容',
    user_id INT COMMENT '作者ID',
    status TINYINT COMMENT '状态'
);
```

生成的 proto：
```protobuf
syntax = "proto3";

import "User.proto";

package Protobuf.Wenyuehui.Posts;
option php_metadata_namespace = "GPBMetadata\\Wenyuehui";

message PostsProto {
    int32 id = 1;
    string title = 2;
    string content = 3;
    int32 user_id = 4;
    int32 status = 5;
    int32 page_number = 6;
    int32 page_size = 7;
    bool isLiked = 8;
    int32 likeCount = 9;
    UserProto author = 10;
}

message PostsListsProto {
    repeated PostsProto lists = 1;
    int32 total = 2;
    int32 curr_page = 3;
    int32 total_page = 4;
}
```

---

### 17.9 前端对接

#### 响应格式

客户端可以请求 JSON 或二进制格式：

```http
POST /api/user/info
Accept: application/x-protobuf  # 二进制格式
Accept: application/json         # JSON 格式（默认）
```

JSON 响应示例：
```json
{
    "id": 123,
    "username": "john_doe",
    "email": "john@example.com",
    "status": 1,
    "createdAt": 1705324800,
    "createdAtStr": "2024-01-15 10:00:00"
}
```

#### 移动端推荐使用二进制

移动端（iOS/Android）推荐使用二进制格式以获得最佳性能：

```swift
// iOS 示例
let url = URL(string: "https://api.example.com/user/info")!
var request = URLRequest(url: url)
request.setValue("application/x-protobuf", forHTTPHeaderField: "Accept")

let (data, _) = try await URLSession.shared.data(for: request)
let user = try UserProto(serializedData: data)
```

```kotlin
// Android 示例
val request = Request.Builder()
    .url("https://api.example.com/user/info")
    .header("Accept", "application/x-protobuf")
    .build()

val response = okHttpClient.newCall(request).execute()
val user = UserProto.parseFrom(response.body?.byteStream())
```

---

### 17.10 最佳实践

#### 命名规范

```sql
-- 推荐：清晰的字段名和注释
CREATE TABLE orders (
    id INT PRIMARY KEY AUTO_INCREMENT COMMENT '订单ID',
    order_no VARCHAR(32) COMMENT '订单编号',
    user_id INT COMMENT '用户ID protobuf:item:int64',  -- 关联ID用int64
    total_amount DECIMAL(10,2) COMMENT '订单金额 g-str-field',  -- 金额保留精度
    status TINYINT COMMENT '订单状态'
);
```

#### 避免频繁修改字段

Protobuf 字段编号一旦分配就不应更改，框架使用 `field_maps` 缓存来保持编号稳定：

```json
// protos/field_maps/Wenyuehui/User.json
{
    "int32 id": 1,
    "string username": 2,
    "string email": 3
}
```

#### 合理使用扩展字段

```sql
-- 推荐：只在需要时添加扩展字段
COMMENT 'protobuf:ext:json:[
    "item:isFocus:bool",        -- 用户是否关注
    "item:focusCount:int32"     -- 关注数量
]'

-- 不推荐：过度使用扩展字段
COMMENT 'protobuf:ext:json:[
    "item:field1:string",
    "item:field2:string",
    "item:field3:string",
    ...大量扩展字段
]'
```

#### 性能优化

```php
// 推荐：批量设置列表数据
$proto = new UserListsProto();
$proto->setLists($userList);  // 一次性设置
$proto->setTotal($total);

// 不推荐：循环中频繁操作
foreach ($users as $user) {
    $proto->getLists()[] = $user;  // 每次都调用 getter
}
```
