<?php
declare(strict_types=1);

namespace Swlib\Parse\Router;

use ReflectionException;
use Swlib\Event\EventEnum;
use Swlib\Parse\Router\GenApi\FlutterApiGenerator;
use Swlib\Parse\Router\GenApi\TsApiGenerator;
use Swlib\Utils\File;
use Throwable;


class ParseRouter
{

    use ParseRouterRouter;

    /**
     * @throws ReflectionException
     * @throws Throwable
     */
    public function __construct()
    {
        $dir = ROOT_DIR . "App";
        $files = File::eachDir($dir, function ($filePath) {
            return str_ends_with($filePath, '.php');
        });

        foreach ($files as $key => $filePath) {
            $filePath = str_replace(
                [".php", ROOT_DIR, "/"],
                ["", "", '\\'],
                $filePath
            );
            $files[$key] = $filePath;
        }

        $attributes = $this->createByPathRouter($files);

        // 创建 TS API
        $tsGenerator = new TsApiGenerator();
        if (!$tsGenerator->generate($attributes)) {
            return; // 生成失败，终止流程
        }

        // 创建 Flutter API
        $flutterGenerator = new FlutterApiGenerator();
        if (!$flutterGenerator->generate($attributes)) {
            return; // 生成失败，终止流程
        }

        $this->saveRouter();

        $this->cleanRouterProcess();


        EventEnum::OnParseRouterEvent->emit([
            'attributes' => $attributes,
        ]);

    }


}