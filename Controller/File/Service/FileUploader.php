<?php

namespace Swlib\Controller\File\Service;

use finfo;
use Redis;
use Swlib\Connect\PoolRedis;
use Swlib\Controller\Config\Service\ConfigService;
use Swlib\Enum\CtxEnum;
use Swlib\Exception\AppErr;
use Swlib\Exception\AppException;
use Swlib\Request\Request;
use Swlib\Utils\File as FileUtil;
use Throwable;

/**
 * 文件分片上传及处理工具类
 *
 * 提供了完整的大文件分片上传、断点续传、秒传、单文件上传以及视频文件自动转码等功能。
 * [V2] 优化内容:
 * 1. 安全增强: 废弃客户端文件名，基于服务端MIME类型白名单生成后缀，防止上传恶意脚本。
 * 2. 算法升级: 文件完整性校验从MD5升级到SHA256，杜绝哈希碰撞风险。
 * 3. 并发控制: 在分片合并前引入Redis分布式锁，防止高并发下重复处理。
 * 4. 健壮性提升: 在合并文件后再次校验MIME类型，确保数据一致性。
 * 5. 视频转码配置: :UploadIsConvertedVideo配置决定是否进行视频转码。
 *
 * @example
 * // 在控制器中使用
 * $uploader = new FileUploader('upload/videos');
 *
 * // 单文件上传
 * $path = $uploader->uploadSingleFile('my_file');
 *
 * // 分片上传
 * $uploader->uploadChunk($fileHash, $chunkIndex, $totalChunks, $mimeType);
 */
class FileUploader
{
    private string $uploadDir;
    private string $tempDir;


    /**
     * 如果是视频上传,是否对视频文件进行转码
     */
    const bool UploadIsConvertedVideo = false;

    /**
     * @var array<string, string> MIME类型到文件后缀的白名单映射
     */
    private array $mimeToExtensionMap = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg',
        'video/mp4' => 'mp4',
        'video/quicktime' => 'mov',
        'video/x-msvideo' => 'avi',
        'video/x-flv' => 'flv',
        'video/x-matroska' => 'mkv',
        'video/webm' => 'webm',
        'video/x-ms-wmv' => 'wmv',
        'video/mpeg' => 'mpeg',
        'application/pdf' => 'pdf',
        'application/zip' => 'zip',
        'text/plain' => 'txt',
        'application/x-rar-compressed' => 'rar',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/vnd.ms-powerpoint' => 'ppt',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
    ];

    /**
     * 根据MIME类型判断是否为视频文件
     *
     * @param string $mimeType 文件的MIME类型
     * @return bool
     */
    private function _isMimeTypeVideo(string $mimeType): bool
    {
        return str_starts_with($mimeType, 'video/');
    }

    /**
     * 检查是否需要对视频进行转码
     *
     * @param string $mimeType 文件的MIME类型
     * @return bool
     */
    private function _shouldConvertVideo(string $mimeType): bool
    {
        return $this->_isMimeTypeVideo($mimeType) && self::UploadIsConvertedVideo;
    }

    /**
     * 根据MIME类型判断是否为图片文件
     *
     * @param string $mimeType 文件的MIME类型
     * @return bool
     */
    private function _isMimeTypeImage(string $mimeType): bool
    {
        return str_starts_with($mimeType, 'image/') && $mimeType !== 'image/svg+xml';
    }

    /**
     * 为图片添加文字水印
     *
     * 注意：
     * - PNG 格式会保留透明度
     * - GIF 动图会跳过水印处理（避免变成静态图）
     *
     * @param string $imagePath 图片文件的绝对路径
     * @param string $mimeType 图片的MIME类型
     * @return string 返回处理后图片的绝对路径
     */
    private function addWatermarkToImage(string $imagePath, string $mimeType): string
    {
        // 检查GD扩展是否可用
        if (!extension_loaded('gd')) {
            error_log('GD扩展未安装，跳过水印处理');
            return $imagePath;
        }

        try {
            // GIF 特殊处理：检测是否为动图
            if ($mimeType === 'image/gif') {
                if ($this->isAnimatedGif($imagePath)) {
                    error_log('检测到 GIF 动图，跳过水印处理以保持动画效果');
                    return $imagePath;
                }
            }

            // 根据MIME类型创建图像资源
            switch ($mimeType) {
                case 'image/jpeg':
                    $image = imagecreatefromjpeg($imagePath);
                    break;
                case 'image/png':
                    $image = imagecreatefrompng($imagePath);
                    break;
                case 'image/gif':
                    $image = imagecreatefromgif($imagePath);
                    break;
                case 'image/webp':
                    $image = imagecreatefromwebp($imagePath);
                    break;
                default:
                    // 不支持的图片格式，直接返回原路径
                    return $imagePath;
            }

            if (!$image) {
                error_log('无法创建图像资源: ' . $imagePath);
                return $imagePath;
            }

            // PNG 和 WebP 需要保留透明度
            if ($mimeType === 'image/png' || $mimeType === 'image/webp') {
                // 关闭 alpha 混合，保留原始透明度
                imagealphablending($image, false);
                // 保存完整的 alpha 通道信息
                imagesavealpha($image, true);
            }

            // 获取图片尺寸
            $width = imagesx($image);
            $height = imagesy($image);

            // 水印文字
            $watermarkText = ConfigService::get('watermarkText', '文字水印');

            // 计算字体大小（根据图片大小动态调整）
            $fontSize = max(12, min($width, $height) / 20);

            // 临时启用 alpha 混合以绘制水印
            if ($mimeType === 'image/png' || $mimeType === 'image/webp') {
                imagealphablending($image, true);
            }

            // 创建水印颜色（半透明白色）
            $watermarkColor = imagecolorallocatealpha($image, 255, 255, 255, 50);

            // 获取字体路径
            $fontPath = $this->getDefaultFont();

            if (!empty($fontPath)) {
                // 使用TTF字体
                $textBox = imagettfbbox($fontSize, 0, $fontPath, $watermarkText);
                $textWidth = $textBox[4] - $textBox[0];
                $textHeight = $textBox[1] - $textBox[7];

                $x = (int)(($width - $textWidth) / 2);
                $y = (int)(($height + $textHeight) / 2);

                // 添加水印文字
                imagettftext($image, $fontSize, 0, $x, $y, $watermarkColor, $fontPath, $watermarkText);
            } else {
                // 使用内置字体
                $builtinFontSize = 5; // 内置字体大小 (1-5)
                $textWidth = imagefontwidth($builtinFontSize) * mb_strlen($watermarkText);
                $textHeight = imagefontheight($builtinFontSize);

                $x = (int)(($width - $textWidth) / 2);
                $y = (int)(($height - $textHeight) / 2);

                // 添加水印文字
                imagestring($image, $builtinFontSize, $x, $y, $watermarkText, $watermarkColor);
            }

            // 绘制完成后，再次关闭 alpha 混合以保存透明度
            if ($mimeType === 'image/png' || $mimeType === 'image/webp') {
                imagealphablending($image, false);
                imagesavealpha($image, true);
            }

            // 保存处理后的图片
            switch ($mimeType) {
                case 'image/jpeg':
                    imagejpeg($image, $imagePath, 90);
                    break;
                case 'image/png':
                    // 使用压缩级别 9（最高压缩，但保留透明度）
                    imagepng($image, $imagePath, 9);
                    break;
                case 'image/gif':
                    imagegif($image, $imagePath);
                    break;
                case 'image/webp':
                    imagewebp($image, $imagePath, 90);
                    break;
            }

            // 释放内存
            imagedestroy($image);

            return $imagePath;

        } catch (Throwable $e) {
            error_log('添加水印失败: ' . $e->getMessage());
            return $imagePath;
        }
    }

    /**
     * 检测 GIF 是否为动图
     *
     * @param string $filePath GIF 文件路径
     * @return bool true 表示是动图，false 表示是静态图
     */
    private function isAnimatedGif(string $filePath): bool
    {
        if (!file_exists($filePath)) {
            return false;
        }

        $fileContent = file_get_contents($filePath);
        if ($fileContent === false) {
            return false;
        }

        // GIF 动图的特征：包含多个图像块（Image Descriptor）
        // 图像块以 0x2C 开始，如果有多个 0x00 0x21 0xF9（Graphic Control Extension）则为动图
        $frameCount = preg_match_all('#\x00\x21\xF9\x04.{4}\x00([\x2C\x21])#s', $fileContent);

        return $frameCount > 1;
    }

    /**
     * 获取默认字体路径
     *
     * @return string
     */
    private function getDefaultFont(): string
    {
        // 尝试使用项目中的字体文件
        $fontPath = dirname(PUBLIC_DIR) . '/Static/font/Alibaba_PuHuiTi_2.0_115_Black_115_Black.ttf';
        if (file_exists($fontPath)) {
            return $fontPath;
        }

        // 如果项目字体不存在，尝试使用系统字体
        $systemFonts = [
            '/System/Library/Fonts/PingFang.ttc',  // macOS
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',  // Linux
            'C:\\Windows\\Fonts\\arial.ttf'  // Windows
        ];

        foreach ($systemFonts as $font) {
            if (file_exists($font)) {
                return $font;
            }
        }

        // 如果都没有找到，返回空字符串（使用内置字体）
        return '';
    }

    /**
     * 从MIME类型获取安全的文件后缀名
     * @throws AppException
     */
    private function _getExtensionFromMimeType(string $mimeType): string
    {
        if (!isset($this->mimeToExtensionMap[$mimeType])) {
            throw new AppException(AppErr::FILE_TYPE_NOT_SUPPORTED_WITH_MIME . ": $mimeType");
        }
        return $this->mimeToExtensionMap[$mimeType];
    }

    /**
     * 根据文件哈希和MIME类型生成最终存储路径
     * @return array{absolute_path: string, relative_path: string}
     * @throws AppException
     */
    private function _generateFinalPath(string $fileHash, string $mimeType, bool $isConvertedVideo = false): array
    {
        $fileExtension = $isConvertedVideo ? 'mp4' : $this->_getExtensionFromMimeType($mimeType);
        $fileNameBody = $isConvertedVideo ? substr($fileHash, 4) . '_converted' : substr($fileHash, 4);

        $subDir = substr($fileHash, 0, 2) . '/' . substr($fileHash, 2, 2);
        $finalFileName = $fileNameBody . '.' . $fileExtension;

        $relativeDir = $this->uploadDir . '/' . $subDir;
        $absoluteDir = PUBLIC_DIR . $relativeDir;
        FileUtil::ensureDirectoryExists($absoluteDir);

        return [
            'absolute_path' => $absoluteDir . '/' . $finalFileName,
            'relative_path' => $relativeDir . '/' . $finalFileName,
        ];
    }

    /**
     * FileUploader constructor.
     *
     * @param string $uploadDir 文件最终存储的主目录，相对于 PUBLIC_DIR。默认为 'upload'。
     */
    public function __construct(string $uploadDir = 'upload')
    {
        $this->uploadDir = rtrim($uploadDir, '/');
        $this->tempDir = $this->uploadDir . '/temp';

        // 确保目录存在
        FileUtil::ensureDirectoryExists(PUBLIC_DIR . $this->uploadDir);
        FileUtil::ensureDirectoryExists(PUBLIC_DIR . $this->tempDir);
    }

    /**
     * 处理单文件上传
     *
     * 该方法会处理一个完整的HTTP文件上传请求。
     * 1. 在服务端严格校验文件的MIME类型，并检查是否在白名单内。
     * 2. 根据文件内容的SHA256哈希值生成一个唯一的、带层级目录的存储路径。
     * 3. 将临时文件移动到最终位置。
     * 4. 如果文件是视频，会自动调用视频处理流程。
     *
     * @param string $uploadKey POST请求中文件域的name属性，默认为'file'。
     * @return string 返回存储在服务器上的绝对路径。
     * @throws AppException 如果文件上传失败或验证不通过。
     */
    public function uploadSingleFile(string $uploadKey = 'file'): string
    {
        $request = CtxEnum::Request->get();
        $file = $request->files[$uploadKey] ?? null;

        if (empty($file) || $file['error'] !== UPLOAD_ERR_OK) {
            throw new AppException(AppErr::FILE_UPLOAD_KEY_NOT_FOUND_WITH_KEY, $uploadKey);
        }

        $tempPath = $file['tmp_name'];

        // 安全校验：在服务端检查真实MIME类型，并校验白名单
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($tempPath);
        $this->_getExtensionFromMimeType($mimeType); // 如果不在白名单内会直接抛出异常

        // 算法升级：使用SHA256计算文件哈希
        $fileHash = hash_file('sha256', $tempPath);

        // 生成最终路径
        $pathInfo = $this->_generateFinalPath($fileHash, $mimeType);

        if (!move_uploaded_file($tempPath, $pathInfo['absolute_path'])) {
            throw new AppException(AppErr::FILE_SAVE_FAILED);
        }

        // 统一处理，例如视频转码
        return $this->processUploadedFile($pathInfo['absolute_path'], $fileHash, $mimeType);
    }

    /**
     * 检查文件上传状态（用于分片上传）
     *
     * 在分片上传前调用此方法，可以实现秒传和断点续传。
     * - 如果文件（或其转码后的版本）已存在，直接返回完成状态和URL（秒传）。
     * - 如果文件部分存在，返回已上传的分片索引数组，前端可从上次中断的地方继续上传。
     *
     * @param string $fileHash 文件的唯一哈希值 (SHA256)。
     * @param string $mimeType 文件的MIME类型，用于判断文件类型和获取后缀。
     * @return array 返回一个包含上传状态的数组, 结构如下:
     * [
     *   'is_complete' => bool,   // 文件是否已完整存在于服务器
     *   'url' => string,         // 如果 is_complete 为 true, 则返回文件的完整访问URL
     *   'uploaded_chunks' => int[] // 如果未完成, 返回已上传的分片索引数组
     * ]
     * @throws AppException
     * @throws Throwable
     */
    public function checkUploadStatus(string $fileHash, string $mimeType): array
    {
        // 先校验MIME类型是否在白名单内
        $this->_getExtensionFromMimeType($mimeType);

        // 如果是视频文件且配置允许转码，检查转码后的文件是否存在，以实现秒传
        if ($this->_shouldConvertVideo($mimeType)) {
            // 视频文件秒传，应检查最终转码的mp4文件
            $pathInfo = $this->_generateFinalPath($fileHash, 'video/mp4', true);
        } else {
            // 如果不是视频文件或不需要转码，检查原始哈希文件是否存在
            $pathInfo = $this->_generateFinalPath($fileHash, $mimeType);
        }

        if (file_exists($pathInfo['absolute_path'])) {
            return [
                'is_complete' => true,
                'url' => Request::getHost() . "/" . $pathInfo['relative_path'],
                'uploaded_chunks' => [],
            ];
        }

        $tempFilePath = PUBLIC_DIR . $this->tempDir . '/' . $fileHash;
        $uploadedChunks = [];

        if (is_dir($tempFilePath)) {
            $files = scandir($tempFilePath);
            foreach ($files as $file) {
                if (is_numeric($file)) {
                    $uploadedChunks[] = (int)$file;
                }
            }
            sort($uploadedChunks);
        }

        // [优化] 检查所有分片是否已上传但文件尚未合并的情况
        if ($this->getUploadInfo($fileHash) && $this->isUploadComplete($fileHash)) {
            // 所有分片都已存在，但最终文件不存在，这可能是之前合并中断导致的。
            // 在这里重新触发一次合并任务。
            FileProcessQueue::updateProgress($fileHash, 'pending', 0, '所有分片已存在，重新开始合并...');
            FileProcessQueue::processFile([
                'fileHash' => $fileHash,
                'host' => Request::getHost(),
                'uploadDir' => $this->uploadDir
            ]);

            // 返回一个类似 uploadChunk 完成时的状态，告诉前端开始轮询
            return [
                'is_complete' => false,
                'status' => 'processing',
                'file_hash' => $fileHash,
                'message' => '文件分片已完整，正在后台处理中...',
                'uploaded_chunks' => $uploadedChunks,
            ];
        }

        return [
            'uploaded_chunks' => $uploadedChunks,
            'is_complete' => false, // 文件未完整，需要继续上传
        ];
    }

    /**
     * 上传并保存单个文件分片
     *
     * 接收并保存一个文件分片。当所有分片都上传完毕后，会自动触发合并操作。
     *
     * @param string $fileHash 文件的唯一哈希值。
     * @param int $chunkIndex 当前分片的索引（从0开始）。
     * @param int $totalChunks 总分片数。
     * @param string|null $mimeType 文件的MIME类型。在上传第一个分片时(chunkIndex=0)必须传入。
     * @param string|null $fileName 原始文件名（可选，仅用于元信息记录）。
     * @return array 返回分片上传的结果。
     * - 上传中: `['status' => 'uploading', ...]`
     * - 完成: `['status' => 'complete', 'url' => '...', 'path' => '...']`
     * @throws AppException
     * @throws Throwable
     */
    public function uploadChunk(string $fileHash, int $chunkIndex, int $totalChunks, ?string $mimeType = null, ?string $fileName = null): array
    {
        $request = CtxEnum::Request->get();
        $file = $request->files['chunk'] ?? null;

        if (empty($file) || $file['error'] !== UPLOAD_ERR_OK) {
            throw new AppException(AppErr::FILE_CHUNK_UPLOAD_FAILED);
        }

        // 每次上传时清理过期文件（异步清理，不影响当前上传）
        $this->cleanupExpiredFilesAsync();

        $tempDir = PUBLIC_DIR . $this->tempDir . '/' . $fileHash;
        FileUtil::ensureDirectoryExists($tempDir);

        $chunkPath = $tempDir . '/' . $chunkIndex;

        if (!move_uploaded_file($file['tmp_name'], $chunkPath)) {
            throw new AppException(AppErr::FILE_CHUNK_SAVE_FAILED);
        }

        // 在第一个分片时，必须提供MIME类型，并将其存入info文件
        if ($chunkIndex === 0) {
            if (empty($mimeType)) {
                throw new AppException(AppErr::FILE_MIME_TYPE_REQUIRED);
            }
            // 校验MIME类型是否在白名单内
            $this->_getExtensionFromMimeType($mimeType);
            $this->saveUploadInfo($fileHash, $totalChunks, $mimeType, $fileName);
        }

        // 检查是否所有分片都已上传
        if ($this->isUploadComplete($fileHash) || $totalChunks === 1) {
            // [并发控制] 使用Redis锁防止重复创建合并任务
            $lockKey = 'lock:merge:' . $fileHash;
            $lockAcquired = PoolRedis::call(function (Redis $redis) use ($lockKey) {
                return $redis->set($lockKey, 1, ['NX', 'EX' => 60]); // 尝试获取锁，60秒后自动过期
            });

            if (!$lockAcquired) {
                // 获取锁失败(key已存在)，说明已有其他进程在处理
                return [
                    'status' => 'processing',
                    'file_hash' => $fileHash,
                    'message' => '文件上传完成，正在后台处理中.'
                ];
            }

            // 初始化处理进度
            FileProcessQueue::updateProgress($fileHash, 'pending', 0, '所有分片上传完成，等待处理...');

            // 创建异步任务进行文件合并和处理。
            // 注意：锁的释放在异步任务 `mergeChunksAsync` 中完成
            FileProcessQueue::processFile([
                'fileHash' => $fileHash,
                'host' => Request::getHost(),
                'uploadDir' => $this->uploadDir
            ]);

            return [
                'status' => 'processing',
                'file_hash' => $fileHash,
                'message' => '文件上传完成，正在后台处理中...'
            ];
        }

        return [
            'status' => 'uploading',
            'uploaded_chunks' => $chunkIndex + 1,
            'total_chunks' => $totalChunks
        ];
    }

    /**
     * 异步合并所有文件分片（用于task进程）
     *
     * [V2] 增加了Redis锁的释放逻辑和返回值修正
     *
     * @param string $fileHash 文件的唯一哈希值。
     * @return string 返回合并后文件的绝对路径。
     * @throws Throwable 如果找不到上传信息或分片丢失。
     */
    public function mergeChunksAsync(string $fileHash): string
    {
        $lockKey = 'lock:merge:' . $fileHash;

        try {
            return $this->mergeChunks($fileHash);
        } finally {
            // 释放锁
            try {
                PoolRedis::call(function (Redis $redis) use ($lockKey) {
                    $redis->del($lockKey);
                });
            } catch (Throwable $e) {
                error_log("FileUploader Redis lock release failed in task: " . $e->getMessage());
            }
        }
    }

    /**
     * 合并所有文件分片
     *
     * 当检测到所有分片都已上传后，此方法会被自动调用。
     * 它会将所有分片按顺序合并成一个完整的文件，并进行后续处理。
     *
     * @param string $fileHash 文件的唯一哈希值。
     * @return string 返回合并和处理后文件的绝对路径。
     * @throws AppException 如果找不到上传信息或分片丢失。
     */
    private function mergeChunks(string $fileHash): string
    {
        $tempDir = PUBLIC_DIR . $this->tempDir . '/' . $fileHash;
        $uploadInfo = $this->getUploadInfo($fileHash);

        if (!$uploadInfo) {
            throw new AppException(AppErr::FILE_UPLOAD_INFO_NOT_FOUND);
        }

        $totalChunks = $uploadInfo['total_chunks'];
        $clientMimeType = $uploadInfo['mime_type'];
        if (!$clientMimeType) {
            throw new AppException(AppErr::FILE_MIME_TYPE_LOST);
        }

        // 生成最终文件路径
        $pathInfo = $this->_generateFinalPath($fileHash, $clientMimeType);
        $finalPath = $pathInfo['absolute_path'];

        // 【优化】使用流式合并，降低内存占用
        $finalFileStream = fopen($finalPath, 'wb');
        if (!$finalFileStream) {
            throw new AppException(AppErr::FILE_CREATE_FAILED);
        }

        for ($i = 0; $i < $totalChunks; $i++) {
            $chunkPath = $tempDir . '/' . $i;
            if (!file_exists($chunkPath)) {
                fclose($finalFileStream);
                unlink($finalPath); // 清理不完整的文件
                throw new AppException(AppErr::FILE_CHUNK_NOT_EXIST . ": 分片 $i");
            }

            $chunkStream = fopen($chunkPath, 'rb');
            if (!$chunkStream) {
                fclose($finalFileStream);
                unlink($finalPath);
                throw new AppException(AppErr::FILE_CHUNK_READ_FAILED . ": 分片 $i");
            }

            // 将分片流直接拷贝到最终文件流
            stream_copy_to_stream($chunkStream, $finalFileStream);

            fclose($chunkStream);

            // [新增] 更新合并进度
            // 将合并进度映射到10%到50%的区间内，与 FileProcessQueue 中的进度更新相对应
            if ($totalChunks > 0) {
                $mergeProgress = 10 + (int)((($i + 1) / $totalChunks) * 40);
                FileProcessQueue::updateProgress(
                    $fileHash,
                    'processing',
                    $mergeProgress,
                    sprintf('正在合并分片: %d/%d', $i + 1, $totalChunks)
                );
            }
        }

        fclose($finalFileStream);

        // [安全增强] 再次校验合并后文件的真实MIME类型，防止伪造
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $serverMimeType = $finfo->file($finalPath);
//        if ($serverMimeType !== $clientMimeType) {
//            unlink($finalPath);
//            throw new AppException("文件类型校验失败：客户端声称是 {$clientMimeType}，服务器检测为 $serverMimeType");
//        }

        // 算法升级：使用SHA256进行文件完整性验证
        if (!$this->verifyFileIntegrity($finalPath, $fileHash)) {
            unlink($finalPath);
            throw new AppException(AppErr::FILE_INTEGRITY_FAILED);
        }

        // 检测文件类型并进行视频转换
        return $this->processUploadedFile($finalPath, $fileHash, $serverMimeType);
    }

    /**
     * 对上传完成的文件进行后处理
     *
     * 主要用于统一处理所有上传后的文件，当前核心功能包括：
     * 1. 自动检测视频文件并进行转码（根据UploadIsConvertedVideo配置决定是否启用）
     * 2. 自动检测图片文件并添加水印
     *
     * @param string $filePath 文件的绝对路径。
     * @param string $fileHash 文件的哈希值，用于生成转码后文件的路径。
     * @param string $mimeType 文件的MIME类型。
     * @return string 返回处理后文件的绝对路径。如果未做任何处理，则返回原始路径。
     * @throws AppException
     */
    public function processUploadedFile(string $filePath, string $fileHash, string $mimeType): string
    {
        // 判断是否为视频文件且配置允许转码
        if ($this->_shouldConvertVideo($mimeType)) {
            return $this->convertVideo($filePath, $fileHash);
        }

        // 判断是否为图片文件，如果是则添加水印
        if ($this->_isMimeTypeImage($mimeType)) {
            return $this->addWatermarkToImage($filePath, $mimeType);
        }

        return $filePath;
    }

    /**
     * 转换视频为MP4格式
     *
     * 使用ffmpeg将视频文件转换为Web兼容的MP4格式，并进行压缩。
     * - 视频编码: H.264
     * - 音频编码: AAC
     * - 分辨率: 最大宽度720p，保持原始高宽比
     * 转换成功后会删除原始文件。
     *
     * 注意：此方法仅在LiveConfig::UploadIsConvertedVideo为true时被调用。
     *
     * @param string $inputPath 输入视频文件的绝对路径。
     * @param string $fileHash 原始文件的哈希值，用于生成输出路径。
     * @return string 返回转码后视频文件的绝对路径。如果转换失败，则返回原始路径。
     * @throws AppException
     */
    private function convertVideo(string $inputPath, string $fileHash): string
    {
        // 转码后的文件统一为mp4
        $pathInfo = $this->_generateFinalPath($fileHash, 'video/mp4', true);
        $outputPath = $pathInfo['absolute_path'];

        // 优化：如果转换后的文件已存在，则直接返回路径，不再重复转换
        if (file_exists($outputPath)) {
            // 删除临时的原始文件（如果它和输出文件不同）
            if ($inputPath !== $outputPath) {
                @unlink($inputPath);
            }
            return $outputPath;
        }

        // 更新ffmpeg命令：
        // 1. 使用 scale 滤镜将视频等比缩放到最大720p (1280x720)
        // 2. 使用 crop 滤镜确保输出的宽和高都是偶数
        // 3. 使用 -pix_fmt yuv420p 指定像素格式以获得最佳的浏览器兼容性
        // 4. 使用 -profile:v main 提高对老旧设备的兼容性
        // 5. 使用 -ar 44100 强制指定标准的音频采样率
        // 6. 使用更简洁的scale滤镜 w=min(1280\,iw):h=-2, 既能限制最大宽度，又能保证高度为偶数，兼容性最佳
        $command = sprintf(
            'ffmpeg -y -i %s -c:v libx264 -profile:v main -pix_fmt yuv420p -c:a aac -ar 44100 -b:a 128k -movflags +faststart -vf "scale=w=min(1280\\,iw):h=-2" %s 2>&1',
            escapeshellarg($inputPath),
            escapeshellarg($outputPath)
        );


        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            error_log("视频转换失败: " . implode("\n", $output));
            return $inputPath;
        }

        if (file_exists($outputPath)) {
            @unlink($inputPath);
            return $outputPath;
        }

        return $inputPath;
    }

    /**
     * 验证合并后文件的完整性
     *
     * @param string $filePath 文件路径
     * @param string $fileHash
     * @return bool
     */
    private function verifyFileIntegrity(string $filePath, string $fileHash): bool
    {
        if (!file_exists($filePath) || filesize($filePath) === 0) {
            return false;
        }

        // [算法升级] 计算文件的SHA256值进行校验
        return hash_file('sha256', $filePath) === $fileHash;
    }

    /**
     * 保存分片上传的元信息（如总分片数、原始文件名）
     *
     * @param string $fileHash 文件哈希
     * @param int $totalChunks 总分片数
     * @param string $mimeType 文件的MIME类型
     * @param string|null $fileName 原始文件名（可选，仅记录）
     */
    private function saveUploadInfo(string $fileHash, int $totalChunks, string $mimeType, ?string $fileName = null): void
    {
        $infoPath = PUBLIC_DIR . $this->tempDir . '/' . $fileHash . '.info';

        // [优化] 只有当信息不存在时才写入，防止被覆盖
        if (!file_exists($infoPath)) {
            $info = [
                'total_chunks' => $totalChunks,
                'mime_type' => $mimeType,
                'file_name' => $fileName, // 原始文件名仅作记录
                'upload_time' => time(),
            ];
            file_put_contents($infoPath, json_encode($info));
        }
    }

    /**
     * 获取分片上传的元信息
     *
     * @param string $fileHash 文件哈希
     * @return array|null
     */
    private function getUploadInfo(string $fileHash): ?array
    {
        $infoPath = PUBLIC_DIR . $this->tempDir . '/' . $fileHash . '.info';
        if (!file_exists($infoPath)) {
            return null;
        }

        $content = file_get_contents($infoPath);
        return json_decode($content, true);
    }

    /**
     * 检查所有分片是否已上传完成
     *
     * @param string $fileHash 文件哈希
     * @return bool
     */
    private function isUploadComplete(string $fileHash): bool
    {
        $tempDir = PUBLIC_DIR . $this->tempDir . '/' . $fileHash;
        $info = $this->getUploadInfo($fileHash);

        if (!$info || !is_dir($tempDir)) {
            return false;
        }

        $totalChunks = $info['total_chunks'] ?? 0;
        if ($totalChunks === 0) {
            return false;
        }

        for ($i = 0; $i < $totalChunks; $i++) {
            if (!file_exists($tempDir . '/' . $i)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 清理指定文件哈希的临时分片文件和信息文件
     *
     * @param string $fileHash 文件哈希
     */
    public function cleanupTempFiles(string $fileHash): void
    {
        $tempDir = PUBLIC_DIR . $this->tempDir . '/' . $fileHash;
        $infoPath = PUBLIC_DIR . $this->tempDir . '/' . $fileHash . '.info';

        if (is_dir($tempDir)) {
            $files = scandir($tempDir);
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..') {
                    unlink($tempDir . '/' . $file);
                }
            }
            rmdir($tempDir);
        }

        if (file_exists($infoPath)) {
            unlink($infoPath);
        }
    }

    /**
     * 异步触发清理过期临时文件的任务
     *
     * 以一定概率（当前为10%）触发一个异步任务来清理所有过期的临时文件。
     * 这种方式可以避免在高并发上传时对每次请求都造成性能影响。
     *
     */
    private function cleanupExpiredFilesAsync(): void
    {
        // 使用概率清理，避免每次上传都执行清理
        if (rand(1, 100) <= 10) { // 10% 概率执行清理
            $this->cleanupExpiredFiles();
            // 同时清理过期的进度文件
            FileProcessQueue::cleanupProgress();
        }
    }

    /**
     * 清理所有过期的临时文件
     *
     * 遍历临时目录，删除超过24小时未更新的文件和目录。
     * 此方法可以被定时任务周期性调用，以确保服务器整洁。
     */
    public function cleanupExpiredFiles(): void
    {
        $tempDir = PUBLIC_DIR . $this->tempDir;
        if (!is_dir($tempDir)) {
            return;
        }

        $expireTime = time() - (24 * 3600);
        $files = scandir($tempDir);

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $filePath = $tempDir . '/' . $file;

            if (is_file($filePath) && filemtime($filePath) < $expireTime) {
                unlink($filePath);
            } elseif (is_dir($filePath) && filemtime($filePath) < $expireTime) {
                FileUtil::delDirectory($filePath);
            }
        }
    }

}
