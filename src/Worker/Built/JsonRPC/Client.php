<?php declare(strict_types=1);
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


namespace Cclilshy\PRipple\Worker\Built\JsonRPC;

use Cclilshy\PRipple\Core\Coroutine\Coroutine;
use Cclilshy\PRipple\Core\Event\Event;
use Cclilshy\PRipple\Core\Map\CoroutineMap;
use Cclilshy\PRipple\Core\Map\EventMap;
use Cclilshy\PRipple\Core\Output;
use Cclilshy\PRipple\Core\Standard\WorkerInterface;
use Cclilshy\PRipple\Facade\RPC;
use Cclilshy\PRipple\PRipple;
use Cclilshy\PRipple\Protocol\Slice;
use Cclilshy\PRipple\Worker\Built\JsonRPC\Attribute\RPCMethod;
use Cclilshy\PRipple\Worker\Built\JsonRPC\Exception\RPCException;
use Cclilshy\PRipple\Worker\Built\ProcessManager;
use Cclilshy\PRipple\Worker\Socket\TCPConnection;
use Cclilshy\PRipple\Worker\Worker;
use Cclilshy\PRipple\Worker\WorkerNet;
use Exception;
use Override;
use Throwable;
use function call_user_func_array;
use function Co\async;
use function count;
use function function_exists;
use function json_decode;
use function json_encode;
use function method_exists;
use function posix_getpid;
use function property_exists;

/**
 * @class Client
 * This is a Json Rpc client for this process to interact with services of other processes.
 * Workers of all network types in this process can access the services of other processes through Json Rpc Client.
 * During passive fork, the connection between the current process and other rpc services should be re-established.
 *
 * The original Json Rpc client passively accepts responses and resumes coroutines, but this approach is not elegant enough
 * But that's not enough, it should be able to handle passively accepting requests
 */
final class Client extends WorkerNet implements WorkerInterface
{
    /**
     * 门面类
     * @var string $facadeClass
     */
    public static string $facadeClass = RPC::class;

    /**
     * Client ID
     * @var int $clientId
     */
    public int $clientId;

    /**
     * RPC服务列表
     * @var Worker[] $rpcServices
     */
    public array $rpcServices = [];

    /**
     * RPC服务哈希表
     * @var array<string,TCPConnection> $rpcServiceConnections
     * @var TCPConnection[]             $rpcServiceConnections
     */
    public array $rpcServiceConnections = [];

    /**
     * RPC服务地址哈希表
     * @var array<string,array> $rpcServiceAddressList
     * @var array               $rpcServiceAddressList
     */
    public array $rpcServiceAddressList = [];

    /**
     * initialize
     * @var string $socketType
     */
    public function __construct()
    {
        parent::__construct(Client::class, Slice::class);
        $this->clientId = posix_getpid();
    }

    /**
     * @param string        $context
     * @param TCPConnection $TCPConnection
     * @return void
     */
    #[Override] protected function onMessage(string $context, TCPConnection $TCPConnection): void
    {
        $info = json_decode($context);
        try {
            if (property_exists($info, 'result')) {
                CoroutineMap::resume(
                    $info->id,
                    Event::build(Coroutine::EVENT_RESUME, $info->result, $TCPConnection->getName())
                );
            } elseif (property_exists($info, 'error')) {
                CoroutineMap::throw($info->id, new RPCException($info->error->message, $info->error->code));
            } elseif (property_exists($info, 'method')) {
                /**
                 * @deprecated 禁止主动请求客户端
                 */
                if (method_exists($this, $info->method)) {
                    $info->params[] = $TCPConnection;
                    $result         = call_user_func_array([$this, $info->method], $info->params);
                    $this->slice->send($TCPConnection, json_encode([
                        'version' => '2.0',
                        'result'  => $result,
                        'id'      => $info->id
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                } elseif (function_exists($info->method)) {
                    $result = call_user_func_array($info->method, $info->params);
                    $this->slice->send($TCPConnection, json_encode([
                        'version' => '2.0',
                        'result'  => $result,
                        'id'      => $info->id
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
        if (!$this->connectProcessManager()) {
            exit(-1);
        }
        async(function () {
            $rpcServiceList = $this->call([ProcessManager::class, 'setProcessId'], $this->clientId);
            foreach ($rpcServiceList as $rpcService) {
                EventMap::push(Event::build(Server::EVENT_ONLINE, [
                    'name'    => $rpcService->name,
                    'address' => $rpcService->address,
                    'type'    => $rpcService->type
                ], $this->name));
            }
        });
    }

    /**
     * @return bool
     */
    private function connectProcessManager(): bool
    {
        $count = 0;
        connect:
        $connect = $this->connectService(
            ProcessManager::class,
            ProcessManager::getInstance()->getRPCServiceAddress(),
            ProcessManager::class
        );
        if (!$connect && $count++ < 3) {
            goto connect;
        }
        return $connect;
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
     * @param array $route
     * @param mixed ...$arguments
     * @return mixed
     * @throws RPCException
     * @throws Exception
     */
    public function call(array $route, mixed ...$arguments): mixed
    {
        if (count($route) !== 2) {
            throw new RPCException('Invalid router');
        }
        $serviceName = $route[0];
        $methodName  = $route[1];
        $context     = json_encode([
            'method' => $methodName,
            'params' => $arguments,
            'id'     => CoroutineMap::this()?->hash ?? 'anonymous'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!$serviceConnection = $this->rpcServiceConnections[$serviceName] ?? null) {
            if (!$serviceInfo = $this->rpcServiceAddressList[$serviceName]) {
                throw new Exception("RPC service {$serviceName} is not connected");
            }
            try {
                $this->connectService($serviceName, $serviceInfo['address'], $serviceInfo['type']);
                $serviceConnection = $this->rpcServiceConnections[$serviceName];
            } catch (Exception $exception) {
                throw new Exception("RPC service {$serviceName} is not connected : {$exception->getMessage()}");
            }
        } elseif ($serviceConnection->deprecated === true) {
            if (!$serviceInfo = $this->rpcServiceAddressList[$serviceName]) {
                throw new Exception("RPC service {$serviceName} is not connected");
            }
            try {
                $this->connectService($serviceName, $serviceInfo['address'], $serviceInfo['type']);
                $serviceConnection = $this->rpcServiceConnections[$serviceName];
            } catch (Exception $exception) {
                Output::printException($exception);
            }
        }

        try {
            $this->slice->send($serviceConnection, $context);
            if ($coroutine = CoroutineMap::this()) {
                return $coroutine->suspend();
            }
        } catch (Throwable $exception) {
            Output::printException($exception);
        }
        return false;
    }

    /**
     * 连接RPC服务器
     * @param string $name
     * @param string $address
     * @param string $type
     * @return bool
     */
    public function connectService(string $name, string $address, string $type): bool
    {
        if ($TCPConnection = $this->connect($address)) {
            $TCPConnection->setName($name);
            $TCPConnection->setSendBufferSize(PRipple::getArgument('PP_RPC_BUFFER_SIZE', 204800));
            $TCPConnection->setReceiveBufferSize(PRipple::getArgument('PP_RPC_BUFFER_SIZE', 204800));
            $this->rpcServiceConnections[$name] = $TCPConnection;
            $this->addService($name, $address, $type);
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param string $name
     * @param string $address
     * @param string $type
     * @return void
     */
    #[RPCMethod('RPC服务上线')] private function noticeRPCServiceOnline(string $name, string $address, string $type): void
    {
        EventMap::push(Event::build(Server::EVENT_ONLINE, [
            'name'    => $name,
            'address' => $address,
            'type'    => $type
        ], $this->name));
    }

    /**
     * @param string $name
     * @return void
     */
    #[RPCMethod('RPC服务下线')] private function noticeRPCServiceOffline(string $name): void
    {
        EventMap::push(Event::build('rpcServiceOffline', [
            'name' => $name
        ], $this->name));
    }
}
