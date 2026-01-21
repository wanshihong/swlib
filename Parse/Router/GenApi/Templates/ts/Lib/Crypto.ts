/**
 * 纯 JavaScript 实现的加密工具类
 * 支持 SHA-256 和 HMAC-SHA256
 */

// 声明全局 uni 对象类型（UniApp环境）
declare const uni: any;

/**
 * SHA-256 常量 K
 */
const K: number[] = [
    0x428a2f98, 0x71374491, 0xb5c0fbcf, 0xe9b5dba5, 0x3956c25b, 0x59f111f1, 0x923f82a4, 0xab1c5ed5,
    0xd807aa98, 0x12835b01, 0x243185be, 0x550c7dc3, 0x72be5d74, 0x80deb1fe, 0x9bdc06a7, 0xc19bf174,
    0xe49b69c1, 0xefbe4786, 0x0fc19dc6, 0x240ca1cc, 0x2de92c6f, 0x4a7484aa, 0x5cb0a9dc, 0x76f988da,
    0x983e5152, 0xa831c66d, 0xb00327c8, 0xbf597fc7, 0xc6e00bf3, 0xd5a79147, 0x06ca6351, 0x14292967,
    0x27b70a85, 0x2e1b2138, 0x4d2c6dfc, 0x53380d13, 0x650a7354, 0x766a0abb, 0x81c2c92e, 0x92722c85,
    0xa2bfe8a1, 0xa81a664b, 0xc24b8b70, 0xc76c51a3, 0xd192e819, 0xd6990624, 0xf40e3585, 0x106aa070,
    0x19a4c116, 0x1e376c08, 0x2748774c, 0x34b0bcb5, 0x391c0cb3, 0x4ed8aa4a, 0x5b9cca4f, 0x682e6ff3,
    0x748f82ee, 0x78a5636f, 0x84c87814, 0x8cc70208, 0x90befffa, 0xa4506ceb, 0xbef9a3f7, 0xc67178f2
];

/**
 * SHA-256 初始哈希值
 */
const H: number[] = [
    0x6a09e667, 0xbb67ae85, 0x3c6ef372, 0xa54ff53a,
    0x510e527f, 0x9b05688c, 0x1f83d9ab, 0x5be0cd19
];

/**
 * 右旋转
 */
function rotr(n: number, x: number): number {
    return (x >>> n) | (x << (32 - n));
}

/**
 * SHA-256 中的 Ch 函数
 */
function ch(x: number, y: number, z: number): number {
    return (x & y) ^ (~x & z);
}

/**
 * SHA-256 中的 Maj 函数
 */
function maj(x: number, y: number, z: number): number {
    return (x & y) ^ (x & z) ^ (y & z);
}

/**
 * SHA-256 中的 Sigma0 函数
 */
function sigma0(x: number): number {
    return rotr(2, x) ^ rotr(13, x) ^ rotr(22, x);
}

/**
 * SHA-256 中的 Sigma1 函数
 */
function sigma1(x: number): number {
    return rotr(6, x) ^ rotr(11, x) ^ rotr(25, x);
}

/**
 * SHA-256 中的 gamma0 函数
 */
function gamma0(x: number): number {
    return rotr(7, x) ^ rotr(18, x) ^ (x >>> 3);
}

/**
 * SHA-256 中的 gamma1 函数
 */
function gamma1(x: number): number {
    return rotr(17, x) ^ rotr(19, x) ^ (x >>> 10);
}

/**
 * 将字符串转换为 UTF-8 字节数组
 */
function stringToBytes(str: string): number[] {
    const bytes: number[] = [];
    for (let i = 0; i < str.length; i++) {
        let c = str.charCodeAt(i);
        if (c < 0x80) {
            bytes.push(c);
        } else if (c < 0x800) {
            bytes.push(0xc0 | (c >> 6));
            bytes.push(0x80 | (c & 0x3f));
        } else if (c < 0xd800 || c >= 0xe000) {
            bytes.push(0xe0 | (c >> 12));
            bytes.push(0x80 | ((c >> 6) & 0x3f));
            bytes.push(0x80 | (c & 0x3f));
        } else {
            // 代理对
            i++;
            c = 0x10000 + (((c & 0x3ff) << 10) | (str.charCodeAt(i) & 0x3ff));
            bytes.push(0xf0 | (c >> 18));
            bytes.push(0x80 | ((c >> 12) & 0x3f));
            bytes.push(0x80 | ((c >> 6) & 0x3f));
            bytes.push(0x80 | (c & 0x3f));
        }
    }
    return bytes;
}

/**
 * 将字节数组转换为十六进制字符串
 */
function bytesToHex(bytes: number[]): string {
    let hex = '';
    for (let i = 0; i < bytes.length; i++) {
        hex += (bytes[i] >>> 4).toString(16);
        hex += (bytes[i] & 0xf).toString(16);
    }
    return hex;
}

/**
 * SHA-256 核心计算
 */
function sha256Core(bytes: number[]): number[] {
    // 预处理：添加填充位
    const originalLength = bytes.length;
    const bitLength = originalLength * 8;

    // 添加 1 位（0x80）
    bytes.push(0x80);

    // 添加 0 位直到长度 ≡ 448 (mod 512)
    while ((bytes.length % 64) !== 56) {
        bytes.push(0);
    }

    // 添加原始长度（64位大端序）
    for (let i = 7; i >= 0; i--) {
        bytes.push((bitLength / Math.pow(2, i * 8)) & 0xff);
    }

    // 初始化哈希值
    const hash = H.slice();

    // 处理每个 512 位块
    for (let i = 0; i < bytes.length; i += 64) {
        const w: number[] = new Array(64);

        // 将块分成 16 个 32 位字
        for (let j = 0; j < 16; j++) {
            w[j] = (bytes[i + j * 4] << 24) |
                   (bytes[i + j * 4 + 1] << 16) |
                   (bytes[i + j * 4 + 2] << 8) |
                   bytes[i + j * 4 + 3];
        }

        // 扩展到 64 个字
        for (let j = 16; j < 64; j++) {
            w[j] = (gamma1(w[j - 2]) + w[j - 7] + gamma0(w[j - 15]) + w[j - 16]) >>> 0;
        }

        // 初始化工作变量
        let a = hash[0];
        let b = hash[1];
        let c = hash[2];
        let d = hash[3];
        let e = hash[4];
        let f = hash[5];
        let g = hash[6];
        let h = hash[7];

        // 主循环
        for (let j = 0; j < 64; j++) {
            const t1 = (h + sigma1(e) + ch(e, f, g) + K[j] + w[j]) >>> 0;
            const t2 = (sigma0(a) + maj(a, b, c)) >>> 0;
            h = g;
            g = f;
            f = e;
            e = (d + t1) >>> 0;
            d = c;
            c = b;
            b = a;
            a = (t1 + t2) >>> 0;
        }

        // 更新哈希值
        hash[0] = (hash[0] + a) >>> 0;
        hash[1] = (hash[1] + b) >>> 0;
        hash[2] = (hash[2] + c) >>> 0;
        hash[3] = (hash[3] + d) >>> 0;
        hash[4] = (hash[4] + e) >>> 0;
        hash[5] = (hash[5] + f) >>> 0;
        hash[6] = (hash[6] + g) >>> 0;
        hash[7] = (hash[7] + h) >>> 0;
    }

    // 转换为字节数组
    const result: number[] = [];
    for (let i = 0; i < 8; i++) {
        result.push((hash[i] >>> 24) & 0xff);
        result.push((hash[i] >>> 16) & 0xff);
        result.push((hash[i] >>> 8) & 0xff);
        result.push(hash[i] & 0xff);
    }

    return result;
}

/**
 * 加密工具类
 */
export class Crypto {
    /**
     * 计算字符串的 SHA-256 哈希值
     * @param message 要哈希的消息
     * @returns 十六进制格式的哈希值
     */
    public static sha256(message: string): string {
        const bytes = stringToBytes(message);
        const hash = sha256Core(bytes);
        return bytesToHex(hash);
    }

    /**
     * 计算 HMAC-SHA256
     * @param message 要签名的消息
     * @param key 密钥
     * @returns 十六进制格式的 HMAC 值
     */
    public static hmacSha256(message: string, key: string): string {
        const blockSize = 64; // SHA-256 块大小为 64 字节

        // 将密钥转换为字节数组
        let keyBytes = stringToBytes(key);

        // 如果密钥长度超过块大小，先进行哈希
        if (keyBytes.length > blockSize) {
            keyBytes = sha256Core(keyBytes.slice());
        }

        // 如果密钥长度不足块大小，用 0 填充
        while (keyBytes.length < blockSize) {
            keyBytes.push(0);
        }

        // 计算 ipad 和 opad
        const ipad: number[] = [];
        const opad: number[] = [];
        for (let i = 0; i < blockSize; i++) {
            ipad.push(keyBytes[i] ^ 0x36);
            opad.push(keyBytes[i] ^ 0x5c);
        }

        // 计算内部哈希：H(ipad || message)
        const messageBytes = stringToBytes(message);
        const innerData = ipad.concat(messageBytes);
        const innerHash = sha256Core(innerData);

        // 计算外部哈希：H(opad || innerHash)
        const outerData = opad.concat(innerHash);
        const outerHash = sha256Core(outerData);

        return bytesToHex(outerHash);
    }

    /**
     * 生成 API 签名
     * @param url API 路径
     * @param random 随机数
     * @param timestamp 时间戳
     * @param appSecret 应用密钥
     * @returns 签名字符串
     */
    public static sign(url: string, random: number, timestamp: number, appSecret: string): string {
        const message = `${url}.${random}.${timestamp}`;
        return this.hmacSha256(message, appSecret);
    }
}
