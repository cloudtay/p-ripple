<?php
declare(strict_types=1);

namespace Std;

use Worker\WorkerBase;

/**
 * 门面标准
 */
abstract class FacadeStd
{
    public static mixed $instance;

    /**
     * @return mixed
     */
    abstract public static function getInstance(): mixed;

    /**
     * @param WorkerBase $worker
     * @return mixed
     */
    abstract public static function setInstance(WorkerBase $worker): mixed;

    /**
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    abstract public static function __callStatic(string $name, array $arguments): mixed;
}
