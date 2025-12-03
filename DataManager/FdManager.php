<?php
declare(strict_types=1);

namespace Swlib\DataManager;


class FdManager
{
    public static function new(int $fd):DataManager
    {
        $key = "FdDataManager:$fd";
        $dataManager =  WorkerManager::get($key);
        if(empty($dataManager)){
            $dataManager = new DataManager();
            WorkerManager::set($key, $dataManager);
        }
        return $dataManager;
    }
}
