/**
 * Token 管理类
 * 管理用户认证 Token 的存储、获取和刷新
 */

import { CacheMgr } from "./CacheMgr";

// 声明全局 uni 对象类型（UniApp环境）
declare const uni: any;

/** 短 Token 缓存键 */
const SHORT_TOKEN_KEY = 'auth:shortToken';
/** 长 Token 缓存键 */
const LONG_TOKEN_KEY = 'auth:longToken';
/** 用户信息缓存键 */
const USER_INFO_KEY = 'auth:userInfo';

/**
 * 用户信息接口
 */
export interface UserInfo {
    id?: number;
    username?: string;
    nickname?: string;
    avatar?: string;
    [key: string]: any;
}

/**
 * Token 管理类
 */
export class Auth {
    /** Token 刷新回调 */
    private static refreshCallback: (() => Promise<string>) | null = null;
    /** 登出回调 */
    private static logoutCallback: (() => void) | null = null;

    /**
     * 设置 Token 刷新回调
     * @param callback 刷新 Token 的回调函数，返回新的短 Token
     */
    public static setRefreshCallback(callback: () => Promise<string>): void {
        this.refreshCallback = callback;
    }

    /**
     * 设置登出回调
     * @param callback 登出时的回调函数
     */
    public static setLogoutCallback(callback: () => void): void {
        this.logoutCallback = callback;
    }

    /**
     * 获取短 Token
     * @returns 短 Token 或 null
     */
    public static getShortToken(): string | null {
        return CacheMgr.get<string>(SHORT_TOKEN_KEY);
    }

    /**
     * 设置短 Token
     * @param token 短 Token
     * @param expired 过期时间（秒），默认 1 小时
     */
    public static setShortToken(token: string, expired: number = 3600): void {
        CacheMgr.set(SHORT_TOKEN_KEY, token, expired);
    }

    /**
     * 获取长 Token
     * @returns 长 Token 或 null
     */
    public static getLongToken(): string | null {
        return CacheMgr.get<string>(LONG_TOKEN_KEY);
    }

    /**
     * 设置长 Token
     * @param token 长 Token
     * @param expired 过期时间（秒），默认 30 天
     */
    public static setLongToken(token: string, expired: number = 86400 * 30): void {
        CacheMgr.set(LONG_TOKEN_KEY, token, expired);
    }

    /**
     * 获取用户信息
     * @returns 用户信息或 null
     */
    public static getUserInfo(): UserInfo | null {
        return CacheMgr.get<UserInfo>(USER_INFO_KEY);
    }

    /**
     * 设置用户信息
     * @param userInfo 用户信息
     * @param expired 过期时间（秒），默认 30 天
     */
    public static setUserInfo(userInfo: UserInfo, expired: number = 86400 * 30): void {
        CacheMgr.set(USER_INFO_KEY, userInfo, expired);
    }

    /**
     * 检查是否已登录
     * @returns 是否已登录
     */
    public static isLoggedIn(): boolean {
        return !!this.getShortToken() || !!this.getLongToken();
    }

    /**
     * 刷新 Token
     * @returns Promise<string> 新的短 Token
     */
    public static async refreshToken(): Promise<string> {
        if (!this.refreshCallback) {
            throw new Error('未设置 Token 刷新回调');
        }

        try {
            const newToken = await this.refreshCallback();
            return newToken;
        } catch (error) {
            console.error('刷新 Token 失败:', error);
            // 刷新失败，执行登出
            this.logout();
            throw error;
        }
    }

    /**
     * 登出
     */
    public static logout(): void {
        // 清除所有认证信息
        CacheMgr.del(SHORT_TOKEN_KEY);
        CacheMgr.del(LONG_TOKEN_KEY);
        CacheMgr.del(USER_INFO_KEY);

        // 执行登出回调
        if (this.logoutCallback) {
            this.logoutCallback();
        }
    }

    /**
     * 登录
     * @param shortToken 短 Token
     * @param longToken 长 Token
     * @param userInfo 用户信息
     */
    public static login(shortToken: string, longToken: string, userInfo?: UserInfo): void {
        this.setShortToken(shortToken);
        this.setLongToken(longToken);
        if (userInfo) {
            this.setUserInfo(userInfo);
        }
    }

    /**
     * 跳转到登录页面
     * @param redirectUrl 登录后跳转的 URL
     */
    public static redirectToLogin(redirectUrl?: string): void {
        let url = '/pages/login/index';
        if (redirectUrl) {
            url += `?redirect=${encodeURIComponent(redirectUrl)}`;
        }

        uni.navigateTo({
            url: url,
            fail: () => {
                // 如果 navigateTo 失败，尝试 redirectTo
                uni.redirectTo({ url: url });
            }
        });
    }

    /**
     * 检查登录状态，未登录则跳转到登录页
     * @param redirectUrl 登录后跳转的 URL
     * @returns 是否已登录
     */
    public static checkLogin(redirectUrl?: string): boolean {
        if (!this.isLoggedIn()) {
            this.redirectToLogin(redirectUrl);
            return false;
        }
        return true;
    }
}
