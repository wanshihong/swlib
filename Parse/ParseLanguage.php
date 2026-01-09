<?php
declare(strict_types=1);

namespace Swlib\Parse;


use Generate\ConfigEnum;
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
            $adminNamespace = ConfigEnum::get('ADMIN_NAMESPACE');
            $className = $adminNamespace . '\AdminConfig';
            $adminTitle = (new $className)->configAdminTitle();
            $strings[] = $adminTitle;
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

        $zhs = PoolMysql::query("select `zh` FROM `language`")->fetch_all(MYSQLI_ASSOC);
        $zhs = array_column($zhs, 'zh');

        $saveStrings = [];
        foreach ($strings as $str) {
            $s = trim($str);
            if (in_array($s, $zhs)) {
                continue;
            }
            $saveStrings[] = [
                'zh' => $s,
            ];
        }

        if (!empty($saveStrings)) {
            PoolMysql::query("INSERT INTO language (`zh`) VALUES " . implode(',', array_map(function ($item) {
                    return "('{$item['zh']}')";
                }, $saveStrings)));
        }

    }


}