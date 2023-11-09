<?php
declare(strict_types=1);

namespace Std;

use Worker\WorkerInterface;

/**
 *
 */
abstract class Facade
{
    public static mixed $instance;

    /**
     * @return mixed
     */
    abstract public static function getInstance(): mixed;

    /**
     * @param WorkerInterface $worker
     * @return mixed
     */
    abstract public static function setInstance(WorkerInterface $worker): mixed;

    /**
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    abstract public static function __callStatic(string $name, array $arguments): mixed;
}
