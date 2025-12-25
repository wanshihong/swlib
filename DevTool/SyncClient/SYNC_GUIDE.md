# TypeScript 代码同步说明文档

## 📋 概述

本文档说明了 TypeScript 代码同步系统的工作原理、使用方法和 API 接口。该系统用于将后端生成的 TypeScript 代码（如 API 定义、Proto 文件等）同步到前端项目中。

---

## 🏗️ 系统架构

### 核心组件

| 组件 | 文件 | 说明 |
|------|------|------|
| **同步客户端** | `sync-client.js` | Node.js 客户端，负责从服务器拉取文件 |
| **批量同步脚本** | `sync-batch.js` | 批量执行多个同步任务的脚本 |
| **后端 API** | `Swlib/DevTool/Ctrls/SyncApi.php` | PHP 后端控制器，提供同步接口 |

### 工作流程

```
sync-batch.js (批量同步)
    ↓
sync-client.js (单个同步)
    ↓
HTTP GET 请求
    ↓
SyncApi.php (后端 API)
    ↓
扫描源目录 → 返回文件列表和内容
    ↓
sync-client.js 写入本地文件
```

---

## 🚀 使用方法

### 1. 单个同步 (sync-client.js)

#### 基本用法

```bash
node sync-client.js [服务器地址] [输出目录] [选项]
```

#### 示例

```bash
# 同步所有文件到 ./src/generated
node sync-client.js http://localhost:9501 ./src/generated

# 只同步 apis 目录
node sync-client.js http://localhost:9501 ./network/api/ --source-dir=apis

# 同步 proto 目录并扁平化输出
node sync-client.js http://localhost:9501 ./proto/protos/ --source-dir=proto --flatten

# 监听模式（自动检测变化并同步）
node sync-client.js http://localhost:9501 ./src/generated --watch
```

#### 选项说明

| 选项 | 说明 | 示例 |
|------|------|------|
| `--source-dir=<目录>` | 指定要同步的源目录（相对于服务器源目录） | `--source-dir=apis` |
| `--flatten` | 将所有文件输出到同一目录（扁平化） | `--flatten` |
| `--watch, -w` | 启用监听模式，自动检测文件变化 | `--watch` |

### 2. 批量同步 (sync-batch.js)

#### 基本用法

```bash
node sync-batch.js
```

#### 功能

- 自动从 `Config.ts` 读取服务器地址
- 顺序执行多个同步任务（APIs → Proto）
- 同步完成后自动执行构建命令

#### 配置

在 `sync-batch.js` 中修改 `syncConfigs` 数组：

```javascript
this.syncConfigs = [
    {
        name: 'APIs同步',
        serverUrl: serverHost,
        outputDir: './network/api/',
        sourceDir: 'apis/apps/live',
        flatten: false,
        color: '\x1b[36m'
    },
    {
        name: 'Proto同步',
        serverUrl: serverHost,
        outputDir: './proto/protos/',
        sourceDir: 'proto',
        flatten: true,
        color: '\x1b[33m'
    }
];
```

---

## 📡 后端 API 接口

### 基础信息

- **基础 URL**: `http://localhost:9501/dev-tool/sync-api`
- **环境**: 仅在开发环境下可用
- **响应格式**: JSON

### API 端点

#### 1. 获取服务状态

```
GET /dev-tool/sync-api/status
```

**响应示例**:
```json
{
    "errno": 0,
    "msg": "success",
    "data": {
        "status": "running",
        "source_dir": "/path/to/source",
        "timestamp": 1234567890,
        "php_version": "8.1.0",
        "swoole_version": "4.8.0",
        "allowed_extensions": [".ts", ".js", ".json", ".md", ".proto"]
    }
}
```

#### 2. 获取文件列表

```
GET /dev-tool/sync-api/files
```

**响应示例**:
```json
{
    "errno": 0,
    "msg": "success",
    "data": {
        "files": ["apis/user.ts", "apis/post.ts", "proto/message.proto"]
    }
}
```

#### 3. 获取指定文件内容

```
GET /dev-tool/sync-api/file?path={path}
```

**参数**:
- `path`: 文件路径（必需）

**响应示例**:
```json
{
    "errno": 0,
    "msg": "success",
    "data": {
        "path": "apis/user.ts",
        "content": "export interface User { ... }",
        "size": 1024,
        "modified": 1234567890
    }
}
```

#### 4. 同步所有文件（核心接口）

```
GET /dev-tool/sync-api/run
```

**参数**:
- `source_dir`: 指定要同步的源目录（可选）
- `flatten`: 是否扁平化输出，1=是，0=否（可选）

**响应示例**:
```json
{
    "errno": 0,
    "msg": "success",
    "data": {
        "success": true,
        "files": [
            {
                "path": "apis/user.ts",
                "content": "export interface User { ... }",
                "size": 1024,
                "modified": 1234567890
            }
        ],
        "count": 1,
        "timestamp": 1234567890,
        "source_dir": "apis",
        "flatten": false,
        "scan_dir": "/path/to/source/apis"
    }
}
```

---

## 🔧 配置说明

### Config.ts 配置

客户端会自动从 `Config.ts` 读取服务器地址：

```typescript
public static HOST = 'http://localhost:9501';
```

### 源目录配置

后端默认查找以下路径（优先级从高到低）：
- `ROOT_DIR/runtime/codes/ts`
- `ROOT_DIR/runtime/Codes/ts`
- `ROOT_DIR/runtime/codes/typescript`
- `ROOT_DIR/runtime/typescript`

### 允许的文件类型

- `.ts` - TypeScript 文件
- `.js` - JavaScript 文件
- `.json` - JSON 文件
- `.md` - Markdown 文件
- `.proto` - Protocol Buffer 文件

---

## 📊 同步流程详解

### sync-client.js 执行流程

1. **初始化**: 读取命令行参数和 Config.ts 配置
2. **检查服务器**: 调用 `/status` 接口验证服务器可用性
3. **获取文件**: 调用 `/run` 接口获取文件列表和内容
4. **创建目录**: 确保输出目录存在
5. **写入文件**: 逐个写入文件到本地
6. **设置时间戳**: 保持文件修改时间与服务器一致

### sync-batch.js 执行流程

1. **读取配置**: 从 Config.ts 读取服务器地址
2. **顺序执行**: 依次执行每个同步任务
3. **执行构建**: 同步完成后运行构建命令
   - `npm run build-proto:pbjs`
   - `npm run build-proto:pbts`

---

## 🐛 故障排查

### 常见问题

| 问题 | 原因 | 解决方案 |
|------|------|--------|
| 无法连接到服务器 | 服务器未启动或地址错误 | 检查 Config.ts 中的 HOST 配置 |
| 找不到源目录 | 后端源目录配置错误 | 检查 runtime 目录是否存在 |
| 文件权限错误 | 输出目录权限不足 | 检查输出目录的写入权限 |
| 同步失败 | 非开发环境 | 确保在开发环境下运行 |

### 调试技巧

```bash
# 显示帮助信息
node sync-batch.js --help

# 查看详细输出
node sync-client.js http://localhost:9501 ./output --source-dir=apis

# 监听模式调试
node sync-client.js http://localhost:9501 ./output --watch
```

---

## 📝 注意事项

1. **开发环境限制**: 同步服务仅在开发环境下可用
2. **安全检查**: 后端会验证目录是否在允许范围内
3. **文件覆盖**: 同步会覆盖本地同名文件
4. **时间戳保留**: 文件修改时间会与服务器保持一致
5. **扁平化输出**: 启用后所有文件输出到同一目录，可能导致文件名冲突

---

## 🔗 相关文件

- `sync-client.js` - 同步客户端实现
- `sync-batch.js` - 批量同步脚本
- `Swlib/DevTool/Ctrls/SyncApi.php` - 后端 API 实现
- `Config.ts` - 客户端配置文件

---

**最后更新**: 2025-12-12

