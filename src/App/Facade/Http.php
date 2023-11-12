<?php
declare(strict_types=1);

namespace App\Facade;

use App\Http\HttpWorker;
use Std\FacadeStd;
use Worker\WorkerBase;

/**
 * Http门面
 */
class Http extends FacadeStd
{
    /**
     * @var mixed
     */
    public static mixed $instance;

    /**
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public static function __callStatic(string $name, array $arguments): mixed
    {
        return call_user_func_array([Http::$instance, $name], $arguments);
    }

    /**
     * @return HttpWorker
     */
    public static function getInstance(): HttpWorker
    {
        return Http::$instance;
    }

    /**
     * @param WorkerBase $worker
     * @return HttpWorker
     */
    public static function setInstance(WorkerBase $worker): HttpWorker
    {
        Http::$instance = $worker;
        return Http::$instance;
    }
}
