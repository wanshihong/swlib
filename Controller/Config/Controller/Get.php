<?php

namespace Swlib\Controller\Config\Controller;

use Swlib\Controller\Abstract\AbstractController;

use Swlib\Controller\Config\Service\ConfigService;
use Swlib\Response\JsonResponse;
use Swlib\Router\Router;
use Throwable;

class Get extends AbstractController
{
    /**
     * @throws Throwable
     */
    #[Router(errorTitle: '获取配置信息失败')]
    public function run(): JsonResponse
    {
        $key = $this->post('key');
        $keyArr = explode(',', $key);
        $desc = $this->post('desc');
        $arr = ConfigService::get(
            key: $keyArr,
            allowQuery: 1,
            description: $desc
        );
        $nodes = [];
        foreach ($arr as $k => $v) {
            $nodes[] = [
                'key' => $k,
                'value' => $v ?: '',
            ];
        }


        return JsonResponse::success($nodes);
    }

}