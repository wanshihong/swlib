<?php

namespace Swlib\Table\Event;

/**
 * 数据库操作事件基类
 * 包含数据库操作的详细信息
 */
enum DatabaseOperationEnum: string
{
    case INSERT = 'insert';
    case  UPDATE = 'update';
    case  DELETE = 'delete';
    case  SELECT = 'select';


    /**
     * 判断是否为写操作
     */
    public function isWriteOperation(): bool
    {
        return match($this) {
            self::INSERT, self::UPDATE, self::DELETE => true,
            self::SELECT => false,
        };
    }

    /**
     * 判断是否为读操作
     */
    public function isReadOperation(): bool
    {
        return $this === self::SELECT;
    }

}