<?php

declare(strict_types=1);

namespace Swlib\Controller\Config\Service;

use Generate\ConfigMap;
use Generate\Tables\Main\ConfigTable;
use Swlib\Event\Attribute\Event;
use Swlib\Lock\Attribute\RedisLockAttribute;
use Swlib\Parse\Config\ParseDatabasesConfig;
use Swlib\Utils\Log;
use Throwable;

class ConfigService
{
    public const string VALUE_TYPE_TXT = 'txt';
    public const string VALUE_TYPE_NUMBER = 'number';
    public const string VALUE_TYPE_URL = 'url';
    public const string VALUE_TYPE_IMAGE = 'image';
    public const string VALUE_TYPE_TIME = 'time';
    public const string VALUE_TYPE_COLOR = 'color';
    public const string VALUE_TYPE_RANGE = 'range';

    /**
     * 监听到配置发生改变，重新生成静态文件
     */
    #[Event(name: ConfigTable::UpdateAfter)]
    #[Event(name: ConfigTable::InsertAfter)]
    #[Event(name: ConfigTable::DeleteAfter)]
    public function regenerateConfigMap(): void
    {
        try {
            new ParseDatabasesConfig();
        } catch (Throwable $e) {
            Log::save("ConfigMap regenerate failed: " . $e->getMessage(), 'config_error');
        }
    }

    /**
     * 获取配置值
     * @param string|array $key 配置键名，可以是单个字符串或者字符串数组
     * @param mixed $default 默认值
     * @param bool $checkEnable 是否检查启用状态（默认 true）
     * @param string $desc 默认说明（仅在未提供 descMap 时使用）
     * @param array<string,string> $descMap 每个 key 对应的说明
     * @return mixed 如果$key是字符串，返回单个配置值；如果$key是数组，返回关联数组[key=>value]
     * @throws Throwable
     */
    public static function get(string|array $key, mixed $default = null, bool $checkEnable = true, string $desc = '', array $descMap = []): mixed
    {
        $keys = is_string($key) ? [$key] : $key;
        $ret = [];

        foreach ($keys as $k) {
            $config = ConfigMap::$configs[$k] ?? null;

            if ($config === null) {
                // 静态文件不存在，尝试从数据库创建
                $descForKey = $descMap[$k] ?? $desc;
                $ret[$k] = self::createConfigFromDb($k, $default, $descForKey);
            } elseif ($checkEnable && $config['is_enable'] != 1) {
                $ret[$k] = $default;
            } else {
                $ret[$k] = self::parseValueByType(
                    value: $config['value'] ?? null,
                    valueType: (string)($config['value_type'] ?? self::VALUE_TYPE_TXT),
                    default: $default
                );
            }
        }

        return is_string($key) ? $ret[$key] : $ret;
    }

    /**
     * 获取原始配置信息（不管是否启用）
     * @param string $key 配置键名
     * @return array|null 返回完整配置信息或 null
     */
    public static function getRaw(string $key): ?array
    {
        return ConfigMap::$configs[$key] ?? null;
    }

    public static function clearCache(): void
    {
        ConfigMap::$configs = [];
        new ParseDatabasesConfig();
    }

    public static function parseValueByType(mixed $value, string $valueType, mixed $default = null): mixed
    {
        return match ($valueType) {
            self::VALUE_TYPE_NUMBER => self::parseNumberValue($value, $default),
            self::VALUE_TYPE_RANGE => self::parseRangeValue($value, $default),
            default => self::parseDefaultValue($value, $default),
        };
    }

    public static function parseRowValue(array|object $row, mixed $default = null): mixed
    {
        $value = is_array($row) ? ($row['value'] ?? null) : ($row->value ?? null);
        $valueType = is_array($row)
            ? (string)($row['value_type'] ?? $row['valueType'] ?? self::VALUE_TYPE_TXT)
            : (string)($row->valueType ?? $row->value_type ?? self::VALUE_TYPE_TXT);

        return self::parseValueByType($value, $valueType, $default);
    }

    /**
     * 从数据库创建配置并重新生成静态文件
     * @param string $key 配置键名
     * @param mixed $default 默认值
     * @param string $desc
     * @return mixed
     * @throws Throwable
     */
    #[RedisLockAttribute]
    private static function createConfigFromDb(string $key, mixed $default, string $desc = ''): mixed
    {
        $config = new ConfigTable()->where([ConfigTable::KEY => $key])->selectOne();

        if (empty($config)) {
            try {
                new ConfigTable()->insert([
                    ConfigTable::KEY => $key,
                    ConfigTable::VALUE => $default,
                    ConfigTable::IS_ENABLE => 1,
                    ConfigTable::DESC => $desc ?: '自动创建的配置项',
                    ConfigTable::ALLOW_QUERY => 0,
                    ConfigTable::VALUE_TYPE => 'txt',
                ]);
            } catch (Throwable $e) {
                Log::save("Failed to create config: key=$key, error: " . $e->getMessage(), 'config_error');
            }
            // 创建后重新生成静态文件
            new ParseDatabasesConfig();
            return $default;
        }

        // 如果配置存在但静态文件中缺失，重新生成静态文件
        new ParseDatabasesConfig();

        if ($config->isEnable == 1) {
            return self::parseValueByType(
                value: $config->value,
                valueType: (string)$config->valueType,
                default: $default
            );
        }

        return $default;
    }

    private static function parseDefaultValue(mixed $value, mixed $default): mixed
    {
        if (is_numeric($value)) {
            return self::parseNumberString((string)$value);
        }

        return empty($value) ? $default : $value;
    }

    private static function parseNumberValue(mixed $value, mixed $default): mixed
    {
        if (!is_numeric($value)) {
            return $default;
        }

        return self::parseNumberString((string)$value);
    }

    private static function parseRangeValue(mixed $value, mixed $default): mixed
    {
        if (is_array($value) && count($value) === 2) {
            $parts = array_values($value);
        } else {
            $raw = trim((string)$value);
            if ($raw === '') {
                return self::normalizeRangeDefault($default);
            }

            $parts = preg_split('/\s*,\s*/', $raw);
        }

        if (!is_array($parts) || count($parts) !== 2) {
            return self::normalizeRangeDefault($default);
        }

        [$startRaw, $endRaw] = $parts;
        if (!is_numeric($startRaw) || !is_numeric($endRaw)) {
            return self::normalizeRangeDefault($default);
        }

        $start = self::parseNumberString((string)$startRaw);
        $end = self::parseNumberString((string)$endRaw);

        return $start <= $end ? [$start, $end] : [$end, $start];
    }

    private static function normalizeRangeDefault(mixed $default): mixed
    {
        if (!is_array($default) || count($default) !== 2) {
            return $default;
        }

        [$startRaw, $endRaw] = array_values($default);
        if (!is_numeric($startRaw) || !is_numeric($endRaw)) {
            return $default;
        }

        $start = self::parseNumberString((string)$startRaw);
        $end = self::parseNumberString((string)$endRaw);

        return $start <= $end ? [$start, $end] : [$end, $start];
    }

    private static function parseNumberString(string $value): int|float
    {
        return str_contains($value, '.') ? (float)$value : (int)$value;
    }
}
