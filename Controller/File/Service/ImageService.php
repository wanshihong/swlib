<?php

namespace Swlib\Controller\File\Service;

use finfo;
use mysqli;
use Swlib\Connect\PoolMysql;
use Swlib\Enum\CtxEnum;
use Swlib\Utils\Ip;
use Throwable;

/**
 * 图片服务类
 * 提供图片信息保存到数据库的通用方法
 */
class ImageService
{

    /**
     * 保存图片信息到数据库
     *
     * @param string $filePath 文件的绝对路径
     * @param string|null $originalName 原始文件名（可选）
     * @param string|null $md5Hash MD5哈希值（可选，如果已计算则传入，避免重复计算）
     * @return int 返回图片ID
     * @throws Throwable
     */
    public static function saveImage(
        string  $filePath,
        ?string $originalName = null,
        ?string $md5Hash = null
    ): int
    {
        // 检查文件是否存在
        if (!file_exists($filePath)) {
            return 0;
        }

        // 获取文件信息
        $pathInfo = pathinfo($filePath);
        $fileName = $pathInfo['basename'];
        $fileExt = $pathInfo['extension'] ?? '';
        $storagePath = $pathInfo['dirname'] ? str_replace(PUBLIC_DIR, '', $pathInfo['dirname']) : '';

        // 获取文件大小
        $fileSize = filesize($filePath);

        // 获取MIME类型
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($filePath);

        // 计算MD5哈希（如果未传入则计算）
        if ($md5Hash === null) {
            $md5Hash = md5_file($filePath);
        }

        // 获取图片尺寸（如果是图片）
        $width = null;
        $height = null;
        if (str_starts_with($mimeType, 'image/') && $mimeType !== 'image/svg+xml') {
            $imageInfo = @getimagesize($filePath);
            if ($imageInfo) {
                $width = $imageInfo[0];
                $height = $imageInfo[1];
            }
        }


        // 如果没有原始文件名，使用存储的文件名
        if (empty($originalName)) {
            $originalName = $fileName;
        }

        // 获取上传者信息
        $uploaderId = CtxEnum::Data->getData('userId');
        $uploaderIp = Ip::get();

        // 记录图片信息到数据库
        return PoolMysql::call(function ($mysqli) use (
            $originalName, $storagePath, $fileName, $fileSize, $fileExt,
            $mimeType, $md5Hash, $width, $height, $uploaderId, $uploaderIp
        ) {
            $sql = "INSERT INTO images (
                original_name, storage_path, file_name, file_size, file_ext,
                mime_type, md5_hash, width, height, uploader_id, uploader_ip,
                status, access_count, upload_time
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

            $stmt = $mysqli->prepare($sql);
            $status = 1;
            $accessCount = 0;
            $stmt->bind_param(
                'sssssssiiisis',
                $originalName, $storagePath, $fileName, $fileSize, $fileExt,
                $mimeType, $md5Hash, $width, $height, $uploaderId, $uploaderIp,
                $status, $accessCount
            );

            $stmt->execute();
            $insertId = $mysqli->insert_id;
            $stmt->close();

            return (int)$insertId;
        });
    }

}

