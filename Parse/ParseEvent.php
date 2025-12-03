<?php

namespace Swlib\Parse;

use ReflectionClass;
use ReflectionException;
use Swlib\Event\Attribute\Event;
use Swlib\Utils\File;
use Swlib\Utils\Func;

class ParseEvent
{

    private static array $maps = [];

    public function __construct()
    {

        $filesLib = File::eachDir(SWLIB_DIR, function ($filePath) {
            return str_ends_with($filePath, '.php');
        });
        $filesApp = File::eachDir(ROOT_DIR . 'App', function ($filePath) {
            return str_ends_with($filePath, '.php');
        });
        $files = array_merge($filesLib, $filesApp);

        foreach ($files as $file) {
            $file = str_replace(SWLIB_DIR, 'Swlib\\', $file);
            $className = str_replace([ROOT_DIR, '.php'], '', $file);
            $className = str_replace("/", '\\', $className);

            try {
                $reflector = new ReflectionClass($className);

                // 解析类上的 Event 注解
                $classAttributes = $reflector->getAttributes(Event::class);
                if (!empty($classAttributes)) {
                    /** @var Event $eventAttribute */
                    $eventAttribute = $classAttributes[0]->newInstance();
                    $eventName = $eventAttribute->name;
                    $this->addMap($eventName, $className, 'handle', $eventAttribute->priority);
                }

                // 解析方法上的 Event 注解
                foreach ($reflector->getMethods() as $method) {
                    $methodAttributes = $method->getAttributes(Event::class);
                    if (empty($methodAttributes)) {
                        continue;
                    }

                    /** @var Event $eventAttribute */
                    $eventAttribute = $methodAttributes[0]->newInstance();
                    $eventName = $eventAttribute->name;
                    $methodName = $method->getName();

                    $this->addMap($eventName, $className, $methodName, $eventAttribute->priority);
                }
            } catch (ReflectionException $e) {
                var_dump($e->getMessage());
            }
        }

    }

    /**
     * @param $eventName string  事件名称
     * @param $className string 执行的类方法
     * @param $methodName string 执行的函数
     * @param $priority int 执行优先级
     * @return void
     */
    public static function addMap(string $eventName, string $className, string $methodName, int $priority): void
    {

        $listener = [
            'priority' => $priority,
            'run' => [$className, $methodName],
        ];

        // 使用Hash Table结构，支持同一事件名称多个监听器
        if (!isset(self::$maps[$eventName])) {
            self::$maps[$eventName] = [];
        }
        self::$maps[$eventName][] = $listener;
    }


    public function __destruct()
    {

        $map = Func::exportShort(self::$maps);
        $str = <<<PHP
<?php

declare(strict_types=1);

namespace Generate;


class EventMap
{

    const array EVENTS = $map;


}
        
PHP;


        File::save(
            RUNTIME_DIR . 'Generate/EventMap.php',
            $str
        );

    }
}