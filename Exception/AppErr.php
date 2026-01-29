<?php

declare(strict_types=1);

namespace Swlib\Exception;

use Swlib\Attribute\I18nAttribute;

/**
 * 框架异常消息 Key 定义
 *
 * 设计原则：
 * 1. 按功能模块分组（validation/db/file/router/http/admin/event/dev/lock等）
 * 2. 使用点分层级结构，便于翻译文件组织
 * 3. 完整语义常量，消除运行时拼接，确保翻译完整性
 * 4. 保留基础语义常量以确保向后兼容
 */
class AppErr
{
    // ============================================================
    // 基础语义常量（保留兼容性）
    // ============================================================

    #[I18nAttribute(zh: '为空', en: 'Empty')]
    public const string EMPTY = 'empty';

    #[I18nAttribute(zh: '不支持', en: 'Not supported')]
    public const string NOT_SUPPORTED = 'not.supported';

    #[I18nAttribute(zh: '未初始化', en: 'Not initialized')]
    public const string NOT_INIT = 'not.init';

    // ============================================================
    // 参数错误语义
    // ============================================================

    #[I18nAttribute(zh: '参数错误', en: 'Parameter error')]
    public const string PARAM_ERROR = 'param.error';

    #[I18nAttribute(zh: '参数为空', en: 'Parameter is empty')]
    public const string PARAM_EMPTY = 'param.empty';

    #[I18nAttribute(zh: '参数必填', en: 'Parameter is required')]
    public const string PARAM_REQUIRED = 'param.required';

    #[I18nAttribute(zh: '参数无效', en: 'Parameter is invalid')]
    public const string PARAM_INVALID = 'param.invalid';

    #[I18nAttribute(zh: '参数必须是字符串', en: 'Parameter must be a string')]
    public const string PARAM_MUST_STRING = 'param.must_string';

    #[I18nAttribute(zh: '参数必须是整数', en: 'Parameter must be an integer')]
    public const string PARAM_MUST_INT = 'param.must_int';

    #[I18nAttribute(zh: '参数必须是数字', en: 'Parameter must be a number')]
    public const string PARAM_MUST_NUMBER = 'param.must_number';

    #[I18nAttribute(zh: '参数必须是邮箱', en: 'Parameter must be an email')]
    public const string PARAM_MUST_EMAIL = 'param.must_email';

    #[I18nAttribute(zh: '参数必须是URL', en: 'Parameter must be a URL')]
    public const string PARAM_MUST_URL = 'param.must_url';

    #[I18nAttribute(zh: '参数必须是数组', en: 'Parameter must be an array')]
    public const string PARAM_MUST_ARRAY = 'param.must_array';

    #[I18nAttribute(zh: '参数最小长度错误', en: 'Parameter minimum length error')]
    public const string PARAM_MIN_LENGTH = 'param.min_length';

    #[I18nAttribute(zh: '参数最大长度错误', en: 'Parameter maximum length error')]
    public const string PARAM_MAX_LENGTH = 'param.max_length';

    #[I18nAttribute(zh: '参数最小值错误', en: 'Parameter minimum value error')]
    public const string PARAM_MIN_VALUE = 'param.min_value';

    #[I18nAttribute(zh: '参数最大值错误', en: 'Parameter maximum value error')]
    public const string PARAM_MAX_VALUE = 'param.max_value';

    #[I18nAttribute(zh: '参数最小数量错误', en: 'Parameter minimum count error')]
    public const string PARAM_MIN_COUNT = 'param.min_count';

    #[I18nAttribute(zh: '参数最大数量错误', en: 'Parameter maximum count error')]
    public const string PARAM_MAX_COUNT = 'param.max_count';

    // ============================================================
    // 通用语义常量
    // ============================================================

    #[I18nAttribute(zh: '不存在', en: 'Not found')]
    public const string NOT_FOUND = 'not_found';

    #[I18nAttribute(zh: '执行失败', en: 'Execution failed')]
    public const string EXECUTE_FAILED = 'execute.failed';

    #[I18nAttribute(zh: '格式无效', en: 'Invalid format')]
    public const string FORMAT_INVALID = 'format.invalid';

    #[I18nAttribute(zh: '值无效', en: 'Invalid value')]
    public const string VALUE_INVALID = 'value.invalid';

    #[I18nAttribute(zh: '加锁失败', en: 'Lock failed')]
    public const string LOCK_FAILED = 'lock.failed';

    // ============================================================
    // 文件语义
    // ============================================================

    #[I18nAttribute(zh: '文件不存在', en: 'File not found')]
    public const string FILE_NOT_FOUND = 'file.not_found';

    #[I18nAttribute(zh: '请指定文件', en: 'File must be specified')]
    public const string FILE_SPECIFY_REQUIRED = 'file.specify_required';

    #[I18nAttribute(zh: '目录不存在', en: 'Directory does not exist')]
    public const string DIR_NOT_EXIST = 'dir_not_exist';

    // ============================================================
    // 数据库模块 (db.*)
    // ============================================================

    #[I18nAttribute(zh: '事务跨数据库操作', en: 'Transaction cross-database operation')]
    public const string DB_TRANSACTION_CROSS_DB = 'db.transaction_cross_db';

    #[I18nAttribute(zh: '不支持的数据库操作', en: 'Unsupported database operation')]
    public const string DB_UNSUPPORTED_ACTION = 'db.unsupported_action';

    #[I18nAttribute(zh: '不支持的数据库操作(含操作名)', en: 'Unsupported database operation (with operation name)')]
    public const string DB_UNSUPPORTED_ACTION_WITH_NAME = 'db.unsupported_action_with_name';

    #[I18nAttribute(zh: '数据库执行失败', en: 'Database execution failed')]
    public const string DB_EXECUTE_FAILED = 'db.execute_failed';

    #[I18nAttribute(zh: '数据库执行失败(含消息)', en: 'Database execution failed (with message)')]
    public const string DB_EXECUTE_FAILED_WITH_MSG = 'db.execute_failed_with_msg';

    #[I18nAttribute(zh: '不支持的参数类型(含类型和值)', en: 'Unsupported parameter type (with type and value)')]
    public const string DB_UNSUPPORTED_PARAM_TYPE = 'db.unsupported_param_type';

    #[I18nAttribute(zh: '别名字段不存在', en: 'Alias field not found')]
    public const string DB_FIELD_NOT_FOUND_IN_ALIAS = 'db.field_not_found_in_alias';

    #[I18nAttribute(zh: '定义字段不存在', en: 'Definition field not found')]
    public const string DB_FIELD_NOT_FOUND_IN_DEFINITION = 'db.field_not_found_in_definition';

    #[I18nAttribute(zh: '上下文无写入数据', en: 'Context has no write data')]
    public const string DB_CONTEXT_NO_WRITE = 'db.context_no_write';

    #[I18nAttribute(zh: '上下文字段无新值', en: 'Context field has no new value')]
    public const string DB_CONTEXT_FIELD_NEW_VALUE = 'db.context_field_new_value';

    #[I18nAttribute(zh: '无效的事务隔离级别', en: 'Invalid transaction isolation level')]
    public const string DB_INVALID_ISOLATION_LEVEL = 'db.invalid_isolation_level';

    #[I18nAttribute(zh: '查询结果无效', en: 'Query result is invalid')]
    public const string DB_QUERY_RESULT_INVALID = 'db.query_result_invalid';

    #[I18nAttribute(zh: '查询行必须是数组', en: 'Query row must be an array')]
    public const string DB_QUERY_ROW_MUST_BE_ARRAY = 'db.query_row_must_be_array';

    #[I18nAttribute(zh: 'orderType类型无效(含类型名)', en: 'OrderType is invalid (with type name)')]
    public const string DB_ORDER_TYPE_INVALID_WITH_NAME = 'db.order_type_invalid_with_name';

    #[I18nAttribute(zh: 'insertAll数据类型无效', en: 'insertAll data type is invalid')]
    public const string DB_INSERTALL_DATA_TYPE_INVALID = 'db.insertall_data_type_invalid';

    #[I18nAttribute(zh: '字段类型无效(含字段名)', en: 'Field type is invalid (with field name)')]
    public const string DB_FIELD_TYPE_INVALID_WITH_NAME = 'db.field_type_invalid_with_name';

    #[I18nAttribute(zh: '连接池获取连接失败', en: 'Connection pool failed to get connection')]
    public const string DB_POOL_GET_CONNECTION_FAILED = 'db.pool_get_connection_failed';

    #[I18nAttribute(zh: '数据库需要WHERE条件', en: 'Database requires WHERE clause')]
    public const string DB_WHERE_REQUIRED = 'db.where_required';

    #[I18nAttribute(zh: '数据库连接错误', en: 'Database connection error')]
    public const string DB_CONNECT_ERROR = 'db.connect_error';

    // ============================================================
    // 文件上传模块 (file.*)
    // ============================================================

    #[I18nAttribute(zh: '不支持的文件类型(含MIME)', en: 'Unsupported file type (with MIME)')]
    public const string FILE_TYPE_NOT_SUPPORTED_WITH_MIME = 'file.type_not_supported_with_mime';

    #[I18nAttribute(zh: '上传Key不存在(含Key)', en: 'Upload key not found (with key)')]
    public const string FILE_UPLOAD_KEY_NOT_FOUND_WITH_KEY = 'file.upload_key_not_found_with_key';

    #[I18nAttribute(zh: '文件保存失败', en: 'File save failed')]
    public const string FILE_SAVE_FAILED = 'file.save_failed';

    #[I18nAttribute(zh: '分片上传失败', en: 'Chunk upload failed')]
    public const string FILE_CHUNK_UPLOAD_FAILED = 'file.chunk_upload_failed';

    #[I18nAttribute(zh: '分片保存失败', en: 'Chunk save failed')]
    public const string FILE_CHUNK_SAVE_FAILED = 'file.chunk_save_failed';

    #[I18nAttribute(zh: 'MIME类型不能为空', en: 'MIME type is required')]
    public const string FILE_MIME_TYPE_REQUIRED = 'file.mime_type_required';

    #[I18nAttribute(zh: '上传信息不存在', en: 'Upload information not found')]
    public const string FILE_UPLOAD_INFO_NOT_FOUND = 'file.upload_info_not_found';

    #[I18nAttribute(zh: 'MIME类型丢失', en: 'MIME type lost')]
    public const string FILE_MIME_TYPE_LOST = 'file.mime_type_lost';

    #[I18nAttribute(zh: '创建最终文件失败', en: 'Failed to create final file')]
    public const string FILE_CREATE_FAILED = 'file.create_failed';

    #[I18nAttribute(zh: '分片不存在', en: 'Chunk does not exist')]
    public const string FILE_CHUNK_NOT_EXIST = 'file.chunk_not_exist';

    #[I18nAttribute(zh: '读取分片失败', en: 'Failed to read chunk')]
    public const string FILE_CHUNK_READ_FAILED = 'file.chunk_read_failed';

    #[I18nAttribute(zh: '文件完整性验证失败', en: 'File integrity verification failed')]
    public const string FILE_INTEGRITY_FAILED = 'file.integrity_failed';

    #[I18nAttribute(zh: '文件信息缺少MIME', en: 'File information missing MIME')]
    public const string FILE_MISSING_MIME_TYPE = 'file.missing_mime_type';

    // ============================================================
    // 路由模块 (router.*)
    // ============================================================

    #[I18nAttribute(zh: '响应类型无效(含类型)', en: 'Response type is invalid (with type)')]
    public const string ROUTER_RESPONSE_TYPE_INVALID_WITH_TYPE = 'router.response_type_invalid_with_type';

    #[I18nAttribute(zh: '路由Key已存在(含Key名)', en: 'Route key already exists (with key name)')]
    public const string ROUTER_KEY_ALREADY_EXISTS_WITH_NAME = 'router.key_already_exists_with_name';

    // ============================================================
    // 后台管理模块 (admin.*)
    // ============================================================

    #[I18nAttribute(zh: '请登录', en: 'Please login')]
    public const string ADMIN_PLEASE_LOGIN = 'admin.please_login';

    #[I18nAttribute(zh: '用户名或密码错误', en: 'Username or password is incorrect')]
    public const string ADMIN_USERNAME_PASSWORD_ERROR = 'admin.username_password_error';

    #[I18nAttribute(zh: '两次密码不一致', en: 'Passwords do not match')]
    public const string ADMIN_PASSWORD_INCONSISTENT = 'admin.password_inconsistent';

    #[I18nAttribute(zh: '用户名已存在', en: 'Username already exists')]
    public const string ADMIN_USERNAME_EXISTS = 'admin.username_exists';

    #[I18nAttribute(zh: '后台方法不存在(含方法名)', en: 'Admin method does not exist (with method name)')]
    public const string ADMIN_METHOD_NOT_EXIST_WITH_NAME = 'admin.method_not_exist_with_name';

    #[I18nAttribute(zh: '请选择要删除的数据', en: 'Please select data to delete')]
    public const string ADMIN_SELECT_DELETE_REQUIRED = 'admin.select_delete_required';

    #[I18nAttribute(zh: '查询未配置', en: 'Query not configured')]
    public const string ADMIN_QUERY_NOT_CONFIGURED = 'admin.query_not_configured';

    #[I18nAttribute(zh: '字段未配置(含字段名)', en: 'Field not configured (with field name)')]
    public const string ADMIN_FIELD_NOT_CONFIGURED_WITH_NAME = 'admin.field_not_configured_with_name';

    // ============================================================
    // 事件模块 (event.*)
    // ============================================================

    #[I18nAttribute(zh: '监听器格式错误需要数组', en: 'Listener format error, array required')]
    public const string EVENT_LISTENER_FORMAT_NEED_ARRAY = 'event.listener_format_need_array';

    #[I18nAttribute(zh: '监听器类不存在(含类名)', en: 'Listener class not found (with class name)')]
    public const string EVENT_LISTENER_CLASS_NOT_FOUND_WITH_NAME = 'event.listener_class_not_found_with_name';

    #[I18nAttribute(zh: '监听器方法不存在(含类和方法)', en: 'Listener method not found (with class and method)')]
    public const string EVENT_LISTENER_METHOD_NOT_FOUND_WITH_CLASS = 'event.listener_method_not_found_with_class';

    // ============================================================
    // 开发工具模块 (dev.*)
    // ============================================================

    #[I18nAttribute(zh: '仅在开发环境下可用', en: 'Only available in development environment')]
    public const string DEV_ONLY_DEV_ENV = 'dev.only_dev_env';

    #[I18nAttribute(zh: '文件路径不能为空', en: 'File path cannot be empty')]
    public const string DEV_FILE_PATH_EMPTY = 'dev.file_path_empty';

    #[I18nAttribute(zh: '文件不存在或不允许访问', en: 'File does not exist or is not accessible')]
    public const string DEV_FILE_NOT_ACCESSIBLE = 'dev.file_not_accessible';

    #[I18nAttribute(zh: '源目录不存在(含目录名)', en: 'Source directory does not exist (with directory name)')]
    public const string DEV_SOURCE_DIR_NOT_EXIST_WITH_NAME = 'dev.source_dir_not_exist_with_name';

    #[I18nAttribute(zh: '源目录超出范围(含目录名)', en: 'Source directory out of range (with directory name)')]
    public const string DEV_SOURCE_DIR_OUT_OF_RANGE_WITH_NAME = 'dev.source_dir_out_of_range_with_name';

    #[I18nAttribute(zh: '找不到代码同步源目录', en: 'Code sync source directory not found')]
    public const string DEV_SYNC_SOURCE_NOT_FOUND = 'dev.sync_source_not_found';

    #[I18nAttribute(zh: '表中无ID字段(含表名)', en: 'Table has no ID field (with table name)')]
    public const string DEV_TABLE_NO_ID_FIELD_WITH_NAME = 'dev.table_no_id_field_with_name';

    #[I18nAttribute(zh: '位置无效', en: 'Invalid position')]
    public const string DEV_POSITION_INVALID = 'dev.position_invalid';

    // ============================================================
    // HTTP模块 (http.*)
    // ============================================================

    #[I18nAttribute(zh: 'HTTP请求失败(含消息)', en: 'HTTP request failed (with message)')]
    public const string HTTP_REQUEST_FAILED_WITH_MSG = 'http.request_failed_with_msg';

    #[I18nAttribute(zh: '接口返回值类型无效', en: 'API response type is invalid')]
    public const string HTTP_RESPONSE_TYPE_INVALID = 'http.response_type_invalid';

    // ============================================================
    // SSL证书模块 (ssl.*)
    // ============================================================

    #[I18nAttribute(zh: 'SSL生成失败(含命令)', en: 'SSL generation failed (with command)')]
    public const string SSL_GENERATE_FAILED_WITH_COMMAND = 'ssl.generate_failed_with_command';

    #[I18nAttribute(zh: 'iOS SSL生成失败(含命令)', en: 'iOS SSL generation failed (with command)')]
    public const string SSL_IOS_GENERATE_FAILED_WITH_COMMAND = 'ssl.ios_generate_failed_with_command';

    // ============================================================
    // WebSocket模块 (ws.*)
    // ============================================================

    #[I18nAttribute(zh: 'WebSocket页面不存在', en: 'WebSocket page not found')]
    public const string WS_PAGE_NOT_FOUND = 'ws.page_not_found';

    #[I18nAttribute(zh: 'WebSocket访问被拒绝', en: 'WebSocket access denied')]
    public const string WS_ACCESS_NOT_ALLOWED = 'ws.access_not_allowed';

    // ============================================================
    // 配置模块 (config.*)
    // ============================================================

    #[I18nAttribute(zh: '连接失败', en: 'Connection failed')]
    public const string CONFIG_CONNECT_FAILED = 'config.connect_failed';

    #[I18nAttribute(zh: '认证失败', en: 'Authentication failed')]
    public const string CONFIG_AUTH_FAILED = 'config.auth_failed';

    #[I18nAttribute(zh: 'PING测试失败', en: 'PING test failed')]
    public const string CONFIG_PING_FAILED = 'config.ping_failed';

    // ============================================================
    // 解析模块 (parse.*)
    // ============================================================

    #[I18nAttribute(zh: 'AST编译priority有重复值', en: 'AST compilation priority has duplicate values')]
    public const string PARSE_AST_PRIORITY_DUPLICATE = 'parse.ast_priority_duplicate';

    // ============================================================
    // DTO模块 (dto.*)
    // ============================================================

    #[I18nAttribute(zh: '字段为null检查', en: 'Field is null check')]
    public const string DTO_FIELD_IS_NULL_CHECK = 'dto.field_is_null_check';

    #[I18nAttribute(zh: '获取主键值失败(含消息)', en: 'Failed to get primary key value (with message)')]
    public const string DTO_PRIMARY_VALUE_FAILED_WITH_MSG = 'dto.primary_value_failed_with_msg';

    #[I18nAttribute(zh: 'update方法必须传入WHERE条件', en: 'update method requires WHERE clause')]
    public const string DTO_UPDATE_NEEDS_WHERE = 'dto.update_needs_where';

    #[I18nAttribute(zh: 'delete方法必须传入WHERE条件', en: 'delete method requires WHERE clause')]
    public const string DTO_DELETE_NEEDS_WHERE = 'dto.delete_needs_where';

    // ============================================================
    // 日志模块 (log.*)
    // ============================================================

    #[I18nAttribute(zh: '创建目录失败(含目录)', en: 'Failed to create directory (with directory)')]
    public const string LOG_CREATE_DIR_FAILED_WITH_DIR = 'log.create_dir_failed_with_dir';

    // ============================================================
    // 缓存模块 (cache.*)
    // ============================================================

    #[I18nAttribute(zh: '散列值长度不足', en: 'Hash value length is insufficient')]
    public const string CACHE_HASH_LENGTH_INSUFFICIENT = 'cache.hash_length_insufficient';

    // ============================================================
    // 路由路径模块 (router_path.*)
    // ============================================================

    #[I18nAttribute(zh: '路由路径解析失败', en: 'Route path parsing failed')]
    public const string ROUTER_PATH_PARSE_FAILED = 'router_path.parse_failed';

    // ============================================================
    // 表单字段模块 (form_field.*)
    // ============================================================

    #[I18nAttribute(zh: '开始日期格式无效', en: 'Start date format is invalid')]
    public const string FORM_FIELD_START_DATE_FORMAT_INVALID = 'form_field.start_date_format_invalid';

    #[I18nAttribute(zh: '结束日期格式无效', en: 'End date format is invalid')]
    public const string FORM_FIELD_END_DATE_FORMAT_INVALID = 'form_field.end_date_format_invalid';

    #[I18nAttribute(zh: '日期格式无效', en: 'Date format is invalid')]
    public const string FORM_FIELD_DATE_FORMAT_INVALID = 'form_field.date_format_invalid';

    // ============================================================
    // 后台权限模块 (admin_permission.*)
    // ============================================================

    #[I18nAttribute(zh: '无权限', en: 'Access denied')]
    public const string ADMIN_PERMISSION_DENIED = 'admin.permission_denied';

    #[I18nAttribute(zh: '禁止访问', en: 'Access forbidden')]
    public const string ADMIN_ACCESS_FORBIDDEN = 'admin.access_forbidden';

    // ============================================================
    // 表单模块 (form.*)
    // ============================================================

    #[I18nAttribute(zh: '类不存在(含类名)', en: 'Class does not exist (with class name)')]
    public const string FORM_CLASS_NOT_EXIST_WITH_NAME = 'form.class_not_exist_with_name';

    // ============================================================
    // 解析SQL模块 (parse_sql.*)
    // ============================================================

    #[I18nAttribute(zh: 'SQL文件不存在(含路径)', en: 'SQL file not found (with path)')]
    public const string PARSE_SQL_FILE_NOT_FOUND_WITH_PATH = 'parse_sql.file_not_found_with_path';

    // ============================================================
    // 功能支持模块 (feature.*)
    // ============================================================

    #[I18nAttribute(zh: '功能不支持(含功能名)', en: 'Feature not supported (with feature name)')]
    public const string FEATURE_NOT_SUPPORTED_WITH_NAME = 'feature.not_supported_with_name';
}
