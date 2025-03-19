<?php

namespace Swlib\Utils;

use Exception;
use FilesystemIterator;
use Swlib\Enum\CtxEnum;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class File
{

    /**
     * 遍历目录下面的所有文件
     * @param $dir
     * @param callable|null $filterCallback
     * @return array
     */
    public static function eachDir($dir, ?callable $filterCallback = null): array
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
     * @throws Exception
     */
    public static function copyDirectory($sourceDir, $targetDir): void
    {

        if (!is_dir($sourceDir)) {
            throw new Exception('请指定一个需要复制的目录');
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


    public static function delDirectory($dir): void
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
     * @throws Exception
     */
    public static function upload(string $dir, string $uploadKey = 'file'): string
    {
        // 补全后面的斜线
        if (!str_ends_with($dir, '/')) {
            $dir = $dir . '/';
        }

        $request = CtxEnum::Request->get();
        $file = $request->files[$uploadKey];
        if (empty($file)) {
            throw new Exception('请指定一个需要上传的文件');
        }
        $tmp_name = $file['tmp_name'];
        $filename = $file['name'];

        $md5file = md5_file($tmp_name);

        // 生成目录
        // 取MD5的前6位，并分为两段，每段3位
        $dir = $dir . substr($md5file, 0, 3) . '/' . substr($md5file, 3, 3) . '/';

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        // 取得文件后缀，并生成新的文件名称
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $filename = uniqid() . date('YmdHis') . '.' . $ext;

        // 文件存储路径
        $filepath = $dir . $filename;

        move_uploaded_file($tmp_name, $filepath);
        return $filepath;
    }

}