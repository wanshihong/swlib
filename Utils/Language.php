<?php
declare(strict_types=1);

namespace Swlib\Utils;


use Generate\DatabaseConnect;
use Redis;
use Swlib\Connect\PoolRedis;
use Swlib\Enum\CtxEnum;
use Swlib\Exception\AppErr;
use Swlib\Exception\LangException;
use Swlib\Request\Request;
use Throwable;

class Language
{

    private static array $maps = [];


    /**
     * @throws Throwable
     */
    public static function getLanguages(): array
    {
        $languageTableInfo = DatabaseConnect::query("DESCRIBE  language")->fetch_all(MYSQLI_ASSOC);
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
        CtxEnum::Lang->get(Request::getHeader('lang', Request::get('lang', 'en')));
        if (empty($lang)) {
            // 请求头语言为空
            throw new LangException('zh' . AppErr::PARAM_EMPTY);
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
        return empty($maps[$str]['v']) ? false : $maps[$str]['v'];
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
            $find = DatabaseConnect::query("select id,$lang from `language` where `zh`='$str'")->fetch_assoc();
            $time = time();
            if (empty($find)) {
                DatabaseConnect::query("insert into `language` (`zh`,`use_time`) values ('$str',$time)");
                $find = DatabaseConnect::query("select id,$lang from `language` where `zh`='$str'")->fetch_assoc();
            }

            $ret = $find[$lang];
            if (empty($ret)) {
                DatabaseConnect::query("update `language` set `$lang`='$ret',`use_time`=$time where id={$find['id']}");
            } else {
                DatabaseConnect::query("update `language` set `use_time`=$time where id={$find['id']}");
            }


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