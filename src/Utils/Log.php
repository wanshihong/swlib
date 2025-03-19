<?php
declare(strict_types=1);

namespace Swlib\Utils;

use Exception;
use Swlib\Enum\CtxEnum;
use Throwable;

class Log
{


    private static function mkdir(string $logModule = 'default'): string
    {
        $dir = RUNTIME_DIR. "log/$logModule/";

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        return $dir;
    }

    /**
     * @param string $msg 日志的消息
     * @param string $logModule 消息存放目录
     * @return void
     */
    public static function save(string $msg, string $logModule = 'default'): void
    {
        static::saveLog([
            'logModule' => $logModule,
            'msg' => $msg,
            'requestId' => CtxEnum::RequestId->get(),
        ]);
    }


    public static function getTraceMsg(Exception|Throwable $e): string
    {
        $msg = $e->getMessage() . PHP_EOL . $e->getFile() . ' line:' . $e->getLine() . PHP_EOL;
        foreach ($e->getTrace() as $trace) {
            $arg = json_encode($trace['args'] ?? []);
            $file = $trace['file'] ?? '';
            $line = isset($trace['line']) ? "on line:{$trace['line']} " : "";
            $class = $trace['class'] ?? '';
            $type = $trace['type'] ?? '';
            $msg .= "$file $line $class$type{$trace['function']}($arg)" . PHP_EOL;
        }
        return $msg;
    }

    public static function saveException(Exception|Throwable $e, string $logModule = 'default'): void
    {
        $msg = self::getTraceMsg($e);
        static::saveLog([
            'logModule' => $logModule,
            'msg' => $msg,
            'requestId' => CtxEnum::RequestId->get(),
        ]);
    }

    private static function saveLog(array $data): void
    {
        $logModule = $data['logModule'];
        $msg = $data['msg'];
        $requestId = $data['requestId'];
        $dir = self::mkdir($logModule);
        $time = date('H:i:s');
        $filePath = $dir . date('Ymd') . '.log';
        file_put_contents($filePath, PHP_EOL . "[$time]$requestId $msg", FILE_APPEND);
    }
}