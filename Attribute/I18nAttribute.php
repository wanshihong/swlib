<?php

declare(strict_types=1);

namespace Swlib\Attribute;

use Attribute;

/**
 * 多语言翻译注解
 *
 * 用于标记类常量的多语言翻译内容，支持通过反射自动同步到 language 表。
 *
 * @example
 * #[I18nAttribute(
 *     zh: '用户名不能为空',
 *     en: 'Username is required',
 *     zh_tw: '用戶名不能為空',
 *     ja: 'ユーザー名は必須です'
 * )]
 * public const string USER_USERNAME_REQUIRED = 'user.username.required';
 */
#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
class I18nAttribute
{
    /**
     * @param string $zh 中文-简体（必填）
     * @param string $en 英文（必填）
     * @param string|null $zh_tw 中文-繁体(台湾)
     * @param string|null $zh_hk 中文-繁体(香港)
     * @param string|null $ja 日语
     * @param string|null $ko 韩语
     * @param string|null $fr 法语
     * @param string|null $es 西班牙语
     * @param string|null $it 意大利语
     * @param string|null $de 德语
     * @param string|null $tr 土耳其语
     * @param string|null $ru 俄语
     * @param string|null $pt 葡萄牙语
     * @param string|null $pt_br 葡萄牙语(巴西)
     * @param string|null $vi 越南语
     * @param string|null $ina 印尼语
     * @param string|null $th 泰语
     * @param string|null $ms 马来语
     * @param string|null $ar 阿拉伯语
     * @param string|null $hi 印地语
     * @param string|null $nl 荷兰语
     * @param string|null $pl 波兰语
     * @param string|null $sv 瑞典语
     * @param string|null $da 丹麦语
     * @param string|null $fi 芬兰语
     * @param string|null $no 挪威语
     * @param string|null $he 希伯来语
     * @param string|null $el 希腊语
     * @param string|null $cs 捷克语
     * @param string|null $ro 罗马尼亚语
     * @param string|null $hu 匈牙利语
     * @param string|null $uk 乌克兰语
     * @param string|null $fa 波斯语
     * @param string|null $fil 菲律宾语
     * @param string|null $bn 孟加拉语
     * @param string|null $ur 乌尔都语
     * @param string|null $sw 斯瓦希里语
     */
    public function __construct(
        public readonly string $zh,
        public readonly string $en,
        public readonly ?string $zh_tw = null,
        public readonly ?string $zh_hk = null,
        public readonly ?string $ja = null,
        public readonly ?string $ko = null,
        public readonly ?string $fr = null,
        public readonly ?string $es = null,
        public readonly ?string $it = null,
        public readonly ?string $de = null,
        public readonly ?string $tr = null,
        public readonly ?string $ru = null,
        public readonly ?string $pt = null,
        public readonly ?string $pt_br = null,
        public readonly ?string $vi = null,
        public readonly ?string $ina = null,
        public readonly ?string $th = null,
        public readonly ?string $ms = null,
        public readonly ?string $ar = null,
        public readonly ?string $hi = null,
        public readonly ?string $nl = null,
        public readonly ?string $pl = null,
        public readonly ?string $sv = null,
        public readonly ?string $da = null,
        public readonly ?string $fi = null,
        public readonly ?string $no = null,
        public readonly ?string $he = null,
        public readonly ?string $el = null,
        public readonly ?string $cs = null,
        public readonly ?string $ro = null,
        public readonly ?string $hu = null,
        public readonly ?string $uk = null,
        public readonly ?string $fa = null,
        public readonly ?string $fil = null,
        public readonly ?string $bn = null,
        public readonly ?string $ur = null,
        public readonly ?string $sw = null,
    ) {
    }

    /**
     * 获取所有非空的语言翻译
     *
     * @return array ['lang_code' => 'translation']
     */
    public function getTranslations(): array
    {
        $translations = [];

        foreach ([
            'zh' => $this->zh,
            'en' => $this->en,
            'zh_tw' => $this->zh_tw,
            'zh_hk' => $this->zh_hk,
            'ja' => $this->ja,
            'ko' => $this->ko,
            'fr' => $this->fr,
            'es' => $this->es,
            'it' => $this->it,
            'de' => $this->de,
            'tr' => $this->tr,
            'ru' => $this->ru,
            'pt' => $this->pt,
            'pt_br' => $this->pt_br,
            'vi' => $this->vi,
            'ina' => $this->ina,
            'th' => $this->th,
            'ms' => $this->ms,
            'ar' => $this->ar,
            'hi' => $this->hi,
            'nl' => $this->nl,
            'pl' => $this->pl,
            'sv' => $this->sv,
            'da' => $this->da,
            'fi' => $this->fi,
            'no' => $this->no,
            'he' => $this->he,
            'el' => $this->el,
            'cs' => $this->cs,
            'ro' => $this->ro,
            'hu' => $this->hu,
            'uk' => $this->uk,
            'fa' => $this->fa,
            'fil' => $this->fil,
            'bn' => $this->bn,
            'ur' => $this->ur,
            'sw' => $this->sw,
        ] as $lang => $value) {
            if ($value !== null) {
                $translations[$lang] = $value;
            }
        }

        return $translations;
    }
}
