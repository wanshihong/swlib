<?php
declare(strict_types=1);

namespace Swlib\Aop\Aspects;

use Attribute;
use Swlib\Aop\Abstract\AbstractAspect;
use Swlib\Aop\JoinPoint;
use Swlib\Exception\AppErr;
use Swlib\Exception\AppException;
use Swlib\Proxy\Interface\ProxyAttributeInterface;

/**
 * 参数验证切面
 *
 * 在方法执行前进行参数验证
 *
 * @example
 * #[ValidationAspect(rules: [0 => ['required', 'string']])]
 * public function createUser($username, $email) { }
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class ValidationAspect extends AbstractAspect implements ProxyAttributeInterface
{


    /**
     * 构造函数
     *
     * @param array $rules 验证规则
     * @param int $priority 执行优先级，多个注解时需显式指定
     *
     * 规则格式示例:
     * [
     *     0 => ['required', 'string', 'min:3', 'max:20'],  // 第一个参数
     *     1 => ['required', 'email'],                       // 第二个参数
     * ]
     */
    public function __construct(
        public array $rules = [], // 验证规则
        public int   $priority = 0,
        public bool   $async = false
    )
    {

    }

    /**
     * 前置通知 - 执行参数验证
     *
     * @param JoinPoint $joinPoint
     * @return void
     * @throws AppException
     */
    public function before(JoinPoint $joinPoint): void
    {
        $arguments = $joinPoint->arguments;

        foreach ($this->rules as $index => $rules) {
            if (!isset($arguments[$index])) {
                if (in_array('required', $rules)) {
                    throw new AppException("参数 #$index " . AppErr::PARAM_REQUIRED);
                }
                continue;
            }

            $value = $arguments[$index];

            foreach ($rules as $rule) {
                $this->validateRule($value, $rule, $index);
            }
        }
    }

    /**
     * 验证单个规则
     *
     * @param mixed $value 参数值
     * @param string $rule 规则
     * @param int $index 参数索引
     * @return void
     * @throws AppException
     */
    private function validateRule(mixed $value, string $rule, int $index): void
    {
        // 解析规则（如 min:3）
        $parts = explode(':', $rule, 2);
        $ruleName = $parts[0];
        $ruleParam = $parts[1] ?? null;

        match ($ruleName) {
            'required' => $this->validateRequired($value, $index),
            'string' => $this->validateString($value, $index),
            'int', 'integer' => $this->validateInt($value, $index),
            'numeric' => $this->validateNumeric($value, $index),
            'email' => $this->validateEmail($value, $index),
            'url' => $this->validateUrl($value, $index),
            'min' => $this->validateMin($value, $ruleParam, $index),
            'max' => $this->validateMax($value, $ruleParam, $index),
            'array' => $this->validateArray($value, $index),
            'in' => $this->validateIn($value, $ruleParam, $index),
            'regex' => $this->validateRegex($value, $ruleParam, $index),
            default => null,
        };
    }

    /**
     * 验证必填
     * @throws AppException
     */
    private function validateRequired(mixed $value, int $index): void
    {
        if (empty($value) && $value !== 0 && $value !== '0') {
            throw new AppException("参数 #$index " . AppErr::PARAM_REQUIRED);
        }
    }

    /**
     * 验证字符串
     * @throws AppException
     */
    private function validateString(mixed $value, int $index): void
    {
        if (!is_string($value)) {
            throw new AppException("参数 #$index " . AppErr::PARAM_MUST_STRING);
        }
    }

    /**
     * 验证整数
     * @throws AppException
     */
    private function validateInt(mixed $value, int $index): void
    {
        if (!is_int($value) && !ctype_digit((string)$value)) {
            throw new AppException("参数 #$index " . AppErr::PARAM_MUST_INT);
        }
    }

    /**
     * 验证数字
     * @throws AppException
     */
    private function validateNumeric(mixed $value, int $index): void
    {
        if (!is_numeric($value)) {
            throw new AppException("参数 #$index " . AppErr::PARAM_MUST_NUMBER);
        }
    }

    /**
     * 验证邮箱
     * @throws AppException
     */
    private function validateEmail(mixed $value, int $index): void
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new AppException("参数 #$index " . AppErr::PARAM_MUST_EMAIL);
        }
    }

    /**
     * 验证 URL
     * @throws AppException
     */
    private function validateUrl(mixed $value, int $index): void
    {
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            throw new AppException("参数 #$index " . AppErr::PARAM_MUST_URL);
        }
    }

    /**
     * 验证最小值/长度
     * @throws AppException
     */
    private function validateMin(mixed $value, ?string $min, int $index): void
    {
        if ($min === null) {
            return;
        }

        $minValue = (int)$min;

        if (is_string($value)) {
            if (mb_strlen($value) < $minValue) {
                throw new AppException("参数 #$index " . AppErr::PARAM_MIN_LENGTH . " $minValue");
            }
        } elseif (is_numeric($value)) {
            if ($value < $minValue) {
                throw new AppException("参数 #$index " . AppErr::PARAM_MIN_VALUE . " $minValue");
            }
        } elseif (is_array($value)) {
            if (count($value) < $minValue) {
                throw new AppException("参数 #$index " . AppErr::PARAM_MIN_COUNT . " $minValue");
            }
        }
    }

    /**
     * 验证最大值/长度
     * @throws AppException
     */
    private function validateMax(mixed $value, ?string $max, int $index): void
    {
        if ($max === null) {
            return;
        }

        $maxValue = (int)$max;

        if (is_string($value)) {
            if (mb_strlen($value) > $maxValue) {
                throw new AppException("参数 #$index " . AppErr::PARAM_MAX_LENGTH . " $maxValue");
            }
        } elseif (is_numeric($value)) {
            if ($value > $maxValue) {
                throw new AppException("参数 #$index " . AppErr::PARAM_MAX_VALUE . " $maxValue");
            }
        } elseif (is_array($value)) {
            if (count($value) > $maxValue) {
                throw new AppException("参数 #$index " . AppErr::PARAM_MAX_COUNT . " $maxValue");
            }
        }
    }

    /**
     * 验证数组
     * @throws AppException
     */
    private function validateArray(mixed $value, int $index): void
    {
        if (!is_array($value)) {
            throw new AppException("参数 #$index " . AppErr::PARAM_MUST_ARRAY);
        }
    }

    /**
     * 验证值在指定范围内
     * @throws AppException
     */
    private function validateIn(mixed $value, ?string $values, int $index): void
    {
        if ($values === null) {
            return;
        }

        $allowedValues = explode(',', $values);
        if (!in_array($value, $allowedValues, true)) {
            throw new AppException("参数 #$index " . AppErr::VALUE_INVALID . ": 必须是以下值之一: " . implode(', ', $allowedValues));
        }
    }

    /**
     * 验证正则表达式
     * @throws AppException
     */
    private function validateRegex(mixed $value, ?string $pattern, int $index): void
    {
        if ($pattern === null) {
            return;
        }

        if (!preg_match($pattern, (string)$value)) {
            throw new AppException("参数 #$index " . AppErr::FORMAT_INVALID);
        }
    }

    public function handle(array $ctx, callable $next): mixed
    {
        $joinPoint = new JoinPoint($ctx['target'], $ctx['meta']['method'], $ctx['arguments']);
        return $this->around($joinPoint, static fn() => $next($ctx));
    }
}

