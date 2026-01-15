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
 * [V2] 检查文件上传状态（秒传/断点续传）
 *
 * @purpose
 * 这是大文件上传流程的【第一步】。
 * 前端在计算完文件的SHA256哈希后，应立即调用此接口，以检查文件在服务器上的状态。
 *
 * @workflow
 * 1. 前端计算文件SHA256哈希值。
 * 2. 调用本接口，并传递哈希值和MIME类型。
 * 3. 分析响应:
 *    - 如果 `data.is_complete` 为 `true`，则表示"秒传"成功，流程结束。前端可直接使用返回的 `url`。
 *    - 如果 `data.is_complete` 为 `false` 且 `data.status` 为 `processing`，表示文件分片虽已传完但未合并，前端应直接进入【轮询进度】阶段。
 *    - 如果 `data.is_complete` 为 `false`，则进入【分片上传】阶段。前端应根据返回的 `uploaded_chunks` 数组，跳过已上传的分片，从下一个未上传的分片开始调用【分片上传】接口。
 *
 * @param
 *  string file_hash   (必填) 完整文件的SHA256哈希值。
 *  string mime_type   (必填) 文件的MIME类型, 例如: "image/jpeg"。
 *
 * @return JsonResponse
 *
 * @throws Exception
 * @example
 * // 1. 秒传成功 (文件已存在)
 * {
 *   "errno": 0, "msg": "ok",
 *   "data": {
 *     "is_complete": true,
 *     "url": "http://your.domain/upload/xx/xx/xxxxxxxx.jpg",
 *     "uploaded_chunks": []
 *   }
 * }
 *
 * // 2. 需要断点续传 (部分分片已存在)
 * {
 *   "errno": 0, "msg": "ok",
 *   "data": {
 *     "is_complete": false,
 *     "uploaded_chunks": [0, 1, 2, 5, 6] // 前端应从第3片开始上传
 *   }
 * }
 *
 * // 3. 全新上传 (文件完全不存在)
 * {
 *   "errno": 0, "msg": "ok",
 *   "data": {
 *     "is_complete": false,
 *     "uploaded_chunks": [] // 前端应从第0片开始上传
 *   }
 * }
 *
 * // 4. 分片已传完但未合并（罕见，但需处理）
 * {
 *    "errno": 0, "msg": "ok",
 *    "data": {
 *      "is_complete": false,
 *      "status": "processing",
 *      "file_hash": "...",
 *      "message": "文件分片已完整，正在后台处理中...",
 *      "uploaded_chunks": [...]
 *    }
 * }
 * @api
 * 接口: POST /file/check-chunk-status
 *
 */
#[Router(method: 'POST')]
class CheckChunkStatus extends AbstractController
{
    /**
     * @throws AppException
     * @throws Throwable
     */
    public function run(): JsonResponse
    {
        $fileHash = $this->post('file_hash');
        $mimeType = $this->post('mime_type');

        if (!$fileHash || !$mimeType) {
            return JsonResponse::error(new Exception('缺少必要参数: file_hash 和 mime_type'));
        }

        try {
            $uploader = new FileUploader();
            $status = $uploader->checkUploadStatus($fileHash, $mimeType);

            return JsonResponse::success($status);
        } catch (Exception $e) {
            return JsonResponse::error($e);
        }
    }
}
