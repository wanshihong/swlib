<?php
declare(strict_types=1);

namespace Swlib\Parse;


use Generate\ConfigEnum;
use ReflectionClass;

class ParseAdminConfig
{


    public function __construct()
    {
        $reflectionClass = new ReflectionClass(ConfigEnum::class);
        if (!$reflectionClass->hasConstant('ADMIN_CONFIG_PATH')) {
            return;
        }
        $saveDir = ROOT_DIR . "runtime/Generate";
        if (!is_dir($saveDir)) {
            mkdir($saveDir, 0777, true);
        }

        file_put_contents($saveDir . "/AdminConfigMap.php", $this->_gen());

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