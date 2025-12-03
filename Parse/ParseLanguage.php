<?php
declare(strict_types=1);

namespace Swlib\Parse;


use Generate\AdminConfigMap;
use Swlib\Connect\PoolMysql;
use Swlib\Utils\File;
use Throwable;
use function Swoole\Coroutine\parallel;

class ParseLanguage
{

    const array collectDirs = [
        SWLIB_DIR,
        ROOT_DIR . 'App',
        RUNTIME_DIR . 'Generate/Models'
    ];

    /**
     * @throws Throwable
     */
    public function __construct()
    {
        // 收集
        $this->collectLanguage();
        $this->output();
    }


    /**
     * 收集需要翻译的语言，需要自行添加到语言文件
     * @return void
     * @throws Throwable
     */
    private function collectLanguage(): void
    {

        $filter = function ($file) {
            if (str_ends_with($file, '.php')) return true;
            if (str_ends_with($file, '.twig')) return true;
            return false;
        };

        $files = [];
        foreach (self::collectDirs as $dir) {
            array_push($files, ...File::eachDir($dir, $filter));
        }

        $strings = [];

        // 拿到后台的标题
        if (class_exists('AdminConfigMap')) {
            $config = AdminConfigMap::ConfigTitle;
            $className = $config[0];
            $methodName = $config[1];
            $strings[] = (new $className)->$methodName();
        }

        parallel(64, function () use (&$files, &$strings) {
            // 解析所有文件的语言调用
            while ($filePath = array_pop($files)) {
                $content = file_get_contents($filePath);

                $regs = [
                    "/Language::get\([\"|'](.*?)[\"|']\)[,;\s)]/",  // 获取语言
                    "/throw new AppException\([\"|'](.*?)[\"|'][,\s]?.*\)/", // 抛出异常
                    "/throw new RedirectException\([\"|'](.*?)[\"|'][,\s]?.*\)/", // 抛出异常
                    "/this->get\(.*?,\s[\"'](.*?)[\"'].*?\);/",   // get  接收参数
                    "/this->post\(.*?,\s[\"'](.*?)[\"'].*?\);/",  // post 接收参数
                    "/\{\{\s?lang\(['\"](.*?)['\"]\)\s?}}/",   // 模板中的  lang 函数
                    "/,\s?label:\s?['\"](.*?)['\"]\)/",     // 后台定义的字段和操作
                    "/[,\s(]?errorTitle:\s?['\"](.*)['\"][,)\s]/", // 后台路由的错误提示
                    "/config->pageName\s?=\s?['\"](.*)['\"]\s?;/", // 后台页面名称
                    "/Menu\(label:\s?['\"](.*?)['\"][,\s]/", // 后台导航配置
                    "/Group\(label:\s?['\"](.*?)['\"][,\s]/", // 后台导航配置
                    "/Action\(label:\s?['\"](.*?)['\"][,\s]/", // 后台导航配置
                    "/Action\(?['\"](.*?)['\"][,\s]/", // 后台导航配置
                ];
                foreach ($regs as $reg) {
                    preg_match_all($reg, $content, $matches);
                    if (!empty($matches[1])) {
                        foreach ($matches[1] as $str) {
                            // 过滤掉变量
                            if (str_starts_with($str, '$')) {
                                continue;
                            }
                            // 过滤掉字符串拼接变量
                            if (str_starts_with($str, '{$')) {
                                continue;
                            }
                            $strings[] = $str;
                        }
                    }
                }
            }
        });


        $strings = array_filter(array_unique($strings));

        $languageTableInfo = PoolMysql::query("DESCRIBE  language")->fetch_all(MYSQLI_ASSOC);
        foreach ($languageTableInfo as $index => $item) {
            if ($index <= 1) continue;
            $strings[] = $item['Field'];
        }

        $keys = PoolMysql::query("select `key` FROM `language`")->fetch_all(MYSQLI_ASSOC);
        $keys = array_column($keys, 'key');

        $saveStrings = [];
        foreach ($strings as $str) {
            $s = trim($str);
            if (in_array($s, $keys)) {
                continue;
            }
            $saveStrings[] = [
                'key' => $s,
                'zh' => $s,
            ];
        }

        if (!empty($saveStrings)) {
            PoolMysql::query("INSERT INTO language (`key`,zh) VALUES " . implode(',', array_map(function ($item) {
                    return "('{$item['key']}','{$item['zh']}')";
                }, $saveStrings)));
        }

    }

    /**
     * @throws Throwable
     */
    private function output(): void
    {
        $all = PoolMysql::query("select * FROM `language`")->fetch_all(MYSQLI_ASSOC);
        $lang = [];
        foreach ($all[0] as $key => $item) {
            if (in_array($key, ['id', 'use_time', 'key'])) {
                continue;
            }
            $lang[] = $key;
        }
        $ret = [];
        foreach ($all as $item) {
            foreach ($lang as $l) {
                $ret[$item['key']][$l] = $item[$l];
            }
        }
        if ($ret) {
            File::save(RUNTIME_DIR . '/codes/ts/language.json', json_encode($ret, JSON_UNESCAPED_UNICODE));
        }
    }

}