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
     * @param string $key 配置键名
     * @param mixed $default 默认值
     * @param string $desc 默认说明
     * @param string $valueType 默认值类型
     * @return mixed
     * @throws Throwable
     */
    public static function get(
        string $key,
        mixed $default = null,
        string $desc = '',
        string $valueType = self::VALUE_TYPE_TXT
    ): mixed
    {
        return self::getOne($key, $default, $desc, $valueType);
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

    public static function formatValueForStorage(mixed $value, string $valueType = self::VALUE_TYPE_TXT): mixed
    {
        return match ($valueType) {
            self::VALUE_TYPE_RANGE => self::formatRangeValueForStorage($value),
            default => $value,
        };
    }

    /**
     * @throws Throwable
     */
    private static function getOne(
        string $key,
        mixed $default = null,
        string $desc = '',
        string $valueType = self::VALUE_TYPE_TXT
    ): mixed {
        $config = ConfigMap::$configs[$key] ?? null;

        if ($config === null) {
            return self::createConfigFromDb($key, $default, $desc, $valueType);
        }

        if ($config['is_enable'] != 1) {
            return $default;
        }

        return self::parseValueByType(
            value: $config['value'] ?? null,
            valueType: (string)($config['value_type'] ?? self::VALUE_TYPE_TXT),
            default: $default
        );
    }

    /**
     * 从数据库创建配置并重新生成静态文件
     * @param string $key 配置键名
     * @param mixed $default 默认值
     * @param string $desc
     * @param string $valueType
     * @return mixed
     * @throws Throwable
     */
    #[RedisLockAttribute]
    private static function createConfigFromDb(
        string $key,
        mixed $default,
        string $desc = '',
        string $valueType = self::VALUE_TYPE_TXT
    ): mixed
    {
        $config = new ConfigTable()->where([ConfigTable::KEY => $key])->selectOne();

        if (empty($config)) {
            try {
                new ConfigTable()->insert([
                    ConfigTable::KEY => $key,
                    ConfigTable::VALUE => self::formatValueForStorage($default, $valueType),
                    ConfigTable::IS_ENABLE => 1,
                    ConfigTable::DESC => $desc ?: '自动创建的配置项',
                    ConfigTable::ALLOW_QUERY => 0,
                    ConfigTable::VALUE_TYPE => $valueType,
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

    private static function formatRangeValueForStorage(mixed $value): mixed
    {
        $normalized = self::normalizeRangeDefault(self::parseValueByType($value, self::VALUE_TYPE_RANGE, $value));
        if (!is_array($normalized) || count($normalized) !== 2) {
            return $value;
        }

        return $normalized[0] . ',' . $normalized[1];
    }

    private static function parseNumberString(string $value): int|float
    {
        return str_contains($value, '.') ? (float)$value : (int)$value;
    }
}
