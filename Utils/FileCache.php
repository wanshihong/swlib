<?php
declare(strict_types=1);

namespace Swlib\Utils;

use FilesystemIterator;
use Swlib\Exception\AppErr;
use Swlib\Exception\AppException;
use InvalidArgumentException;


class FileCache
{
    /**
     * 缓存文件存储的基础目录
     */
    private const string CACHE_DIR = RUNTIME_DIR . 'cache/distributed_file_cache/';


    /**
     * 默认缓存过期时间（秒）
     */
    private const int DEFAULT_TTL = 86400;


    /**
     * 获取缓存数据
     *
     * @param string $key 缓存键
     * @return mixed|null 缓存值，不存在或已过期返回null
     */
    public static function get(string $key): mixed
    {
        $cachePath = self::getCachePath($key);
        if (!is_file($cachePath)) {
            return null;
        }
        return self::getValue($cachePath);

    }

    /**
     * 设置缓存数据
     *
     * @param string $key 缓存键
     * @param mixed $value 缓存值
     * @param int $ttl 过期时间（秒），0表示使用默认过期时间
     */
    public static function set(string $key, mixed $value, int $ttl = 0): false|int
    {
        $cachePath = self::getCachePath($key);
        // 确保目录存在
        File::ensureDirectoryExists(dirname($cachePath));

        return file_put_contents($cachePath, json_encode([
            'value' => $value,
            'expire' => time() + ($ttl ?: self::DEFAULT_TTL),
        ], JSON_UNESCAPED_UNICODE));
    }

    public static function delete(string $key): bool
    {
        $cachePath = self::getCachePath($key);
        return unlink($cachePath);
    }

    /**
     * 生成缓存键
     *
     * @param string $key
     * @return string 缓存键
     */
    private static function getCachePath(string $key): string
    {
        $hash = hash('sha256', $key);
        $hashLength = strlen($hash);


        $levelsCount = 9;
        $levelLength = 2;

        // 计算需要的总字符数
        $totalCharsNeeded = $levelsCount * $levelLength;

        // 如果散列值长度不足，抛出异常
        if ($totalCharsNeeded > $hashLength) {
            throw new AppException(AppErr::CACHE_HASH_LENGTH_INSUFFICIENT);
        }

        // 将散列值拆分成指定级数的目录
        $levels = [];
        for ($i = 0; $i < $levelsCount; $i++) {
            $start = $i * $levelLength;
            $levels[] = substr($hash, $start, $levelLength);
        }

        // 组合成目录路径，并添加剩余部分
        $directoryPath = implode(DIRECTORY_SEPARATOR, $levels);
        $remainingHash = substr($hash, $totalCharsNeeded);

        // 如果还有剩余部分，添加到路径中
        if ($remainingHash !== '') {
            return self::CACHE_DIR . $directoryPath . DIRECTORY_SEPARATOR . $remainingHash . '.cache';
        }

        return self::CACHE_DIR . $directoryPath . '.cache';
    }


    public static function clearExpire(?int $timestamp = null): void
    {
        File::eachDir(self::CACHE_DIR, function ($filePath) use ($timestamp) {
            // 如果传入了时间戳，检查文件修改时间
            if ($timestamp !== null) {
                $fileModTime = filemtime($filePath);
                // 只处理修改时间小于传入时间戳的文件
                if ($fileModTime === false || $fileModTime >= $timestamp) {
                    return;
                }
            }

            $res = self::getValue($filePath);
            if ($res === null) {
                while (($filePath . '/') !== self::CACHE_DIR) {
                    $dirPath = dirname($filePath);
                    $iterator = new FilesystemIterator($dirPath);
                    if (!$iterator->valid()) {
                        rmdir($dirPath);
                        $filePath = dirname($filePath);
                    }
                }
            }
        });
    }

    private static function getValue($cachePath): mixed
    {
        $ctx = file_get_contents($cachePath);
        if ($ctx === false) {
            unlink($cachePath);
            return null;
        }

        $array = json_decode($ctx, true);
        if (!$array) {
            unlink($cachePath);
            return null;
        }
        if ($array['expire'] < time()) {
            unlink($cachePath);
            return null;
        }
        return $array['value'];
    }


}
