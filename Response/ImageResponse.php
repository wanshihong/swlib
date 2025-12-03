<?php

namespace Swlib\Response;

use GdImage;
use Swlib\Enum\CtxEnum;

readonly class ImageResponse implements ResponseInterface
{


    public function __construct(private GdImage $data)
    {
    }

    /**
     * 返回正确
     * @param mixed $data
     * @return static
     */
    public static function image(GdImage $data): static
    {
        return new static($data);
    }


    public function output(): void
    {
        $response = CtxEnum::Response->get();
        $response->header('Content-Type', 'image/jpeg');
        ob_start();
        imagejpeg($this->data);
        $imageData = ob_get_clean();
        $response->end($imageData);
        // 释放内存
        imagedestroy($this->data);
    }

}