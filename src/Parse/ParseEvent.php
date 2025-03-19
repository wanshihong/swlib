<?php

namespace Swlib\Parse;

use Swlib\Event\Event;
use Swlib\Utils\File;
use ReflectionClass;
use ReflectionException;

class ParseEvent
{
    public function __construct()
    {

        $filesLib = File::eachDir(SWLIB_DIR, function ($filePath) {
            return str_ends_with($filePath, 'Event.php');
        });
        $filesApp = File::eachDir(ROOT_DIR . 'App', function ($filePath) {
            return str_ends_with($filePath, 'Event.php');
        });
        $files = array_merge($filesLib, $filesApp);

        $items = [];
        foreach ($files as $file) {
            $className = str_replace([ROOT_DIR, '.php'], '', $file);
            $className = str_replace("/", '\\', $className);

            try {
                $reflector = new ReflectionClass($className);
                $classAttributes = $reflector->getAttributes(Event::class);
                if (empty($classAttributes)) {
                    continue;
                }
                /** @var Event $classAttributes */
                $classAttributes = $classAttributes[0]->newInstance();
                $items[] = [
                    'name' => $classAttributes->name,
                    'run' => [$className, 'handle'],
                ];


            } catch (ReflectionException $e) {
                var_dump($e->getMessage());
            }
        }

        file_put_contents(
            RUNTIME_DIR . 'Generate/EventMap.php',
            $this->_gen(var_export($items, true)),
            LOCK_EX
        );

    }


    private function _gen(string $str): string
    {

        return <<<PHP
<?php

declare(strict_types=1);

namespace Generate;


class EventMap
{

    const array EVENTS = $str;


}
        
PHP;

    }
}