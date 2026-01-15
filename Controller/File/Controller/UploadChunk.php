<?php

namespace Swlib\Controller\File\Controller;

use Exception;
use Swlib\Controller\Abstract\AbstractController;
use Swlib\Controller\File\Service\FileUploader;
use Swlib\Exception\AppException;
use Swlib\Response\JsonResponse;
use Swlib\Router\Router;
use Throwable;


/**
 * [V2] 上传文件分片
 *
 * @purpose
 * 这是大文件上传流程的【第二步】。
 * 在调用【检查接口】确认需要上传后，前端循环调用此接口来逐个上传文件的分片。
 *
 * @workflow
 * 1. 根据【检查接口】返回的 `uploaded_chunks` 数组，确定需要上传的分片范围。
 * 2. 循环遍历需要上传的分片，构造 `FormData` 并调用本接口。
 * 3. 每次上传成功后，检查返回的 `data.status` 字段：
 *    - 如果值为 `uploading`，表示分片上传成功，继续上传下一个分片。
 *    - 如果值为 `processing`，表示这是【最后一个分片】，服务器已接收并开始异步合并处理。此时，前端应【停止上传】，并使用 `file_hash` 开始轮询【查询进度】接口。
 *
 * @param
 *  string file_hash     (必填) 完整文件的SHA256哈希值。
 *  int    chunk_index   (必填) 当前分片的索引（从0开始）。
 *  int    total_chunks  (必填) 文件被分成的总片数。
 *  string mime_type     (首个分片必填) 文件的MIME类型。为保证数据一致性，建议每次都传。
 *  string file_name     (可选) 原始文件名，仅供服务器记录参考。
 *  file   chunk         (必填) 当前分片的二进制数据。
 *
 * @return JsonResponse
 *
 * @throws Throwable
 * @example
 * // 1. 分片上传中
 * {
 *   "errno": 0, "msg": "ok",
 *   "data": {
 *     "status": "uploading",
 *     "uploaded_chunks": 5, // 这通常是 chunk_index + 1
 *     "total_chunks": 20
 *   }
 * }
 *
 * // 2. 最后一个分片上传完成，开始后台处理
 * {
 *   "errno": 0, "msg": "ok",
 *   "data": {
 *     "status": "processing",
 *     "file_hash": "...",
 *     "message": "文件上传完成，正在后台处理中..."
 *   }
 * }
 *
 * @api
 * 接口: POST /common/file/upload-chunk
 * Content-Type: multipart/form-data
 *
 */
#[Router(method: 'POST')]
class UploadChunk extends AbstractController
{
    /**
     * @throws Throwable
     * @throws AppException
     */
    public function run(): JsonResponse
    {
        $fileHash = $this->post('file_hash');
        $chunkIndex = (int)$this->post('chunk_index');
        $totalChunks = (int)$this->post('total_chunks');
        $mimeType = $this->post('mime_type');
        $fileName = $this->post('file_name');

        if (!$fileHash || $totalChunks <= 0) {
            return JsonResponse::error(new Exception('缺少必要参数'));
        }

        // 第一次上传分片时，MIME类型是必需的
        if ($chunkIndex === 0 && empty($mimeType)) {
            return JsonResponse::error(new Exception('第一次上传分片时缺少MIME类型'));
        }

        try {
            $uploader = new FileUploader();
            $result = $uploader->uploadChunk($fileHash, $chunkIndex, $totalChunks, $mimeType, $fileName);

            return JsonResponse::success($result);
        } catch (Exception $e) {
            return JsonResponse::error($e);
        }
    }
}
