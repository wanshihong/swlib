<?php
declare(strict_types=1);

namespace Swlib\Parse;

use Swlib\Event\EventEnum;
use Swlib\Utils\File;
use ReflectionException;
use Throwable;


class ParseRouter
{

    use ParseRouterRouter;
    use ParseRouterCreateApi;
    use ParseRouterCopyProtoFile;

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
        $this->createTsApi($attributes);
        $this->copyProtoFiles($attributes);

        $this->saveRouter();

        $this->cleanRouterProcess();


        EventEnum::OnParseRouterEvent->emit([
            'attributes' => $attributes,
        ]);

    }


}