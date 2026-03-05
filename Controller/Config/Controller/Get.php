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
    public function run(ConfigListsProto $request): ConfigListsProto
    {
        $arr = $request->getLists();
        $nodes = [];
        foreach ($arr as $item) {
            $key = $item->getKey();
            $desc = $item->getDesc();
            $value = ConfigService::get(key: $key, desc: $desc);
            $proto = new ConfigProto();
            $proto->setKey($key);
            $proto->setValue($value);

            $nodes[] = $proto;
        }

        $message = new ConfigListsProto();
        $message->setLists($nodes);
        return $message;
    }

}