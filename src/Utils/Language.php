<?php
declare(strict_types=1);

namespace Swlib\Utils;


use Swlib\Connect\PoolMysql;
use Swlib\Connect\PoolRedis;
use Swlib\Enum\CtxEnum;
use Swlib\Exception\LangException;
use Redis;
use Throwable;

class Language
{

    private static array $maps = [];


    /**
     * @throws Throwable
     */
    public static function getLanguages(): array
    {
        $languageTableInfo = PoolMysql::query("DESCRIBE  language")->fetch_all(MYSQLI_ASSOC);
        $strings = [];
        foreach ($languageTableInfo as $index => $item) {
            if ($index <= 2) continue;
            $strings[] = $item['Field'];
        }
        $ret = [];
        foreach ($strings as $lang) {
            $ret[$lang] = self::get($lang);
        }
        return $ret;
    }


    /**
     * 获取翻译后的文本
     * 如果占位需要替换请参考  sprintf 函数
     * 示例 字段序号%d 名称%s不存在
     * 示例 ::get('字段序号%d 名称%s不存在',1,'name')
     * @throws Throwable
     */
    public static function get(string $str, ...$arg): string
    {
        $lang = CtxEnum::Lang->get('zh');
        if (empty($lang)) {
            throw new LangException('request header lang is empty');
        }

        // 缓存查询
        $cache = self::getCache($str, $lang);
        if ($cache !== false) {
            return sprintf($cache, ...$arg);
        }

        // 数据库查询
        $ret = self::query($str, $lang);
        return sprintf($ret, ...$arg);
    }

    /**
     * 从缓存获取翻译配置
     * @param string $str
     * @param string $lang
     * @return false|string
     */
    private static function getCache(string $str, string $lang): false|string
    {
        $maps = self::$maps[$lang] ?? [];

        if (!isset($maps[$str])) {
            return false;
        }
        $data = $maps[$str];
        if (time() - $data['t'] > 3600) {
            return false;
        }
        return $maps[$str]['v'];
    }


    /**
     * 从数据库查询翻译配置
     * @throws Throwable
     */
    private static function query(string $str, string $lang)
    {
        $ret = PoolRedis::call(function (Redis $redis) use ($str, $lang) {
            $key = "lang:$lang:$str";
            return $redis->get($key);
        });
        if (empty($ret)) {
            $find = PoolMysql::query("select id,$lang from `language` where `key`='$str'")->fetch_assoc();
            if (empty($find)) {
                throw new LangException('not find language key :' . $str);
            }
            $time = time();
            PoolMysql::query("update `language` set `use_time`=$time where id={$find['id']}");
            $ret = $find[$lang];

            PoolRedis::call(function (Redis $redis) use ($str, $lang, $ret) {
                $key = "lang:$lang:$str";
                $redis->set($key, $ret);
                $redis->expire($key, mt_rand(1800, 5400));
            });
        }


        self::$maps[$lang][$str] = [
            'v' => $ret, // 值
            't' => time() + mt_rand(-300, 300), //查询出来的时间 , 用于判断是否过期
        ];

        return $ret;
    }

}