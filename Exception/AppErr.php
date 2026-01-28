<?php

namespace Swlib\Exception;

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

    public const string PARAM = 'param';
    public const string REQUIRED = 'required';
    public const string UNAVAILABLE = 'unavailable';
    public const string TIMEOUT = 'timeout';
    public const string ERROR = 'error';
    public const string FAILED = 'failed';
    public const string CREATE = 'create';
    public const string UPDATE = 'update';
    public const string DELETE = 'delete';
    public const string EMPTY = 'empty';
    public const string NOT_SUPPORTED = 'not.supported';
    public const string NOT_INIT = 'not.init';
    public const string INVALID = 'invalid';
    public const string ALREADY_EXISTS = 'already.exists';

    // 参数错误语义
    public const string PARAM_ERROR = self::PARAM . '.' . self::ERROR;
    public const string PARAM_EMPTY = 'param.empty';
    public const string PARAM_REQUIRED = 'param.required';
    public const string PARAM_INVALID = 'param.invalid';
    public const string PARAM_MUST_STRING = 'param.must_string';
    public const string PARAM_MUST_INT = 'param.must_int';
    public const string PARAM_MUST_NUMBER = 'param.must_number';
    public const string PARAM_MUST_EMAIL = 'param.must_email';
    public const string PARAM_MUST_URL = 'param.must_url';
    public const string PARAM_MUST_ARRAY = 'param.must_array';
    public const string PARAM_MIN_LENGTH = 'param.min_length';
    public const string PARAM_MAX_LENGTH = 'param.max_length';
    public const string PARAM_MIN_VALUE = 'param.min_value';
    public const string PARAM_MAX_VALUE = 'param.max_value';
    public const string PARAM_MIN_COUNT = 'param.min_count';
    public const string PARAM_MAX_COUNT = 'param.max_count';

    // 记录/资源不存在语义
    public const string NOT_FOUND = 'not_found';

    // 操作失败语义
    public const string OPERATION_FAILED = 'operation.failed';
    public const string EXECUTE_FAILED = 'execute.failed';

    // 权限/认证语义
    public const string ACCESS_DENIED = 'access.denied';
    public const string NOT_AUTHENTICATED = 'not.authenticated';

    // 格式/类型错误语义
    public const string FORMAT_INVALID = 'format.invalid';
    public const string TYPE_INVALID = 'type.invalid';
    public const string VALUE_INVALID = 'value.invalid';

    // 配置语义
    public const string NOT_CONFIGURED = 'not.configured';
    public const string CONFIG_ERROR = 'config.error';

    // 连接/网络语义
    public const string CONNECT_FAILED = 'connect.failed';
    public const string REQUEST_FAILED = 'request.failed';

    // 文件基础语义
    public const string FILE_NOT_FOUND = 'file.not_found';
    public const string FILE_TYPE_NOT_SUPPORTED = 'file.type_not_supported';
    public const string FILE_SPECIFY_REQUIRED = 'file.specify_required';

    // 数据库基础语义
    public const string DB_OPERATION_FAILED = 'db.operation_failed';
    public const string DB_TRANSACTION_ERROR = 'db.transaction_error';
    public const string DB_CONNECT_FAILED = 'db.connect_failed';
    public const string DB_CONNECT_ERROR = 'db.connect_error';
    public const string DB_WHERE_REQUIRED = 'db.where_required';
    public const string DB_FIELD_NOT_FOUND = 'db.field_not_found';

    // 其他基础语义
    public const string LOCK_FAILED = 'lock.failed';
    public const string DIR_REQUIRED = 'dir.required';
    public const string CLASS_NOT_EXIST = 'class.not_exist';
    public const string METHOD_NOT_EXIST = 'method.not_exist';
    public const string SERVER_NOT_INITIALIZED = 'server.not_initialized';

    // ============================================================
    // 验证器模块 (validation.*)
    // ============================================================

    /** 参数索引不能为空 */
    public const string VALIDATION_PARAM_INDEX_REQUIRED = 'validation.param_index_required';

    /** 参数必须是字符串 */
    public const string VALIDATION_PARAM_MUST_STRING = 'validation.param_must_string';

    /** 参数必须是整数 */
    public const string VALIDATION_PARAM_MUST_INT = 'validation.param_must_int';

    /** 参数必须是数字 */
    public const string VALIDATION_PARAM_MUST_NUMBER = 'validation.param_must_number';

    /** 参数必须是邮箱 */
    public const string VALIDATION_PARAM_MUST_EMAIL = 'validation.param_must_email';

    /** 参数必须是URL */
    public const string VALIDATION_PARAM_MUST_URL = 'validation.param_must_url';

    /** 参数必须是数组 */
    public const string VALIDATION_PARAM_MUST_ARRAY = 'validation.param_must_array';

    /** 参数最小长度错误 */
    public const string VALIDATION_PARAM_MIN_LENGTH = 'validation.param_min_length';

    /** 参数最小值错误 */
    public const string VALIDATION_PARAM_MIN_VALUE = 'validation.param_min_value';

    /** 参数最小元素数错误 */
    public const string VALIDATION_PARAM_MIN_COUNT = 'validation.param_min_count';

    /** 参数最大长度错误 */
    public const string VALIDATION_PARAM_MAX_LENGTH = 'validation.param_max_length';

    /** 参数最大值错误 */
    public const string VALIDATION_PARAM_MAX_VALUE = 'validation.param_max_value';

    /** 参数最大元素数错误 */
    public const string VALIDATION_PARAM_MAX_COUNT = 'validation.param_max_count';

    /** 参数值不在允许列表 */
    public const string VALIDATION_PARAM_VALUE_NOT_ALLOWED = 'validation.param_value_not_allowed';

    /** 参数格式无效 */
    public const string VALIDATION_PARAM_FORMAT_INVALID = 'validation.param_format_invalid';

    // ============================================================
    // 数据库模块 (db.*)
    // ============================================================

    /** 数据库连接错误(含消息) */
    public const string DB_CONNECT_ERROR_WITH_MSG = 'db.connect_error_with_msg';

    /** 事务跨数据库操作(含数据库名) */
    public const string DB_TRANSACTION_CROSS_DB_WITH_NAMES = 'db.transaction_cross_db_with_names';

    /** 事务跨数据库操作 */
    public const string DB_TRANSACTION_CROSS_DB = 'db.transaction_cross_db';

    /** 不支持的数据库操作(含操作名) */
    public const string DB_UNSUPPORTED_ACTION_WITH_NAME = 'db.unsupported_action_with_name';

    /** 数据库执行失败(含消息) */
    public const string DB_EXECUTE_FAILED_WITH_MSG = 'db.execute_failed_with_msg';

    /** 不支持的参数类型(含类型和值) */
    public const string DB_UNSUPPORTED_PARAM_TYPE_WITH_VALUE = 'db.unsupported_param_type_with_value';

    /** 别名字段不存在(含字段名) */
    public const string DB_FIELD_NOT_FOUND_IN_ALIAS_WITH_NAME = 'db.field_not_found_in_alias_with_name';

    /** 定义字段不存在(含字段名) */
    public const string DB_FIELD_NOT_FOUND_IN_DEFINITION_WITH_NAME = 'db.field_not_found_in_definition_with_name';

    /** 上下文无写入数据 */
    public const string DB_CONTEXT_NO_WRITE = 'db.context_no_write';

    /** 上下文无写入数据(含提示) */
    public const string DB_CONTEXT_NO_WRITE_WITH_HINT = 'db.context_no_write_with_hint';

    /** 上下文字段无新值(含字段名) */
    public const string DB_CONTEXT_FIELD_NO_NEW_VALUE_WITH_NAME = 'db.context_field_no_new_value_with_name';

    /** 上下文字段无新值 */
    public const string DB_CONTEXT_FIELD_NEW_VALUE = 'db.context_field_new_value';

    /** 无效的事务隔离级别 */
    public const string DB_INVALID_ISOLATION_LEVEL = 'db.invalid_isolation_level';

    /** 查询结果无效 */
    public const string DB_QUERY_RESULT_INVALID = 'db.query_result_invalid';

    /** 查询行必须是数组 */
    public const string DB_QUERY_ROW_MUST_BE_ARRAY = 'db.query_row_must_be_array';

    /** orderType类型无效(含类型名) */
    public const string DB_ORDER_TYPE_INVALID_WITH_NAME = 'db.order_type_invalid_with_name';

    /** insertAll数据类型无效 */
    public const string DB_INSERTALL_DATA_TYPE_INVALID = 'db.insertall_data_type_invalid';

    /** 字段类型无效(含字段名) */
    public const string DB_FIELD_TYPE_INVALID_WITH_NAME = 'db.field_type_invalid_with_name';

    /** 连接池获取连接失败 */
    public const string DB_POOL_GET_CONNECTION_FAILED = 'db.pool_get_connection_failed';

    // ============================================================
    // 文件上传模块 (file.*)
    // ============================================================

    /** 不支持的文件类型(含MIME) */
    public const string FILE_TYPE_NOT_SUPPORTED_WITH_MIME = 'file.type_not_supported_with_mime';

    /** 上传Key不存在(含Key) */
    public const string FILE_UPLOAD_KEY_NOT_FOUND_WITH_KEY = 'file.upload_key_not_found_with_key';

    /** 文件保存失败 */
    public const string FILE_SAVE_FAILED = 'file.save_failed';

    /** 分片上传失败 */
    public const string FILE_CHUNK_UPLOAD_FAILED = 'file.chunk_upload_failed';

    /** 分片保存失败 */
    public const string FILE_CHUNK_SAVE_FAILED = 'file.chunk_save_failed';

    /** MIME类型不能为空 */
    public const string FILE_MIME_TYPE_REQUIRED = 'file.mime_type_required';

    /** 上传信息不存在 */
    public const string FILE_UPLOAD_INFO_NOT_FOUND = 'file.upload_info_not_found';

    /** 上传信息不存在(含路径) */
    public const string FILE_UPLOAD_INFO_NOT_FOUND_WITH_PATH = 'file.upload_info_not_found_with_path';

    /** MIME类型丢失 */
    public const string FILE_MIME_TYPE_LOST = 'file.mime_type_lost';

    /** 创建最终文件失败 */
    public const string FILE_CREATE_FAILED = 'file.create_failed';

    /** 分片不存在(含索引) */
    public const string FILE_CHUNK_NOT_EXIST_WITH_INDEX = 'file.chunk_not_exist_with_index';

    /** 分片不存在 */
    public const string FILE_CHUNK_NOT_EXIST = 'file.chunk_not_exist';

    /** 读取分片失败(含索引) */
    public const string FILE_CHUNK_READ_FAILED_WITH_INDEX = 'file.chunk_read_failed_with_index';

    /** 读取分片失败 */
    public const string FILE_CHUNK_READ_FAILED = 'file.chunk_read_failed';

    /** 文件完整性验证失败 */
    public const string FILE_INTEGRITY_FAILED = 'file.integrity_failed';

    /** 文件信息缺少MIME */
    public const string FILE_MISSING_MIME_TYPE = 'file.missing_mime_type';

    // ============================================================
    // 路由模块 (router.*)
    // ============================================================

    /** 响应类型无效(含类型) */
    public const string ROUTER_RESPONSE_TYPE_INVALID_WITH_TYPE = 'router.response_type_invalid_with_type';

    /** 路由Key已存在(含Key名) */
    public const string ROUTER_KEY_ALREADY_EXISTS_WITH_NAME = 'router.key_already_exists_with_name';

    // ============================================================
    // 后台管理模块 (admin.*)
    // ============================================================

    /** 请登录 */
    public const string ADMIN_PLEASE_LOGIN = 'admin.please_login';

    /** 用户名或密码错误 */
    public const string ADMIN_USERNAME_PASSWORD_ERROR = 'admin.username_password_error';

    /** 两次密码不一致 */
    public const string ADMIN_PASSWORD_INCONSISTENT = 'admin.password_inconsistent';

    /** 用户名已存在 */
    public const string ADMIN_USERNAME_EXISTS = 'admin.username_exists';

    /** 后台方法不存在(含方法名) */
    public const string ADMIN_METHOD_NOT_EXIST_WITH_NAME = 'admin.method_not_exist_with_name';

    /** 请选择要删除的数据 */
    public const string ADMIN_SELECT_DELETE_REQUIRED = 'admin.select_delete_required';

    /** 查询未配置 */
    public const string ADMIN_QUERY_NOT_CONFIGURED = 'admin.query_not_configured';

    /** 字段未配置(含字段名) */
    public const string ADMIN_FIELD_NOT_CONFIGURED_WITH_NAME = 'admin.field_not_configured_with_name';

    // ============================================================
    // 事件模块 (event.*)
    // ============================================================

    /** 监听器格式错误需要数组 */
    public const string EVENT_LISTENER_FORMAT_NEED_ARRAY = 'event.listener_format_need_array';

    /** 监听器类不存在(含类名) */
    public const string EVENT_LISTENER_CLASS_NOT_FOUND_WITH_NAME = 'event.listener_class_not_found_with_name';

    /** 监听器方法不存在(含类和方法) */
    public const string EVENT_LISTENER_METHOD_NOT_FOUND_WITH_CLASS = 'event.listener_method_not_found_with_class';

    // ============================================================
    // 开发工具模块 (dev.*)
    // ============================================================

    /** 仅在开发环境下可用 */
    public const string DEV_ONLY_DEV_ENV = 'dev.only_dev_env';

    /** 文件路径不能为空 */
    public const string DEV_FILE_PATH_EMPTY = 'dev.file_path_empty';

    /** 文件不存在或不允许访问 */
    public const string DEV_FILE_NOT_ACCESSIBLE = 'dev.file_not_accessible';

    /** 源目录不存在 */
    public const string DIR_NOT_EXIST = 'dir_not_exist';

    /** 源目录不存在(含目录名) */
    public const string DEV_SOURCE_DIR_NOT_EXIST_WITH_NAME = 'dev.source_dir_not_exist_with_name';

    /** 源目录超出范围(含目录名) */
    public const string DEV_SOURCE_DIR_OUT_OF_RANGE_WITH_NAME = 'dev.source_dir_out_of_range_with_name';

    /** 源目录超出范围 */
    public const string DEV_SOURCE_DIR_OUT_OF_RANGE = 'dev.source_dir_out_of_range';

    /** 找不到代码同步源目录 */
    public const string DEV_SYNC_SOURCE_NOT_FOUND = 'dev.sync_source_not_found';

    /** 表中无ID字段(含表名) */
    public const string DEV_TABLE_NO_ID_FIELD_WITH_NAME = 'dev.table_no_id_field_with_name';

    /** 位置无效 */
    public const string DEV_POSITION_INVALID = 'dev.position_invalid';

    // ============================================================
    // HTTP模块 (http.*)
    // ============================================================

    /** HTTP请求失败(含消息) */
    public const string HTTP_REQUEST_FAILED_WITH_MSG = 'http.request_failed_with_msg';

    /** 接口返回值类型无效 */
    public const string HTTP_RESPONSE_TYPE_INVALID = 'http.response_type_invalid';

    // ============================================================
    // SSL证书模块 (ssl.*)
    // ============================================================

    /** SSL生成失败(含命令) */
    public const string SSL_GENERATE_FAILED_WITH_COMMAND = 'ssl.generate_failed_with_command';

    /** iOS SSL生成失败(含命令) */
    public const string SSL_IOS_GENERATE_FAILED_WITH_COMMAND = 'ssl.ios_generate_failed_with_command';

    // ============================================================
    // 锁模块 (lock.*)
    // ============================================================

    /** 锁失败(含Key) */
    public const string LOCK_FAILED_WITH_KEY = 'lock.failed_with_key';

    // ============================================================
    // WebSocket模块 (ws.*)
    // ============================================================

    /** WebSocket页面不存在 */
    public const string WS_PAGE_NOT_FOUND = 'ws.page_not_found';

    /** WebSocket访问被拒绝 */
    public const string WS_ACCESS_NOT_ALLOWED = 'ws.access_not_allowed';

    // ============================================================
    // 配置模块 (config.*)
    // ============================================================

    /** 连接失败 */
    public const string CONFIG_CONNECT_FAILED = 'config.connect_failed';

    /** 认证失败 */
    public const string CONFIG_AUTH_FAILED = 'config.auth_failed';

    /** PING测试失败 */
    public const string CONFIG_PING_FAILED = 'config.ping_failed';

    // ============================================================
    // 解析模块 (parse.*)
    // ============================================================

    /** AST编译priority有重复值 */
    public const string PARSE_AST_PRIORITY_DUPLICATE = 'parse.ast_priority_duplicate';

    // ============================================================
    // DTO模块 (dto.*)
    // ============================================================

    /** 字段为空检查(含字段名) */
    public const string DTO_FIELD_IS_NULL_WITH_NAME = 'dto.field_is_null_with_name';

    /** 字段为null检查 */
    public const string DTO_FIELD_IS_NULL_CHECK = 'dto.field_is_null_check';

    /** 获取主键值失败(含消息) */
    public const string DTO_PRIMARY_VALUE_FAILED_WITH_MSG = 'dto.primary_value_failed_with_msg';

    /** update方法必须传入WHERE条件 */
    public const string DTO_UPDATE_NEEDS_WHERE = 'dto.update_needs_where';

    /** delete方法必须传入WHERE条件 */
    public const string DTO_DELETE_NEEDS_WHERE = 'dto.delete_needs_where';

    // ============================================================
    // 其他模块
    // ============================================================

    /** 任务调度callable参数错误 */
    public const string TASK_CALLABLE_PARAM_ERROR = 'task.callable_param_error';

    /** 任务调度执行失败(含消息) */
    public const string TASK_EXECUTE_FAILED_WITH_MSG = 'task.execute_failed_with_msg';

    /** 参数模板无效(含模板) */
    public const string LOCK_PARAM_TEMPLATE_INVALID_WITH_NAME = 'lock.param_template_invalid_with_name';

    /** SERVER为空 */
    public const string RESPONSE_SERVER_EMPTY = 'response.server_empty';

    /** 功能不支持(含功能名) */
    public const string FEATURE_NOT_SUPPORTED_WITH_NAME = 'feature.not_supported_with_name';
}
