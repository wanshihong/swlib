<?php

namespace Swlib\Event;


use Swlib\Connect\PoolRedis;
use Swlib\Exception\AppException;
use Swlib\Table\Db;
use Swlib\Utils\Server;
use Throwable;

/**
 * 路由访问事件，访问所有的路由都会触发这个事件
 */
#[Event('HttpRouteEnterEvent')]
class HttpRouteEnterEvent extends AbstractEvent
{

    /**
     * @throws AppException
     */
    public function handle(array $args): void
    {
        // 到 task 进程去执行
        Server::task([__CLASS__, 'saveHistory'], [
            'uri' => $args['uri'],
            'ip' => $args['ip']
        ]);
    }


    /**
     * 异步存储路由的访问历史记录
     * @throws Throwable
     */
    public function saveHistory(array $data): void
    {
        $uri = $data['uri'];
        if (!str_starts_with($uri, '/')) {
            $uri = '/' . $uri;
        }
        $ip = $data['ip'];

        // 使用 ReflectionClass 动态导入类
        $routerTableReflection = Db::getTableReflection('RouterTable');
        $routerHisTableReflection = Db::getTableReflection('RouterHisTable');


        // 使用 ReflectionClass 动态访问类常量
        $routerId = PoolRedis::getSet("saveRouterHistory:$uri", function () use ($routerTableReflection, $uri) {
            $router = $routerTableReflection->newInstance()->addWhere($routerTableReflection->getConstant('URI'), "$uri")->selectOne();
            return $router->id;
        });

        if ($routerId) {
            $routerTableReflection->newInstance()->addWhere($routerTableReflection->getConstant('ID'), $routerId)->update([
                $routerTableReflection->getConstant('LAST_TIME') => time(),
                $routerTableReflection->getConstant('NUM') => $routerTableReflection->getConstant('NUM') . '+1'
            ]);
            // 写入访问日志
            $routerHisTableReflection->newInstance()->insert([
                $routerHisTableReflection->getConstant('ROUTER_ID') => $routerId,
                $routerHisTableReflection->getConstant('URI') => "$uri",
                $routerHisTableReflection->getConstant('TIME') => time(),
                $routerHisTableReflection->getConstant('IP') => $ip,
            ]);

            // 只保留3个月的访问日志
            $routerHisTableReflection->newInstance()->where([
                [$routerHisTableReflection->getConstant('TIME'), '<', time() - 86400 * 90]
            ])->limit(1000)->delete();
        }
    }
}