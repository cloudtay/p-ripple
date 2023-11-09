<?php

namespace App\Facade;

use App\Http\HttpWorker;
use Std\Facade;
use Worker\WorkerInterface;

class Http extends Facade
{
    public static mixed $instance;

    public static function __callStatic(string $name, array $arguments): mixed
    {
        return call_user_func_array([Http::$instance, $name], $arguments);
    }

    public static function getInstance(): HttpWorker
    {
        return Http::$instance;
    }

    public static function setInstance(WorkerInterface $worker): HttpWorker
    {
        Http::$instance = $worker;
        return Http::$instance;
    }
}
