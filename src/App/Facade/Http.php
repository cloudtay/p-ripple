<?php

namespace PRipple\App\Facade;

use PRipple\Std\Facade;
use PRipple\Worker\Worker;

class Http extends Facade
{
    public static mixed $instance;

    public static function __callStatic(string $name, array $arguments): mixed
    {
        return call_user_func_array([Http::$instance, $name], $arguments);
    }

    public static function getInstance(): \PRipple\App\Http\Http
    {
        return Http::$instance;
    }

    public static function setInstance(Worker $worker): \PRipple\App\Http\Http
    {
        Http::$instance = $worker;
        return Http::$instance;
    }
}
