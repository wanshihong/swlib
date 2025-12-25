#!/usr/bin/env node
/**
 * TypeScript 批量同步脚本
 * 
 * 使用方法：
 * node sync-batch.js
 * 
 * 功能：
 * - 执行一次性同步
 * - 同步完成后自动执行构建命令
 */

const { spawn } = require('child_process');
const path = require('path');
const fs = require('fs');

/**
 * 从 Config.ts 文件中读取 HOST 配置
 */
function readHostFromConfig() {
    try {
        const configPath = path.join(__dirname, 'Config.ts');
        if (fs.existsSync(configPath)) {
            const configContent = fs.readFileSync(configPath, 'utf8');
            // 按行分割，过滤掉注释行和空行，然后重新组合
            const lines = configContent.split('\n')
                .filter(line => {
                    const trimmedLine = line.trim();
                    // 忽略空行和以 // 开头的注释行
                    return trimmedLine && !trimmedLine.startsWith('//');
                })
                .join('\n');
            
            // 使用正则表达式匹配 HOST 配置
            const hostMatch = lines.match(/public\s+static\s+HOST\s*=\s*['"](.*?)['"].*?(?:\/\/.*)?$/m);
            if (hostMatch && hostMatch[1]) {
                const host = hostMatch[1].trim();
                console.log(`\x1b[36m📝 从 Config.ts 读取服务器地址: ${host}\x1b[0m`);
                return host;
            }
        }
    } catch (error) {
        console.warn(`\x1b[33m⚠️  读取 Config.ts 失败: ${error.message}，使用默认地址\x1b[0m`);
    }
    return 'http://localhost:9501'; // 默认值
}

class BatchSyncClient {
    constructor() {
        // 从 Config.ts 读取服务器地址
        const serverHost = readHostFromConfig();
        
        // 同步配置（直接内置在代码中）
        this.syncConfigs = [
            {
                name: 'APIs同步',
                serverUrl: serverHost,
                outputDir: './network/api/',
                sourceDir: 'apis/apps/live',
                flatten: false,
                color: '\x1b[36m' // 青色
            },
            {
                name: 'Proto同步',
                serverUrl: serverHost,
                outputDir: './proto/protos/',
                sourceDir: 'proto',
                flatten: true,
                color: '\x1b[33m' // 黄色
            }
        ];
    }

    /**
     * 启动所有同步任务
     */
    async startAll() {
        console.log('\x1b[32m🚀 启动批量TypeScript代码同步...\x1b[0m');
        console.log('\x1b[90m─'.repeat(60) + '\x1b[0m');
        
        // 检查sync-client.js是否存在
        const clientPath = path.join(__dirname, 'sync-client.js');
        if (!fs.existsSync(clientPath)) {
            console.error('\x1b[31m❌ 找不到 sync-client.js 文件\x1b[0m');
            process.exit(1);
        }
        
        try {
            // 顺序执行每个同步任务
            for (const config of this.syncConfigs) {
                await this.startSyncProcess(config);
            }
            
            console.log('\x1b[90m─'.repeat(60) + '\x1b[0m');
            console.log('\x1b[32m✅ 所有同步任务已完成\x1b[0m');
            console.log('\x1b[36m🔨 开始执行构建命令...\x1b[0m');
            console.log('\x1b[90m─'.repeat(60) + '\x1b[0m');
            
            // 执行构建命令
            await this.runBuildCommands();
            
            console.log('\x1b[90m─'.repeat(60) + '\x1b[0m');
            console.log('\x1b[32m🎉 所有任务已完成！\x1b[0m');
            
        } catch (error) {
            console.error('\x1b[31m❌ 执行失败:', error.message, '\x1b[0m');
            process.exit(1);
        }
    }

    /**
     * 启动单个同步进程
     */
    async startSyncProcess(config) {
        return new Promise((resolve, reject) => {
            const args = [
                'sync-client.js',
                config.serverUrl,
                config.outputDir,
                `--source-dir=${config.sourceDir}`
            ];
            
            if (config.flatten) {
                args.push('--flatten');
            }
            
            console.log(`${config.color}🔄 启动 ${config.name}...\x1b[0m`);
            console.log(`${config.color}   命令: node ${args.join(' ')}\x1b[0m`);
            
            const child = spawn('node', args, {
                stdio: 'pipe',
                cwd: __dirname
            });
            
            // 处理输出
            child.stdout.on('data', (data) => {
                const lines = data.toString().split('\n').filter(line => line.trim());
                lines.forEach(line => {
                    console.log(`${config.color}[${config.name}]\x1b[0m ${line}`);
                });
            });
            
            child.stderr.on('data', (data) => {
                const lines = data.toString().split('\n').filter(line => line.trim());
                lines.forEach(line => {
                    console.error(`${config.color}[${config.name}]\x1b[31m ERROR:\x1b[0m ${line}`);
                });
            });
            
            child.on('close', (code) => {
                if (code === 0) {
                    console.log(`${config.color}[${config.name}]\x1b[32m ✅ 同步完成\x1b[0m`);
                    resolve();
                } else {
                    console.error(`${config.color}[${config.name}]\x1b[31m ❌ 同步失败，退出代码: ${code}\x1b[0m`);
                    reject(new Error(`${config.name} 同步失败`));
                }
            });
            
            child.on('error', (error) => {
                console.error(`${config.color}[${config.name}]\x1b[31m 启动失败: ${error.message}\x1b[0m`);
                reject(error);
            });
        });
    }

    /**
     * 执行构建命令
     */
    async runBuildCommands() {
        const commands = [
            'npm run build-proto:pbjs',
            'npm run build-proto:pbts'
        ];

        for (const command of commands) {
            try {
                console.log(`\x1b[36m🔨 执行: ${command}\x1b[0m`);
                await this.runCommand(command);
                console.log(`\x1b[32m✅ ${command} 执行完成\x1b[0m`);
            } catch (error) {
                console.error(`\x1b[31m❌ ${command} 执行失败: ${error.message}\x1b[0m`);
                throw error;
            }
        }
    }

    /**
     * 执行单个命令
     */
    async runCommand(command) {
        return new Promise((resolve, reject) => {
            const [cmd, ...args] = command.split(' ');
            
            const child = spawn(cmd, args, {
                stdio: 'pipe',
                cwd: __dirname,
                shell: true
            });
            
            let output = '';
            let errorOutput = '';
            
            child.stdout.on('data', (data) => {
                const text = data.toString();
                output += text;
                // 实时显示输出
                const lines = text.split('\n').filter(line => line.trim());
                lines.forEach(line => {
                    console.log(`\x1b[90m   ${line}\x1b[0m`);
                });
            });
            
            child.stderr.on('data', (data) => {
                const text = data.toString();
                errorOutput += text;
                // 实时显示错误输出
                const lines = text.split('\n').filter(line => line.trim());
                lines.forEach(line => {
                    console.error(`\x1b[31m   ${line}\x1b[0m`);
                });
            });
            
            child.on('close', (code) => {
                if (code === 0) {
                    resolve({ output, errorOutput });
                } else {
                    reject(new Error(`命令退出码: ${code}, 错误输出: ${errorOutput}`));
                }
            });
            
            child.on('error', (error) => {
                reject(error);
            });
        });
    }
}

// 主程序
if (require.main === module) {
    const batchClient = new BatchSyncClient();
    
    // 显示帮助信息
    if (process.argv.includes('--help') || process.argv.includes('-h')) {
        console.log(`
\x1b[32mTypeScript 批量同步脚本\x1b[0m

\x1b[36m使用方法:\x1b[0m
  node sync-batch.js                启动一次性同步任务
  node sync-batch.js --help         显示帮助信息

\x1b[36m功能:\x1b[0m
  1. 从 Config.ts 自动读取服务器地址
  2. 顺序执行同步任务（APIs → Proto）
  3. 同步完成后自动执行构建命令：
     - npm run build-proto:pbjs
     - npm run build-proto:pbts

\x1b[36m同步配置:\x1b[0m`);
        
        batchClient.syncConfigs.forEach((config, index) => {
            console.log(`  ${index + 1}. ${config.name}`);
            console.log(`     服务器: ${config.serverUrl}`);
            console.log(`     输出目录: ${config.outputDir}`);
            console.log(`     源目录: ${config.sourceDir}`);
            console.log(`     扁平化: ${config.flatten ? '是' : '否'}`);
        });
        
        process.exit(0);
    }
    
    // 启动批量同步
    batchClient.startAll().catch(error => {
        console.error('\x1b[31m💥 执行失败:', error.message, '\x1b[0m');
        process.exit(1);
    });
}

module.exports = BatchSyncClient; 