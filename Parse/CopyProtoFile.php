<?php
declare(strict_types=1);

namespace Swlib\Parse;

use Swlib\Utils\File;

/**
 * 复制 Proto 文件到 runtime/codes/proto 目录
 * 扫描 App 目录下所有 PHP 文件的 use 语句，提取 Protobuf 引用并复制对应的 proto 文件
 */
class CopyProtoFile
{

    public function __construct()
    {
        $appFiles = File::eachDir(ROOT_DIR . "App", function ($filePath) {
            return str_ends_with($filePath, '.php');
        });

        $swlibFiles = File::eachDir(ROOT_DIR . "Swlib/Controller", function ($filePath) {
            return str_ends_with($filePath, '.php');
        });
        $files = array_merge($appFiles, $swlibFiles);

        $protoFiles = [];

        foreach ($files as $filePath) {
            $content = file_get_contents($filePath);
            if ($content === false) {
                continue;
            }

            // 解析 use 语句
            $useStatements = $this->parseUseStatements($content);
            foreach ($useStatements as $useStatement) {
                $protoFile = $this->extractProtoFile($useStatement);
                if ($protoFile !== null) {
                    $protoFiles[$protoFile['source']] = $protoFile;
                }
            }

            // 解析代码中直接使用的完整命名空间 (如 \Protobuf\Wenyuehui\... 或 Protobuf\Wenyuehui\...)
            $inlineReferences = $this->parseInlineProtobufReferences($content);
            foreach ($inlineReferences as $reference) {
                $protoFile = $this->extractProtoFile($reference);
                if ($protoFile !== null) {
                    $protoFiles[$protoFile['source']] = $protoFile;
                }
            }
        }

        $this->copyProtoFiles($protoFiles);
    }

    /**
     * 解析 PHP 文件中的 use 语句
     * @param string $content PHP 文件内容
     * @return array use 语句数组
     */
    private function parseUseStatements(string $content): array
    {
        $useStatements = [];
        // 匹配 use 语句，支持多行和分组导入
        if (preg_match_all('/^use\s+([^;]+);/m', $content, $matches)) {
            foreach ($matches[1] as $match) {
                // 处理分组导入 use Foo\{Bar, Baz};
                if (str_contains($match, '{')) {
                    $baseNamespace = trim(substr($match, 0, strpos($match, '{')));
                    $baseNamespace = rtrim($baseNamespace, '\\');
                    preg_match('/\{([^}]+)}/', $match, $groupMatches);
                    if (!empty($groupMatches[1])) {
                        $items = explode(',', $groupMatches[1]);
                        foreach ($items as $item) {
                            $useStatements[] = $baseNamespace . '\\' . trim($item);
                        }
                    }
                } else {
                    $useStatements[] = trim($match);
                }
            }
        }

        return $useStatements;
    }

    /**
     * 从 use 语句中提取 proto 文件信息
     * @param string $useStatement use 语句
     * @return array|null proto 文件信息，包含 source 和 targetDir
     */
    private function extractProtoFile(string $useStatement): ?array
    {
        // 匹配 Protobuf\DatabaseName\TableName\... 格式
        if (!str_starts_with($useStatement, 'Protobuf\\')) {
            return null;
        }

        $parts = explode('\\', $useStatement);
        // 至少需要 Protobuf\DatabaseName\TableName
        if (count($parts) < 3) {
            return null;
        }

        // $parts[0] = 'Protobuf'
        // $parts[1] = 数据库名称 (如 'Wenyuehui')
        // $parts[2] = 表名/Proto名称 (如 'User', 'LivePosts')
        $dbName = $parts[1];
        $tableName = $parts[2];

        // 源文件路径
        $sourceFile = ROOT_DIR . "protos/$dbName/$tableName.proto";
        if (!file_exists($sourceFile)) {
            return null;
        }

        return [
            'source' => $sourceFile,
            'fileName' => "$tableName.proto",
        ];
    }

    /**
     * 解析代码中直接使用的完整命名空间引用
     * 匹配 \Protobuf\Wenyuehui\TableName\... 或 Protobuf\Wenyuehui\TableName\... 格式
     * @param string $content PHP 文件内容
     * @return array Protobuf 引用数组
     */
    private function parseInlineProtobufReferences(string $content): array
    {
        $references = [];

        // 匹配 \Protobuf\... 或 Protobuf\... 格式（不在 use 语句中）
        if (preg_match_all('/\\\\?Protobuf\\\\[A-Za-z0-9_\\\\]+/', $content, $matches)) {
            foreach ($matches[0] as $match) {
                // 移除开头的反斜杠
                $reference = ltrim($match, '\\');
                $references[] = $reference;
            }
        }

        return array_unique($references);
    }

    /**
     * 复制 proto 文件到目标目录（扁平化，不再按子目录分类）
     * @param array $protoFiles proto 文件信息数组
     */
    private function copyProtoFiles(array $protoFiles): void
    {
        $targetDir = RUNTIME_DIR . "codes/proto/";
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        foreach ($protoFiles as $protoFile) {
            copy($protoFile['source'], $targetDir . $protoFile['fileName']);
        }
    }
}
