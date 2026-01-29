## 17. Protobuf 集成

### 17.1 自动生成

框架根据数据库表结构自动生成 Protobuf 文件。

### 17.2 扩展字段定义

在 ID 字段注释中添加扩展字段定义：

```sql
-- 在 ID 字段注释中添加：
-- protobuf:ext:json:[
--   "item:isFocus:bool",
--   "item:focusCount:int32",
--   "lists:counts:repeated string"
-- ]
```
