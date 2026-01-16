<?php

namespace Swlib\Controller\File\Controller;

use Exception;
use Generate\DatabaseConnect;
use Generate\RouterPath;
use Swlib\Controller\Abstract\AbstractController;
use Swlib\Controller\File\Service\FileUploader;
use Swlib\Controller\File\Service\ImageService;
use Swlib\Enum\CtxEnum;
use Swlib\Exception\AppException;
use Swlib\Request\Request;
use Swlib\Response\JsonResponse;
use Swlib\Router\Router;
use Throwable;


/**
 * 单文件上传
 *
 * 接口: POST /common/file/upload
 *
 * 请求参数:
 *  string path  上传路径，默认为'upload' (Header参数)
 *  file   file  上传的文件 (FormData文件上传)
 *
 * 返回数据结构:
 * {
 *   "errno": 0,
 *   "msg": "ok",
 *   "data": {
 *     "url": "http://domain.com/upload/20231201123456_abc123.jpg" // 文件访问URL
 *   }
 * }
 *
 * 注意:
 * - 视频文件会自动转换为MP4格式
 * - 转换后的文件名会添加'_converted'后缀
 * - 支持的视频格式会被压缩到720p以优化网络传输
 * - 如果上传相同的文件（MD5相同），会直接返回已存在的文件ID（秒传）
 *
 * @throws Exception
 * @throws Throwable
 */
#[Router(method: 'POST')]
class Upload extends AbstractController
{
    /**
     * @throws AppException
     * @throws Throwable
     */
    public function run(): JsonResponse
    {
        $savePath = $this->getHeader('path', 'upload');

        // 获取原始文件信息
        $request = CtxEnum::Request->get();
        $file = $request->files['file'] ?? null;
        $originalName = $file['name'] ?? '';

        if (empty($file) || $file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('请指定一个需要上传的文件');
        }

        $tempPath = $file['tmp_name'];

        // 计算文件的 MD5 hash（只计算一次）
        $md5Hash = md5_file($tempPath);


        // 检查数据库中是否已存在相同 MD5 的文件（秒传）
        $existingImage = DatabaseConnect::call(function ($mysqli) use ($md5Hash) {
            $sql = "SELECT id FROM images
                    WHERE md5_hash = ? AND status = 1
                    LIMIT 1";

            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param('s', $md5Hash);
            $stmt->execute();

            $result = $stmt->get_result();
            $row = $result->fetch_object();
            $stmt->close();

            return $row ?: null;
        });

        $host = Request::getHost();

        // 如果找到相同的文件，直接返回已有的文件 ID（秒传）
        if ($existingImage) {
            return JsonResponse::success([
                'url' => $host . RouterPath::FileRead . "/id/$existingImage->id",
            ]);
        }

        // 文件不存在，执行正常的上传流程
        $uploader = new FileUploader($savePath);
        $filePath = $uploader->uploadSingleFile();

        // 使用 ImageService 保存图片信息到数据库，传入已计算的 MD5
        $imageId = ImageService::saveImage($filePath, $originalName, $md5Hash);

        return JsonResponse::success([
            'url' => $host . RouterPath::FileRead . "/id/$imageId",
        ]);
    }
}
