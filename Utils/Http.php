<?php

declare(strict_types=1);

namespace Swlib\Utils;


use CurlHandle;
use Exception;
use Swlib\Exception\AppErr;
use Swlib\Exception\AppException;

class Http
{

    private false|CurlHandle $ch;
    public string $url;
    private int $timeout = 10;

    public function __construct()
    {
        $this->ch = curl_init();
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($this->ch, CURLOPT_HEADER, 0);
        curl_setopt($this->ch, CURLOPT_AUTOREFERER, 1);
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, false);
        $this->setTimeout($this->timeout);
    }

    public function setTimeout($time): void
    {
        curl_setopt($this->ch, CURLOPT_TIMEOUT, $time);
    }

    /**
     * @param $url
     * @param array $param
     * @return Http
     */
    public function get($url, array $param = []): Http
    {
        if ($param) {
            $url .= '?' . http_build_query($param);
        }
        $this->url = $url;
        curl_setopt($this->ch, CURLOPT_URL, $url);
        return $this;
    }

    /**
     * @param $url
     * @param array $param
     * @return Http
     * @throws Exception
     */
    public function post($url, mixed $param = []): self
    {
        $this->url = $url;
        curl_setopt($this->ch, CURLOPT_URL, $url);

        curl_setopt($this->ch, CURLOPT_POST, true);
        curl_setopt($this->ch, CURLOPT_POSTFIELDS, $param);
        return $this;
    }

    public function setHeader($header = []): void
    {
        if ($header) {
            foreach ($header as $key => $value) {
                curl_setopt($this->ch, $key, $value);
            }
        }
    }

    /**
     * 设置自定义头部信息
     *
     *
     * 错误示范 二维数组
     *  $headers = [
     *      "Content-Type"     => "application/json",
     *      "X-Requested-With" => "XMLHttpRequest",
     *  ];
     *
     * 正确写法
     *  $headers = [
     *      "Content-Type:application/json",
     *      "X-Requested-With:XMLHttpRequest",
     *  ];
     * @param array $headers
     */
    public function setCustomHeaders(array $headers = []): void
    {
        if ($headers) {
            curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);

        }
    }

    /**
     * @return bool|string
     * @throws Exception
     */
    private function send(): bool|string
    {
        $data = curl_exec($this->ch);

        $errno = curl_errno($this->ch);


        //curl_error==0 表示没有错误
        if ($errno !== 0) {
            $errorMsg = curl_error($this->ch);
            throw new AppException(AppErr::HTTP_REQUEST_FAILED_WITH_MSG, "error_no:$errno;error_msg:$errorMsg");
        }

        curl_close($this->ch);
        return $data;
    }

    /**
     * 获取Http请求原始返回数据
     * @return bool|string
     * @throws Exception
     */
    public function responseOriginal(): bool|string
    {
        return $this->send();
    }

    /**
     * Http请求 返回值 解析成 array
     * @return array
     * @throws Exception
     */
    public function responseArray(): array
    {
        $responseStr = $this->send();
        $response = json_decode($responseStr, true);
        if (empty($response)) {
            Log::save($this->url . " response:" . $responseStr, 'curl');
            throw new AppException(AppErr::HTTP_RESPONSE_TYPE_INVALID);
        }
        return $response;
    }
}
