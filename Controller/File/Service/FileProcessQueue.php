<?php

namespace Swlib\Controller\File\Service;

use Exception;
use Generate\RouterPath;
use Swlib\Exception\AppErr;
use Swlib\Exception\AppException;
use Swlib\TaskProcess\Attribute\TaskAttribute;
use Swlib\Utils\File as FileUtil;
use Swlib\Utils\Log;
use Throwable;

/**
 * 文件处理队列
 * 负责异步处理文件合并和视频转换等耗时操作
 */
class FileProcessQueue
{
    /**
     * 处理文件合并和转换
     *
     * @param array $params 包含以下参数:
     *   - string fileHash 文件哈希值
     *   - string uploadDir 上传目录
     * @return void
     * @throws Throwable
     */
    #[TaskAttribute]
    public static function processFile(array $params): void
    {
        $fileHash = $params['fileHash'];
        $host = $params['host'];
        $uploadDir = $params['uploadDir'] ?? 'upload';

        try {
            Log::save("开始处理文件: $fileHash", 'file_process');

            // 更新处理状态为处理中
            self::updateProgress($fileHash, 'processing', 10, '开始合并分片...');

            // 创建FileUploader实例
            $uploader = new FileUploader($uploadDir);

            // 调用FileUploader的合并方法
            $finalPath = $uploader->mergeChunksAsync($fileHash);

            // 【修复】从info文件中获取MIME类型，为后续处理做准备
            $infoPath = PUBLIC_DIR . $uploadDir . '/temp/' . $fileHash . '.info';
            if (!file_exists($infoPath)) {
                throw new AppException($infoPath . AppErr::FILE_NOT_FOUND);
            }
            $uploadInfo = json_decode(file_get_contents($infoPath), true);
            $mimeType = $uploadInfo['mime_type'] ?? null;
            if (empty($mimeType)) {
                throw new AppException(AppErr::FILE_MISSING_MIME_TYPE);
            }

            // 更新进度
            self::updateProgress($fileHash, 'processing', 50, '分片合并完成，开始处理文件...');

            // 处理文件（视频转换等）
            $processedPath = $uploader->processUploadedFile($finalPath, $fileHash, $mimeType);

            // 获取原始文件名（从info文件中）
            $originalName = $uploadInfo['file_name'] ?? null;

            // 保存图片信息到数据库
            $imageId = ImageService::saveImage($processedPath, $originalName);

            // 生成通过 read API 访问的 URL
            $url = $host . "/" . RouterPath::FileRead . "?id=$imageId";

            // 更新为完成状态
            self::updateProgress($fileHash, 'completed', 100, '处理完成', [
                'url' => $url,
            ]);

            Log::save("文件处理完成: $fileHash, URL: $url, ImageId: $imageId", 'file_process');

            // [新增] 在所有处理完成后，清理临时文件
            $uploader->cleanupTempFiles($fileHash);

        } catch (Exception $e) {
            // 更新为失败状态
            self::updateProgress($fileHash, 'failed', 0, '处理失败: ' . $e->getMessage());
            Log::saveException($e, 'file_process');
        }
    }

    /**
     * 更新处理进度
     *
     * @param string $fileHash 文件哈希
     * @param string $status 状态: pending, processing, completed, failed
     * @param int $progress 进度百分比 0-100
     * @param string $message 状态消息
     * @param array $result 结果数据（完成时包含url和path）
     */
    public static function updateProgress(string $fileHash, string $status, int $progress, string $message, array $result = []): void
    {
        $progressData = [
            'status' => $status,
            'progress' => $progress,
            'message' => $message,
            'updated_at' => time()
        ];

        if (!empty($result)) {
            $progressData['result'] = $result;
        }

        // 将进度信息保存到文件
        $progressDir = RUNTIME_DIR . 'upload/progress';
        FileUtil::ensureDirectoryExists($progressDir);

        $progressFile = $progressDir . '/' . $fileHash . '.json';
        file_put_contents($progressFile, json_encode($progressData));
    }

    /**
     * 获取处理进度
     *
     * @param string $fileHash 文件哈希
     * @return array|null
     */
    public static function getProgress(string $fileHash): ?array
    {
        $progressFile = RUNTIME_DIR . 'upload/progress/' . $fileHash . '.json';

        if (!file_exists($progressFile)) {
            return null;
        }

        $content = file_get_contents($progressFile);
        return json_decode($content, true);
    }

    /**
     * 清理过期的进度文件
     * 删除超过7天的进度记录
     */
    public static function cleanupProgress(): void
    {
        $progressDir = RUNTIME_DIR . 'upload/progress';

        if (!is_dir($progressDir)) {
            return;
        }

        $expireTime = time() - (7 * 24 * 3600); // 7天
        $files = glob($progressDir . '/*.json');

        foreach ($files as $file) {
            if (filemtime($file) < $expireTime) {
                unlink($file);
            }
        }
    }
} 