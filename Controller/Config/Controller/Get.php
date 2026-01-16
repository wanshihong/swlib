<?php

namespace Swlib\Controller\Config\Controller;

use Protobuf\Main\Config\ConfigListsProto;
use Protobuf\Main\Config\ConfigProto;
use Swlib\Controller\Abstract\AbstractController;

use Swlib\Controller\Config\Service\ConfigService;
use Swlib\Router\Router;
use Throwable;

class Get extends AbstractController
{
    /**
     * @throws Throwable
     */
    #[Router(errorTitle: '获取配置信息失败')]
    public function run(ConfigProto $request): ConfigListsProto
    {
        $key = $request->getKey();
        $keyArr = explode(',', $key);
        $desc = $this->post('desc');
        $arr = ConfigService::get(
            key: $keyArr,
            allowQuery: 1,
            description: $desc
        );
        $nodes = [];
        foreach ($arr as $k => $v) {
            $proto = new ConfigProto();
            $proto->setKey($k);
            $proto->setValue($v);

            $nodes[] = $proto;
        }

        $message = new ConfigListsProto();
        $message->setLists($nodes);
        return $message;
    }

}