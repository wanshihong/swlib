<?php

namespace Swlib\Admin\Menu;

use Swlib\Admin\Interface\PermissionInterface;
use Swlib\Admin\Trait\PermissionTrait;
use Swlib\Enum\CtxEnum;
use Swlib\Utils\Language;
use Swlib\Utils\Url;
use Swoole\Http\Request;
use Throwable;

class Menu implements PermissionInterface
{
    use PermissionTrait;

    public bool $isActive = false;

    public int $activeMatchWeight = 999;

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
        $this->url = Url::appendQueryParams($url, $params);
    }

    public function checkActive(): void
    {
        $this->isActive = false;
        $this->activeMatchWeight = 999;

        $this->checkActiveByFull();
        if ($this->isActive) {
            $this->activeMatchWeight = 1;
            return;
        }

        $this->checkActiveByPathParams();
        if ($this->isActive) {
            $this->activeMatchWeight = 2;
            return;
        }

        $this->checkActiveByPath();
        if ($this->isActive) {
            $this->activeMatchWeight = 3;
        }
    }


    /**
     * 获取当浏览器访问的 URI
     *
     * 如果有  _source_url 来源 uri 就直接返回，否则或者地址栏地址
     *
     * @return string
     */
    private function getCurrPathInfo(): string
    {
        /** @var Request $request */
        $request = CtxEnum::Request->get();
        return $request->get['_source_url'] ?? $request->server['path_info'];
    }

    public function checkActiveByFull(): void
    {
        $request = CtxEnum::Request->get();
        $get = $request->get ?: [];
        if ($get) {
            $currUrl = $this->getCurrPathInfo() . '?' . http_build_query($get);
            if ($currUrl == $this->url) {
                $this->isActive = true;
            }
        }
    }

    public function checkActiveByPathParams(): void
    {
        $request = CtxEnum::Request->get();
        $get = $request->get ?: [];

        // 没有 get 参数 跳过匹配
        if (empty($get)) {
            return;
        }
        $parse = parse_url($this->url);
        $parseQuery = $parse['query'] ?? '';
        $parsePath = $parse['path'] ?? '';
        if ($parsePath != $this->getCurrPathInfo()) {
            return;
        }

        parse_str($parseQuery, $result);

        $find = true;
        foreach ($result as $k => $v) {
            if (!isset($get[$k]) || $get[$k] != $v) {
                $find = false;
            }
        }

        if ($find) {
            $this->isActive = true;
        }
    }

    public function checkActiveByPath(): void
    {
        $path_info = $this->getCurrPathInfo();
        $path_info_arr = explode('/', $path_info);
        $url_arr = explode('/', $this->url);

        array_pop($path_info_arr);
        array_pop($url_arr);

        // 设置当前是否选中
        $this->isActive = $path_info_arr == $url_arr;
    }


}
