<?php
declare(strict_types=1);

namespace Swlib\Parse;

use Swlib\Utils\File;

class ParseDevTool
{
    /**
     * 解析开发工具路由
     * 检测 APP_DIR 目录下是否有 DevTool 目录和相关文件
     * 如果没有则自动创建并继承 Swlib/DevTool/Ctrls 下的对应文件
     */
    public function __construct()
    {
        $appDevToolDir = APP_DIR . 'DevTool' . DIRECTORY_SEPARATOR;

        // 检查是否需要创建 DevTool 目录
        if (!is_dir($appDevToolDir)) {
            mkdir($appDevToolDir, 0777, true);
        }

        // 定义需要创建的文件
        $files = [
            'SyncApi.php' => $this->generateSyncApiContent(),
            'ProtobufExtEditor.php' => $this->generateProtobufExtEditorContent(),
            'DevSslCert.php' => $this->generateDevSslCertContent(),
        ];

        // 创建文件
        foreach ($files as $filename => $content) {
            $filePath = $appDevToolDir . $filename;
            if (!file_exists($filePath)) {
                File::save($filePath, $content);
            }
        }
    }

    /**
     * 生成 SyncApi.php 内容
     */
    private function generateSyncApiContent(): string
    {
        return <<<'PHP'
<?php

namespace App\DevTool;

use Swlib\DevTool\Ctrls\SyncApi as BaseSyncApi;

class SyncApi extends BaseSyncApi
{
    // 继承自 Swlib\DevTool\Ctrls\SyncApi
    // 可在此添加项目特定的自定义逻辑
}
PHP;
    }

    /**
     * 生成 ProtobufExtEditor.php 内容
     */
    private function generateProtobufExtEditorContent(): string
    {
        return <<<'PHP'
<?php

namespace App\DevTool;

use Swlib\DevTool\Ctrls\ProtobufExtEditor as BaseProtobufExtEditor;

class ProtobufExtEditor extends BaseProtobufExtEditor
{
    // 继承自 Swlib\DevTool\Ctrls\ProtobufExtEditor
    // 可在此添加项目特定的自定义逻辑
}
PHP;
    }

    /**
     * 生成 DevSslCert.php 内容
     */
    private function generateDevSslCertContent(): string
    {
        return <<<'PHP'
<?php

namespace App\DevTool;

use Swlib\DevTool\Ctrls\DevSslCert as BaseDevSslCert;

class DevSslCert extends BaseDevSslCert
{
    // 继承自 Swlib\DevTool\Ctrrs\DevSslCert
    // 可在此添加项目特定的自定义逻辑
}
PHP;
    }
}
