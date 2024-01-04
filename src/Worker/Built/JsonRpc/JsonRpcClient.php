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
use Core\Map\WorkerMap;
use Core\Output;
use Exception;
use Facade\JsonRpc;
use Protocol\Slice;
use Throwable;
use Worker\Built\JsonRpc\Attribute\RPC;
use Worker\Built\JsonRpc\Exception\RpcException;
use Worker\Built\ProcessManager;
use Worker\Prop\Build;
use Worker\Socket\SocketInet;
use Worker\Socket\SocketUnix;
use Worker\Socket\TCPConnection;
use Worker\Worker;
use function PRipple\async;

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
    public array $rpcServices = [];

    /**
     * @var TCPConnection[] $rpcServiceConnections
     */
    public array $rpcServiceConnections = [];

    /**
     * @var array $rpcServiceAddressList
     */
    public array $rpcServiceAddressList = [];

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
    }

    /**
     * 连接RPC服务器
     * @param string $name
     * @param string $address
     * @param string $type
     * @return void
     */
    public function addService(string $name, string $address, string $type): void
    {
        $this->rpcServiceAddressList[$name] = ['address' => $address, 'type' => $type];
    }

    /**
     * 连接RPC服务器
     * @param string $name
     * @param string $address
     * @param string $type
     * @return void
     * @throws Exception
     */
    public function connect(string $name, string $address, string $type): void
    {
        $this->addService($name, $address, $type);
        [$type, $addressFull, $addressInfo, $address, $port] = Worker::parseAddress($address);
        match ($type) {
            SocketUnix::class => $serverSocket = SocketUnix::connect($address, null, ['nonblock' => true]),
            SocketInet::class => $serverSocket = SocketInet::connect($address, $port, ['nonblock' => true]),
            default => throw new Exception("Unsupported socket type: {$type}")
        };
        socket_set_option($serverSocket, SOL_SOCKET, SO_KEEPALIVE, 1);
        $this->rpcServiceConnections[$name] = $this->addSocket($serverSocket, $type);
        $this->rpcServiceConnections[$name]->setName($name);
    }

    /**
     * @param array $route
     * @param mixed ...$arguments
     * @return mixed
     * @throws RpcException
     */
    public function call(array $route, mixed ...$arguments): mixed
    {
        if (count($route) !== 2) {
            throw new RpcException('Invalid router');
        }
        $serviceName = $route[0];
        $methodName  = $route[1];

        $context = json_encode([
            'method' => $methodName,
            'params' => $arguments,
            'id'     => CollaborativeFiberMap::current()?->hash ?? 'anonymous',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!$serviceConnection = $this->rpcServiceConnections[$serviceName] ?? null) {
            // 如果不存在则判断连接服务信息
            if ($serviceInfo = $this->rpcServiceAddressList[$serviceName]) {
                try {
                    $this->connect($serviceName, $serviceInfo['address'], $serviceInfo['type']);
                    $serviceConnection = $this->rpcServiceConnections[$serviceName];
                } catch (Exception $exception) {
                    throw new Exception("RPC service {$serviceName} is not connected");
                }
            } else {
                throw new Exception("RPC service {$serviceName} is not connected");
            }
        } elseif ($serviceConnection->deprecated === true) {
            // 如果存在且已启用则报错
            if ($serviceInfo = $this->rpcServiceAddressList[$serviceName]) {
                try {
                    $this->connect($serviceName, $serviceInfo['address'], $serviceInfo['type']);
                    $serviceConnection = $this->rpcServiceConnections[$serviceName];
                } catch (Exception $exception) {
                    throw new Exception("RPC service {$serviceName} is not connected");
                }
            }
        }

        try {
            $this->slice->send($serviceConnection, $context);
            return CollaborativeFiberMap::current()?->publishAwait('suspend', []);
        } catch (Throwable $exception) {
            Output::printException($exception);
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
        try {
            if (property_exists($info, 'result')) {
                CollaborativeFiberMap::getCollaborativeFiber($info->id)?->resume($info->result);
            } elseif (property_exists($info, 'error')) {
                CollaborativeFiberMap::getCollaborativeFiber($info->id)?->exceptionHandler(
                    new RpcException($info->error->message, $info->error->code)
                );
            } elseif (property_exists($info, 'method')) {
                if (method_exists($this, $info->method)) {
                    $info->params[] = $client;
                    $result         = call_user_func_array([$this, $info->method], $info->params);
                    $this->slice->send($client, json_encode([
                        'version' => '2.0',
                        'result'  => $result,
                        'id'      => $info->id ?? null
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                } elseif (function_exists($info->method)) {
                    $result = call_user_func_array($info->method, $info->params);
                    $this->slice->send($client, json_encode([
                        'version' => '2.0',
                        'result'  => $result,
                        'id'      => $info->id ?? null
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                }
            }
        } catch (Throwable $exception) {
            Output::printException($exception);
        }
    }

    /**
     * The child process has its own RPC service connection
     * 进程分生后会自动驱动当前子进程重连主程ProcessMassager提供的RPC服务
     * @return void
     */
    public function forkPassive(): void
    {
        parent::forkPassive();
        $this->clientId              = posix_getpid();
        $this->rpcServiceConnections = [];
        $this->rpcServices           = [];
        try {
            $connectionState = $this->connectProcessManager();
        } catch (Exception $exception) {
            $connectionState = false;
            $count           = 0;
            while ($count < 3) {
                usleep(10000);
                try {
                    $connectionState = $this->connectProcessManager();
                    break;
                } catch (Exception $exception) {
                    Output::printException($exception);
                }
                $count++;
            }
        }
        if ($connectionState) {
            async(function () {
                $rpcServiceList = $this->call([ProcessManager::class, 'setProcessId'], posix_getpid());
                foreach ($rpcServiceList as $rpcService) {
                    $this->publishAsync(Build::new('rpcServiceOnline', [
                        'name'    => $rpcService->name,
                        'address' => $rpcService->address,
                        'type'    => $rpcService->type
                    ], $this->name));
                }
            });
        } else {
            Output::printException(new Exception('Failed to connect to ProcessManager'));
        }
    }

    /**
     * @return bool
     * @throws Exception
     */
    private function connectProcessManager(): bool
    {
        $this->connect(
            ProcessManager::class,
            WorkerMap::get(ProcessManager::class)->getRpcServiceAddress(),
            ProcessManager::class
        );
        return true;
    }

    /**
     * @param string $name
     * @param string $address
     * @param string $type
     * @return void
     */
    #[RPC('RPC服务上线')] private function noticeRpcServiceOnline(string $name, string $address, string $type): void
    {
        $this->publishAsync(Build::new('rpcServiceOnline', [
            'name'    => $name,
            'address' => $address,
            'type'    => $type
        ], $this->name));
    }

    /**
     * @param string $name
     * @return void
     */
    #[RPC('RPC服务下线')] private function noticeRpcServiceOffline(string $name): void
    {
        $this->publishAsync(Build::new('rpcServiceOffline', [
            'name' => $name
        ], $this->name));
    }

    public function onConnect(TCPConnection $client): void
    {
        // TODO: Implement onConnect() method.
    }

    public function onClose(TCPConnection $client): void
    {
        // TODO:
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
