<?php

namespace Swlib\Admin\Menu;

use Swlib\Admin\Interface\PermissionInterface;
use Swlib\Admin\Trait\PermissionTrait;
use Swlib\Admin\Utils\Func;
use Swlib\Enum\CtxEnum;
use Swlib\Utils\Language;
use Throwable;

class Menu implements PermissionInterface
{
    use PermissionTrait;

    public bool $isActive = false;

    /**
     * @throws Throwable
     */
    public function __construct(
        public string $label,
        public string $url,
        public        $icon = '',
        public array  $params = []
    )
    {
        $this->label = Language::get($label);
        $this->url = Func::formatUrl($url, $params);
    }

    public function checkActiveByFull(): void
    {
        $request = CtxEnum::Request->get();
        $get = $request->get ?: [];
        if ($get) {
            $currUrl = $request->server['path_info'] . '?' . http_build_query($get);
            if ($currUrl == $this->url) {
                $this->isActive = true;
            }
        }
    }

    public function checkActiveByPathParams(): void
    {
        $request = CtxEnum::Request->get();
        $get = $request->get ?: [];
        if (empty($get)) {
            return;
        }

        $arr = explode('?', $this->url);
        if (!isset($arr[1])) return;
        $paramStr = $arr[1];
        $urlArr = explode('/', $arr[0]);
        array_pop($urlArr);
        $url = implode('/', $urlArr);
        $url = $url . '?' . $paramStr;


        $pathInfo = $request->server['path_info'];
        $pathInfoArr = explode('/', $pathInfo);
        array_pop($pathInfoArr);
        $pathInfo = implode('/', $pathInfoArr);
        $currUrl = $pathInfo . '?' . http_build_query($get);

        if ($currUrl == $url) {
            $this->isActive = true;
        }

    }

    public function checkActiveByPath(): void
    {
        $request = CtxEnum::Request->get();
        $path_info = $request->server['path_info'];
        $path_info_arr = explode('/', $path_info);
        $url_arr = explode('/', $this->url);

        array_pop($path_info_arr);
        array_pop($url_arr);

        // 设置当前是否选中
        $this->isActive = $path_info_arr == $url_arr;
    }


}

