<?php

namespace Worker\Map;

use Worker\Built\JsonRpc\JsonRpcConnection;
use Worker\Worker;

/**
 * Class RpcServers
 */
class RpcServices
{
    /**
     * Rpc服务器列表
     * @var JsonRpcConnection[] $jsonRpcServices
     */
    public static array $jsonRpcServices = [];

    /**
     * 注册Rpc服务器
     * @param Worker $worker
     * @return void
     */
    public static function register(Worker $worker): void
    {
        RpcServices::$jsonRpcServices[$worker->name] = new JsonRpcConnection(
            $worker,
            $worker->getRpcServiceAddress()
        );
    }
}
