# Swlib - 基于Swoole的PHP框架

Swlib是一个基于Swoole的PHP框架，提供了一套完整的解决方案来构建高性能的Web应用和API服务。

## 特性

- 基于Swoole构建，提供高性能的异步非阻塞I/O
- 支持WebSocket服务器
- 内置路由解析和控制器
- 数据库连接池管理
- 事件驱动系统
- 内置管理后台
- 自定义进程管理
- 配置文件解析工具

## 安装

### 前提条件

- PHP >= 8.4
- Swoole >= 6.0.0
- mysqli PHP 扩展
- Redis PHP 扩展


### 在现有项目中添加依赖

```bash
composer require wansh/Swlib
```
### 创建启动文件
```php
<?php
// bin/start.php
<?php
declare(strict_types=1);

use Swlib\App;

require_once dirname(__DIR__) . "/Swlib/App.php";


$app = new App();
try {
    // APP_ENV_DEV 开发环境，APP_ENV_PROD 生产环境
    $app->run(APP_ENV_DEV);
} catch (Throwable $e) {
    var_dump($e->getMessage());
    var_dump($e->getTraceAsString());
}

```

## 快速开始

### 启动服务器

```bash
php bin/start.php
```

访问 http://127.0.0.1:9501 即可看到欢迎页面。



## 配置

Swlib框架使用.env文件和配置文件来管理应用设置。

### 环境变量

复制环境变量示例文件并根据需要修改：

```bash
cp .env.example .env
```
