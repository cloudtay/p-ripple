<?php

namespace Std;

use Worker\WorkerInterface;

abstract class Facade
{
    public static mixed $instance;

    abstract public static function getInstance(): mixed;

    abstract public static function setInstance(WorkerInterface $worker): mixed;

    abstract public static function __callStatic(string $name, array $arguments): mixed;
}
