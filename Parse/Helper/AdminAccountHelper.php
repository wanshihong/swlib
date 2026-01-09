<?php
declare(strict_types=1);

namespace Swlib\Parse\Helper;

use Random\RandomException;
use Swlib\Connect\PoolMysql;
use Throwable;

/**
 * 超级管理员账号管理工具
 */
class AdminAccountHelper
{
    /**
     * 检查并创建超级管理员账号
     * 如果不存在 ROLE_SUPPER_ADMIN 角色的管理员，则自动创建
     *
     * @return void
     * @throws Throwable
     */
    public static function ensureSuperAdminExists(): void
    {
        // 检查是否存在超级管理员 - 使用原生 SQL
        $sql = "SELECT * FROM admin_manager WHERE JSON_CONTAINS(`roles`, '[\"ROLE_SUPPER_ADMIN\"]') LIMIT 1";
        $result = PoolMysql::query($sql)->fetch_assoc();
        if (!empty($result)) {
            // 超级管理员已存在
            return;
        }

        // 创建超级管理员账号
        self::createSuperAdmin();
    }

    /**
     * 创建超级管理员账号
     *
     * @return void
     * @throws Throwable
     */
    private static function createSuperAdmin(): void
    {
        // 生成随机用户名和密码
        $username = 'admin_' . bin2hex(random_bytes(4));
        $plainPassword = self::generatePassword();
        $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);

        // 超级管理员角色 JSON
        $roles = json_encode(['ROLE_SUPPER_ADMIN']);

        // 插入数据库 - 使用原生 SQL
        $sql = "INSERT INTO admin_manager (`username`, `roles`, `password`) VALUES ('$username', '$roles', '$hashedPassword')";

        PoolMysql::call(function ($mysqli) use ($sql) {
            $mysqli->query($sql);
        });

        // 保存账号密码到文件
        self::saveAdminAccountToFile($username, $plainPassword);
    }

    /**
     * 生成随机密码
     *
     * @return string
     * @throws RandomException
     */
    private static function generatePassword(): string
    {
        $length = 12;
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';

        for ($i = 0; $i < $length; $i++) {
            $password .= $characters[random_int(0, strlen($characters) - 1)];
        }

        return $password;
    }

    /**
     * 保存管理员账号密码到文件
     *
     * @param string $username
     * @param string $password
     * @return void
     */
    private static function saveAdminAccountToFile(string $username, string $password): void
    {
        $accountFile = RUNTIME_DIR . 'admin_account.txt';

        $content = "超级管理员账号信息:" . PHP_EOL;
        $content .= "用户名: $username" . PHP_EOL;
        $content .= "密码: $password" . PHP_EOL;
        $content .= "创建时间: " . date('Y-m-d H:i:s') . PHP_EOL;
        $content .= PHP_EOL;
        $content .= "⚠️  请妥善保管此账号信息，首次登录后请立即修改密码！非开发环境会主动删除记录的账号密码;" . PHP_EOL;

        file_put_contents($accountFile, $content);
    }

    /**
     * 显示管理员账号信息
     * 从文件中读取并输出超级管理员账号密码
     *
     * @return void
     */
    public static function displayAdminAccount(): void
    {
        $adminAccountFile = RUNTIME_DIR . 'admin_account.txt';

        if (file_exists($adminAccountFile)) {
            $content = file_get_contents($adminAccountFile);
            if (!empty($content)) {
                echo PHP_EOL;
                ConsoleColor::writeSuccessHighlight('✔ 超级管理员账号信息:');
                echo $content;
            }
        }
    }

    /**
     * 删除账号文件
     *
     * @return void
     */
    public static function delAccountFile(): void
    {
        @unlink(RUNTIME_DIR . 'admin_account.txt');
    }
}

