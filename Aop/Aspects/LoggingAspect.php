<?php
declare(strict_types=1);

namespace Swlib\Aop\Aspects;

use Swlib\Aop\Abstract\AbstractAspect;
use Attribute;

use Swlib\Aop\JoinPoint;
use Swlib\Utils\Log;
use Throwable;

/**
 * 日志切面
 *
 * 自动记录方法的调用、参数、返回值和异常
 *
 * @example
 * #[LoggingAspect(logModule: 'info')]
 * public function save($data) { }
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class LoggingAspect extends AbstractAspect
{
    /**
     * @var bool 是否记录参数
     */
    private bool $logArguments;

    /**
     * @var bool 是否记录返回值
     */
    private bool $logResult;

    /**
     * @var string 日志模块
     */
    private string $logModule;


    /**
     * 构造函数
     *
     * @param bool $logArguments 是否记录参数，默认 true
     * @param bool $logResult 是否记录返回值，默认 true
     * @param string $logModule 日志级别，默认 'info'
     */
    public function __construct(
        bool   $logArguments = true,
        bool   $logResult = true,
        string $logModule = 'info'
    )
    {
        $this->logArguments = $logArguments;
        $this->logResult = $logResult;
        $this->logModule = $logModule;
    }

    /**
     * 前置通知 - 记录方法调用
     *
     * @param JoinPoint $joinPoint
     * @return void
     */
    public function before(JoinPoint $joinPoint): void
    {
        $message = "调用方法: {$joinPoint->getSignature()}";

        if ($this->logArguments) {
            $arguments = json_encode($joinPoint->arguments, JSON_UNESCAPED_UNICODE);
            $message .= " | 参数: $arguments";
        }
        Log::save($message, $this->getLogModule());
    }

    /**
     * 后置通知 - 记录返回值
     *
     * @param JoinPoint $joinPoint
     * @param mixed $result
     * @return void
     */
    public function after(JoinPoint $joinPoint, mixed $result): void
    {
        if (!$this->logResult) {
            return;
        }

        $resultStr = json_encode($result, JSON_UNESCAPED_UNICODE);
        $message = "方法执行成功: {$joinPoint->getSignature()} | 返回值: $resultStr" . PHP_EOL;

        Log::save($message, $this->getLogModule());
    }

    /**
     * 异常通知 - 记录异常
     *
     * @param JoinPoint $joinPoint
     * @param Throwable $exception
     * @return void
     */
    public function afterThrowing(JoinPoint $joinPoint, Throwable $exception): void
    {
        Log::saveException($exception, $this->getLogModule());
    }

    /**
     * 获取日志模块名称
     *
     * @return string
     */
    private function getLogModule(): string
    {
        return "aop_$this->logModule";
    }
}

