/**
 * HTTP 客户端
 * 封装 UniApp 的网络请求，支持 Protobuf、缓存、签名、Token 刷新等功能
 */

import { Protobuf } from "@/proto/proto.js";
import { Config } from "../Config";
import { Crypto } from "./Crypto";
import { Auth } from "./Auth";
import { CacheMgr } from "./CacheMgr";
import { Network } from "./Network";

// 声明全局 uni 对象类型（UniApp环境）
declare const uni: any;

/**
 * 显示 API 错误弹窗
 * @param content 错误内容
 * @param title 错误标题
 */
export const showApiError = async (content: string, title: string = "请求错误") => {
    uni.showModal({
        title: title,
        content: content,
        confirmText: '确定',
        showCancel: false
    });
};

/**
 * 生成缓存键
 * @param router API 路径
 * @param params 请求参数
 * @returns 缓存键
 */
export const getCacheKey = (router: string, params: Record<string, any> | null = null): string => {
    const version = uni.getAppBaseInfo?.()?.appVersionCode || '1';
    let cacheKey = `Api:${version}:${router}`;

    if (!params) {
        return cacheKey;
    }

    // 处理参数部分
    const paramValues = Object.keys(params).map(key => {
        const value = params[key];
        return typeof value === 'object'
            ? Object.values(value).join('')
            : String(value);
    }).join('');

    return cacheKey + (paramValues ? `:${paramValues}` : '');
};

/**
 * 编码 Protobuf 消息
 * @param ProtobufMessage Protobuf 消息类
 * @param params 参数对象
 * @returns 编码后的 Uint8Array
 */
export const encodeProtobuf = (ProtobufMessage: any, params: any): Uint8Array => {
    const msg = new ProtobufMessage(params);
    return ProtobufMessage.encode(msg).finish();
};

/**
 * HTTP 客户端类
 */
export class Http {
    /** Token 刷新 Promise（防止并发刷新） */
    private static refreshTokenPromise: Promise<any> | null = null;

    /**
     * 调用 API
     * @param url API 完整 URL
     * @param params 请求参数
     * @param showError 是否显示错误
     * @param errorMessage 错误提示消息
     * @param requestProtoBuf 请求 Protobuf 类
     * @param responseProtoBuf 响应 Protobuf 类
     * @param cacheTime 缓存时间（秒），0 表示不缓存
     * @returns Promise<T> 响应数据
     */
    public static callApi = <T>(
        url: string,
        params: object | null,
        showError: boolean,
        errorMessage: string,
        requestProtoBuf: any,
        responseProtoBuf: any,
        cacheTime: number = 0
    ): Promise<T> => {
        const cacheKey: string = getCacheKey(url, params as Record<string, any>);

        return new Promise<T>((resolve, reject) => {
            // 检查缓存
            if (cacheTime > 0) {
                const cacheData = CacheMgr.get(cacheKey);
                if (cacheData) {
                    try {
                        const cacheObj = JSON.parse(cacheData);
                        const cacheMsg = new responseProtoBuf(cacheObj);
                        resolve(cacheMsg as T);
                        return;
                    } catch (e) {
                        // 缓存解析失败，继续请求
                    }
                }
            }

            // 编码请求参数
            let sendParams: Uint8Array | null = null;
            if (params && typeof params === 'object' && !Array.isArray(params) && Object.keys(params).length > 0) {
                sendParams = encodeProtobuf(requestProtoBuf, params);
            }

            // 发送请求
            Http.send(url, sendParams).then((res: any) => {
                const info = responseProtoBuf.decode(res);

                // 设置缓存
                if (cacheTime > 0 && cacheKey !== '') {
                    const cacheString = JSON.stringify(info.toJSON());
                    CacheMgr.set(cacheKey, cacheString, cacheTime);
                }

                resolve(info as T);
            }).catch(async (err: any) => {
                if (showError) {
                    await showApiError(err, errorMessage);
                }
                reject(err);
            });
        });
    };

    /**
     * 使用新 Token 重试请求
     * @param url 请求 URL
     * @param uint8Array 请求数据
     * @returns Promise<any> 响应数据
     */
    private static async retryRequestWithNewToken(url: string, uint8Array: Uint8Array | null): Promise<any> {
        // 如果已经有正在进行的刷新 Token 请求，直接复用它
        if (!Http.refreshTokenPromise) {
            Http.refreshTokenPromise = Auth.refreshToken().finally(() => {
                // 请求完成后清空 Promise
                Http.refreshTokenPromise = null;
            });
        }

        // 等待 Token 刷新完成
        await Http.refreshTokenPromise;

        // 使用新 Token 重试原始请求
        return await Http.send(url, uint8Array, false);
    }

    /**
     * 发送 HTTP 请求
     * @param url 请求 URL
     * @param uint8Array 请求数据（Protobuf 编码后的数据）
     * @param allowRetry 是否允许 Token 过期后重试
     * @returns Promise<Uint8Array> 响应数据
     */
    public static send(url: string, uint8Array: Uint8Array | null = null, allowRetry: boolean = true): Promise<Uint8Array> {
        return new Promise((resolve, reject) => {
            // 等待网络就绪（处理首次打开权限问题）
            Network.waitForReady().then(() => {
                const nowTime = Math.ceil(new Date().getTime() / 1000);
                const random = Math.ceil(Math.random() * 10000);

                // 构建请求头
                const headers: Record<string, string> = {
                    'withCredentials': 'true',
                    'Content-type': 'application/x-protobuf',
                    'time': String(nowTime),
                    'random': String(random),
                    'app-id': String(Config.AppId),
                    'url': url,
                    'token': Crypto.sign(url, random, nowTime, Config.APP_SECRET),
                    'x-forwarded-proto': Config.API_URL.startsWith('https') ? 'https' : 'http'
                };

                // 添加语言设置
                const currentLang = CacheMgr.get('currentLang');
                headers['lang'] = currentLang || Config.DEFAULT_LANGUAGE || 'zh';

                // 添加短 Token
                const shortToken = Auth.getShortToken();
                if (shortToken) {
                    headers['authorization'] = shortToken;
                }

                try {
                    uni.request({
                        url: url,
                        method: 'POST',
                        responseType: 'arraybuffer',
                        dataType: 'protobuf',
                        timeout: Config.TIMEOUT,
                        data: uint8Array ? new Uint8Array(Array.from(uint8Array)).buffer : '',
                        header: headers,
                        success: (res: any) => {
                            // 处理 401 未授权
                            if (res.statusCode === 401 && allowRetry) {
                                Http.retryRequestWithNewToken(url, uint8Array).then(result => {
                                    resolve(result);
                                }).catch(error => {
                                    reject(error);
                                });
                                return;
                            }

                            // 处理非 200 状态码
                            if (res.statusCode !== 200) {
                                uni.showModal({
                                    title: '服务器错误',
                                    content: `状态码: ${res.statusCode}`,
                                    confirmText: '确定',
                                    showCancel: false
                                });
                                reject(`HTTP Error: ${res.statusCode}`);
                                return;
                            }

                            // 检查响应数据
                            if (!res.data) {
                                reject('响应数据为空');
                                return;
                            }

                            // 解码 Protobuf 响应
                            const bytes = new Uint8Array(res.data);
                            const response = Protobuf.Common.Response.decode(bytes);

                            if (response.errno === 0) {
                                resolve(response.data);
                            } else {
                                reject(response.msg);
                            }
                        },
                        fail: (err: any) => {
                            console.error('HTTP 请求失败:', url, err);
                            reject(JSON.stringify(err));
                        }
                    });
                } catch (e) {
                    console.error('HTTP 请求异常:', e);
                    reject(String(e));
                }
            });
        });
    }
}
