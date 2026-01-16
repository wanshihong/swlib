<?php

namespace Swlib\Controller\File\Controller;

use Exception;
use Generate\Tables\Main\ImagesTable;
use Swlib\Controller\Abstract\AbstractController;
use Swlib\Exception\AppException;
use Swlib\Request\Request;
use Swlib\Response\RedirectResponse;
use Swlib\Router\Router;
use Swlib\Table\Db;
use Throwable;


/**
 * 访问图片
 *
 * 接口: GET /file/read
 *
 * 请求参数:
 *  int id  图片ID (GET参数)
 *
 * 功能:
 * - 根据图片ID查询图片信息
 * - 更新访问次数和最后访问时间
 * - 重定向到图片的真实静态访问地址
 *
 * @throws Exception
 * @throws Throwable
 */
#[Router(method: 'GET')]
class Read extends AbstractController
{
    /**
     * @throws Throwable
     * @throws AppException
     */
    public function run(): RedirectResponse
    {
        $id = (int)$this->get('id', '缺少图片ID参数');

        // 查询图片信息
        $image = new ImagesTable()->where([
            ImagesTable::ID => $id,
            ImagesTable::STATUS => 1,
        ])->selectOne();

        if (empty($image)) {
            throw new Exception('图片不存在或已删除');
        }

        // 更新访问次数和最后访问时间
        new ImagesTable()->where([
            ImagesTable::ID => $id,
        ])->update([
            ImagesTable::ACCESS_COUNT => Db::incr(),
            ImagesTable::LAST_ACCESS_TIME => date('Y-m-d H:i:s'),
        ]);

        // 构建图片的真实访问地址
        $host = Request::getHost();
        $imageUrl = $host . '/' . $image->storagePath . '/' . $image->fileName;

        return RedirectResponse::url($imageUrl);
    }
}
