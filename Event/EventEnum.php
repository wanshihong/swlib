<?php

namespace Swlib\Event;


use Swlib\Event\Trait\EventEnumMethodTrait;

enum EventEnum
{

    use EventEnumMethodTrait;

    case OnParseRouterEvent; // 路由解析
    case ServerCloseEvent; //
    case ServerFinishEvent; //
    case ServerPipeMessageEvent; //
    case ServerShutdownEvent; //
    case ServerStartEvent; //
    case ServerTaskEvent; //
    case ServerWorkerStartEvent; //
    case ServerWorkerStopEvent; //
    case ServerReceiveEvent; //
    case WebSocketOnMessageEvent; //
    case WebSocketOnOpenEvent; //
    case RouteEnterEvent; //
    case HttpRequestEvent; //
    case HttpRouteEnterEvent; //

    // 数据库操作事件
    case DatabaseBeforeExecuteEvent; // 数据库操作执行前事件
    case DatabaseAfterExecuteEvent;  // 数据库操作执行后事件

    // 数据库事务事件
    case DatabaseTransactionEvent;   // 数据库事务生命周期事件（开始/提交/回滚等）


}
