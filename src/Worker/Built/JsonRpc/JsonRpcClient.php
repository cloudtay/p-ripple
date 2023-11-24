<?php
/*
 * Copyright (c) 2023 cclilshy
 * Contact Information:
 * Email: jingnigg@gmail.com
 * Website: https://cc.cloudtay.com/
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * 版权所有 (c) 2023 cclilshy
 *
 * 特此免费授予任何获得本软件及相关文档文件（“软件”）副本的人，不受限制地处理
 * 本软件，包括但不限于使用、复制、修改、合并、出版、发行、再许可和/或销售
 * 软件副本的权利，并允许向其提供本软件的人做出上述行为，但须符合以下条件：
 *
 * 上述版权声明和本许可声明应包含在本软件的所有副本或主要部分中。
 *
 * 本软件按“原样”提供，不提供任何形式的保证，无论是明示或暗示的，
 * 包括但不限于适销性、特定目的的适用性和非侵权性的保证。在任何情况下，
 * 无论是合同诉讼、侵权行为还是其他方面，作者或版权持有人均不对
 * 由于软件或软件的使用或其他交易而引起的任何索赔、损害或其他责任承担责任。
 */

namespace Worker\Built\JsonRpc;

use Core\Output;
use Facade\JsonRpc;
use Protocol\Slice;
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
     * Client ID
     * @var int $clientId
     */
    protected int $clientId;

    /**
     * initialize
     * @var string $socketType
     */
    public function __construct()
    {
        parent::__construct(JsonRpcClient::class, Slice::class);
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
     * Handle response
     * @param string        $context
     * @param TCPConnection $client
     * @return void
     */
    public function onMessage(string $context, TCPConnection $client): void
    {
        echo 123;
        /**
         * @var Build $build
         */
        $build = unserialize($context);
        $this->resume($build->source, $build->data);
    }


    /**
     * The child process has its own RPC service connection
     * @return void
     */
    public function forkPassive(): void
    {
        parent::forkPassive();
        $this->clientId = posix_getpid();
        $this->connectAll();
    }

    /**
     * Reconnect all RPC services
     * @return void
     */
    public function connectAll(): void
    {
        if ($this->isFork) {
            foreach (RpcServices::$jsonRpcServices as $jsonRpcServiceName => $jsonRpcService) {
                $jsonRpcService->connect();
            }
        }
    }

    /**
     * Connect to RPC service
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
                    $serverSocket     = SocketInet::connect($address, $port);
                    break;
                case SocketUnix::class:
                    $this->socketType = SocketInet::class;
                    $serverSocket     = SocketUnix::connect($address);
                    break;
                default:
                    return false;
            }
            $this->bindAddressHashMap[$addressFull] = $serverSocket;
            $this->subscribeSocket($serverSocket);
            $client                                = $this->addSocket($serverSocket);
            $this->rpcServiceSockets[$serviceName] = $client;
            $client->setIdentity(Worker::IDENTITY_RPC);
        } catch (Throwable $exception) {
            Output::printException($exception);
            return false;
        }
        return true;
    }

    /**
     * Get an Rpc connection
     * @param string $serviceName
     * @return JsonRpcConnection|null
     */
    public function use(string $serviceName): JsonRpcConnection|null
    {
        return RpcServices::$jsonRpcServices[$serviceName] ?? null;
    }
}
