<?php
declare(strict_types=1);

namespace Core\Map;

use Fiber;
use Worker\Worker;

/**
 * Class WorkerMap
 */
class WorkerMap
{
    /**
     * @var Worker[] $workerMap
     */
    public static array $workerMap = [];

    /**
     * @var Fiber[] $fiberMap
     */
    public static array $fiberMap = [];

    /**
     * @param Worker $worker
     * @return Fiber
     */
    public static function addWorker(Worker $worker): Fiber
    {
        WorkerMap::$workerMap[$worker->name] = $worker;
        return WorkerMap::$fiberMap[$worker->name] = new Fiber(function () use ($worker) {
            $worker->launch();
        });
    }

    /**
     * @param string $name
     * @return Worker|null
     */
    public static function getWorker(string $name): Worker|null
    {
        return WorkerMap::$workerMap[$name] ?? null;
    }

    /**
     * @param string $name
     * @return Fiber|null
     */
    public static function getFiber(string $name): Fiber|null
    {
        return WorkerMap::$fiberMap[$name] ?? null;
    }

    /**
     * @param string $name
     * @return Worker|null
     */
    public static function get(string $name): Worker|null
    {
        return WorkerMap::$workerMap[$name] ?? null;
    }

    /**
     * 卸载一个服务
     * @param Worker    $worker
     * @param bool|null $isFork
     * @return void
     */
    public static function unload(Worker $worker, bool|null $isFork = false): void
    {
        unset(WorkerMap::$workerMap[$worker->name]);
        unset(WorkerMap::$fiberMap[$worker->name]);
        $worker->unload($isFork);
    }
}
