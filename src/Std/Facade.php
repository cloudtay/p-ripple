<?php

namespace PRipple\Std;

use PRipple\Worker\Worker;

abstract class Facade
{
    public static mixed $instance;

    abstract public static function getInstance(): mixed;

    abstract public static function setInstance(Worker $worker): mixed;

    abstract public static function __callStatic(string $name, array $arguments): mixed;
}
