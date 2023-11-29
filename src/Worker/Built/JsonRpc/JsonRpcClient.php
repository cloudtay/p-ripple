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

use Core\FileSystem\FileException;
use Core\Map\CollaborativeFiberMap;
use Core\Map\WorkerMap;
use Core\Output;
use Exception;
use Facade\JsonRpc;
use Protocol\Slice;
use Throwable;
use Worker\Built\JsonRpc\Exception\RpcException;
use Worker\Built\ProcessManager;
use Worker\Prop\Build;
use Worker\Socket\SocketInet;
use Worker\Socket\SocketUnix;
use Worker\Socket\TCPConnection;
use Worker\Worker;

/**
 * This is a Json Rpc client for this process to interact with services of other processes.
 * Workers of all network types in this process can access the services of other processes through Json Rpc Client.
 * During passive fork, the connection between the current process and other rpc services should be re-established.
 *
 * The original Json Rpc client passively accepts responses and resumes coroutines, but this approach is not elegant enough
 * But that's not enough, it should be able to handle passively accepting requests
 */
class JsonRpcClient extends Worker
{
    /**
     * Client ID
     * @var int $clientId
     */
    public int $clientId;

    /**
     * @var Worker[] $rpcServices
     */
    public array $rpcServices;

    /**
     * @var TCPConnection[] $rpcServiceConnections
     */
    public array $rpcServiceConnections = [];

    /**
     * @var string $facadeClass
     */
    public static string $facadeClass = JsonRpc::class;

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
     * @param Worker    $worker
     * @param bool|null $connect
     * @return void
     */
    public function add(Worker $worker, bool|null $connect = false): void
    {
        $this->rpcServices[$worker->name] = $worker;
        if ($this->isFork || $connect) {
            try {
                [$type, $addressFull, $addressInfo, $address, $port] = Worker::parseAddress($worker->getRpcServiceAddress());
                match ($type) {
                    SocketUnix::class => $serverSocket = SocketUnix::connect($address),
                    SocketInet::class => $serverSocket = SocketInet::connect($address, $port),
                    default => throw new Exception("Unsupported socket type: {$type}")
                };
                socket_set_option($serverSocket, SOL_SOCKET, SO_KEEPALIVE, 1);
                $this->rpcServiceConnections[$worker->name] = $this->addSocket($serverSocket, $type);
                $this->rpcServiceConnections[$worker->name]->setName($worker->name);
            } catch (Exception $exception) {
                Output::printException($exception);
            }
        }
    }

    /**
     * @param string $serviceName
     * @param string $methodName
     * @param mixed  ...$arguments
     * @return mixed
     */
    public function call(string $serviceName, string $methodName, mixed ...$arguments): mixed
    {
        if (!$this->isFork && $this->rpcServices[$serviceName]->mode === Worker::MODE_COLLABORATIVE) {
            return call_user_func_array([$this->rpcServices[$serviceName], $methodName], $arguments);
        } else {
            $context = json_encode([
                'method' => $methodName,
                'params' => $arguments,
                'id'     => CollaborativeFiberMap::current()?->hash ?? 'anonymous',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            try {
                $this->slice->send($this->rpcServiceConnections[$serviceName], $context);
                return CollaborativeFiberMap::current()?->publishAwait('suspend', []);
            } catch (Throwable $exception) {
                Output::printException($exception);
            }
        }
        return false;
    }

    /**
     * @param string        $context
     * @param TCPConnection $client
     * @return void
     */
    public function onMessage(string $context, TCPConnection $client): void
    {
        $info = json_decode($context);
        if (property_exists($info, 'result')) {
            try {
                CollaborativeFiberMap::getCollaborativeFiber($info->id)?->resumeFiberExecution($info->result);
            } catch (Throwable $exception) {
                Output::printException($exception);
            }
        } elseif (property_exists($info, 'error')) {
            try {
                CollaborativeFiberMap::getCollaborativeFiber($info->id)?->exceptionHandler(
                    new RpcException($info->error->message, $info->error->code)
                );
            } catch (Throwable $exception) {
                Output::printException($exception);
            }
        } elseif (property_exists($info, 'method')) {
            if (method_exists($worker = $this->rpcServices[$client->getName()], $info->method)) {
                $info->params[] = $client;
                $result         = call_user_func_array([$worker, $info->method], $info->params);
            } else
                if (function_exists($info->method)) {
                    $result = call_user_func_array($info->method, $info->params);
                } else {
                    $result = null;
                }
            try {
                $this->slice->send($client, json_encode([
                    'code'   => 0,
                    'result' => $result,
                    'id'     => $info->id,
                ]));
            } catch (FileException $exception) {
                Output::printException($exception);
            }
        }
    }

    /**
     * The child process has its own RPC service connection
     * @return void
     */
    public function forkPassive(): void
    {
        parent::forkPassive();
        $this->clientId              = posix_getpid();
        $this->rpcServiceConnections = [];
        $this->rpcServices           = [];
        $this->add(WorkerMap::get(ProcessManager::class));
        $this->call(ProcessManager::class, 'setProcessId', posix_getpid());
    }

    public function onConnect(TCPConnection $client): void
    {
        // TODO: Implement onConnect() method.
    }

    public function onClose(TCPConnection $client): void
    {
        // TODO: Implement onClose() method.
    }

    public function onHandshake(TCPConnection $client): void
    {
        // TODO: Implement onHandshake() method.
    }

    public function heartbeat(): void
    {
        // TODO: Implement heartbeat() method.
    }

    public function handleEvent(Build $event): void
    {
        // TODO: Implement handleEvent() method.
    }
}
