<?php
declare(strict_types=1);

namespace Swlib\Parse\Helper;

use Throwable;

/**
 * 终端颜色输出工具类
 */
class ConsoleColor
{
    // ANSI 颜色码
    const string COLOR_RESET = "\033[0m";
    const string COLOR_RED = "\033[31m";
    const string COLOR_GREEN = "\033[32m";
    const string COLOR_YELLOW = "\033[33m";
    const string COLOR_BLUE = "\033[34m";
    const string COLOR_MAGENTA = "\033[35m";
    const string COLOR_CYAN = "\033[36m";
    const string COLOR_WHITE = "\033[37m";

    // 背景色
    const string BG_RED = "\033[41m";
    const string BG_GREEN = "\033[42m";
    const string BG_YELLOW = "\033[43m";

    // 文本样式
    const string BOLD = "\033[1m";
    const string DIM = "\033[2m";
    const string UNDERLINE = "\033[4m";

    /**
     * 检查终端是否支持颜色输出
     * @return bool
     */
    public static function isColorSupported(): bool
    {
        // Windows 环境下检查
        if (DIRECTORY_SEPARATOR === '\\') {
            // Windows 10 版本 1511+ 支持 ANSI 颜色
            return function_exists('sapi_windows_vt100_support') &&
                sapi_windows_vt100_support(STDOUT);
        }

        // Unix-like 系统检查
        $term = getenv('TERM');
        if ($term === false || $term === 'dumb') {
            return false;
        }

        // 检查是否在支持颜色的终端中
        return is_resource(STDOUT) &&
            function_exists('posix_isatty') &&
            posix_isatty(STDOUT);
    }

    /**
     * 输出带颜色的文本
     * @param string $text 文本内容
     * @param string $color 颜色码
     * @param bool $newline 是否换行
     */
    public static function write(string $text, string $color = '', bool $newline = true): void
    {
        if (self::isColorSupported() && !empty($color)) {
            echo $color . $text . self::COLOR_RESET;
        } else {
            echo $text;
        }

        if ($newline) {
            echo PHP_EOL;
        }
    }

    /**
     * 输出默认颜色文本
     * @param string $text
     * @param bool $newline
     */
    public static function writeDefault(string $text, bool $newline = true): void
    {
        self::write($text, '', $newline);
    }

    /**
     * 输出绿色成功信息
     * @param string $text
     * @param bool $newline
     */
    public static function writeSuccess(string $text, bool $newline = true): void
    {
        self::write($text, self::COLOR_GREEN, $newline);
    }

    /**
     * 输出红色错误信息
     * @param string $text
     * @param bool $newline
     */
    public static function writeError(string $text, bool $newline = true): void
    {
        self::write($text, self::COLOR_RED, $newline);
    }

    /**
     * 输出黄色警告信息
     * @param string $text
     * @param bool $newline
     */
    public static function writeWarning(string $text, bool $newline = true): void
    {
        self::write($text, self::COLOR_YELLOW, $newline);
    }

    /**
     * 输出蓝色信息
     * @param string $text
     * @param bool $newline
     */
    public static function writeInfo(string $text, bool $newline = true): void
    {
        self::write($text, self::COLOR_BLUE, $newline);
    }

    /**
     * 输出绿色背景白色字体的成功提示
     * @param string $text
     * @param bool $newline
     */
    public static function writeSuccessHighlight(string $text, bool $newline = true): void
    {
        $color = self::BG_GREEN . self::COLOR_WHITE;
        self::write($text, $color, $newline);
    }

    /**
     * 输出绿色背景白色字体的成功提示
     * @param string $text
     * @param bool $newline
     */
    public static function writeErrorHighlight(string $text, bool $newline = true): void
    {
        $color = self::BG_RED . self::COLOR_WHITE;
        self::write($text, $color, $newline);
    }

    /**
     * 输出自定义背景色和前景色的文本
     * @param string $text 文本内容
     * @param string $bgColor 背景色常量
     * @param string $fgColor 前景色常量
     * @param bool $newline 是否换行
     */
    public static function writeWithColors(string $text, string $bgColor = '', string $fgColor = '', bool $newline = true): void
    {
        $color = $bgColor . $fgColor;
        self::write($text, $color, $newline);
    }

    /**
     * 输出带时间戳的步骤信息
     * @param string $stepName 步骤名称
     * @param string $status 状态 ('start', 'success', 'error')
     * @param float|null $duration 执行时间(秒)
     */
    public static function writeStep(string $stepName, string $status = 'start', ?float $duration = null): void
    {
        $timestamp = date('H:i:s');
        $durationText = $duration !== null ? sprintf(' (耗时: %.4f秒)', $duration) : '';

        switch ($status) {
            case 'start':
                self::writeDefault("[$timestamp] 正在$stepName...", false);
                break;
            case 'success':
                self::writeSuccess("[$timestamp] {$stepName}完成$durationText");
                break;
            case 'error':
                self::writeError("[$timestamp] {$stepName}失败$durationText");
                break;
            default:
                self::writeDefault("[$timestamp] $stepName$durationText");
        }
    }

    /**
     * 输出错误信息到STDERR
     * @param string $message 错误信息
     * @param Throwable|null $exception 异常对象
     */
    public static function writeErrorToStderr(string $message, ?Throwable $exception = null): void
    {
        $errorOutput = self::COLOR_RED . "[ERROR] " . $message . self::COLOR_RESET . PHP_EOL;

        if ($exception !== null) {
            $errorOutput .= self::COLOR_RED . $exception->getMessage() . self::COLOR_RESET . PHP_EOL;
            $errorOutput .= self::COLOR_RED . $exception->getTraceAsString() . self::COLOR_RESET . PHP_EOL;
        }

        fwrite(STDERR, $errorOutput);
    }
}
