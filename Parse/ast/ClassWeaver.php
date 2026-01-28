<?php
declare(strict_types=1);

namespace Swlib\Parse\ast;

use PhpParser\Modifiers;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard as PrettyPrinter;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Swlib\Exception\AppErr;
use Swlib\Exception\AppException;
use Swlib\Parse\Helper\ConsoleColor;
use Swlib\Proxy\Interface\ProxyAttributeInterface;
use Swlib\Utils\File;

/**
 * 单个类的代理静态编织器
 *
 * 支持的注解类型：
 * - AOP 切面（AspectInterface）
 * - 事务（Transaction）
 * - 协程（CoroutineAttribute）
 * - 队列（QueueAttribute）
 * - Task 进程（TaskAttribute）
 *
 * 互斥规则：
 * - Queue/Task/Coroutine 三者互斥
 * - Queue/Task/Coroutine 与 AOP 互斥
 * - AOP + Transaction 允许
 * - Transaction + Queue/Task/Coroutine 允许（在异步上下文中执行事务）
 *
 * chainType 类型：
 * - aop: 只有 AOP 切面
 * - tx: 只有事务
 * - tx_aop: 事务 + AOP 切面
 * - queue: 只有队列
 * - tx_queue: 事务 + 队列
 * - task: 只有 Task
 * - tx_task: 事务 + Task
 * - coroutine: 只有协程
 * - tx_coroutine: 事务 + 协程
 */
readonly class ClassWeaver
{
    public function __construct(
        private ReflectionClass $refClass,
        private string          $sourceFile
    )
    {
    }

    /**
     * 编织当前类/trait，并返回本类相关的代理元数据
     *
     * @return array<string, array{class:string, method:string, proxyMethod:string, isStatic:bool, stages:array}>
     * @throws AppException
     */
    public function weave(): array
    {
        // 收集需要织入的目标方法以及运行时需要的元数据
        ['methods' => $methods, 'map' => $map] = $this->collectTargetMethods();
        $outputFile = $this->getOutputPath();

        if ($methods === []) {
            // 没有任何注解，清理旧的运行时代码
            if (is_file($outputFile)) {
                @unlink($outputFile);
            }
            return [];
        }

        $code = file_get_contents($this->sourceFile);
        if ($code === false) {
            return [];
        }

        $factory = new ParserFactory();
        $parser = $factory->createForNewestSupportedVersion();

        $ast = $parser->parse($code);
        if ($ast === null) {
            return [];
        }

        $this->rewriteClassAst($ast, $methods);

        $printer = new PrettyPrinter();
        $compiled = $printer->prettyPrintFile($ast);

        File::save($outputFile, $compiled);

        return $map;
    }

    /**
     * 收集需要代理的方法及其元数据
     *
     * @return array{
     *     methods: array<string, array{isStatic: bool, isVoid: bool, constKey: string}>,
     *     map: array<string, array{class:string, method:string, proxyMethod:string, isStatic:bool, stages:array}>
     * }
     * @throws AppException
     */
    private function collectTargetMethods(): array
    {
        $methods = [];
        $map = [];
        $className = $this->refClass->getName();

        foreach ($this->refClass->getMethods(
            ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED | ReflectionMethod::IS_PRIVATE
        ) as $method) {
            if ($method->isConstructor() || $method->isDestructor()) {
                continue;
            }
            if ($method->getDeclaringClass()->getName() !== $className) {
                continue;
            }

            $methodName = $method->getName();
            $parsed = $this->parseMethodAttributes($method);

            if ($parsed === null) {
                continue;
            }

            $returnType = $method->getReturnType();
            $hasReturnType = $returnType !== null;
            $isVoid = $returnType instanceof ReflectionNamedType
                && !$returnType->allowsNull()
                && $returnType->getName() === 'void';

            if ($parsed['hasAsync'] && $hasReturnType && !$isVoid) {
                $location = $method->getDeclaringClass()->getName() . '::' . $method->getName();
                $returnTypeName = $returnType instanceof ReflectionNamedType ? $returnType->getName() : 'mixed';
                ConsoleColor::writeErrorHighlight(
                    "[AST 编译警告] $location 方法声明了返回值类型 '$returnTypeName'，但使用了异步注解。" .
                    "异步操作会改变方法的执行方式，可能导致返回值类型冲突或运行时错误。" .
                    "建议移除异步注解，或删除方法的返回值类型声明（让方法变为 void 或无返回值声明）。",
                );
            }

            // 生成常量 KEY 名称：K_App_Service_UserService__getUserInfo
            $constKey = 'K_' . str_replace('\\', '_', $className) . '__' . $methodName;
            $proxyMethod = $methodName . '__proxy';

            $methods[$methodName] = [
                'isStatic' => $method->isStatic(),
                'isVoid' => $isVoid,
                'constKey' => $constKey,
            ];

            // 使用常量 KEY 作为 map 的 key，并存储基础信息
            $parsed['proxyMethod'] = $proxyMethod;
            $parsed['method'] = $methodName;
            $parsed['isStatic'] = $method->isStatic();
            $parsed['class'] = $className;
            $parsed['parameters'] = array_map(static fn($p) => $p->getName(), $method->getParameters());
            $map[$constKey] = $parsed;
        }

        return [
            'methods' => $methods,
            'map' => $map,
        ];
    }

    /**
     * 解析方法上的所有可编织注解（实现 StageInterface）
     * @throws AppException
     */
    private function parseMethodAttributes(ReflectionMethod $method): ?array
    {
        $stageMetas = [];
        $hasAsyncBeforeSync = false; // 记录是否有异步注解
        foreach ($method->getAttributes() as $attr) {
            $attrClass = $attr->getName();
            if (!is_a($attrClass, ProxyAttributeInterface::class, true)) {
                continue;
            }

            $instance = $attr->newInstance();
            if (!$instance instanceof ProxyAttributeInterface) {
                continue;
            }

            $stageMetas[] = [
                'class' => $attrClass,
                'arguments' => $attr->getArguments(),
                'priority' => $instance->priority,
                'async' => $instance->async,
            ];
        }

        if ($stageMetas === []) {
            return null;
        }

        // 按 priority 降序排序（数字大的先执行）
        usort($stageMetas, static fn($a, $b) => $b['priority'] <=> $a['priority']);

        // 检查是否存在异步注解
        $hasAsync = false;
        foreach ($stageMetas as $meta) {
            if ($meta['async']) {
                $hasAsync = true;
                break;
            }
        }

        // 多注解验证
        if (count($stageMetas) > 1) {

            $location = $method->getDeclaringClass()->getName() . '::' . $method->getName();
            $priorities = array_unique(array_column($stageMetas, 'priority'));

            // 检查 priority 是否有重复
            if (count($priorities) !== count($stageMetas)) {
                throw new AppException(AppErr::PARSE_AST_PRIORITY_DUPLICATE . ": $location 存在多个注解，但 priority 有重复值");
            }

            // 检查异步注解执行顺序：异步注解会阻断后续同步注解的执行
            foreach ($stageMetas as $meta) {
                if ($meta['async']) {
                    $hasAsyncBeforeSync = true;
                } elseif ($hasAsyncBeforeSync) {
                    // 发现同步注解在异步注解之后
                    ConsoleColor::writeWithColors(
                        "[AST 编译警告] $location 存在多个注解，其中包含异步和同步注解。" .
                        "异步注解会阻断执行流程，导致后续同步注解无法运行。" .
                        "建议将异步注解的优先级设为最低（数字最小），确保异步操作在最后执行。",
                        ConsoleColor::BG_YELLOW,
                        ConsoleColor::COLOR_WHITE
                    );
                    break;
                }
            }
        }


        return [
            'stages' => $stageMetas,
            'hasAsync' => $hasAsync,
        ];
    }

    private function getOutputPath(): string
    {
        $className = $this->refClass->getName();

        if (str_starts_with($className, 'App\\')) {
            $relative = substr($className, 4); // 去掉 App\
            $relativePath = str_replace('\\', '/', $relative) . '.php';
            return RUNTIME_DIR . 'Proxy/App/' . $relativePath;
        }

        if (str_starts_with($className, 'Swlib\\')) {
            $relative = substr($className, 6); // 去掉 Swlib\
            $relativePath = str_replace('\\', '/', $relative) . '.php';
            return RUNTIME_DIR . 'Proxy/Swlib/' . $relativePath;
        }

        // 其他命名空间（例如第三方库），直接覆盖原文件（当前项目暂不使用）
        return $this->sourceFile;
    }

    /**
     * @param Node[] $ast
     * @param array<string, array{isStatic:bool, isVoid:bool}> $methods
     */
    private function rewriteClassAst(array $ast, array $methods): void
    {
        $namespace = $this->refClass->getNamespaceName();
        $shortName = $this->refClass->getShortName();

        foreach ($ast as $node) {
            if ($node instanceof Stmt\Namespace_) {
                $nsName = $node->name?->toString() ?? '';
                if ($nsName !== $namespace) {
                    continue;
                }
                foreach ($node->stmts as $stmt) {
                    if (($stmt instanceof Stmt\Class_ || $stmt instanceof Stmt\Trait_)
                        && $stmt->name?->name === $shortName) {
                        $this->rewriteClassNode($stmt, $methods);
                        return;
                    }
                }
            } elseif ($namespace === ''
                && ($node instanceof Stmt\Class_ || $node instanceof Stmt\Trait_)
                && $node->name?->name === $shortName) {
                $this->rewriteClassNode($node, $methods);
                return;
            }
        }
    }

    /**
     * @param array<string, array{isStatic:bool, isVoid:bool}> $methods
     */
    private function rewriteClassNode(Stmt\ClassLike $classNode, array $methods): void
    {
        $newStmts = [];

        foreach ($classNode->stmts as $stmt) {
            if ($stmt instanceof Stmt\ClassMethod) {
                $name = $stmt->name->toString();
                if (isset($methods[$name])) {
                    $isStatic = $methods[$name]['isStatic'];
                    $isVoid = $methods[$name]['isVoid'] ?? false;
                    $constKey = $methods[$name]['constKey'];

                    // proxy 方法：原方法体 + 去除属性，且强制设为 public
                    $proxy = clone $stmt;
                    $proxy->name = new Node\Identifier($name . '__proxy');
                    $proxy->attrGroups = [];
                    // 调整可见性：保留 static/abstract/final，只把可见性改为 public
                    $visibilityMask = Modifiers::VISIBILITY_MASK;
                    $proxy->flags = ($proxy->flags & ~$visibilityMask) | Modifiers::PUBLIC;

                    // 包装方法：保留签名和 Attribute，仅替换方法体
                    $wrapper = $stmt;
                    $wrapper->stmts = $this->buildWrapperStmts($isStatic, $isVoid, $constKey);

                    $newStmts[] = $wrapper;
                    $newStmts[] = $proxy;
                    continue;
                }
            }
            $newStmts[] = $stmt;
        }

        $classNode->stmts = $newStmts;
    }

    /**
     * 构建包装方法体，调用 ProxyDispatcher::dispatch()
     *
     * 使用常量 KEY 进行调度，消除运行时字符串拼接
     */
    private function buildWrapperStmts(bool $isStatic, bool $isVoid, string $constKey): array
    {
        $argsArray = new Expr\FuncCall(new Name('func_get_args'));

        $targetExpr = $isStatic
            ? new Expr\ClassConstFetch(new Name('self'), 'class')
            : new Expr\Variable('this');

        // 使用常量引用：\Generate\CallChainMap::K_App_Service_UserService__getUserInfo
        $keyExpr = new Expr\ClassConstFetch(
            new Name\FullyQualified('Generate\\CallChainMap'),
            $constKey
        );

        $call = new Expr\StaticCall(
            new Name\FullyQualified('Swlib\\Proxy\\ProxyDispatcher'),
            'dispatch',
            [
                new Arg($keyExpr),
                new Arg($targetExpr),
                new Arg($argsArray),
            ]
        );

        if ($isVoid) {
            // 原方法显式声明为 void：不能在包装方法中 return 值
            return [new Stmt\Expression($call)];
        }

        return [new Stmt\Return_($call)];
    }
}

