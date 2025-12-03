<?php
declare(strict_types=1);

namespace Swlib\Parse;


use Swlib\Utils\File;

class ParseConfig
{

    public array $data = [];


    public function __construct()
    {

        $lockFile = RUNTIME_DIR . 'server_id.lock';
        if (is_file($lockFile)) {
            $serverId = file_get_contents($lockFile);
        } else {
            $serverId = uniqid();
            File::save($lockFile, $serverId);
        }

        $prodEnv = ROOT_DIR . '.env.prod';
        $devEnv = ROOT_DIR . '.env';
        if (is_file($prodEnv)) {
            $envFile = $prodEnv;
        } else {
            $envFile = $devEnv;
        }

        $config = $this->parse($envFile);

        if (!isset($config['SERVER_ID'])) {
            $config['SERVER_ID'] = $serverId;
        }
        if (!isset($config['PROJECT_UNIQUE'])) {
            $config['PROJECT_UNIQUE'] = md5(json_encode($config));
        }

        if (!isset($config['HTTPS'])) {
            $config['HTTPS'] = "false";
        }

        $str = '';
        foreach ($config as $key => $value) {
            $value = trim($value);
            $str .= $this->_genItem($key, $value) . PHP_EOL;

            if ($key === 'ADMIN_CONFIG_PATH') {
                $arr = explode('\\', $value);
                $result = strtolower(preg_replace('/(?<!^)([A-Z])/', '-$1', $arr[1]));
                $str .= $this->_genItem('ADMIN_ROUTER_START_PATH', $result) . PHP_EOL;
            }

        }


        $saveDir = ROOT_DIR . "runtime/Generate";
        File::save($saveDir . "/ConfigEnum.php", $this->_gen($str));
    }


    private function parse(string $envFile): array
    {
        if (!file_exists($envFile)) {
            echo "$envFile 文件不存在" . PHP_EOL;
            exit();
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        // 定义一个数组来存储解析后的环境变量
        $envVars = [];

        // 遍历每一行
        foreach ($lines as $line) {
            // 去掉注释
            if (str_starts_with(trim($line), '#')) {
                continue; // 跳过注释行
            }

            // 分割键值对
            $parts = explode('=', trim($line), 2);
            if (count($parts) == 2) {
                list($key, $value) = $parts;
                $envVars[$key] = trim($value, '"'); // 去掉两端的双引号
            }
        }

        return $envVars;
    }

    private function _genItem(string $key, mixed $value): string
    {
        if (is_numeric($value)) {
            $retVal = intval($value);
            $retType = 'int';
        } elseif ($value == 'true' || $value == 'false') {
            $retVal = $value;
            $retType = 'bool';
        } else {
            if (str_contains($value, ',')) {
                $retVal = var_export(explode(',', $value), true);
                $retType = 'array';
            } else {
                $retVal = "'$value'";
                $retType = 'string';
            }
        }

        return "    const $retType $key = $retVal;";
    }


    private function _gen(string $str): string
    {
        return <<<STR
<?php

declare(strict_types=1);

namespace Generate;


class ConfigEnum
{

$str

}
STR;

    }
}