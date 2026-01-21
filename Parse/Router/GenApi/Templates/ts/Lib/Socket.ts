/**
 * WebSocket 连接管理类
 * 采用单例模式，管理应用程序中的 WebSocket 连接
 * 提供自动重连、消息队列、心跳检测等功能
 */

import { Protobuf } from "@/proto/proto.js";
import { Config } from "../Config";
import { Auth } from "./Auth";
import { CacheMgr } from "./CacheMgr";
import { Crypto } from "./Crypto";

// 声明全局 uni 对象类型（UniApp环境）
declare const uni: any;

/**
 * WebSocket 发送请求接口
 */
interface SocketSendRequest {
    url: string;
    params: Object;
    requestProtoBuf: any;
    retryCount?: number;
}

/**
 * WebSocket 绑定响应接口
 */
interface SocketBindOnResponse {
    url: string;
    responseProtoBuf: any;
    callback: Function;
    showError: Boolean;
    errorMessage: string;
}

/**
 * WebSocket 连接管理类
 */
export class Socket {
    /** 单例实例 */
    private static instance: Socket | null = null;
    /** WebSocket 任务对象 */
    private socketTask: any = null;
    /** 连接状态标志 */
    private isConnected: boolean = false;
    /** 消息队列，存储待发送的消息 */
    private messageQueue: SocketSendRequest[] = [];
    /** 重连定时器 */
    private reconnectTimer: any = null;
    /** 当前重连次数 */
    private reconnectCount: number = 0;
    /** 初始重连间隔（毫秒） */
    private reconnectInterval: number = 3000;
    /** 最大重连间隔（毫秒） */
    private maxReconnectInterval: number = 30000;
    /** 连接 URL 参数 */
    private url: string = '';
    /** 消息回调映射 */
    private messageCallbacks: Map<string, SocketBindOnResponse[]>;
    /** 心跳定时器 */
    private heartbeatTimer: any = null;
    /** 心跳间隔（毫秒） */
    private heartbeatInterval: number = 30000;
    /** 心跳响应超时时间（毫秒） */
    private heartbeatTimeout: number = 10000;
    /** 上次心跳时间戳 */
    private lastHeartbeatTime: number = 0;
    /** 心跳响应等待标志 */
    private waitingForHeartbeatResponse: boolean = false;
    /** 心跳超时检测定时器 */
    private heartbeatCheckTimer: any = null;
    /** 消息队列最大大小 */
    private maxQueueSize: number = 100;
    /** 网络状态监听器 */
    private networkStatusListener: any = null;
    /** 刷新 Token 的 Promise 对象 */
    private refreshTokenPromise: Promise<any> | null = null;
    /** 连接状态监听器数组 */
    private connectStatusCallbacks: Function[] = [];
    /** 错误监听器数组 */
    private errorCallbacks: Function[] = [];
    /** 连接参数 */
    private connectParams = {};

    /**
     * 私有构造函数，实现单例模式
     */
    private constructor() {
        this.messageCallbacks = new Map<string, SocketBindOnResponse[]>();
        this.setupNetworkListener();
    }

    /**
     * 获取 Socket 实例（单例模式）
     */
    public static getInstance(): Socket {
        if (!Socket.instance) {
            Socket.instance = new Socket();
        }
        return Socket.instance;
    }

    /**
     * 连接 WebSocket
     * @param params 连接参数
     */
    public connect(params: object): void {
        this.connectParams = params;
        this._connect();
    }

    /**
     * 设置网络状态监听
     */
    private setupNetworkListener(): void {
        this.networkStatusListener = (res: any) => {
            if (res.isConnected && !this.isConnected) {
                console.log('网络已恢复，尝试重新连接 WebSocket');
                this.reconnectCount = 0;
                this._connect();
            } else if (res.isConnected && this.isConnected) {
                console.log('网络状态变化，发送心跳验证连接');
                this.ping();
            }
        };

        uni.onNetworkStatusChange(this.networkStatusListener);
    }

    /**
     * 添加 WebSocket 连接状态监听
     * @param callback 状态变化回调函数
     */
    public onConnectStatus(callback: Function): void {
        if (typeof callback === 'function') {
            this.connectStatusCallbacks.push(callback);
        }
    }

    /**
     * 移除 WebSocket 连接状态监听
     * @param callback 要移除的回调函数
     */
    public offConnectStatus(callback?: Function): void {
        if (callback) {
            const index = this.connectStatusCallbacks.indexOf(callback);
            if (index !== -1) {
                this.connectStatusCallbacks.splice(index, 1);
            }
        } else {
            this.connectStatusCallbacks = [];
        }
    }

    /**
     * 添加 WebSocket 错误监听
     * @param callback 错误回调函数
     */
    public onError(callback: Function): void {
        if (typeof callback === 'function') {
            this.errorCallbacks.push(callback);
        }
    }

    /**
     * 移除 WebSocket 错误监听
     * @param callback 要移除的回调函数
     */
    public offError(callback?: Function): void {
        if (callback) {
            const index = this.errorCallbacks.indexOf(callback);
            if (index !== -1) {
                this.errorCallbacks.splice(index, 1);
            }
        } else {
            this.errorCallbacks = [];
        }
    }

    /**
     * 获取认证参数
     */
    private getAuthParam(): string {
        const nowTime = Math.ceil(new Date().getTime() / 1000);
        const random = Math.ceil(Math.random() * 10000);

        let shortToken = Auth.getShortToken();
        if (!shortToken) {
            shortToken = '';
        }

        const token = Crypto.sign(shortToken, random, nowTime, Config.APP_SECRET);
        const lang = CacheMgr.get('currentLang') || Config.DEFAULT_LANGUAGE || 'zh';
        const xForwardedProto = Config.API_URL.startsWith('https') ? 'https' : 'http';

        return `?time=${nowTime}&random=${random}&appid=${Config.AppId}&token=${token}&lang=${lang}&x-forwarded-proto=${xForwardedProto}&authorization=${shortToken}`;
    }

    /**
     * 创建 WebSocket 连接
     */
    private _connect(): void {
        let sendParams = this.getAuthParam();

        for (const key in this.connectParams) {
            const value = (this.connectParams as any)[key];
            sendParams += `&${key}=${value}`;
        }

        this.url = sendParams;

        if (this.socketTask) {
            this.socketTask.close();
        }

        this.socketTask = uni.connectSocket({
            url: `${Config.WS_HOST}/${this.url}`,
            success: () => {
                console.log('WebSocket 连接成功');
            },
            fail: (err: any) => {
                console.error('WebSocket 连接失败:', err);
                this.handleReconnect();
            }
        });

        this.socketTask.onOpen(() => {
            console.log('WebSocket 连接已打开');
            this.isConnected = true;
            this.reconnectCount = 0;
            this.sendQueuedMessages();
            this.startHeartbeat();

            for (const callback of this.connectStatusCallbacks) {
                try {
                    callback(true);
                } catch (e) {
                    console.error('调用连接状态回调时出错:', e);
                }
            }
        });

        this.socketTask.onClose((res: any) => {
            console.log('WebSocket 连接已关闭', res);
            this.isConnected = false;
            this.stopHeartbeat();

            for (const callback of this.connectStatusCallbacks) {
                try {
                    callback(false);
                } catch (e) {
                    console.error('调用连接状态回调时出错:', e);
                }
            }

            if (res.code !== 1000) {
                this.handleReconnect();
            }
        });

        this.socketTask.onError((err: any) => {
            console.error('WebSocket 错误:', err);
            this.isConnected = false;
            this.stopHeartbeat();

            for (const callback of this.errorCallbacks) {
                try {
                    callback(err);
                } catch (e) {
                    console.error('调用错误回调时出错:', e);
                }
            }

            for (const callback of this.connectStatusCallbacks) {
                try {
                    callback(false);
                } catch (e) {
                    console.error('调用连接状态回调时出错:', e);
                }
            }

            this.handleReconnect();
        });

        this.socketTask.onMessage((res: any) => {
            try {
                if (res.data === 'pong') {
                    this.waitingForHeartbeatResponse = false;
                    this.lastHeartbeatTime = Date.now();
                    return;
                }

                const bytes = new Uint8Array(res.data);
                const response = Protobuf.Common.Response.decode(bytes);

                const pathValue = response.path;
                const path = pathValue ? pathValue.toString() : this.url;
                const callbacksList = this.messageCallbacks.get(path);

                if (response.errno !== 0) {
                    console.error('WebSocket 消息错误:', response.msg);

                    if (response.errno === 401 || response.errno === 403) {
                        console.log('Token 已过期，尝试重新获取认证');
                        this.retryWithNewToken();
                        return;
                    }

                    if (callbacksList && callbacksList.length > 0) {
                        const showErrorCallback = callbacksList.find(cb => cb.showError);
                        if (showErrorCallback) {
                            uni.showModal({
                                title: '服务器错误',
                                content: showErrorCallback.errorMessage,
                                confirmText: '确定',
                                showCancel: false
                            });
                        }
                    }

                    return;
                }

                if (callbacksList && callbacksList.length > 0) {
                    for (const socketBindOnResponse of callbacksList) {
                        try {
                            const responseProtoBuf = socketBindOnResponse.responseProtoBuf.decode(response.data);
                            socketBindOnResponse.callback(responseProtoBuf);
                        } catch (callbackError) {
                            console.error('执行回调函数时出错:', callbackError);
                        }
                    }
                } else {
                    console.log('未找到 WebSocket 消息处理器:', path);
                }
            } catch (error) {
                console.error('WebSocket 消息解析错误:', error);
            }
        });
    }

    /**
     * 刷新 Token 并重试
     */
    private async retryWithNewToken(): Promise<void> {
        const pages = getCurrentPages();
        const currentPage = pages[pages.length - 1];
        if (currentPage.route === 'pages/login/index') {
            return;
        }

        if (!this.refreshTokenPromise) {
            this.refreshTokenPromise = Auth.refreshToken().finally(() => {
                this.refreshTokenPromise = null;

                if (this.socketTask) {
                    console.log('关闭现有 WebSocket 连接...');
                    this.close();
                    this.handleReconnect();
                }
            });
        }

        await this.refreshTokenPromise;
    }

    /**
     * 启动心跳检测
     */
    private startHeartbeat(): void {
        this.stopHeartbeat();

        this.lastHeartbeatTime = Date.now();
        this.waitingForHeartbeatResponse = false;

        this.heartbeatTimer = setInterval(() => {
            if (!this.isConnected) {
                this.stopHeartbeat();
                return;
            }
            this.ping();
        }, this.heartbeatInterval);

        this.heartbeatCheckTimer = setInterval(() => {
            if (!this.isConnected) {
                this.stopHeartbeat();
                return;
            }

            const currentTime = Date.now();

            if (this.waitingForHeartbeatResponse &&
                (currentTime - this.lastHeartbeatTime > this.heartbeatTimeout)) {
                console.warn('心跳响应超时，认为连接已断开');
                this.isConnected = false;
                this.handleReconnect();
                return;
            }

            if (currentTime - this.lastHeartbeatTime > this.heartbeatInterval * 0.8) {
                console.log('长时间没有心跳通信，发送额外心跳');
                this.ping();
            }
        }, this.heartbeatInterval / 3);
    }

    /**
     * 发送心跳包
     */
    public ping(): void {
        if (!this.isConnected) {
            return;
        }

        this.waitingForHeartbeatResponse = true;
        this.lastHeartbeatTime = Date.now();

        try {
            const heartbeatRequest = new Protobuf.Common.Request({
                uri: "+ping",
                data: new Uint8Array([])
            });

            const data = Protobuf.Common.Request.encode(heartbeatRequest).finish();

            this.socketTask.send({
                data: data,
                fail: (err: any) => {
                    console.error('心跳包发送失败:', err);
                    this.waitingForHeartbeatResponse = false;
                    this.isConnected = false;
                    this.handleReconnect();
                }
            });
        } catch (error) {
            console.error('发送心跳包出错:', error);
            this.waitingForHeartbeatResponse = false;
        }
    }

    /**
     * 停止心跳检测
     */
    private stopHeartbeat(): void {
        if (this.heartbeatTimer) {
            clearInterval(this.heartbeatTimer);
            this.heartbeatTimer = null;
        }
        if (this.heartbeatCheckTimer) {
            clearInterval(this.heartbeatCheckTimer);
            this.heartbeatCheckTimer = null;
        }
        this.waitingForHeartbeatResponse = false;
    }

    /**
     * 处理 WebSocket 重连逻辑
     */
    private handleReconnect(): void {
        if (this.isConnected) {
            return;
        }

        if (this.reconnectTimer) {
            clearTimeout(this.reconnectTimer);
        }

        this.stopHeartbeat();

        const backoffInterval = Math.min(
            this.reconnectInterval * Math.pow(1.5, this.reconnectCount),
            this.maxReconnectInterval
        );

        this.reconnectTimer = setTimeout(() => {
            this.reconnectCount++;
            console.log(`WebSocket 尝试第 ${this.reconnectCount} 次重连, 间隔: ${backoffInterval}ms`);

            uni.getNetworkType({
                success: (res: any) => {
                    if (res.networkType !== 'none') {
                        this._connect();
                    } else {
                        console.log('当前无网络连接，等待网络恢复');
                    }
                },
                fail: () => {
                    this._connect();
                }
            });
        }, backoffInterval);
    }

    /**
     * 发送队列中的消息
     */
    private sendQueuedMessages(): void {
        if (!this.isConnected || !this.socketTask) {
            console.log('WebSocket 连接未就绪，消息将在连接成功后发送');
            return;
        }

        const maxRetryCount = 3;

        let i = 0;
        while (i < this.messageQueue.length) {
            const message = this.messageQueue[i];
            if (!message) {
                i++;
                continue;
            }

            if (message.retryCount === undefined) {
                message.retryCount = 0;
            }

            try {
                const requestMessage = new message.requestProtoBuf(message.params);
                const heartbeatRequest = new Protobuf.Common.Request({
                    uri: message.url,
                    data: message.requestProtoBuf.encode(requestMessage).finish()
                });
                const data = Protobuf.Common.Request.encode(heartbeatRequest).finish();

                const currentIndex = i;

                this.socketTask.send({
                    data: data,
                    success: () => {
                        console.log('WebSocket 消息发送成功:', message.url);
                        this.messageQueue.splice(currentIndex, 1);
                        i--;
                    },
                    fail: (err: any) => {
                        console.error('WebSocket 消息发送失败:', err);
                        this._connect();
                        message.retryCount = (message.retryCount || 0) + 1;

                        if (message.retryCount >= maxRetryCount) {
                            console.error(`消息发送失败次数过多 (${message.retryCount} 次)，放弃发送:`, message.url);
                            this.messageQueue.splice(currentIndex, 1);
                            i--;
                        }
                    }
                });

                i++;
            } catch (error) {
                console.error('准备发送消息时出错:', error);
                this.messageQueue.splice(i, 1);
            }
        }
    }

    /**
     * 发送 WebSocket 消息
     * @param opt 发送消息的配置对象
     */
    public send = (opt: SocketSendRequest): void => {
        if (this.messageQueue.length >= this.maxQueueSize) {
            console.warn('WebSocket 消息队列已满，丢弃最早消息');
            this.messageQueue.shift();
        }

        this.messageQueue.push(opt);
        this.sendQueuedMessages();
    };

    /**
     * 注册 WebSocket 消息监听
     * @param opt 监听配置对象
     */
    public on(opt: SocketBindOnResponse): void {
        const { url } = opt;

        const callbacksList = this.messageCallbacks.get(url) || [];

        const isDuplicate = callbacksList.some(existingOpt =>
            existingOpt.url === opt.url &&
            existingOpt.responseProtoBuf === opt.responseProtoBuf &&
            existingOpt.callback === opt.callback &&
            existingOpt.showError === opt.showError &&
            existingOpt.errorMessage === opt.errorMessage
        );

        if (!isDuplicate) {
            callbacksList.push(opt);
            this.messageCallbacks.set(url, callbacksList);
            console.log(`已注册 WebSocket 监听: ${url}, 当前监听数: ${callbacksList.length}`);
        } else {
            console.log(`跳过重复的 WebSocket 监听: ${url}`);
        }
    }

    /**
     * 取消 WebSocket 消息监听
     * @param url 要取消监听的 URL 路径
     * @param callback 要取消的特定回调函数
     */
    public off(url: string, callback?: Function): void {
        if (!callback) {
            this.messageCallbacks.delete(url);
            console.log(`已删除 URL 的所有监听: ${url}`);
            return;
        }

        const callbacksList = this.messageCallbacks.get(url);
        if (!callbacksList || callbacksList.length === 0) {
            console.log(`URL 没有注册的监听: ${url}`);
            return;
        }

        const newCallbacksList = callbacksList.filter(item => item.callback !== callback);

        if (newCallbacksList.length === 0) {
            this.messageCallbacks.delete(url);
            console.log(`已删除 URL 的最后一个监听: ${url}`);
        } else {
            this.messageCallbacks.set(url, newCallbacksList);
            console.log(`已删除特定回调, URL: ${url}, 剩余监听数: ${newCallbacksList.length}`);
        }
    }

    /**
     * 关闭 WebSocket 连接
     */
    public close(): void {
        if (this.socketTask) {
            this.socketTask.close();
            this.socketTask = null;
        }

        this.isConnected = false;

        if (this.reconnectTimer) {
            clearTimeout(this.reconnectTimer);
            this.reconnectTimer = null;
        }

        this.stopHeartbeat();

        if (this.networkStatusListener) {
            uni.offNetworkStatusChange(this.networkStatusListener);
            this.networkStatusListener = null;
        }

        this.messageQueue = [];
    }
}
