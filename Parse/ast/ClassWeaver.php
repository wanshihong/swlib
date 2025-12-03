<?php
declare(strict_types=1);

namespace Swlib\Parse\ast;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar;
use PhpParser\Node\Stmt;
use PhpParser\Modifiers;

use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard as PrettyPrinter;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Swlib\Aop\Interface\AspectInterface;
use Swlib\Table\Attributes\Transaction;
use Swlib\Utils\File;

/**
 * 单个类的 AOP 静态编织器
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
     * 编织当前类/trait，并返回本类相关的切面/事务元数据
     *
     * @return array<string, array{aspects: array<int, array{class:string, arguments:array}>, transaction: ?array{class:string, arguments:array}}>
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

        // 新版 nikic/php-parser 使用 createForNewestSupportedVersion()
        // 当前项目 vendor 中的版本已经不再提供 ParserFactory::create() 以及相关常量
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
     * @return array{
     *     methods: array<string, array{isStatic: bool, isVoid: bool}>,
     *     map: array<string, array{
     *         aspects: array<int, array{class: string, arguments: array}>,
     *         transaction: ?array{class: string, arguments: array}
     *     }>
     * }
     */
    private function collectTargetMethods(): array
    {
        $methods = [];
        $map = [];

        foreach ($this->refClass->getMethods(
            ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED | ReflectionMethod::IS_PRIVATE
        ) as $method) {
            if ($method->isConstructor() || $method->isDestructor()) {
                continue;
            }
            if ($method->getDeclaringClass()->getName() !== $this->refClass->getName()) {
                continue;
            }

            $hasAspect = false;
            $hasTx = false;
            $aspectMeta = [];
            $transactionMeta = null;

            foreach ($method->getAttributes() as $attr) {
                $attrClass = $attr->getName();
                $arguments = $attr->getArguments();

                if (is_a($attrClass, AspectInterface::class, true)) {
                    $hasAspect = true;
                    $aspectMeta[] = [
                        'class' => $attrClass,
                        'arguments' => $arguments,
                    ];
                }

                if (is_a($attrClass, Transaction::class, true)) {
                    $hasTx = true;
                    // 只记录第一个 Transaction 注解
                    if ($transactionMeta === null) {
                        $transactionMeta = [
                            'class' => $attrClass,
                            'arguments' => $arguments,
                        ];
                    }
                }
            }

            if (!$hasAspect && !$hasTx) {
                continue;
            }

            $returnType = $method->getReturnType();
            $isVoid = $returnType instanceof ReflectionNamedType
                && !$returnType->allowsNull()
                && $returnType->getName() === 'void';

            $name = $method->getName();

            $methods[$name] = [
                'isStatic' => $method->isStatic(),
                'isVoid' => $isVoid,
            ];

            $map[$name] = [
                'aspects' => $aspectMeta,
                'transaction' => $transactionMeta,
            ];
        }

        return [
            'methods' => $methods,
            'map' => $map,
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
        $isTrait = $this->refClass->isTrait();

        foreach ($classNode->stmts as $stmt) {
            if ($stmt instanceof Stmt\ClassMethod) {
                $name = $stmt->name->toString();
                if (isset($methods[$name])) {
                    $isStatic = $methods[$name]['isStatic'];
                    $isVoid = $methods[$name]['isVoid'] ?? false;

                    // inner 方法：原方法体 + 去除属性，且强制设为 public，避免在 MethodInvoker 中因访问控制无法调用
                    $inner = clone $stmt;
                    $inner->name = new Node\Identifier($name . '__inner');
                    $inner->attrGroups = [];
                    // 调整可见性：保留 static/abstract/final，只把可见性改为 public
                    $visibilityMask = Modifiers::VISIBILITY_MASK;
                    $inner->flags = ($inner->flags & ~$visibilityMask) | Modifiers::PUBLIC;

                    // 包装方法：保留签名和 Attribute，仅替换方法体
                    $wrapper = $stmt;
                    $wrapper->stmts = $this->buildWrapperStmts($isStatic, $isVoid, $isTrait);

                    $newStmts[] = $wrapper;
                    $newStmts[] = $inner;
                    continue;
                }
            }
            $newStmts[] = $stmt;
        }

        $classNode->stmts = $newStmts;
    }

    private function buildWrapperStmts(bool $isStatic, bool $isVoid, bool $isTrait): array
    {
        $argsArray = new Expr\FuncCall(new Name('func_get_args'));
        $methodConst = new Scalar\MagicConst\Function_();

        $targetExpr = $isStatic
            ? new Expr\ClassConstFetch(new Name('self'), 'class')
            : new Expr\Variable('this');

        $declaringConst = $isTrait
            ? new Scalar\MagicConst\Trait_()
            : new Scalar\MagicConst\Class_();

        $call = new Expr\StaticCall(
            new Name\FullyQualified('Swlib\\Aop\\MethodInvoker'),
            'invoke',
            [
                new Arg($targetExpr),
                new Arg($methodConst),
                new Arg($argsArray),
                new Arg($declaringConst),
            ]
        );

        if ($isVoid) {
            // 原方法显式声明为 void：不能在包装方法中 return 值
            return [new Stmt\Expression($call)];
        }

        return [new Stmt\Return_($call)];
    }
}

