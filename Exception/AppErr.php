<?php

namespace Swlib\Exception;

/**
 * 框架异常消息 Key 定义
 * 用于国际化翻译，框架异常消息统一使用这些 Key
 */
class AppErr
{
    // ===== 通用 =====
    public const string PARAM_ERROR = 'common.param_error'; // 参数错误
    public const string PARAM_REQUIRED = 'common.param_required'; // 参数不能为空
    public const string PARAM_INVALID = 'common.param_invalid'; // 参数无效
    public const string NOT_FOUND = 'common.not_found'; // 记录不存在
    public const string OPERATION_FAILED = 'common.operation_failed'; // 操作失败

    // ===== 路由 Router =====
    public const string ROUTER_RESPONSE_TYPE_INVALID = 'router.response_type_invalid'; // 返回类型不支持
    public const string ROUTER_PATH_INFO_PARAM_EMPTY = 'router.path_info_param_empty'; // PathInfo参数名不能为空
    public const string ROUTER_PATH_INFO_PARAM_DUPLICATE = 'router.path_info_param_duplicate'; // PathInfo参数重复
    public const string ROUTER_PAGE_NOT_FOUND = 'router.page_not_found'; // 页面不存在
    public const string ROUTER_ACCESS_DENIED = 'router.access_denied'; // 访问被拒绝

    // ===== AOP/验证 =====
    public const string VALIDATION_PARAM_REQUIRED = 'validation.param_required'; // 参数是必需的
    public const string VALIDATION_PARAM_EMPTY = 'validation.param_empty'; // 参数不能为空
    public const string VALIDATION_PARAM_MUST_STRING = 'validation.param_must_string'; // 参数必须是字符串
    public const string VALIDATION_PARAM_MUST_INT = 'validation.param_must_int'; // 参数必须是整数
    public const string VALIDATION_PARAM_MUST_NUMBER = 'validation.param_must_number'; // 参数必须是数字
    public const string VALIDATION_PARAM_MUST_EMAIL = 'validation.param_must_email'; // 参数必须是有效的邮箱地址
    public const string VALIDATION_PARAM_MUST_URL = 'validation.param_must_url'; // 参数必须是有效的URL
    public const string VALIDATION_PARAM_MIN_LENGTH = 'validation.param_min_length'; // 参数长度不能小于
    public const string VALIDATION_PARAM_MIN_VALUE = 'validation.param_min_value'; // 参数不能小于
    public const string VALIDATION_PARAM_MAX_LENGTH = 'validation.param_max_length'; // 参数长度不能大于
    public const string VALIDATION_PARAM_MAX_VALUE = 'validation.param_max_value'; // 参数不能大于
    public const string VALIDATION_PARAM_MUST_ARRAY = 'validation.param_must_array'; // 参数必须是数组
    public const string VALIDATION_PARAM_INVALID_VALUE = 'validation.param_invalid_value'; // 参数必须是以下值之一
    public const string VALIDATION_PARAM_FORMAT_INVALID = 'validation.param_format_invalid'; // 参数格式不正确

    // ===== 数据库 Table =====
    public const string TABLE_ORDER_DIRECTION_INVALID = 'table.order_direction_invalid'; // 无效的排序方向
    public const string TABLE_INSERTALL_NEEDS_ARRAY = 'table.insertall_needs_array'; // insertAll需要二维数组
    public const string TABLE_UPDATE_NEEDS_WHERE = 'table.update_needs_where'; // update必须使用where条件
    public const string TABLE_DELETE_NEEDS_WHERE = 'table.delete_needs_where'; // delete操作必须使用where条件
    public const string TABLE_FIELD_TYPE_ARRAY = 'table.field_type_array'; // 字段是数组不支持直接写入
    public const string TABLE_FIELD_TYPE_OBJECT = 'table.field_type_object'; // 字段是对象不支持直接写入
    public const string TABLE_FIELD_TYPE_RESOURCE = 'table.field_type_resource'; // 字段是资源类型不支持直接写入
    public const string TABLE_TRANSACTION_CROSS_DB = 'table.transaction_cross_db'; // 事务内部不能跨数据库操作
    public const string TABLE_NO_WRITE_DATA = 'table.no_write_data'; // 数据库操作没有写入数据
    public const string TABLE_FIELD_NOT_SET = 'table.field_not_set'; // 字段在写操作中没有被设置
    public const string TABLE_ALIAS_NOT_FOUND = 'table.alias_not_found'; // 在别名定义中没有找到字段
    public const string TABLE_FIELD_NOT_FOUND = 'table.field_not_found'; // 在字段定义中没有找到字段
    public const string TABLE_CONNECT_FAILED = 'table.connect_failed'; // 数据库连接失败
    public const string TABLE_UNSUPPORTED_ACTION = 'table.unsupported_action'; // 不支持的操作
    public const string TABLE_EXECUTE_FAILED = 'table.execute_failed'; // 执行失败
    public const string TABLE_UNSUPPORTED_PARAM_TYPE = 'table.unsupported_param_type'; // 不支持的参数类型

    // ===== 队列 Queue =====
    public const string QUEUE_WRITE_FAILED = 'queue.write_failed'; // 写入消息队列失败

    // ===== 锁 Lock =====
    public const string LOCK_ACQUIRE_FAILED = 'lock.acquire_failed'; // 无法获取锁
    public const string LOCK_KEY_TEMPLATE_INVALID = 'lock.key_template_invalid'; // 锁keyTemplate未匹配到方法参数

    // ===== 管理后台 Admin =====
    public const string ADMIN_PLEASE_LOGIN = 'admin.please_login'; // 请登录
    public const string ADMIN_USERNAME_PASSWORD_ERROR = 'admin.username_password_error'; // 用户名或者密码错误
    public const string ADMIN_PASSWORD_INCONSISTENT = 'admin.password_inconsistent'; // 两次密码不一致
    public const string ADMIN_USERNAME_EXISTS = 'admin.username_exists'; // 用户名已存在
    public const string ADMIN_METHOD_NOT_EXISTS = 'admin.method_not_exists'; // 方法不存在
    public const string ADMIN_SELECT_DELETE_REQUIRED = 'admin.select_delete_required'; // 请选择要删除的数据
    public const string ADMIN_QUERY_NOT_CONFIGURED = 'admin.query_not_configured'; // 查询未配置
    public const string ADMIN_FIELD_NOT_CONFIGURED = 'admin.field_not_configured'; // 字段未配置
    public const string ADMIN_ACTION_DISABLED = 'admin.action_disabled'; // 禁止访问
    public const string ADMIN_NO_PERMISSION = 'admin.no_permission'; // 无权限

    // ===== 文件上传 File/Upload =====
    public const string FILE_TYPE_NOT_SUPPORTED = 'file.type_not_supported'; // 不支持的文件类型
    public const string FILE_SPECIFY_REQUIRED = 'file.specify_required'; // 请指定需要上传的文件
    public const string FILE_SAVE_FAILED = 'file.save_failed'; // 保存文件失败
    public const string FILE_CHUNK_UPLOAD_FAILED = 'file.chunk_upload_failed'; // 分片上传失败
    public const string FILE_CHUNK_SAVE_FAILED = 'file.chunk_save_failed'; // 保存分片失败
    public const string FILE_MIME_TYPE_REQUIRED = 'file.mime_type_required'; // 首次上传分片时必须提供MIME类型
    public const string FILE_UPLOAD_INFO_NOT_FOUND = 'file.upload_info_not_found'; // 找不到上传信息
    public const string FILE_MIME_TYPE_LOST = 'file.mime_type_lost'; // 文件MIME类型信息丢失
    public const string FILE_CREATE_FAILED = 'file.create_failed'; // 无法创建最终文件
    public const string FILE_CHUNK_NOT_EXIST = 'file.chunk_not_exist'; // 分片不存在
    public const string FILE_CHUNK_READ_FAILED = 'file.chunk_read_failed'; // 无法读取分片
    public const string FILE_INTEGRITY_FAILED = 'file.integrity_failed'; // 文件完整性验证失败
    public const string FILE_INFO_NOT_FOUND = 'file.info_not_found'; // 找不到文件信息
    public const string FILE_MISSING_MIME_TYPE = 'file.missing_mime_type'; // 文件信息中缺少MIME类型
    public const string FILE_NOT_EXIST = 'file.not_exist'; // 文件不存在或已删除

    // ===== 任务调度 TaskProcess =====
    public const string TASK_CALLABLE_FORMAT_INVALID = 'task.callable_format_invalid'; // callable参数必须是数组格式
    public const string TASK_SERVER_NOT_INITIALIZED = 'task.server_not_initialized'; // Swoole Server未初始化

    // ===== 模板 Twig =====
    public const string TEMPLATE_DIR_NOT_CONFIGURED = 'template.dir_not_configured'; // 未配置模板目录

    // ===== 缓存 Cache =====
    public const string CACHE_DIR_CREATE_FAILED = 'cache.dir_create_failed'; // 创建日志目录失败

    // ===== HTTP 请求 =====
    public const string HTTP_REQUEST_FAILED = 'http.request_failed'; // HTTP请求失败
    public const string HTTP_RESPONSE_MUST_ARRAY = 'http.response_must_array'; // 接口返回值必须是数组

    // ===== 配置验证 =====
    public const string CONFIG_CONNECT_FAILED = 'config.connect_failed'; // 连接失败
    public const string CONFIG_AUTH_FAILED = 'config.auth_failed'; // 认证失败
    public const string CONFIG_PING_FAILED = 'config.ping_failed'; // PING测试失败

    // ===== 开发工具 DevTool =====
    public const string DEV_ONLY_DEV_ENV = 'dev.only_dev_env'; // 仅在开发环境下可用
    public const string DEV_FILE_PATH_EMPTY = 'dev.file_path_empty'; // 文件路径不能为空
    public const string DEV_FILE_NOT_ACCESSIBLE = 'dev.file_not_accessible'; // 文件不存在或不允许访问
    public const string DEV_SOURCE_DIR_NOT_EXIST = 'dev.source_dir_not_exist'; // 指定的源目录不存在
    public const string DEV_SOURCE_DIR_OUT_OF_RANGE = 'dev.source_dir_out_of_range'; // 指定的源目录超出允许范围
    public const string DEV_SYNC_SOURCE_NOT_FOUND = 'dev.sync_source_not_found'; // 找不到代码同步源目录
    public const string DEV_TABLE_NO_ID_FIELD = 'dev.table_no_id_field'; // 表中没有id字段
    public const string DEV_POSITION_INVALID = 'dev.position_invalid'; // 位置只能是item或lists

    // ===== 语言 Language =====
    public const string LANG_HEADER_EMPTY = 'lang.header_empty'; // 请求头语言为空

    // ===== DTO =====
    public const string DTO_FIELD_NOT_INCLUDED = 'dto.field_not_included'; // 字段不包含在查询字段中
    public const string DTO_PRIMARY_VALUE_FAILED = 'dto.primary_value_failed'; // 获取主键值失败

    // ===== 事件 Event =====
    public const string EVENT_LISTENER_FORMAT_ERROR = 'event.listener_format_error'; // listener参数错误需要数组格式
    public const string EVENT_LISTENER_CLASS_NOT_EXIST = 'event.listener_class_not_exist'; // listener类不存在
    public const string EVENT_LISTENER_METHOD_NOT_EXIST = 'event.listener_method_not_exist'; // listener方法不存在

    // ===== 定时任务 Crontab =====
    public const string CRON_EXPRESSION_INVALID = 'cron.expression_invalid'; // 无效的cron表达式

    // ===== SelectField =====
    public const string SELECT_CLASS_NOT_EXIST = 'select.class_not_exist'; // 类不存在

    // ===== File/Directory =====
    public const string DIR_SPECIFY_REQUIRED = 'dir.specify_required'; // 请指定需要复制的目录

    // ===== SSL 证书 =====
    public const string SSL_GENERATE_FAILED = 'ssl.generate_failed'; // 生成SSL证书失败

    // ===== AOP JoinPoint =====
    public const string JOINPOINT_INDEX_NOT_EXIST = 'joinpoint.index_not_exist'; // 参数索引不存在

    // ===== 数据库操作上下文 =====
    public const string DB_CONTEXT_NO_WRITE = 'db.context_no_write'; // 当前数据库操作没有写入数据
    public const string DB_CONTEXT_FIELD_NEW_VALUE = 'db.context_field_new_value'; // 无法获取字段的新值
}
