<?php

namespace Worker\Built\JsonRpc;

use Core\Output;
use Facade\JsonRpc;
use Protocol\CCL;
use Throwable;
use Worker\Map\RpcServices;
use Worker\Prop\Build;
use Worker\Socket\SocketInet;
use Worker\Socket\SocketUnix;
use Worker\Socket\TCPConnection;
use Worker\Worker;

/**
 * This is a Json Rpc client for this process to interact with services of other processes.
 * Workers of all network types in this process can access the services of other processes through Json Rpc Client.
 * During passive fork, the connection between the current process and other rpc services should be re-established.
 */
class JsonRpcClient extends Worker
{
    /**
     * 客户端ID
     * @var int $clientId
     */
    protected int $clientId;

    /**
     * 服务列表
     * @var array $rpcServiceHashMap
     */
    private array $rpcServiceHashMap = [];

    /**
     * 初始化
     * @var string $socketType
     */
    public function __construct()
    {
        parent::__construct(JsonRpcClient::class, CCL::class);
        $this->clientId = posix_getpid();
        JsonRpc::setInstance($this);
    }

    /**
     * @return JsonRpcClient
     */
    public static function instance(): JsonRpcClient
    {
        return JsonRpc::getInstance();
    }

    /**
     * 处理响应
     * @param string        $context
     * @param TCPConnection $client
     * @return void
     */
    public function onMessage(string $context, TCPConnection $client): void
    {
        /**
         * @var Build $build
         */
        $build = unserialize($context);
        $this->resume($build->source, $build->data);
    }

    /**
     * 子进程拥有自己的RPC服务连接
     * @return void
     */
    public function forkPassive(): void
    {
        parent::forkPassive();
        $this->clientId = posix_getpid();
        $this->reConnects();
    }

    /**
     * 重连RPC服务
     * @return void
     */
    public function reConnects(): void
    {
        if ($this->isFork) {
            foreach (RpcServices::$jsonRpcServices as $jsonRpcServiceName => $jsonRpcService) {
                $jsonRpcService->connect();
            }
        }
    }

    /**
     * 连接RPC服务
     * @param string $serviceName
     * @param string $addressFull
     * @return bool
     */
    public function connect(string $serviceName, string $addressFull): bool
    {
        try {
            [$type, $addressFull, $addressInfo, $address, $port] = Worker::parseAddress($addressFull);
            switch ($type) {
                case SocketInet::class:
                    $this->socketType = SocketInet::class;
                    $listenSocket     = SocketInet::connect($address, $port);
                    break;
                case SocketUnix::class:
                    $this->socketType = SocketInet::class;
                    $listenSocket     = SocketUnix::connect($address);
                    break;
                default:
                    return false;
            }
            $this->bindAddressHashMap[$addressFull] = $listenSocket;
            $this->subscribeSocket($listenSocket);
            $client                                = $this->addSocket($listenSocket);
            $this->rpcServiceSockets[$serviceName] = $client;
        } catch (Throwable $exception) {
            Output::printException($exception);
            return false;
        }
        return true;
    }
}
