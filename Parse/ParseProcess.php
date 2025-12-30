<?php

namespace Swlib\Parse;

use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use Swlib\Parse\Helper\ConsoleColor;
use Swlib\Process\Attribute\ProcessAttribute;
use Swlib\Utils\DataConverter;
use Swlib\Utils\File;

class ParseProcess
{
    public function __construct()
    {
        $filesLib = File::eachDir(SWLIB_DIR, function ($filePath) {
            return str_ends_with($filePath, '.php');
        });
        $filesApp = File::eachDir(ROOT_DIR . 'App', function ($filePath) {
            return str_ends_with($filePath, '.php');
        });
        $files = array_merge($filesLib, $filesApp);

        $items = [];
        foreach ($files as $file) {
            // 跳过 ProcessAttribute.php 文件本身
            if (str_ends_with($file, 'ProcessAttribute.php')) {
                continue;
            }

            $file = str_replace(SWLIB_DIR, 'Swlib\\', $file);
            $className = str_replace([ROOT_DIR, '.php'], '', $file);
            $className = str_replace("/", '\\', $className);

            try {
                $reflector = new ReflectionClass($className);

                // 解析类级别的注解
                $classAttributes = $reflector->getAttributes(ProcessAttribute::class);
                if (!empty($classAttributes)) {
                    /** @var ProcessAttribute $classAttributes */
                    $classAttributes = $classAttributes[0]->newInstance();
                    $redirect_stdin_stdout = $classAttributes->redirect_stdin_stdout;
                    $pipe_type = $classAttributes->pipe_type;
                    $enable_coroutine = $classAttributes->enable_coroutine;
                    $interval = $classAttributes->interval;

                    $items[] = [
                        'run' => [$className, 'handle'],
                        'redirect_stdin_stdout' => $redirect_stdin_stdout,
                        'pipe_type' => $pipe_type,
                        'enable_coroutine' => $enable_coroutine,
                        'interval' => $interval,
                    ];
                }

                // 解析方法级别的注解
                $methods = $reflector->getMethods(ReflectionMethod::IS_PUBLIC);
                foreach ($methods as $method) {
                    $methodAttributes = $method->getAttributes(ProcessAttribute::class);
                    if (!empty($methodAttributes)) {
                        /** @var ProcessAttribute $methodAttribute */
                        $methodAttribute = $methodAttributes[0]->newInstance();
                        $redirect_stdin_stdout = $methodAttribute->redirect_stdin_stdout;
                        $pipe_type = $methodAttribute->pipe_type;
                        $enable_coroutine = $methodAttribute->enable_coroutine;
                        $interval = $methodAttribute->interval;

                        $items[] = [
                            'run' => [$className, $method->getName()],
                            'redirect_stdin_stdout' => $redirect_stdin_stdout,
                            'pipe_type' => $pipe_type,
                            'enable_coroutine' => $enable_coroutine,
                            'interval' => $interval,
                        ];
                    }
                }
            } catch (ReflectionException $e) {
                ConsoleColor::writeError("进程解析错误: {$e->getMessage()}");
            }
        }

        File::save(
            RUNTIME_DIR . 'Generate/ProcessMap.php',
            $this->_gen(DataConverter::exportShort($items, true))
        );

    }

    private function _gen(string $str): string
    {
        return <<<EOF
<?php

declare(strict_types=1);

namespace Generate;


class ProcessMap
{

    const array PROCESS = $str;


}
EOF;
    }
}