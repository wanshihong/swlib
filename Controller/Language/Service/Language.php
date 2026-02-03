<?php

declare(strict_types=1);

namespace Swlib\Controller\Language\Service;

use Generate\LanguageMap;
use ReflectionClass;
use Swlib\Attribute\I18nAttribute;
use Swlib\Controller\Language\Enum\LanguageEnum;
use Swlib\Enum\CtxEnum;
use Swlib\Exception\AppException;
use Swlib\Request\Request;

class Language
{

    /**
     * @throws AppException
     */
    public static function getLang()
    {
        // 1. 优先使用已设置的语言
        $lang = CtxEnum::Lang->get();

        // 2. 如果没有，从请求中获取
        if (empty($lang)) {
            $lang = Request::getHeader('lang');
            if (empty($lang)) {
                $lang = Request::get('lang', 'en');
            }
        }

        $lang = $lang ?: 'en';
        CtxEnum::Lang->set($lang);
        return $lang;
    }

    /**
     * 获取翻译后的文本
     * 如果占位需要替换请参考 sprintf 函数
     * @param string $str 翻译 key
     * @param mixed ...$arg sprintf 参数
     * @return string
     * @throws AppException
     */
    public static function get(string $str, ...$arg): string
    {
        $lang = self::getLang();

        // 1. 优先从静态文件读取
        $translation = self::getFromStaticFile($str, $lang);
        if ($translation !== null) {
            return sprintf($translation, ...$arg);
        }

        // 2. 兜底：从注解读取默认中文
        $default = self::getDefaultFromAnnotation($str);
        if ($default !== null) {
            return sprintf($default, ...$arg);
        }

        // 3. 最终兜底：返回 key 本身
        return $str;
    }

    /**
     * 从静态文件读取翻译
     */
    private static function getFromStaticFile(string $key, string $lang): ?string
    {
        $map = LanguageMap::$map[$lang] ?? [];
        return $map[$key] ?? null;
    }

    /**
     * 从注解获取默认中文翻译
     */
    private static function getDefaultFromAnnotation(string $key): ?string
    {
        static $annotationCache = [];

        if (isset($annotationCache[$key])) {
            return $annotationCache[$key];
        }

        // 扫描所有 LanguageInterface 实现类
        $classes = [
            LanguageEnum::class,
            \App\Enum\LanguageEnum::class,
        ];

        foreach ($classes as $class) {
            if (!class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);
            $constants = $reflection->getReflectionConstants();

            foreach ($constants as $constant) {
                $constantValue = $constant->getValue();
                if ($constantValue !== $key) {
                    continue;
                }

                $attributes = $constant->getAttributes(I18nAttribute::class);
                if (empty($attributes)) {
                    continue;
                }

                /** @var I18nAttribute $i18nAttr */
                $i18nAttr = $attributes[0]->newInstance();
                $annotationCache[$key] = $i18nAttr->zh;
                return $i18nAttr->zh;
            }
        }

        $annotationCache[$key] = null;
        return null;
    }

    /**
     * 获取所有语言列表
     * @throws AppException
     */
    public static function getLanguages(): array
    {
        $languages = array_keys(LanguageMap::$map);
        $ret = [];
        foreach ($languages as $lang) {
            $ret[$lang] = self::get($lang);
        }
        return $ret;
    }
}
