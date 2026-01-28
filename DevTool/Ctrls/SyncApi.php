<?php

namespace Swlib\DevTool\Ctrls;

use Exception;
use Generate\ConfigEnum;
use Swlib\Controller\Abstract\AbstractController;
use Swlib\Exception\AppErr;
use Swlib\Exception\AppException;
use Swlib\Response\JsonResponse;
use Swlib\Router\Router;
use Throwable;

/**
 * 项目会自动创建 一些 proto 文件  和 API 路由调用方法
 * 前台调用 这个API 文件可以把   proto 和 API 同步到前台直接使用
 */
class SyncApi extends AbstractController
{
    private static ?string $sourceDir = null;
    private static array $allowedExtensions = ['.ts', '.js', '.json', '.md', '.proto', '.dart'];
    private static bool $initialized = false;

    /**
     * 获取服务状态
     * GET /dev-tool/sync-api/status
     * @throws Throwable
     */
    #[Router(method: 'GET')]
    public function status(): JsonResponse
    {
        try {
            if (!$this->checkDevEnvironment()) {
                throw new AppException(AppErr::DEV_ONLY_DEV_ENV);
            }

            $this->ensureInitialized();

            return JsonResponse::success([
                'status' => 'running',
                'source_dir' => self::$sourceDir,
                'timestamp' => time(),
                'php_version' => PHP_VERSION,
                'swoole_version' => SWOOLE_VERSION,
                'allowed_extensions' => self::$allowedExtensions
            ]);
        } catch (Throwable $e) {
            return JsonResponse::error($e);
        }
    }

    /**
     * 获取文件列表
     * GET /dev-tool/sync-api/files
     * @throws Throwable
     */
    #[Router(method: 'GET')]
    public function files(): JsonResponse
    {
        try {

            if (!$this->checkDevEnvironment()) {
                throw new AppException(AppErr::DEV_ONLY_DEV_ENV);
            }

            $this->ensureInitialized();
            $files = $this->scanDirectory(self::$sourceDir);

            return JsonResponse::success(['files' => $files]);
        } catch (Exception $e) {
            return JsonResponse::error($e);
        }
    }

    /**
     * 获取指定文件内容
     * GET /dev-tool/sync-api/file
     * @throws Throwable
     */
    #[Router(method: 'GET')]
    public function file(): JsonResponse
    {
        try {

            if (!$this->checkDevEnvironment()) {
                throw new AppException(AppErr::DEV_ONLY_DEV_ENV);
            }

            $path = $this->get('path');
            if (empty($path)) {
                throw new AppException(AppErr::DEV_FILE_PATH_EMPTY);
            }

            $this->ensureInitialized();
            $fileData = $this->getFileContent($path);

            if ($fileData === null) {
                throw new AppException(AppErr::DEV_FILE_NOT_ACCESSIBLE);
            }

            return JsonResponse::success($fileData);
        } catch (Exception $e) {
            return JsonResponse::error($e);
        }
    }

    /**
     * 同步所有文件
     * GET /dev-tool/sync-api/run
     * 支持参数:
     * - source_dir: 指定要同步的源目录 (相对于源目录的路径)
     * - flatten: 是否扁平化输出 (1=是, 0=否)
     * @throws Throwable
     */
    #[Router(method: 'GET')]
    public function run(): JsonResponse
    {
        try {
            if (!$this->checkDevEnvironment()) {
                throw new AppException(AppErr::DEV_ONLY_DEV_ENV);
            }

            $this->ensureInitialized();

            // 获取查询参数
            $sourceDir = trim($this->get('source_dir', '', ''));
            $flatten = $this->get('flatten', '', '0') === '1';

            // 确定扫描目录
            $scanDir = self::$sourceDir;
            if (!empty($sourceDir)) {
                $targetDir = self::$sourceDir . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $sourceDir);
                $targetDir = realpath($targetDir);

                if (!$targetDir || !is_dir($targetDir)) {
                    throw new AppException(AppErr::DEV_SOURCE_DIR_NOT_EXIST_WITH_NAME, $sourceDir);
                }

                // 安全检查：确保目标目录在源目录内
                if (!str_starts_with($targetDir, realpath(self::$sourceDir))) {
                    throw new AppException(AppErr::DEV_SOURCE_DIR_OUT_OF_RANGE_WITH_NAME, $sourceDir);
                }

                $scanDir = $targetDir;
            }

            $files = $this->scanDirectory($scanDir);
            $syncData = [];

            foreach ($files as $file) {
                $filePath = $scanDir . DIRECTORY_SEPARATOR . $file;
                if ($this->isValidFile($filePath)) {
                    // 处理相对路径
                    if ($flatten) {
                        // 扁平化：只保留文件名
                        $relativePath = basename($file);
                    } elseif (!empty($sourceDir)) {
                        // 如果指定了源目录，保持相对于指定目录的路径结构
                        $relativePath = $file;
                    } else {
                        // 默认情况：保持完整的相对路径
                        $relativePath = str_replace(self::$sourceDir . DIRECTORY_SEPARATOR, '', $filePath);
                        $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);
                    }

                    $syncData[] = [
                        'path' => $relativePath,
                        'content' => file_get_contents($filePath),
                        'size' => filesize($filePath),
                        'modified' => filemtime($filePath)
                    ];
                }
            }

            return JsonResponse::success([
                'success' => true,
                'files' => $syncData,
                'count' => count($syncData),
                'timestamp' => time(),
                'source_dir' => $sourceDir,
                'flatten' => $flatten,
                'scan_dir' => $scanDir
            ]);
        } catch (Exception $e) {
            return JsonResponse::error($e);
        }
    }

    /**
     * 同步服务首页
     * GET /dev-tool/sync-api/index
     * @throws Throwable
     */
    #[Router(method: 'GET')]
    public function index(): void
    {
        if (!$this->checkDevEnvironment()) {
            $this->response->header('Content-Type', 'text/html; charset=utf-8');
            $this->response->end('<h1>TypeScript 同步服务仅在开发环境下可用</h1>');
            return;
        }

        $this->response->header('Content-Type', 'text/html; charset=utf-8');
        $this->response->end($this->getIndexHtml());
    }

    /**
     * 检查开发环境
     */
    private function checkDevEnvironment(): bool
    {
        return ConfigEnum::APP_PROD === false;
    }

    /**
     * 确保已初始化
     * @throws Exception
     */
    private function ensureInitialized(): void
    {
        if (!self::$initialized) {
            self::$sourceDir = $this->getSourceDirectory();
            self::$initialized = true;
        }
    }

    /**
     * 获取源目录
     * @throws Exception
     */
    private function getSourceDirectory(): string
    {
        // 统一使用 runtime/codes 作为根目录，支持 ts/flutter 等子目录
        $defaultPaths = [
            ROOT_DIR . 'runtime/codes',
        ];

        foreach ($defaultPaths as $path) {
            if (is_dir($path)) {
                return realpath($path);
            }
        }

        throw new Exception(AppErr::DEV_SYNC_SOURCE_NOT_FOUND);
    }

    /**
     * 扫描目录
     */
    private function scanDirectory(string $dir, string $prefix = ''): array
    {
        $files = [];
        $items = scandir($dir);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $fullPath = $dir . DIRECTORY_SEPARATOR . $item;
            $relativePath = $prefix . $item;

            if (is_dir($fullPath)) {
                $files = array_merge($files, $this->scanDirectory($fullPath, $relativePath . '/'));
            } elseif ($this->isAllowedFile($item)) {
                $files[] = $relativePath;
            }
        }

        return $files;
    }

    /**
     * 检查是否为允许的文件
     */
    private function isAllowedFile(string $filename): bool
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array('.' . $extension, self::$allowedExtensions);
    }

    /**
     * 检查文件是否有效
     */
    private function isValidFile(string $filePath): bool
    {
        return file_exists($filePath) &&
            is_file($filePath) &&
            str_starts_with(realpath($filePath), realpath(self::$sourceDir)) &&
            $this->isAllowedFile(basename($filePath));
    }

    /**
     * 获取文件内容
     */
    private function getFileContent(string $path): ?array
    {
        $filePath = self::$sourceDir . DIRECTORY_SEPARATOR . $path;

        if (!$this->isValidFile($filePath)) {
            return null;
        }

        return [
            'path' => $path,
            'content' => file_get_contents($filePath),
            'size' => filesize($filePath),
            'modified' => filemtime($filePath)
        ];
    }

    /**
     * 获取首页 HTML
     */
    private function getIndexHtml(): string
    {
        $port = ConfigEnum::PORT;

        return <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TypeScript 代码同步服务 (Swoole 路由模式)</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #007acc; padding-bottom: 10px; }
        .api-list { background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0; }
        .api-item { margin: 10px 0; padding: 10px; background: white; border-left: 4px solid #007acc; }
        .method { font-weight: bold; color: #007acc; }
        .url { font-family: monospace; background: #e9ecef; padding: 2px 6px; border-radius: 3px; }
        .param { color: #6c757d; font-size: 0.9em; }
        .status { background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin: 20px 0; }
        .swoole-badge { background: #28a745; color: white; padding: 4px 8px; border-radius: 3px; font-size: 12px; }
        .new-badge { background: #fd7e14; color: white; padding: 2px 6px; border-radius: 3px; font-size: 10px; margin-left: 5px; }
        button { background: #007acc; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; margin: 5px; }
        button:hover { background: #005a9e; }
        .input-group { margin: 10px 0; }
        .input-group label { display: inline-block; min-width: 120px; }
        .input-group input, .input-group select { margin-left: 10px; padding: 5px; }
        #result { background: #f8f9fa; padding: 15px; border-radius: 5px; margin-top: 20px; white-space: pre-wrap; font-family: monospace; }
        .example { background: #e7f3ff; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .example h4 { margin-top: 0; color: #0056b3; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 TypeScript 代码同步服务 <span class="swoole-badge">路由模式</span></h1>
        
        <div class="status">
            ✅ 服务器运行正常 - 端口: $port | 🔥 基于 Swoole 主服务器路由
        </div>
        
        <h2>📡 API 接口</h2>
        <div class="api-list">
            <div class="api-item">
                <span class="method">GET</span> <span class="url">/dev-tool/sync-api/status</span> - 获取服务状态
            </div>
            <div class="api-item">
                <span class="method">GET</span> <span class="url">/dev-tool/sync-api/files</span> - 获取所有文件列表
            </div>
            <div class="api-item">
                <span class="method">GET</span> <span class="url">/dev-tool/sync-api/file?path={path}</span> - 获取指定文件内容
            </div>
            <div class="api-item">
                <span class="method">GET</span> <span class="url">/dev-tool/sync-api/run</span> - 同步所有文件 <span class="new-badge">增强</span>
                <div class="param">
                    • source_dir: 指定要同步的源目录 (可选)<br>
                    • flatten: 是否扁平化输出 (1=是, 0=否, 可选)
                </div>
            </div>
        </div>
        
        <div class="example">
            <h4>🎯 使用示例</h4>
            <strong>客户端命令:</strong><br>
            <code># 同步所有文件</code><br>
            <code>node sync-client.js http://localhost:$port ./src/generated</code><br><br>
            
            <code># 只同步 apis 目录</code><br>
            <code>node sync-client.js http://localhost:$port ./src/apis --source-dir=apis</code><br><br>
            
            <code># 同步 proto 目录并扁平化输出</code><br>
            <code>node sync-client.js http://localhost:$port ./src/proto --source-dir=proto --flatten</code><br><br>
            
            <strong>API 调用:</strong><br>
            <code>GET /dev-tool/sync-api/run?source_dir=apis</code><br>
            <code>GET /dev-tool/sync-api/run?source_dir=proto&flatten=1</code>
        </div>
        
        <h2>🧪 测试接口</h2>
        <button onclick="testApi('/dev-tool/sync-api/status')">测试状态</button>
        <button onclick="testApi('/dev-tool/sync-api/files')">获取文件列表</button>
        <button onclick="testApi('/dev-tool/sync-api/run')">同步所有文件</button>
        
        <div style="margin-top: 20px;">
            <h3>📂 高级同步测试</h3>
            <div class="input-group">
                <label>源目录:</label>
                <input type="text" id="sourceDir" placeholder="如: apis, proto, models" />
            </div>
            <div class="input-group">
                <label>扁平化输出:</label>
                <select id="flatten">
                    <option value="0">否</option>
                    <option value="1">是</option>
                </select>
            </div>
            <button onclick="testAdvancedSync()">测试高级同步</button>
        </div>
        
        <div id="result"></div>
    </div>
    
    <script>
        async function testApi(url) {
            const resultDiv = document.getElementById('result');
            resultDiv.textContent = '请求中...';
            
            try {
                const response = await fetch(url);
                const data = await response.json();
                resultDiv.textContent = JSON.stringify(data, null, 2);
            } catch (error) {
                resultDiv.textContent = '错误: ' + error.message;
            }
        }
        
        async function testAdvancedSync() {
            const sourceDir = document.getElementById('sourceDir').value;
            const flatten = document.getElementById('flatten').value;
            
            const params = new URLSearchParams();
            if (sourceDir) params.append('source_dir', sourceDir);
            if (flatten === '1') params.append('flatten', '1');
            
            const url = '/dev-tool/sync-api/run' + (params.toString() ? '?' + params.toString() : '');
            await testApi(url);
        }
    </script>
</body>
</html>
HTML;
    }
}