# 创建路由表格
CREATE TABLE IF NOT EXISTS `router`
(
    `id`        int unsigned NOT NULL AUTO_INCREMENT,
    `uri`       varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin  DEFAULT NULL COMMENT '路由',
    `last_time` int unsigned                                            DEFAULT '0' COMMENT '最后访问时间',
    `num`       int unsigned                                            DEFAULT '0' COMMENT '访问次数',
    `name`      varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin  DEFAULT NULL COMMENT '页面名称',
    `info`      varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin  DEFAULT NULL COMMENT '页面简介',
    `keyword`   json                                                    DEFAULT NULL COMMENT '页面关键字',
    `desc`      varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '页面详细功能介绍',
    PRIMARY KEY (`id`)
) ENGINE = InnoDB
  AUTO_INCREMENT = 1
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_bin COMMENT ='路由访问历史记录';

# 创建路由表格
CREATE TABLE IF NOT EXISTS `router_his`
(
    `id`        int unsigned NOT NULL AUTO_INCREMENT,
    `router_id` int unsigned                                           DEFAULT NULL,
    `uri`       varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '路由',
    `time`      int unsigned                                           DEFAULT '0' COMMENT '访问时间',
    `ip`        varchar(40) COLLATE utf8mb4_bin                        DEFAULT NULL COMMENT 'ip，ipv6最长39',
    PRIMARY KEY (`id`)
) ENGINE = InnoDB
  AUTO_INCREMENT = 1
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_bin COMMENT ='路由访问历史记录';



# 创建翻译表格
-- 语言表
CREATE TABLE IF NOT EXISTS `translation_languages`
(
    `id`         int(11)     NOT NULL AUTO_INCREMENT,
    `code`       varchar(10) NOT NULL COMMENT '语言代码，如en, zh, es等',
    `name`       varchar(50) NOT NULL COMMENT '语言名称',
    `is_active`  tinyint(1)  NOT NULL DEFAULT '1' COMMENT '是否激活',
    `app_id`     int(11)     NOT NULL COMMENT '所属应用ID',
    `created_at` timestamp   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_app_code` (`app_id`, `code`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4 COMMENT ='语言表';

-- 翻译键表
CREATE TABLE `translation_keys`
(
    `id`         int(11)      NOT NULL AUTO_INCREMENT,
    `group`      varchar(50)  NOT NULL COMMENT '翻译分组',
    `key`        varchar(100) NOT NULL COMMENT '翻译键名',
    `notes`      text COMMENT '翻译说明',
    `app_id`     int(11)      NOT NULL COMMENT '所属应用ID',
    `created_at` timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_app_group_key` (`app_id`, `group`, `key`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4 COMMENT ='翻译键表';

-- 翻译内容表
CREATE TABLE IF NOT EXISTS `translation_texts`
(
    `id`          int(11)   NOT NULL AUTO_INCREMENT,
    `key_id`      int(11)   NOT NULL COMMENT '关联translation_keys.id',
    `language_id` int(11)   NOT NULL COMMENT '关联languages.id',
    `text`        text      NOT NULL COMMENT '翻译内容',
    `app_id`      int(11)   NOT NULL COMMENT '所属应用ID',
    `created_at`  timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_key_language` (`key_id`, `language_id`),
    KEY `idx_language` (`language_id`),
    KEY `idx_app` (`app_id`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4 COMMENT ='翻译内容表';


# 创建消息队列表
CREATE TABLE `message_queue` (
     `id` int unsigned NOT NULL AUTO_INCREMENT,
     `class_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '执行的 类名称',
     `method` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '执行的  类方法',
     `run_num` int unsigned DEFAULT '0' COMMENT '执行了次数',
     `next_run_time` int unsigned DEFAULT '0' COMMENT '下次执行时间',
     `last_run_time` int unsigned DEFAULT NULL COMMENT '上次执行时间',
     `progress` float unsigned DEFAULT '0' COMMENT '长队列的话，显示一下执行进度',
     `error` text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT '执行错误的话，记录错误信息',
     `is_discard` tinyint unsigned DEFAULT '0' COMMENT '是否丢弃了本条消息',
     `is_success` tinyint unsigned DEFAULT '0' COMMENT '是否执行成功了',
     `delay_times` text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT '延迟执行的时间列表，是一个数组',
     `max_num` int unsigned DEFAULT '0' COMMENT '最大执行次数，0不限制',
     `data` text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT '本次执行需要传递的数据',
     `consumer` varchar(50) COLLATE utf8mb4_bin DEFAULT NULL COMMENT '消费者',
     `msg_key` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '消息ID，如果要做清理之前的消息，可以使用这个',
     PRIMARY KEY (`id`),
     KEY `next_run_time` (`next_run_time`,`is_discard`,`consumer`),
     KEY `idx_consumer` (`consumer`),
     KEY `idx_last_run_time` (`last_run_time`),
     KEY `idx_is_discard` (`is_discard`),
     KEY `idx_is_success` (`is_success`),
     KEY `msg_key` (`msg_key`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin COMMENT='消息队列';

# 创建管理员表格
CREATE TABLE IF NOT EXISTS `admin_manager`
(
    `id`       int          NOT NULL AUTO_INCREMENT,
    `username` varchar(180) NOT NULL,
    `roles`    json         NOT NULL,
    `password` varchar(255) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `UNIQ_IDENTIFIER_USERNAME` (`username`)
) ENGINE = InnoDB
  AUTO_INCREMENT = 1
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_0900_ai_ci