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

use Core\Map\CollaborativeFiberMap;
use Core\Output;
use Exception;
use Protocol\Slice;
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
    public TCPConnection $tcpConnection;
    private int          $mode = 1;
    private Worker       $workerBase;
    private Slice        $slice;
    public string        $rpcServiceSocketAddress;

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
        $this->slice = new Slice();
        $this->mode  = JsonRpcConnection::MODE_REMOTE;
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
            $this->workerBase->bindAddressHashMap[$addressFull] = $this->workerBase->name;
            $this->workerBase->subscribeSocket($this->tcpConnection->getSocket());
            $client                                                       = $this->workerBase->addSocket($this->tcpConnection->getSocket());
            $this->workerBase->rpcServiceSockets[$this->workerBase->name] = $client;
            $client->setIdentity(Worker::IDENTITY_RPC);
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
            $this->slice->send(
                $this->tcpConnection,
                Build::new(
                    'rpc.json.call',
                    JsonRpcBuild::create(posix_getpid(), $method, ...$params),
                    CollaborativeFiberMap::current()->hash
                )
            );
            return CollaborativeFiberMap::current()->publishAwait('suspend', []);
        } catch (Throwable $exception) {
            Output::printException($exception);
        }
        return false;
    }
}
