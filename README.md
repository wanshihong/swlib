# SWLib 框架完整使用指南

## 目录

- [1. 框架概述](#1-框架概述)
- [2. 后台管理系统](#2-后台管理系统)
- [3. 前台控制器](#3-前台控制器)
- [4. 数据库操作](#4-数据库操作)
- [5. 连接池管理](#5-连接池管理)
- [6. 路由系统](#6-路由系统)
- [7. 进程管理](#7-进程管理)
- [8. 事件系统](#8-事件系统)
- [9. 工具类](#9-工具类)
- [10. Protobuf 集成](#10-protobuf-集成)


## 1. 框架概述

SWLib 是一个基于 PHP 8.0+ 和 Swoole 的高性能 Web 开发框架，提供了完整的后台管理系统、API 开发支持、数据库 、连接池、进程管理等功能。属于自用的玩具；

### 1.1 框架特性

- 基于 PHP 8.4+ 注解特性
- 集成 Swoole 高性能服务器
- 内置后台管理系统
- 支持 MySQL 和 Redis 连接池
- 自动生成 Protobuf 协议文件
- 多进程管理和事件系统
- 完整的路由系统
## 安装

### 前提条件

- PHP >= 8.4
- Swoole >= 6.0.0
- mysqli PHP 扩展
- Redis PHP 扩展


### 在现有项目中添加依赖

```bash
composer require wansh/swlib
```

### 添加自动加载文件规则
```javascript
//composer.json
"autoload" : {
    "psr-4": {
        "App\\": "App/", // 应用目录
        "Generate\\": "runtime/Generate", // 生成代码
        "GPBMetadata\\": "runtime/Protobuf/GPBMetadata/", // protobuf元数据
        "Protobuf\\": "runtime/Protobuf/Protobuf/" // protobuf代码
    }
}
```

### 创建启动文件
```php
// bin/start.php
<?php
declare(strict_types=1);
require_once "./vendor/autoload.php";

use Generate\ConfigEnum;
use Swlib\App;
use Swlib\Process\Process;
use Swlib\ServerEvents\OnCloseEvent;
use Swlib\ServerEvents\OnFinishEvent;
use Swlib\ServerEvents\OnMessageEvent;
use Swlib\ServerEvents\OnOpenEvent;
use Swlib\ServerEvents\OnPipeMessageEvent;
use Swlib\ServerEvents\OnReceiveEvent;
use Swlib\ServerEvents\OnRequestEvent;
use Swlib\ServerEvents\OnStartEvent;
use Swlib\ServerEvents\OnTaskEvent;
use Swlib\ServerEvents\OnWorkerStartEvent;
use Swlib\ServerEvents\OnWorkerStopEvent;
use Swoole\WebSocket\Server;

define('ROOT_DIR', dirname(__DIR__) . DIRECTORY_SEPARATOR);
const APP_DIR = ROOT_DIR . 'App' . DIRECTORY_SEPARATOR;
const PUBLIC_DIR = ROOT_DIR . 'public' . DIRECTORY_SEPARATOR;
const RUNTIME_DIR = ROOT_DIR . 'runtime' . DIRECTORY_SEPARATOR;

// 开发过程过程中  删除解析锁，上线了可以删除；
unlink(RUNTIME_DIR . 'parse.lock');

$app = new App();

try {
    $app->parse();

    $server = new Server(
        "0.0.0.0",
        ConfigEnum::PORT,
        SWOOLE_PROCESS,
        ConfigEnum::APP_PROD === false ? SWOOLE_SOCK_TCP | SWOOLE_SSL : 0
    );


    $config = [
        'hook_flags' => SWOOLE_HOOK_ALL,
        'daemonize' => ConfigEnum::APP_PROD, // 设为true则以守护进程方式运行
        'worker_num' => ConfigEnum::WORKER_NUM,
        'task_worker_num' => ConfigEnum::TASK_WORKER_NUM,
        'task_enable_coroutine' => true,
        'task_max_request' => 1024,
        'upload_max_filesize' => 100 * 1024 * 1024,
        'heartbeat_idle_time' => 600, // 表示一个连接如果600秒内未向服务器发送任何数据，此连接将被强制关闭
        'heartbeat_check_interval' => 60,  // 表示每60秒遍历一次
        'enable_coroutine' => true,
        'max_request' => 1024,
        'dispatch_mode' => 4,// 根据IP 分配 worker进程
        'max_wait_time' => 10,
        'reload_async' => true,// 平滑重启
        'log_file' => RUNTIME_DIR . '/log/server_error.log'
    ];
    $config = $app->generateDevSSL($config);
    $server->set($config);

    // 绑定服务器事件，如果需要扩展，可以在此进行绑定
    $server->on('receive', [new OnReceiveEvent(), 'handle']);
    $server->on('start', [new OnStartEvent(), 'handle']);
    $server->on('workerStart', [new OnWorkerStartEvent(), 'handle']);
    $server->on('workerStop', [new OnWorkerStopEvent(), 'handle']);
    $server->on('pipeMessage', [new OnPipeMessageEvent(), 'handle']);
    $server->on('open', [new OnOpenEvent(), 'handle']);
    $server->on('message', [new OnMessageEvent(), 'handle']);
    $server->on('close', [new OnCloseEvent(), 'handle']);
    $server->on('request', [new OnRequestEvent($server), 'handle']);
    $server->on('task', [new OnTaskEvent(), 'handle']);
    $server->on('finish', [new OnFinishEvent(), 'handle']);


    // 添加自定义进程
    Process::run($server);

    $port = ConfigEnum::PORT;

    $http = ConfigEnum::APP_PROD === false ? 'https' : 'http';
    echo "Swoole http server is started at $http://127.0.0.1:$port" . PHP_EOL;

    $server->start();
} catch (Exception $e) {
    var_dump($e->getMessage());
    var_dump($e->getTraceAsString());
}

```

## 配置

Swlib框架使用.env文件和配置文件来管理应用设置。

### 环境变量

复制环境变量示例文件并根据需要修改：

```env
# 是否生产环境
APP_PROD=false

# 启动进程数量
WORKER_NUM=5
TASK_WORKER_NUM=2
# 启动端口
PORT=9501


DB_DATABASE=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_ROOT=root
DB_PWD=123456
DB_CHARSET=utf8mb4
# 慢SQL 阈值（毫秒） SQL 执行时间大于这个值，就记录SQL
DB_SLOW_TIME=100
# 是否需要记录 SQL
DB_SAVE_SQL=true
# 连接池数量，是每个进程的连接池数量，可不要配置太多了
DB_POOL_NUM=10
# 心跳时间(秒)
DB_HEART=10

REDIS_HOST=redis
REDIS_PORT=6379
REDIS_AUTH=
# 连接池数量 是每个进程的连接池数量，可不要配置太多了
REDIS_POOL_NUM=10


# 后台菜单配置文件路径，不是文件路径写法，是命名空间  例如 【命名空间\文件名  App\AdminConfig】
ADMIN_CONFIG_PATH=App\Admin\AdminConfig

```
### 启动服务器

```bash
  php bin/start.php
```

访问 http://127.0.0.1:9501 即可看到欢迎页面。



## 2. 后台管理系统

### 2.1 后台控制器

创建后台控制器需要继承 `AbstractAdmin` 类：

```php
use Swlib\Admin\Controller\AbstractAdmin;
use Swlib\Admin\Config\PageConfig;
use Swlib\Admin\Config\PageFieldsConfig;

class YourAdmin extends AbstractAdmin
{
    protected function configPage(PageConfig $config): void
    {
        $config->pageName = "页面名称";
        $config->tableName = YourTable::class;
        $config->order = [
            YourTable::ID => 'desc'
        ];
    }

    protected function configField(PageFieldsConfig $fields): void
    {
        $fields->setFields(
            new NumberField(...),
            new TextField(...),
            new SelectField(...)
        );
    }
}
```

### 2.2 字段类型

#### 2.2.1 TextField（文本字段）
```php
new TextField(
    field: 'name',           // 字段名
    label: '名称',           // 显示标签
    required: true,          // 是否必填
    placeholder: '请输入名称' // 占位文本
)
```

#### 2.2.2 NumberField（数字字段）
```php
new NumberField(
    field: 'age',
    label: '年龄',
    min: 0,        // 最小值
    max: 150,      // 最大值
    step: 1        // 步进值
)
```

#### 2.2.3 SelectField（选择字段）
```php
new SelectField(
    field: 'status',
    label: '状态',
    options: [     // 选项
        new OptionManager(1, '启用'),
        new OptionManager(0, '禁用')
    ]
)
```

#### 2.2.4 ImageField（图片字段）
```php
new ImageField(
    field: 'avatar',
    label: '头像'
)
```



### 2.3 字段关联

```php
new SelectField(
    field: 'category_id',
    label: '分类'
)->setRelation(
    CategoryTable::class,    // 关联表
    CategoryTable::ID,       // 关联表主键
    CategoryTable::NAME      // 显示字段
)
```

### 2.4 表格操作

```php
protected function configTable(TableConfig $config): void
{
    $config->addAction('custom', '自定义操作', function($row) {
        // 自定义操作逻辑
    });
    
    $config->addBatchAction('batch', '批量操作', function($ids) {
        // 批量操作逻辑
    });
}
```

## 3. 前台控制器

### 3.1 基本控制器

```php
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
}
```


## 4. 数据库操作

### 4.1 基本查询

```php
// 查询单条记录
$user = new UserTable()->where([
    UserTable::ID => 1
])->selectOne();

// 查询多条记录
$users = new UserTable()
    ->where([
        UserTable::STATUS => 1
    ])
    ->order([
        UserTable::ID => 'desc'
    ])
    ->page(1, 10)
    ->selectAll();

// 统计
$count = new UserTable()
    ->where([
        UserTable::STATUS => 1
    ])
    ->count(UserTable::ID);
```

### 4.2 复杂查询

```php
$where = [
    // [字段, 任意查询运算符, 查询的值]
    [UserTable::STATUS , '=' , 1],
    [UserTable::AGE ,'>', 18],
    [UserTable::NAME ,'like', "张三"],
    [UserTable::AREA ,'IN', ["重庆","四川"]],
];

```

### 4.3 复杂查询

```php
// 可任意写SQL
$query->addWhereRaw(" and (jy_store.name like ? or jy_store.address like ?)", ["%$name%", "%$name%"]);


```

### 4.3 事务处理

```php
Db::transaction(function () {


});
```

## 5. 连接池管理

### 5.1 MySQL 连接池

```php
// 使用连接池
PoolMysql::call(function($db) {
    $result = $db->query('SELECT * FROM users');
});

// 获取连接
$db = PoolMysql::get();
try {
    // 使用连接
} finally {
    PoolMysql::put($db);
}
```

### 5.2 Redis 连接池

```php
// 使用连接池
PoolRedis::call(function($redis) {
    $redis->set('key', 'value');
});

// 缓存操作
$data = PoolRedis::getSet('cache_key', function() {
    return ['data' => 'value'];
}, 3600);
```

## 6. 路由系统

### 6.1 注解路由

```php
#[Router(method: 'POST', prefix: '/api')]
class UserController
{
    #[Router('/login', errorTitle: '登录失败')]
    public function login(LoginProto $request): LoginProto
    {
        // 登录逻辑
    }
    
    #[Router('/logout')]
    public function logout(): void
    {
        // 登出逻辑
    }
}
```

### 6.2 路由中间件

```php
#[Router(middleware: [AuthMiddleware::class])]
class UserController
{
    // 需要认证的接口
}
```

## 7. 进程管理

### 7.1 自定义进程

```php
use Swlib\Process\Process;
use Swlib\Process\AbstractProcess;

#[Process(interval: 60)]
class CustomProcess extends AbstractProcess
{
    public function run(): void
    {
        while (true) {
            // 进程逻辑
            sleep(1);
        }
    }
}
```


## 8. 事件系统

### 8.1 定义事件

```php
use Swlib\Event\Event;
use Swlib\Event\AbstractEvent;

#[Event(name: 'device.active')]
class UserRegisteredEvent extends AbstractEvent
{
    public function __construct(
        public readonly int $userId,
        public readonly string $username
    ) {}
}
```

### 8.3 触发事件

```php
use Swlib\Event\Event;

 Event::emit('device.active', [
    'deviceId' => $table->id,
]);
```

## 9. 工具类

### 9.1 文件操作

```php
use Swlib\Utils\File;

// 创建目录
File::createDir('/path/to/dir');

// 复制目录
File::copyDirectory('/source', '/target');

// 删除目录
File::delDirectory('/path/to/dir');
```

### 9.2 字符串处理

```php
use Swlib\Utils\Str;

// 驼峰转下划线
$snake = Str::camelToUnderscore('userName'); // user_name

// 下划线转驼峰
$camel = Str::underscoreToCamel('user_name'); // userName
```






## 10. Protobuf 集成

### 10.1 表结构定义

```php
/**
 会根据数据库库表格自动生成 .proto 文件
 如果需要扩展则在 ID 字段添加如下 内容

 protobuf:ext:json:[
      位置（item:表格上，lists:表格列表上）:字段：字段类型
     "item:isFocus:bool",
     "item:focusCount:int32",
     "lists:counts:repeated string"
 ]
 */

```

### 10.2 Proto 文件生成

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

### 10.3 Proto 文件编译

```bash
# 编译所有 proto 文件
protoc -I protos/ protos/*.proto --php_out=runtime/Protobuf/

# 编译指定数据库的 proto 文件
protoc -I protos/Database/ protos/Database/*.proto --php_out=runtime/Protobuf/
```

### 10.4 使用生成的 Protobuf 类

```php
use Protobuf\Database\User\UserProto;
use Protobuf\Database\User\UserListsProto;

class UserController extends AbstractController
{
    public function info(UserProto $request): UserProto
    {
        $user = new UserTable()->where([
            UserTable::ID => $request->getId()
        ])->selectOne();
        
        $proto = new UserProto();
        $proto->setId($user->id);
        $proto->setName($user->name);
        $proto->setStatus($user->status);
        
        return $proto;
    }
    
    public function lists(UserProto $request): UserListsProto
    {
        $users = new UserTable()->selectAll();
        
        $protoList = [];
        foreach ($users as $user) {
            $proto = new UserProto();
            $proto->setId($user->id);
            $proto->setName($user->name);
            $proto->setStatus($user->status);
            $protoList[] = $proto;
        }
        
        $response = new UserListsProto();
        $response->setLists($protoList);
        
        return $response;
    }
}
```



