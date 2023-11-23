<?php

namespace Worker\Built\JsonRpc;

use Core\Map\CollaborativeFiberMap;
use Core\Output;
use Exception;
use Protocol\CCL;
use Throwable;
use Worker\Prop\Build;
use Worker\Socket\SocketInet;
use Worker\Socket\SocketUnix;
use Worker\Socket\TCPConnection;
use Worker\Worker;

class JsonRpcConnection
{
    public const  MODE_LOCAL  = 1;
    public const  MODE_REMOTE = 2;
    private int           $mode = 1;
    private Worker        $workerBase;
    private TCPConnection $tcpConnection;
    private CCL           $ccl;
    private string        $rpcServiceName;
    private string        $rpcServiceSocketAddress;

    /**
     * @param Worker $workerBase
     * @param string $rpcServiceSocketAddress
     */
    public function __construct(Worker $workerBase, string $rpcServiceSocketAddress)
    {
        $this->workerBase              = $workerBase;
        $this->rpcServiceSocketAddress = $rpcServiceSocketAddress;
    }

    /**
     * @return void
     */
    public function connect(): void
    {
        $this->ccl  = new CCL();
        $this->mode = JsonRpcConnection::MODE_REMOTE;
        try {
            [$type, $addressFull, $addressInfo, $address, $port] = Worker::parseAddress($this->rpcServiceSocketAddress);
            switch ($type) {
                case SocketUnix::class:
                    $this->tcpConnection = new TCPConnection(SocketUnix::connect($addressFull), $type);
                    break;
                case SocketInet::class:
                    $this->tcpConnection = new TCPConnection(SocketInet::connect($address, $port), $type);
                    break;
            }
        } catch (Exception $exception) {
            Output::printException($exception);
        }
    }

    /**
     * @param string $method
     * @param mixed  ...$params
     * @return mixed
     */
    public function call(string $method, mixed ...$params): mixed
    {
        return match ($this->mode) {
            JsonRpcConnection::MODE_LOCAL => $this->localCall($method, $params),
            JsonRpcConnection::MODE_REMOTE => $this->remoteCall($method, $params),
            default => false,
        };
    }

    /**
     * @param string $method
     * @param array  $params
     * @return mixed
     */
    private function localCall(string $method, array $params): mixed
    {
        return call_user_func_array([$this->workerBase, $method], $params);
    }

    /**
     * @param string $method
     * @param array  $params
     * @return mixed
     */
    private function remoteCall(string $method, array $params): mixed
    {
        try {
            $this->ccl->send(
                $this->tcpConnection,
                Build::new(
                    'rpc.json.call',
                    JsonRpcBuild::create(posix_getpid(), $method, $params),
                    CollaborativeFiberMap::current()->hash
                )
            );
            return CollaborativeFiberMap::current()->publishAwait('suspend', null);
        } catch (Throwable $exception) {
            Output::printException($exception);
        }
        return false;
    }
}
