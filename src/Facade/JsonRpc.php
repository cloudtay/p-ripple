<?php

namespace Facade;

use Core\Std\FacadeStd;
use Worker\Built\JsonRpc\JsonRpcClient;
use Worker\Worker;

/**
 * @method static object|null use (string $method, array $params = [])
 * @method static void reConnects();
 */
class JsonRpc extends FacadeStd
{
    /**
     * 单例实体
     * @var mixed
     */
    public static mixed $instance;

    /**
     * 执行方法
     * @param string $name
     * @param array  $arguments
     * @return string
     * @return mixed
     */
    public static function __callStatic(string $name, array $arguments): mixed
    {
        return call_user_func_array([JsonRpc::getInstance(), $name], $arguments);
    }

    /**
     * 获取单例
     * @return JsonRpcClient
     */
    public static function getInstance(): JsonRpcClient
    {
        return JsonRpc::$instance;
    }

    /**
     * 设置单例
     * @param Worker $worker
     * @return JsonRpcClient|Worker
     */
    public static function setInstance(Worker $worker): JsonRpcClient|Worker
    {
        return JsonRpc::$instance = $worker;
    }
}
