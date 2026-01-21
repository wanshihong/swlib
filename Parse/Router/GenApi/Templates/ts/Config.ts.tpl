/**
 * 应用配置
 * 此文件由代码生成器自动生成，请勿手动修改
 */

export const Config = {
    /** API 基础 URL */
    API_URL: '{$API_URL}',

    /** WebSocket 主机地址 */
    WS_HOST: '{$WS_HOST}',

    /** 应用 ID */
    AppId: '{$APP_ID}',

    /** 应用密钥（用于签名） */
    APP_SECRET: '{$APP_SECRET}',

    /** 请求超时时间（毫秒） */
    TIMEOUT: {$TIMEOUT},

    /** 默认语言 */
    DEFAULT_LANGUAGE: '{$DEFAULT_LANGUAGE}',
} as const;

/** 配置类型 */
export type ConfigType = typeof Config;
