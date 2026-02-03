<?php

namespace Swlib\Utils;

use Exception;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Swlib\Controller\Language\Enum\LanguageEnum;
use Swlib\Exception\AppException;

/**
 * 通用文件及目录操作工具类
 *
 * 提供了一组与具体业务无关的、静态的、可重用的文件和目录处理方法。
 */
class File
{

    /**
     * 确保指定的目录存在
     *
     * 如果目录或其父目录不存在，此方法会递归创建它们。
     *
     * @param string $dir 需要检查和创建的目录的绝对路径。
     * @return void
     */
    public static function ensureDirectoryExists(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    /**
     * 遍历指定目录下的所有文件和子目录（递归）
     *
     * @param string $dir 需要遍历的目录路径。
     * @param callable|null $filterCallback 一个可选的回调函数，用于过滤文件。
     *                                      该回调函数接收文件路径作为参数，返回true则包含该文件，返回false则排除。
     * @return array 返回包含所有（通过筛选的）文件绝对路径的数组。
     */
    public static function eachDir(string $dir, ?callable $filterCallback = null): array
    {
        if (!is_dir($dir)) {
            return [];
        }
        // 创建一个递归目录迭代器   SKIP_DOTS: 忽略当前目录（.）和父目录（..）
        $iterator = new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS);

        // 创建一个递归迭代器迭代器   SELF_FIRST: 在子元素之前先处理当前元素。
        $files = new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::SELF_FIRST);

        $ret = [];
        // 遍历所有文件和目录
        foreach ($files as $file) {
            if ($file->isFile()) {
                $filePath = $file->getPathname();
                if ($filterCallback) {
                    $filter = $filterCallback($filePath);
                    if (!$filter) {
                        continue;
                    }
                }
                $ret[] = $filePath;
            }
        }
        return $ret;
    }

    /**
     * 递归复制整个目录
     *
     * @param string $sourceDir 源目录路径。
     * @param string $targetDir 目标目录路径。
     * @return void
     * @throws Exception 如果源目录不是一个有效的目录。
     */
    public static function copyDirectory(string $sourceDir, string $targetDir): void
    {

        if (!is_dir($sourceDir)) {
            throw new AppException(LanguageEnum::DIR_NOT_EXIST);
        }

        // 检查目标目录是否存在，如果不存在则创建
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        // 获取源目录下的所有文件和子目录
        $files = scandir($sourceDir);

        foreach ($files as $file) {
            // 跳过当前目录和父目录
            if ($file == '.' || $file == '..') {
                continue;
            }
            // 构造完整路径
            $source_file = $sourceDir . '/' . $file;
            $destination_file = $targetDir . '/' . $file;

            // 判断是文件还是目录
            if (is_dir($source_file)) {
                // 如果是目录，则递归调用 copyDirectory
                self::copyDirectory($source_file, $destination_file);
            } else {
                // 如果是文件，则直接复制
                copy($source_file, $destination_file);
            }
        }
    }


    /**
     * 递归删除目录及其所有内容
     *
     * @param string $dir 需要删除的目录路径。
     * @return void
     */
    public static function delDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $entry) {
            if ($entry == "." || $entry == "..") {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($path)) {
                self::delDirectory($path);
                @rmdir($path);
            } else {
                $filePath = $dir . DIRECTORY_SEPARATOR . $entry;
                @unlink($filePath);
            }
        }
        rmdir($dir);
    }

    /**
     * 将内容保存到文件
     *
     * 在写入文件前，会自动检查并创建目标目录（如果不存在）。
     *
     * @param string $file 文件的绝对路径。
     * @param mixed $content 需要写入文件的内容。
     * @param bool $append 整个文件覆盖，还是追加写入。
     * @return false|int 成功时返回写入的字节数，失败时返回false。
     */
    public static function save(string $file, mixed $content, bool $append = false): false|int
    {
        $dir = dirname($file);

        try {
            self::ensureDirectoryExists($dir);
        } catch (Exception) {

        }
        if ($append) {
            return file_put_contents($file, $content, FILE_APPEND);
        } else {
            return file_put_contents($file, $content);
        }

    }

}