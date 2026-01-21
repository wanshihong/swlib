/**
 * 缓存管理器
 * 提供本地存储的缓存管理功能，支持过期时间和哈希表操作
 */

// 声明全局 uni 对象类型（UniApp环境）
declare const uni: {
    getAppBaseInfo(): { appVersionCode?: string };
    setStorage(options: { key: string; data: string }): void;
    getStorageSync(key: string): string;
    removeStorageSync(key: string): void;
    clearStorage(): void;
};

/**
 * 缓存数据接口定义
 */
interface CacheData<T = any> {
    /** 存储的数据 */
    data: T;
    /** 过期时间戳（秒） */
    expired: number;
}

/**
 * 队列接口定义
 */
interface Queue<T = any> {
    /** 定时器ID */
    timer: ReturnType<typeof setTimeout> | null;
    /** 数据队列 */
    data: Map<string, T>;
}

// 获取应用版本号
const appVersion = uni.getAppBaseInfo?.()?.appVersionCode || '1';

// 默认过期时间（一年，单位：秒）
const DEFAULT_EXPIRE_TIME = 86400 * 365;

// 队列处理延迟时间（毫秒）
const QUEUE_DELAY = 300;

// 轮询间隔时间（毫秒）
const POLL_INTERVAL = 100;

/**
 * 获取带应用版本号的缓存键
 * @param key 原始键名
 * @returns 格式化后的键名
 */
const getKey = (key: string): string => `${key}:${appVersion}`;

/**
 * 缓存管理器
 * 提供本地存储的缓存管理功能，支持过期时间和哈希表操作
 */
export class CacheMgr {
    /** 哈希表操作队列 */
    private static _hSetQueue: Map<string, Queue> = new Map();

    /**
     * 获取当前时间戳（秒）
     * @returns 当前时间戳（秒）
     */
    private static getCurrentTimestamp(): number {
        return Math.floor(Date.now() / 1000);
    }

    /**
     * 计算过期时间
     * @param expired 过期时间（秒）
     * @returns 过期时间戳
     */
    private static getExpireTime(expired?: number): number {
        return this.getCurrentTimestamp() + (expired ?? DEFAULT_EXPIRE_TIME);
    }

    /**
     * 创建缓存数据对象
     * @param data 要存储的数据
     * @param expired 过期时间（秒）
     * @returns 缓存数据对象
     */
    private static createCacheData<T>(data: T, expired?: number): CacheData<T> {
        return {
            data,
            expired: this.getExpireTime(expired)
        };
    }

    /**
     * 检查数据是否过期
     * @param data 缓存数据
     * @returns 是否有效（未过期）
     */
    private static isValid(data: CacheData): boolean {
        return data.expired > this.getCurrentTimestamp();
    }

    /**
     * 设置缓存数据
     * @param key 缓存键名
     * @param val 要存储的值
     * @param expired 过期时间（秒），默认为一年
     */
    public static set<T>(key: string, val: T, expired?: number): void {
        const cacheKey = getKey(key);
        const data = this.createCacheData(val, expired);

        try {
            uni.setStorage({
                key: cacheKey,
                data: JSON.stringify(data)
            });
        } catch (error) {
            console.error('CacheMgr.set error:', error);
        }
    }

    /**
     * 获取缓存数据
     * @param key 缓存键名
     * @param isDel 获取后是否删除该缓存，默认为false
     * @returns 缓存的数据，如果不存在或已过期则返回null
     */
    public static get<T = any>(key: string, isDel: boolean = false): T | null {
        const cacheKey = getKey(key);
        let cacheStr: string;

        try {
            cacheStr = uni.getStorageSync(cacheKey);
        } catch (error) {
            console.error('CacheMgr.get error when reading storage:', error);
            return null;
        }

        if (!cacheStr) return null;

        try {
            const data = JSON.parse(cacheStr) as CacheData<T>;
            if (this.isValid(data)) {
                if (isDel) {
                    this.del(key);
                }
                return data.data;
            } else {
                // 数据已过期，自动删除
                this.del(key);
            }
        } catch (error) {
            console.error('CacheMgr.get error when parsing JSON:', error);
            // 数据格式错误，删除无效数据
            this.del(key);
        }
        return null;
    }

    /**
     * 删除指定的缓存数据
     * @param key 要删除的缓存键名
     */
    public static del(key: string): void {
        try {
            uni.removeStorageSync(getKey(key));
        } catch (error) {
            console.error('CacheMgr.del error:', error);
        }
    }

    /**
     * 清除所有缓存数据
     */
    public static clear(): void {
        try {
            uni.clearStorage();
        } catch (error) {
            console.error('CacheMgr.clear error:', error);
        }
    }

    /**
     * 同步设置哈希表中的字段值
     * @param key 哈希表的键名
     * @param field 要设置的字段名
     * @param val 要设置的值
     * @param expired 可选的过期时间（秒），默认为一年
     */
    public static hSet<T = any>(key: string, field: string, val: T, expired?: number): void {
        // 获取或创建队列
        let queue = this._hSetQueue.get(key) || {
            timer: null,
            data: new Map<string, T>()
        };

        // 设置字段值
        queue.data.set(field, val);

        // 清除现有定时器
        if (queue.timer) {
            clearTimeout(queue.timer);
        }

        // 设置新的定时器，延迟批量处理
        queue.timer = setTimeout(() => {
            // 获取现有哈希数据或创建新的
            const existingData = this.get<Record<string, T>>(key) || {};

            // 合并新数据
            const dataEntries = queue.data.entries();
            let entry = dataEntries.next();

            while (!entry.done) {
                const [field, value] = entry.value;
                existingData[field] = value;
                entry = dataEntries.next();
            }

            // 保存合并后的数据
            this.set(key, existingData, expired);

            // 清理队列
            this._hSetQueue.delete(key);
        }, QUEUE_DELAY);

        // 更新队列
        this._hSetQueue.set(key, queue);
    }

    /**
     * 获取哈希表中的字段值
     * @param key 哈希表的键名
     * @param field 要获取的字段名，如果为undefined则返回整个哈希表
     * @param isDel 获取后是否删除该缓存，默认为false
     * @returns Promise，解析为字段的值或整个哈希表，如果不存在或已过期则返回null
     */
    public static hGet<T = any>(key: string, field?: string, isDel: boolean = false): Promise<T | null> {
        return new Promise(resolve => {
            // 尝试获取数据
            const result = this._hGet<T>(key, field, isDel);

            // 如果返回false，表示有待处理的队列数据
            if (result === false) {
                // 轮询等待队列处理完成
                const timer = setInterval(() => {
                    const data = this._hGet<T>(key, field, isDel);
                    if (data !== false) {
                        clearInterval(timer);
                        resolve(data);
                    }
                }, POLL_INTERVAL);
            } else {
                resolve(result);
            }
        });
    }

    /**
     * 内部方法：获取哈希表中的字段值
     * @param key 哈希表的键名
     * @param field 要获取的字段名，如果为undefined则返回整个哈希表
     * @param isDel 获取后是否删除该缓存
     * @returns 字段的值、整个哈希表或false（表示有待处理的队列）
     */
    private static _hGet<T = any>(key: string, field?: string, isDel: boolean = false): T | null | false {
        // 检查是否有待处理的队列
        const queue = this._hSetQueue.get(key);

        // 如果队列不存在或队列中没有数据，直接从存储中获取
        if (!queue || queue.data.size === 0) {
            const data = this.get<Record<string, T>>(key, isDel);

            // 如果数据不存在，返回null
            if (!data) return null;

            // 根据field参数返回整个哈希表或特定字段
            if (field === undefined) {
                return data as unknown as T;
            } else {
                return data[field] || null;
            }
        }

        // 有待处理的队列数据，返回false
        return false;
    }

    /**
     * 删除哈希表中的字段
     * @param key 哈希表的键名
     * @param field 要删除的字段名
     * @returns Promise，解析为布尔值，表示删除操作是否成功
     */
    public static hDel(key: string, field: string): Promise<boolean> {
        return new Promise(resolve => {
            // 检查是否有待处理的队列
            const queue = this._hSetQueue.get(key);

            // 如果有待处理的队列，先等待队列处理完成
            if (queue && queue.data.size > 0) {
                // 如果队列中有要删除的字段，直接从队列中删除
                if (queue.data.has(field)) {
                    queue.data.delete(field);
                }

                // 轮询等待队列处理完成
                const timer = setInterval(() => {
                    const queue = this._hSetQueue.get(key);
                    if (!this._hSetQueue.has(key) || !queue || queue.data.size === 0) {
                        clearInterval(timer);
                        // 队列处理完成后，执行删除操作
                        this._performHDel(key, field, resolve);
                    }
                }, POLL_INTERVAL);
            } else {
                // 没有待处理的队列，直接执行删除操作
                this._performHDel(key, field, resolve);
            }
        });
    }

    /**
     * 内部方法：执行哈希表字段删除操作
     * @param key 哈希表的键名
     * @param field 要删除的字段名
     * @param resolve Promise的resolve函数
     */
    private static _performHDel(key: string, field: string, resolve: (value: boolean) => void): void {
        try {
            // 获取哈希表数据
            const data = this.get<Record<string, any>>(key);

            // 如果数据不存在，返回false
            if (!data) {
                resolve(false);
                return;
            }

            // 如果字段不存在，返回false
            if (!(field in data)) {
                resolve(false);
                return;
            }

            // 删除字段
            delete data[field];

            // 保存更新后的数据
            this.set(key, data);

            // 返回成功
            resolve(true);
        } catch (error) {
            console.error('CacheMgr.hDel error:', error);
            resolve(false);
        }
    }
}
