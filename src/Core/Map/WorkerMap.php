<?php
declare(strict_types=1);

namespace Core\Map;

use Fiber;
use Worker\WorkerBase;

/**
 *
 */
class WorkerMap
{
    /**
     * @var WorkerBase[] $workerMap
     */
    public static array $workerMap = [];

    /**
     * @var Fiber[] $fiberMap
     */
    public static array $fiberMap = [];

    /**
     * @param WorkerBase $worker
     * @return void
     */
    public static function addWorker(WorkerBase $worker): void
    {
        WorkerMap::$workerMap[$worker->name] = $worker;
        WorkerMap::$fiberMap[$worker->name] = new Fiber(function () use ($worker) {
            $worker->launch();
        });
    }

    /**
     * @param string $name
     * @return WorkerBase|null
     */
    public static function getWorker(string $name): WorkerBase|null
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
     * @return WorkerBase|null
     */
    public static function get(string $name): WorkerBase|null
    {
        return WorkerMap::$workerMap[$name] ?? null;
    }
}
