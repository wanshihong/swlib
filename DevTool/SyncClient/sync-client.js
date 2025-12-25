/**
 * TypeScript 代码同步客户端 (Node.js)
 * 
 * 使用方法：
 * node sync-client.js [服务器地址] [输出目录] [选项]
 * 
 * 示例：
 * node sync-client.js http://localhost:9501 ./src/generated
 * node sync-client.js http://localhost:9501 ./network/apis/ --source-dir=apis
 * node sync-client.js http://localhost:9501 ./proto/protos/ --source-dir=proto --flatten
 * 
 * 选项:
 * --source-dir=<目录>  指定要同步的源目录 (相对于服务器源目录)
 * --flatten           将所有文件输出到同一目录 (扁平化)
 * --watch, -w         监听模式
 * 
 * 注意：连接到 Swoole 主服务器的同步路由
 */

const fs = require('fs');
const path = require('path');
const http = require('http');
const https = require('https');

/**
 * 从 Config.ts 文件中读取 HOST 配置
 */
function readHostFromConfig() {
    try {
        const configPath = path.join(__dirname, 'Config.ts');
        if (fs.existsSync(configPath)) {
            const configContent = fs.readFileSync(configPath, 'utf8');
            // 使用正则表达式匹配 HOST 配置
            const hostMatch = configContent.match(/public\s+static\s+HOST\s*=\s*['"](.*?)['"].*?(?:\/\/.*)?$/m);
            if (hostMatch && hostMatch[1]) {
                const host = hostMatch[1].trim();
                console.log(`📝 从 Config.ts 读取服务器地址: ${host}`);
                return host;
            }
        }
    } catch (error) {
        console.warn(`⚠️  读取 Config.ts 失败: ${error.message}，使用默认地址`);
    }
    return 'http://localhost:9501'; // 默认值
}

class TypeScriptSyncClient {
    constructor(serverUrl, outputDir = './src/generated', options = {}) {
        // 如果没有提供 serverUrl 或者 serverUrl 是默认值，则从 Config.ts 读取
        if (!serverUrl || serverUrl === 'http://localhost:9501') {
            serverUrl = readHostFromConfig();
        }
        
        this.serverUrl = serverUrl.replace(/\/$/, ''); // 移除末尾斜杠
        this.outputDir = outputDir;
        this.httpModule = serverUrl.startsWith('https') ? https : http;
        this.sourceDir = options.sourceDir || ''; // 指定要同步的源目录
        this.flatten = options.flatten || false; // 是否扁平化输出
    }

    /**
     * 同步所有文件
     */
    async syncAll() {
        try {
            console.log('🚀 开始同步 TypeScript 代码...');
            console.log(`📡 服务器地址: ${this.serverUrl}`);
            console.log(`📁 输出目录: ${this.outputDir}`);
            if (this.sourceDir) {
                console.log(`📂 源目录筛选: ${this.sourceDir}`);
            }
            if (this.flatten) {
                console.log(`📄 扁平化输出: 是`);
            }
            console.log('─'.repeat(50));

            // 检查服务器状态
            await this.checkServerStatus();

            // 获取所有文件
            const syncData = await this.fetchSyncData();
            
            // 创建输出目录
            this.ensureDirectory(this.outputDir);
            
            // 写入文件
            let successCount = 0;
            for (const file of syncData.files) {
                try {
                    await this.writeFile(file);
                    successCount++;
                    console.log(`✅ ${file.path}`);
                } catch (error) {
                    console.error(`❌ ${file.path}: ${error.message}`);
                }
            }

            console.log('─'.repeat(50));
            console.log(`🎉 同步完成! 成功: ${successCount}/${syncData.files.length} 个文件`);
            console.log(`📊 总大小: ${this.formatBytes(syncData.files.reduce((sum, f) => sum + f.size, 0))}`);
            
        } catch (error) {
            console.error('❌ 同步失败:', error.message);
            process.exit(1);
        }
    }

    /**
     * 检查服务器状态
     */
    async checkServerStatus() {
        try {
            const status = await this.makeRequest('/dev-tool/sync-api/status');
            // 适配服务器响应格式：{"errno":0,"msg":"success","data":{...}}
            if (status.errno !== 0) {
                throw new Error(status.msg || '服务器状态检查失败');
            }
            
            const data = status.data;
            console.log(`✅ 服务器状态: ${data.status}`);
            console.log(`📂 源目录: ${data.source_dir}`);
            console.log(`🐘 PHP 版本: ${data.php_version}`);
            if (data.swoole_version) {
                console.log(`🔥 Swoole 版本: ${data.swoole_version}`);
            }
        } catch (error) {
            throw new Error(`无法连接到服务器: ${error.message}`);
        }
    }

    /**
     * 获取同步数据
     */
    async fetchSyncData() {
        // 构建查询参数
        const params = new URLSearchParams();
        if (this.sourceDir) {
            params.append('source_dir', this.sourceDir);
        }
        if (this.flatten) {
            params.append('flatten', '1');
        }
        
        const endpoint = '/dev-tool/sync-api/run' + (params.toString() ? '?' + params.toString() : '');
        const response = await this.makeRequest(endpoint);
        
        // 适配服务器响应格式：{"errno":0,"msg":"success","data":{...}}
        if (response.errno !== 0) {
            throw new Error(response.msg || '服务器返回同步失败');
        }
        
        const data = response.data;
        if (!data.success) {
            throw new Error('服务器返回同步失败');
        }
        return data;
    }

    /**
     * 写入文件
     */
    async writeFile(file) {
        let targetPath;
        
        if (this.flatten) {
            // 扁平化输出：只使用文件名
            const fileName = path.basename(file.path);
            targetPath = path.join(this.outputDir, fileName);
        } else {
            // 保持目录结构
            targetPath = path.join(this.outputDir, file.path);
        }
        
        const dir = path.dirname(targetPath);
        
        // 确保目录存在
        this.ensureDirectory(dir);
        
        // 写入文件
        fs.writeFileSync(targetPath, file.content, 'utf8');
        
        // 设置修改时间
        const modifiedTime = new Date(file.modified * 1000);
        fs.utimesSync(targetPath, modifiedTime, modifiedTime);
    }

    /**
     * 确保目录存在
     */
    ensureDirectory(dir) {
        if (!fs.existsSync(dir)) {
            fs.mkdirSync(dir, { recursive: true });
        }
    }

    /**
     * 发起 HTTP 请求
     */
    async makeRequest(endpoint) {
        return new Promise((resolve, reject) => {
            const url = new URL(this.serverUrl + endpoint);
            const options = {
                hostname: url.hostname,
                port: url.port,
                path: url.pathname + url.search,
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'User-Agent': 'TypeScript-Sync-Client/1.0'
                }
            };

            const req = this.httpModule.request(options, (res) => {
                let data = '';
                
                res.on('data', (chunk) => {
                    data += chunk;
                });
                
                res.on('end', () => {
                    try {
                        if (res.statusCode >= 200 && res.statusCode < 300) {
                            const jsonData = JSON.parse(data);
                            resolve(jsonData);
                        } else {
                            reject(new Error(`HTTP ${res.statusCode}: ${data}`));
                        }
                    } catch (error) {
                        reject(new Error(`解析响应失败: ${error.message}`));
                    }
                });
            });

            req.on('error', (error) => {
                reject(new Error(`请求失败: ${error.message}`));
            });

            req.setTimeout(30000, () => {
                req.destroy();
                reject(new Error('请求超时'));
            });

            req.end();
        });
    }

    /**
     * 格式化字节大小
     */
    formatBytes(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    /**
     * 监听文件变化 (简单轮询实现)
     */
    async watch(interval = 5000) {
        console.log(`👀 开始监听文件变化 (间隔: ${interval}ms)`);
        console.log('按 Ctrl+C 停止监听');
        
        let lastTimestamp = 0;
        
        const checkChanges = async () => {
            try {
                const response = await this.makeRequest('/dev-tool/sync-api/status');
                // 适配服务器响应格式：{"errno":0,"msg":"success","data":{...}}
                if (response.errno === 0) {
                    const data = response.data;
                    if (data.timestamp > lastTimestamp) {
                        console.log('🔄 检测到文件变化，开始同步...');
                        await this.syncAll();
                        lastTimestamp = data.timestamp;
                    }
                }
            } catch (error) {
                console.error('❌ 检查变化失败:', error.message);
            }
        };

        // 初始同步
        await this.syncAll();
        const statusResponse = await this.makeRequest('/dev-tool/sync-api/status');
        if (statusResponse.errno === 0) {
            lastTimestamp = statusResponse.data.timestamp;
        }

        // 定期检查
        setInterval(checkChanges, interval);
    }
}

// 命令行使用
if (require.main === module) {
    const args = process.argv.slice(2);
    let serverUrl = readHostFromConfig(); // 从 Config.ts 读取默认服务器地址
    let outputDir = './src/generated';
    let sourceDir = '';
    let flatten = false;
    let watchMode = false;
    
    // 解析命令行参数
    for (let i = 0; i < args.length; i++) {
        const arg = args[i];
        
        if (arg.startsWith('--source-dir=')) {
            sourceDir = arg.split('=')[1];
        } else if (arg === '--flatten') {
            flatten = true;
        } else if (arg === '--watch' || arg === '-w') {
            watchMode = true;
        } else if (!arg.startsWith('--')) {
            // 位置参数
            if (i === 0) serverUrl = arg;
            else if (i === 1) outputDir = arg;
        }
    }
    
    const client = new TypeScriptSyncClient(serverUrl, outputDir, {
        sourceDir,
        flatten
    });
    
    if (watchMode) {
        client.watch().catch(console.error);
    } else {
        client.syncAll().catch(console.error);
    }
}

module.exports = TypeScriptSyncClient; 