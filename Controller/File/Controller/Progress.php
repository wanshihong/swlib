<?php

namespace Swlib\Controller\File\Controller;

use Exception;
use Swlib\Controller\Abstract\AbstractController;
use Swlib\Controller\File\Service\FileProcessQueue;
use Swlib\Exception\AppException;
use Swlib\Response\JsonResponse;
use Swlib\Router\Router;


/**
 * 查询文件处理进度
 *
 * 接口: POST /common/file/progress
 *
 * 请求参数:
 *  string file_hash   文件哈希值 (POST参数)
 *
 * 返回数据结构:
 * {
 *   "errno": 0,
 *   "msg": "ok",
 *   "data": {
 *     "status": "processing",         // 状态: pending, processing, completed, failed
 *     "progress": 50,                 // 进度百分比 0-100
 *     "message": "正在处理文件...",     // 状态消息
 *     "updated_at": 1640995200,       // 最后更新时间
 *     "result": {                     // 仅在completed状态时存在
 *       "url": "http://...",
 *     }
 *   }
 * }
 *
 * @throws Exception
 */
#[Router(method: 'POST')]
class Progress extends AbstractController
{
    /**
     * @throws AppException
     */
    public function run(): JsonResponse
    {
        $fileHash = $this->post('file_hash');

        if (!$fileHash) {
            return JsonResponse::error(new Exception('缺少文件哈希参数'));
        }

        try {
            $progress = FileProcessQueue::getProgress($fileHash);

            if ($progress === null) {
                sleep(1);// 等待一下,合并文件是异步的;可能还没有进度
                $progress = FileProcessQueue::getProgress($fileHash);
            }

            if ($progress === null) {
                return JsonResponse::error(new Exception('找不到该文件的处理记录'));
            }

            return JsonResponse::success($progress);
        } catch (Exception $e) {
            return JsonResponse::error($e);
        }
    }
}
