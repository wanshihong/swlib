<?php
declare(strict_types=1);

namespace Swlib\Utils;

use Exception;
use Generate\ConfigEnum;
use Swlib\Enum\CtxEnum;
use Throwable;

class Log
{
    /**
     * 日志级别常量
     */
    private const string LEVEL_DEBUG = 'DEBUG';
    private const string LEVEL_INFO = 'INFO';
    private const string LEVEL_WARNING = 'WARNING';
    private const string LEVEL_ERROR = 'ERROR';

    /**
     * 格式化日志消息
     *
     * @param string $level 日志级别
     * @param string $message 消息内容
     * @param array $context 上下文数据
     * @return string
     */
    private static function formatMessage(string $level, string $message, array $context = []): string
    {
        $formatted = "[$level] $message";

        if (!empty($context)) {
            $formatted .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $formatted;
    }

    /**
     * 记录调试级别日志
     *
     * @param string $message
     * @param array $context
     * @param string $logModule
     */
    public static function debug(string $message, array $context = [], string $logModule = 'debug'): void
    {
        static::saveLog([
            'logModule' => $logModule,
            'msg' => self::formatMessage(self::LEVEL_DEBUG, $message, $context),
        ]);
    }

    /**
     * 记录信息级别日志
     *
     * @param string $message
     * @param array $context
     * @param string $logModule
     */
    public static function info(string $message, array $context = [], string $logModule = 'info'): void
    {
        static::saveLog([
            'logModule' => $logModule,
            'msg' => self::formatMessage(self::LEVEL_INFO, $message, $context),
        ]);
    }

    /**
     * 记录警告级别日志
     *
     * @param string $message
     * @param array $context
     * @param string $logModule
     */
    public static function warning(string $message, array $context = [], string $logModule = 'warning'): void
    {
        static::saveLog([
            'logModule' => $logModule,
            'msg' => self::formatMessage(self::LEVEL_WARNING, $message, $context),
        ]);
    }

    /**
     * 记录错误级别日志
     *
     * @param string $message
     * @param array $context
     * @param string $logModule
     */
    public static function error(string $message, array $context = [], string $logModule = 'error'): void
    {
        static::saveLog([
            'logModule' => $logModule,
            'msg' => self::formatMessage(self::LEVEL_ERROR, $message, $context),
        ]);
    }

    /**
     * 创建日志目录
     *
     * @return string 日志目录路径
     * @throws Exception
     */
    private static function mkdir(): string
    {
        $date = date('Ymd');
        $dir = RUNTIME_DIR . "log/$date/";

        if (!is_dir($dir)) {
            if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
                throw new Exception("Failed to create log directory: $dir");
            }
        }
        return $dir;
    }

    /**
     * 条件日志记录（仅在开发环境记录）
     *
     * @param string $message
     * @param array $context
     * @param string $logModule
     */
    public static function debugOnly(string $message, array $context = [], string $logModule = 'debug'): void
    {
        if (ConfigEnum::APP_PROD === false) {
            self::debug($message, $context, $logModule);
        }
    }

    /**
     * 批量记录日志
     *
     * @param array $logs 日志数组，每个元素包含 message, context, module
     */
    public static function batch(array $logs): void
    {
        foreach ($logs as $log) {
            $message = $log['message'] ?? '';
            $context = $log['context'] ?? [];
            $module = $log['module'] ?? 'default';
            $level = $log['level'] ?? 'info';

            match ($level) {
                'debug' => self::debug($message, $context, $module),
                'warning' => self::warning($message, $context, $module),
                'error' => self::error($message, $context, $module),
                default => self::info($message, $context, $module),
            };
        }
    }

    /**
     * @param string $msg 日志的消息
     * @param string $logModule 消息存放目录
     * @return void
     */
    public static function save(string $msg, string $logModule = 'default'): void
    {
        if (ConfigEnum::APP_PROD === false) {
            var_dump($msg);
        }
        static::saveLog([
            'logModule' => $logModule,
            'msg' => $msg,
        ]);
    }

    /**
     * 保存日志（增强版，支持字符串或数组消息）
     *
     * @param string|array $message 日志消息或消息数组
     * @param string $logModule 日志模块
     * @return void
     */
    public static function write(string|array $message, string $logModule = 'default'): void
    {
        $msg = is_array($message) ? json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $message;

        if (ConfigEnum::APP_PROD === false) {
            var_dump($msg);
        }

        static::saveLog([
            'logModule' => $logModule,
            'msg' => $msg,
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
        if (ConfigEnum::APP_PROD === false) {
            var_dump($e->getMessage());
            var_dump($e->getTraceAsString());
        }
        $msg = self::getTraceMsg($e);
        static::saveLog([
            'logModule' => $logModule,
            'msg' => $msg,
        ]);
    }

    private static function saveLog(array $data): void
    {
        try {
            $logModule = $data['logModule'] ?? 'default';
            $msg = $data['msg'] ?? '';

            // 获取请求上下文信息
            $requestId = '';
            $requestUri = '';

            try {
                $requestId = CtxEnum::RequestId->get() ?: '';
                $requestUri = CtxEnum::URI->get() ?: '';
            } catch (Throwable) {
                // 忽略上下文获取异常
            }

            $dir = self::mkdir();
            $time = date('H:i:s');
            $filePath = $dir . $logModule . '.log';

            // 格式化日志行
            $logLine = PHP_EOL . "[$time]";
            if ($requestId) {
                $logLine .= " [$requestId]";
            }
            if ($requestUri) {
                $logLine .= " [$requestUri]";
            }
            $logLine .= " $msg";

            File::save($filePath, $logLine, true);

        } catch (Throwable $e) {
            // 日志系统异常时，尝试输出到标准错误
            error_log("Log system error: " . $e->getMessage());
        }
    }
}