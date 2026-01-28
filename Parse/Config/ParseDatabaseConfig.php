<?php
declare(strict_types=1);

namespace Swlib\Parse\Config;

use Generate\ConfigEnum;
use Swlib\Parse\Helper\ConsoleColor;
use Swlib\Utils\DataConverter;
use Swlib\Utils\File;
use Swlib\Utils\StringConverter;

class ParseDatabaseConfig
{

    public function __construct()
    {
        $dbRaw = (string)ConfigEnum::get('DB_DATABASE', '');
        if (trim($dbRaw) === '') {
            ConsoleColor::writeErrorHighlight('配置文件错误: DB_DATABASE 不能为空');
            exit(1);
        }

        $dbNames = $this->splitList($dbRaw);

        if (empty($dbNames)) {
            ConsoleColor::writeErrorHighlight('配置文件错误: DB_DATABASE 不能为空');
            exit(1);
        }


        $count = count($dbNames);
        $hosts = $this->expandList(ConfigEnum::get('DB_HOST', ''), $count, 'DB_HOST');
        $ports = $this->expandList(ConfigEnum::get('DB_PORT', ''), $count, 'DB_PORT');
        $users = $this->expandList(ConfigEnum::get('DB_ROOT', ''), $count, 'DB_ROOT');
        $passes = $this->expandListAllowEmpty(ConfigEnum::get('DB_PWD', ''), $count);
        $charsets = $this->expandList(ConfigEnum::get('DB_CHARSET', ''), $count, 'DB_CHARSET');

        $databaseConfig = [];
        for ($i = 0; $i < $count; $i++) {
            $dbName = (string)$dbNames[$i];
            $arr = explode(':', $dbName);
            $dbName = $arr[0];
            $defaultNamespace = $i === 0 ? 'main' : $arr[0];
            $namespace = $arr[1] ?? $defaultNamespace;
            $namespace = StringConverter::underscoreToCamelCase($namespace);

            $databaseConfig[$dbName] = [
                'namespace' => $namespace,
                'host' => (string)$hosts[$i],
                'port' => (string)$ports[$i],
                'user' => (string)$users[$i],
                'pass' => (string)$passes[$i],
                'charset' => (string)$charsets[$i]
            ];
        }

        $out = $databaseConfig;

        $saveDir = ROOT_DIR . 'runtime/Generate';
        if (!is_dir($saveDir)) {
            mkdir($saveDir, 0777, true);
        }

        File::save($saveDir . '/DatabaseConnect.php', $this->genFile($out));
    }

    private function genFile(array $config): string
    {
        $export = DataConverter::exportShort($config);
        return <<<PHP
<?php
declare(strict_types=1);

namespace Generate;

use Swlib\Table\Trait\DatabaseConnectTrait;

class DatabaseConnect
{
    use DatabaseConnectTrait;

    public const array config = $export;
}
PHP;
    }

    private function splitList(string $value): array
    {
        $items = array_map('trim', explode(',', $value));
        return array_values(array_filter($items, static fn($v) => $v !== ''));
    }


    private function expandList(mixed $value, int $count, string $keyName): array
    {
        $raw = trim((string)$value);
        if ($raw === '') {
            ConsoleColor::writeErrorHighlight("配置文件错误: $keyName 不能为空");
            exit(1);
        }

        $arr = $this->splitList($raw);
        if (count($arr) <= 1) {
            return array_fill(0, $count, $raw);
        }
        if (count($arr) !== $count) {
            ConsoleColor::writeErrorHighlight("配置文件错误: $keyName 数量(" . count($arr) . ")必须与 DB_DATABASE 数量($count)一致");
            exit(1);
        }
        return $arr;
    }

    private function expandListAllowEmpty(mixed $value, int $count): array
    {
        $raw = (string)$value;
        $arr = $this->splitList($raw);
        if (count($arr) <= 1) {
            return array_fill(0, $count, $raw);
        }
        if (count($arr) !== $count) {
            ConsoleColor::writeErrorHighlight("配置文件错误: DB_PWD 数量(" . count($arr) . ")必须与 DB_DATABASE 数量($count)一致");
            exit(1);
        }
        return $arr;
    }

}

