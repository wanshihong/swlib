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
CREATE TABLE IF NOT EXISTS `language`
(
    `id`       int unsigned NOT NULL AUTO_INCREMENT,
    `use_time` int DEFAULT NULL COMMENT '上次使用时间，太久没使用可以删除',
    `zh`       text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT '中文-简体',
    `zh_tw`    text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT '中文-繁体(台湾)',
    `zh_hk`    text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT '中文-繁体(香港)',
    `en`       text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT '英文',
    `ja`       text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT '日语',
    `ko`       text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT '韩语',
    `fr`       text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT '法语',
    `es`       text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT '西班牙语',
    `it`       text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT '意大利语',
    `de`       text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT '德语',
    `tr`       text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT '土耳其语',
    `ru`       text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT '俄语',
    `pt`       text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT '葡萄牙语',
    `pt_br`    text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT '葡萄牙语(巴西)',
    `vi`       text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT '越南语',
    `ina`      text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT '印尼语',
    `th`       text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT '泰语',
    `ms`       text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT '马来语',
    `ar`       text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT '阿拉伯语',
    `hi`       text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT '印地语',
    `nl`       text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT '荷兰语',
    `pl`       text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT '波兰语',
    `sv`       text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT '瑞典语',
    `da`       text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT '丹麦语',
    `fi`       text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT '芬兰语',
    `no`       text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT '挪威语',
    `he`       text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT '希伯来语',
    `el`       text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT '希腊语',
    `cs`       text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT '捷克语',
    `ro`       text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT '罗马尼亚语',
    `hu`       text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT '匈牙利语',
    `uk`       text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT '乌克兰语',
    `fa`       text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT '波斯语',
    `fil`      text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT '菲律宾语',
    `bn`       text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT '孟加拉语',
    `ur`       text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT '乌尔都语',
    `sw`       text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT '斯瓦希里语',
    PRIMARY KEY (`id`) USING BTREE,
    KEY `idx_use_time` (`use_time`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_bin
  ROW_FORMAT = DYNAMIC COMMENT ='多语言翻译表（App 本地化）';


# 创建消息队列表
CREATE TABLE IF NOT EXISTS `message_queue`
(
    `id`            int unsigned NOT NULL AUTO_INCREMENT,
    `run_num`       int unsigned                                          DEFAULT '0' COMMENT '执行了次数',
    `next_run_time` int unsigned                                          DEFAULT '0' COMMENT '下次执行时间',
    `last_run_time` int unsigned                                          DEFAULT NULL COMMENT '上次执行时间',
    `progress`      float unsigned                                        DEFAULT '0' COMMENT '长队列的话，显示一下执行进度',
    `error`         text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT '执行错误的话，记录错误信息',
    `is_discard`    tinyint unsigned                                      DEFAULT '0' COMMENT '是否丢弃了本条消息',
    `is_success`    tinyint unsigned                                      DEFAULT '0' COMMENT '是否执行成功了',
    `delay_times`   text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT '延迟执行的时间列表，是一个数组',
    `max_num`       int unsigned                                          DEFAULT '0' COMMENT '最大执行次数，0不限制',
    `data`          text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT '本次执行需要传递的数据',
    `consumer`      varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '消费者',
    `msg_key`       varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '消息ID，如果要做清理之前的消息，可以使用这个',
    PRIMARY KEY (`id`),
    KEY `next_run_time` (`next_run_time`, `is_discard`, `consumer`),
    KEY `idx_consumer` (`consumer`),
    KEY `idx_last_run_time` (`last_run_time`),
    KEY `idx_is_discard` (`is_discard`),
    KEY `idx_is_success` (`is_success`),
    KEY `msg_key` (`msg_key`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_bin COMMENT ='消息队列';


# 创建后台管理员
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
  COLLATE = utf8mb4_bin COMMENT ='后台管理员';