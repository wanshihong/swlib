<?php

namespace Swlib\Parse;

use Swlib\Process\Process;
use Swlib\Utils\File;
use ReflectionClass;
use ReflectionException;

class ParseProcess
{
    public function __construct()
    {
        $filesLib = File::eachDir(SWLIB_DIR, function ($filePath) {
            return str_ends_with($filePath, 'Process.php');
        });
        $filesApp = File::eachDir(ROOT_DIR . 'App', function ($filePath) {
            return str_ends_with($filePath, 'Process.php');
        });
        $files = array_merge($filesLib, $filesApp);

        $items = [];
        foreach ($files as $file) {
            $file = str_replace(SWLIB_DIR, 'Swlib\\', $file);
            $className = str_replace([ROOT_DIR, '.php'], '', $file);
            $className = str_replace("/", '\\', $className);

            try {
                $reflector = new ReflectionClass($className);
                $classAttributes = $reflector->getAttributes(Process::class);
                if (empty($classAttributes)) {
                    continue;
                }
                /** @var Process $classAttributes */
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
            } catch (ReflectionException $e) {
                var_dump($e->getMessage());
            }
        }

        File::save(
            RUNTIME_DIR . 'Generate/ProcessMap.php',
            $this->_gen(var_export($items, true))
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