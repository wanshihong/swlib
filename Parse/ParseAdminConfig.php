<?php
declare(strict_types=1);

namespace Swlib\Parse;


use Generate\ConfigEnum;
use ReflectionClass;
use Swlib\Utils\File;

class ParseAdminConfig
{


    public function __construct()
    {
        $reflectionClass = new ReflectionClass(ConfigEnum::class);
        if (!$reflectionClass->hasConstant('ADMIN_CONFIG_PATH')) {
            return;
        }
        $saveDir = ROOT_DIR . "runtime/Generate";
        File::save($saveDir . "/AdminConfigMap.php", $this->_gen());

    }

    private function _gen(): string
    {
        $className = ConfigEnum::ADMIN_CONFIG_PATH;
        return <<<STR
<?php

namespace Generate;

class AdminConfigMap
{
    const array Init = ['$className', 'Init'];
    const array ConfigTitle = ['$className', 'configAdminTitle'];
    const array ConfigMenus = ['$className', 'configMenus'];
}
STR;

    }


}