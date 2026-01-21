/**
 * 网络状态管理类
 * 处理 iOS/Android 首次打开 App 时的网络权限问题
 */

// 声明全局 uni 对象类型（UniApp环境）
declare const uni: any;
declare const plus: any;

export class Network {
    /** 网络是否就绪 */
    private static isReady: boolean = false;
    /** 等待网络就绪的 Promise */
    private static readyPromise: Promise<boolean> | null = null;
    /** 检测间隔（毫秒） */
    private static checkInterval: number = 500;
    /** 最大等待时间（毫秒） */
    private static maxWaitTime: number = 10000;
    /** 权限被拒绝时的回调 */
    private static permissionDeniedCallback: (() => void) | null = null;

    /**
     * 设置权限被拒绝时的回调（引导用户开启权限）
     * @param callback 回调函数
     */
    public static onPermissionDenied(callback: () => void): void {
        this.permissionDeniedCallback = callback;
    }

    /**
     * 等待网络就绪（处理首次打开 App 的权限问题）
     * - 轮询检测网络状态
     * - 超时后引导用户开启权限
     * @returns Promise<boolean> 网络是否就绪
     */
    public static waitForReady(): Promise<boolean> {
        if (this.isReady) return Promise.resolve(true);
        if (this.readyPromise) return this.readyPromise;

        this.readyPromise = new Promise((resolve) => {
            let elapsed = 0;

            const check = () => {
                uni.getNetworkType({
                    success: (res: any) => {
                        if (res.networkType !== 'none') {
                            this.isReady = true;
                            this.readyPromise = null;
                            resolve(true);
                        } else {
                            elapsed += this.checkInterval;
                            if (elapsed >= this.maxWaitTime) {
                                // 超时，引导用户开启权限
                                this.handlePermissionDenied(resolve);
                            } else {
                                setTimeout(check, this.checkInterval);
                            }
                        }
                    },
                    fail: () => {
                        elapsed += this.checkInterval;
                        if (elapsed >= this.maxWaitTime) {
                            this.handlePermissionDenied(resolve);
                        } else {
                            setTimeout(check, this.checkInterval);
                        }
                    }
                });
            };

            check();
        });

        return this.readyPromise;
    }

    /**
     * 处理权限被拒绝的情况
     * @param resolve Promise 的 resolve 函数
     */
    private static handlePermissionDenied(resolve: (value: boolean) => void): void {
        if (this.permissionDeniedCallback) {
            this.permissionDeniedCallback();
        } else {
            // 默认行为：弹窗引导用户
            uni.showModal({
                title: '网络权限',
                content: '请允许应用访问网络，否则无法正常使用',
                confirmText: '去设置',
                cancelText: '稍后',
                success: (res: any) => {
                    if (res.confirm) {
                        // 跳转到系统设置
                        this.openAppSettings();
                    }
                    // 继续等待
                    this.readyPromise = null;
                    this.waitForReady().then(resolve);
                }
            });
        }
    }

    /**
     * 跳转到 App 设置页面
     */
    private static openAppSettings(): void {
        // #ifdef APP-PLUS
        uni.openAppAuthorizeSetting({
            success (res:any) {
                console.log(res)
            }
        })
        // #endif
    }

    /**
     * 监听网络状态变化
     * @param callback 状态变化回调，参数为是否已连接
     */
    public static onStatusChange(callback: (isConnected: boolean) => void): void {
        uni.onNetworkStatusChange((res: any) => {
            this.isReady = res.isConnected;
            callback(res.isConnected);
        });
    }

    /**
     * 获取当前网络类型
     * @returns Promise<string> 网络类型（wifi/2g/3g/4g/5g/ethernet/none/unknown）
     */
    public static getNetworkType(): Promise<string> {
        return new Promise((resolve) => {
            uni.getNetworkType({
                success: (res: any) => {
                    resolve(res.networkType);
                },
                fail: () => {
                    resolve('unknown');
                }
            });
        });
    }

    /**
     * 检查网络是否已连接
     * @returns Promise<boolean> 是否已连接
     */
    public static async isConnected(): Promise<boolean> {
        const networkType = await this.getNetworkType();
        return networkType !== 'none';
    }

    /**
     * 重置网络状态（用于测试或重新检测）
     */
    public static reset(): void {
        this.isReady = false;
        this.readyPromise = null;
    }
}
