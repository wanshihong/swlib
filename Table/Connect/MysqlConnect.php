<?php
declare(strict_types=1);

namespace Swlib\Table\Connect;

use Exception;
use mysqli;
use Swoole\Database\MysqliConfig;
use Swoole\Database\MysqliPool;

class MysqlConnect
{


    /**
     * @throws Exception
     */
    public static function connect(
        string $host,
        int    $port,
        string $database,
        string $user,
        string $pass,
        string $charset = 'utf8',
    ): mysqli
    {
        $mysqli = new mysqli($host, $user, $pass, $database, $port);
        if ($mysqli->connect_error) {
            throw new Exception('Connect Error (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
        }
        $mysqli->set_charset($charset);
        return $mysqli;
    }


    /**
     * 创建一个连接池
     */
    public static function createPool(
        string $host,
        int    $port,
        string $database,
        string $user,
        string $pass,
        string $charset = 'utf8',
        int    $poolNum = 10,
    ): MysqliPool
    {
        $conn = new MysqliConfig()
            ->withHost($host)
            ->withPort($port)
            ->withDbName($database)
            ->withCharset($charset)
            ->withUsername($user)
            ->withPassword($pass);

        return new MysqliPool($conn, $poolNum);
    }

}