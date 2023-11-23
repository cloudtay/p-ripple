<?php
declare(strict_types=1);

namespace Core\Std;

use Worker\Worker;

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
     * @param Worker $worker
     * @return mixed
     */
    abstract public static function setInstance(Worker $worker): mixed;

    /**
     * @param string $name
     * @param array  $arguments
     * @return mixed
     */
    abstract public static function __callStatic(string $name, array $arguments): mixed;
}
