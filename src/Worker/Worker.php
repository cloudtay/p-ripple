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

declare(strict_types=1);

namespace Worker;

use Closure;
use Core\Constants;
use Core\Kernel;
use Core\Map\CollaborativeFiberMap;
use Core\Map\EventMap;
use Core\Map\WorkerMap;
use Core\Output;
use Core\Std\ProtocolStd;
use Core\Std\WorkerInterface;
use Exception;
use Fiber;
use JetBrains\PhpStorm\NoReturn;
use PRipple;
use Protocol\Slice;
use Protocol\TCPProtocol;
use Socket;
use Throwable;
use Worker\Built\JsonRpc\JsonRpc;
use Worker\Built\JsonRpc\JsonRpcServer;
use Worker\Built\ProcessManager;
use Worker\Prop\Build;
use Worker\Socket\SocketInet;
use Worker\Socket\SocketUnix;
use Worker\Socket\TCPConnection;

/**
 * WorkerInterface
 */
abstract class Worker implements WorkerInterface
{
    public const HOOK_ON_CONNECT   = 'onConnect';
    public const HOOK_ON_HANDSHAKE = 'onHandshake';
    public const HOOK_ON_MESSAGE   = 'onMessage';
    public const HOOK_ON_CLOSE     = 'onClose';
    public const HOOK_HEARTBEAT    = 'heartbeat';
    public const HOOK_HANDLE_EVENT = 'handleEvent';

    /**
     * 协同工作模式
     */
    public const MODE_COLLABORATIVE = 1;
    const        MODE_INDEPENDENT   = 2;

    /**
     * 运行模式
     * @var int $mode
     */
    public int $mode = 1;

    /**
     * 服务名称
     * @var string
     */
    public string $name;

    /**
     * 活跃的工作者
     * @var bool $busy
     */
    public bool $busy = false;

    /**
     * 是否为分生并行进程
     * @var bool $isFork
     */
    public bool $isFork = false;

    /**
     * 客户端连接列表
     * @var array
     */
    public array $clientSockets = [];

    /**
     * 客户端列表
     * @var TCPConnection []
     */
    public array $clientHashMap = [];

    /**
     * 客户端名称列表
     * @var array $clientNameMap
     */
    public array $clientNameMap = [];

    /**
     * 事件列表
     * @var Build[] $queue
     */
    public array $queue = [];

    /**
     * 订阅事件列表
     * @var array $subscribes
     */
    public array $subscribes = [];

    /**
     * 连接类型
     * @var string
     */
    public string $socketType;

    /**
     * 协议
     * @var ProtocolStd
     */
    public ProtocolStd $protocol;

    /**
     * 监听地址列表,[] = $address=>$options
     * @var array $listenAddressList
     */
    public array $listenAddressList = [];

    /**
     * 监听地址哈希表,[] = $address=>$socket
     * @var Socket[] $listenSocketHashMap
     */
    public array $listenSocketHashMap = [];

    /**
     * Rpc监听地址
     * @var string $rpcServiceListenAddress
     */
    public string $rpcServiceListenAddress;

    /**
     * 报文切割器
     * @var Slice $slice
     */
    public Slice $slice;

    /**
     * 门面类
     * @var string $facadeClass
     */
    public static string $facadeClass;

    /**
     * Rpc服务
     * @var Worker $rpcService
     */
    public Worker $rpcService;

    /**
     * 服务是否已启动
     * @var bool $launched
     */
    public bool $launched = false;

    /**
     * @var bool $root
     */
    public bool $root = true;

    /**
     * @var array $hooks
     */
    public array $hooks  = [];
    public int   $thread = 1;

    /**
     * 构造函数
     * @param string      $name
     * @param string|null $protocol
     */
    public function __construct(string $name, string|null $protocol = TCPProtocol::class)
    {
        $this->name = $name;
        $this->protocol($protocol);
        $this->slice = new Slice;
        $this->hooks = [
            Worker::HOOK_ON_CONNECT   => [],
            Worker::HOOK_ON_HANDSHAKE => [],
            Worker::HOOK_ON_MESSAGE   => [],
            Worker::HOOK_ON_CLOSE     => [],
            Worker::HOOK_HEARTBEAT    => [],
            Worker::HOOK_HANDLE_EVENT => []
        ];
    }

    /**
     * 启动服务
     * @return void
     */
    #[NoReturn] public function launch(): void
    {
        $this->launched = true;
        $this->root     = true;
        while (true) {
            while ($build = array_shift($this->queue)) {
                $this->consumption($build);
            }
            $this->publishAwait();
        }
    }

    /**
     * 绑定协议
     * @param string|null $protocol
     * @return $this
     */
    public function protocol(string|null $protocol = TCPProtocol::class): static
    {
        $this->protocol = new $protocol();
        return $this;
    }

    /**
     * 初始化时执行
     * @return void
     */
    public function initialize(): void
    {
        if (isset(static::$facadeClass)) {
            call_user_func([static::$facadeClass, 'setInstance'], $this);
        }
        if (!$this->isFork()) {
            Output::info('Initialize: ', $this->name . ' [Process:' . posix_getpid() . ']');
        } else {
            \Facade\JsonRpc::call([ProcessManager::class, 'output'], 'Initialize: ', $this->name . ' [Process:' . posix_getpid() . ']'
            );
        }
    }

    /**
     * 是否使用Rpc服务
     * @return bool
     */
    public function checkRpcService(): bool
    {
        return in_array(JsonRpc::class, class_uses($this), true);
    }

    /**
     * 获取Rpc服务地址
     * @return string
     */
    public function getRpcServiceAddress(): string
    {
        if (!isset($this->rpcServiceListenAddress)) {
            $name                          = strtolower(str_replace(['\\', '/'], '_', $this->name));
            $this->rpcServiceListenAddress =
                "unix://" . PP_RUNTIME_PATH . FS . "{$name}.rpc.sock";
        }
        return $this->rpcServiceListenAddress;
    }

    /**
     * 监听服务地址
     * @return void
     */
    public function listen(): void
    {
        try {
            foreach ($this->listenAddressList as $addressFull => $options) {
                [$type, $addressFull, $addressInfo, $address, $port] = Worker::parseAddress($addressFull);
                switch ($type) {
                    case SocketInet::class:
                        $this->socketType = SocketInet::class;
                        $listenSocket     = SocketInet::create($address, $port, $options);
                        break;
                    case SocketUnix::class:
                        unlink($address);
                        $this->socketType = SocketInet::class;
                        $listenSocket     = SocketUnix::create($address, $options);
                        break;
                    default:
                        return;
                }
                $this->listenSocketHashMap[$addressFull] = $listenSocket;
                $this->subscribeSocket($listenSocket);

                if (!$this->isFork()) {
                    Output::info("    |_ ", $addressFull);
                } else {
                    \Facade\JsonRpc::call([ProcessManager::class, 'output'], "    |_ ", $addressFull);
                }
            }
        } catch (Exception $exception) {
            Output::printException($exception);
        }
    }

    /**
     * 订阅一个连接
     * @param Socket $socket
     * @return void
     */
    public function subscribeSocket(Socket $socket): void
    {
        try {
            $this->publishAsync(Build::new(Constants::EVENT_SOCKET_SUBSCRIBE, $socket, $this->name));
        } catch (Throwable $exception) {
            Output::printException($exception);
        }
    }

    /**
     * 发布一个事件
     * @param Build $event
     * @return void
     */
    public function publishAsync(Build $event): void
    {
        EventMap::push($event);
    }

    /**
     * 处理返回
     * @param Build $build
     * @return void
     * @throws Throwable
     */
    public function consumption(Build $build): void
    {
        switch ($build->name) {
            case Constants::EVENT_SOCKET_READ:
                $this->handleSocket($build->data, Constants::EVENT_SOCKET_READ);
                break;
            case Constants::EVENT_SOCKET_EXPECT:
                $this->expectSocket($build->data);
                break;
            case Constants::EVENT_SOCKET_WRITE:
                break;
            case Constants::EVENT_HEARTBEAT:
                $this->callWorkerEvent(Worker::HOOK_HEARTBEAT);
                break;
            default:
                $this->callWorkerEvent(Worker::HOOK_HANDLE_EVENT, $build);
        }
    }

    /**
     * 处理客户端请求
     * @param Socket $socket
     * @param string $event
     * @return void
     */
    public function handleSocket(Socket $socket, string $event): void
    {
        if ($event === Constants::EVENT_SOCKET_EXPECT) {
            $this->expectSocket($socket);
            return;
        } elseif ($event === Constants::EVENT_SOCKET_WRITE) {
            return;
        }

        if (in_array($socket, array_values($this->listenSocketHashMap), true)) {
            $this->accept($socket);
            return;
        } elseif (!$client = $this->getClientBySocket($socket)) {
            $this->expectSocket($socket);
            return;
        }

        $this->handleClientWrite($client);
        if (!$client->readToCache()) {
            if (!$client->cache()) {
                $this->closeClient($client);
                return;
            }
        } elseif (!$client->verify) {
            if ($handshake = $this->protocol->handshake($client)) {
                $client->handshake($this->protocol);
                $this->callWorkerEvent(Worker::HOOK_ON_HANDSHAKE, $client);
                $this->splitMessage($client);
            } elseif ($handshake === false) {
                $this->expectSocket($socket);
            }
        } else {
            $this->splitMessage($client);
        }
    }

    /**
     * @param TCPConnection $client
     * @return void
     */
    private function handleClientWrite(TCPConnection $client): void
    {

    }

    /**
     * 同意一个连接
     * @param Socket $listenSocket
     * @return TCPConnection|false
     */
    public function accept(Socket $listenSocket): TCPConnection|false
    {
        try {
            if ($socket = socket_accept($listenSocket)) {
                socket_set_option($socket, SOL_SOCKET, SO_KEEPALIVE, 1);
                if ($this->socketType === SocketInet::class) {
                    socket_set_option($socket, SOL_TCP, TCP_NODELAY, 1);
                }
                return $this->addSocket($socket, $this->socketType);
            }
        } catch (Exception $exception) {
            Output::printException($exception);
        }
        return false;
    }

    /**
     * 添加一个客户端
     * @param Socket $socket
     * @param string $type
     * @return TCPConnection
     */
    public function addSocket(Socket $socket, string $type): TCPConnection
    {
        $name                       = Worker::getHashBySocket($socket);
        $this->clientSockets[$name] = $socket;
        $this->clientHashMap[$name] = $client = new TCPConnection($socket, $type);
        $this->clientHashMap[$name]->setNoBlock();
        $this->callWorkerEvent(Worker::HOOK_ON_CONNECT, $this->clientHashMap[$name]);
        $this->subscribeSocket($socket);
        return $client;
    }

    /**
     * 获取客户端HASH
     * @param mixed $socket
     * @return string
     */
    public static function getHashBySocket(mixed $socket): string
    {
        return (spl_object_hash($socket));
    }


    /**
     * 通过连接获取客户端
     * @param mixed $clientSocket
     * @return TCPConnection|null
     */
    public function getClientBySocket(mixed $clientSocket): TCPConnection|null
    {
        $hash = Worker::getHashBySocket($clientSocket);
        return $this->getClientByHash($hash);
    }

    /**
     * 通过名称获取客户端
     * @param string $hash
     * @return TCPConnection|null
     */
    public function getClientByHash(string $hash): TCPConnection|null
    {
        return $this->clientHashMap[$hash] ?? null;
    }

    /**
     * 移除某个客户端
     * @param TCPConnection $client
     * @return void
     */
    public function removeClient(TCPConnection $client): void
    {
        $client->destroy();
        unset($this->clientSockets[$client->getHash()]);
        unset($this->clientHashMap[$client->getHash()]);
        unset($this->clientNameMap[$client->getName()]);
        $this->unsubscribeSocket($client->getSocket());
    }

    /**
     * @param TCPConnection $client
     * @return void
     */
    public function closeClient(TCPConnection $client): void
    {
        $client->deprecated = true;
        $this->callWorkerEvent(Worker::HOOK_ON_CLOSE, $client);
        $this->removeClient($client);
    }

    /**
     * 销毁
     * @return void
     */
    public function destroy(): void
    {
        try {
            foreach ($this->listenAddressList as $address => $options) {
                [$type, $addressFull, $addressInfo, $address, $port] = Worker::parseAddress($address);
                $type === SocketUnix::class && unlink($address);
            }
        } catch (Exception $exception) {
            Output::printException($exception);
        }
    }

    /**
     * 取消订阅一个连接
     * @param Socket $socket
     * @return void
     */
    public function unsubscribeSocket(Socket $socket): void
    {
        try {
            $this->publishAsync(Build::new(Constants::EVENT_SOCKET_UNSUBSCRIBE, $socket, $this->name));
        } catch (Throwable $exception) {
            Output::printException($exception);
        }
    }


    /**
     * 处理异常连接
     * @param Socket $socket
     * @return void
     */
    public function expectSocket(Socket $socket): void
    {
        if ($client = $this->getClientBySocket($socket)) {
            $this->closeClient($client);
        } else {
            socket_close($socket);
            $this->unsubscribeSocket($socket);
        }
    }

    /**
     * 切割报文
     * @param TCPConnection $client
     * @return void
     */
    public function splitMessage(TCPConnection $client): void
    {
        while ($context = $client->getPlaintext()) {
            $this->callWorkerEvent(Worker::HOOK_ON_MESSAGE, $context, $client);
        }
    }


    /**
     * 等待驱动
     * @param Build|null $event
     * @return void
     */
    public function publishAwait(Build|null $event = null): void
    {
        try {
            if (!$event) {
                $event = Build::new('suspend', null, $this->name);
            }
            if ($response = Fiber::suspend($event)) {
                $this->queue[] = $response;
            }
        } catch (Throwable $exception) {
            Output::printException($exception);
        }
    }

    /**
     * Worker分生并行
     * @return int $count
     */
    public function fork(): int
    {
        if ($this->checkRpcService() || $this instanceof JsonRpcServer) {
            return -1;
        }
        return PRipple::kernel()->fork(function () {
            $this->isFork = true;
            $this->root   = false;
            foreach (WorkerMap::$workerMap as $worker) {
                if (in_array($worker->name, Kernel::BUILT_SERVICES, true)) {
                    continue;
                }
                if ($worker->name !== $this->name) {
                    $worker->forkPassive();
                }
            }
            $this->forking();
        }, false);
    }

    /**
     * 获取客户端列表
     * @return TCPConnection[]
     */
    public function getClients(): array
    {
        return $this->clientHashMap ?? [];
    }

    /**
     * 进程分生并行时执行
     * 默认会取消接管父进程的所有客户端连接
     * @return void
     */
    public function forking(): void
    {
        // 取消接管父进程的客户端连接
        $this->isFork = true;
        foreach ($this->getClients() as $client) {
            socket_close($client->getSocket());
            unset($this->subscribes[$client->getHash()]);
            unset($this->clientHashMap[$client->getHash()]);
            unset($this->clientNameMap[$client->getName()]);
            $this->unsubscribeSocket($client->getSocket());
        }
    }

    /**
     * 被动分生并行会触发器,默认会取消接管父进程的所有客户端连接和监听
     * @return void
     */
    public function forkPassive(): void
    {
        // 取消接管父进程的客户端连接
        $this->isFork = true;
        $this->root   = false;
        foreach ($this->getClients() as $client) {
            $this->unsubscribeSocket($client->getSocket());
            unset($this->subscribes[$client->getHash()]);
            unset($this->clientHashMap[$client->getHash()]);
            unset($this->clientNameMap[$client->getName()]);
        }

        foreach ($this->listenSocketHashMap as $address => $socket) {
            socket_close($socket);
            unset($this->listenSocketHashMap[$address]);
            $this->unsubscribeSocket($socket);
        }
    }

    /**
     * 通过连接名称获取客户端
     * @param string $name
     * @return mixed
     */
    public function getClientSocketByName(string $name): mixed
    {
        return $this->clientSockets[$name] ?? null;
    }


    /**
     * 获取所有客户端连接列表
     * @return array|null
     */
    public function getClientSockets(): array|null
    {
        return $this->clientSockets ?? null;
    }

    /**
     * 监听地址
     * @param string     $address
     * @param array|null $options
     * @return $this
     */
    public function bind(string $address, array|null $options = []): static
    {
        $this->listenAddressList[$address] = $options;
        return $this;
    }

    /**
     * 订阅一个事件
     * @param string $event
     * @return void
     */
    public function subscribe(string $event): void
    {
        try {
            $this->publishAsync(Build::new(Constants::EVENT_EVENT_SUBSCRIBE, $event, $this->name));
            $this->subscribes[] = $event;
        } catch (Throwable $exception) {
            Output::printException($exception);
        }
    }

    /**
     * 取消订阅一个事件
     * @param string $event
     * @return void
     * @throws Throwable
     */
    public function unsubscribe(string $event): void
    {
        try {
            $this->publishAsync(Build::new(Constants::EVENT_EVENT_UNSUBSCRIBE, $event, $this->name));
            $index = array_search($event, $this->subscribes);
            if ($index !== false) {
                unset($this->subscribes[$index]);
            }
        } catch (Throwable $exception) {
            Output::printException($exception);
        }
    }

    /**
     * The fiber recovery method that comes with the worker automatically follows the standard processing procedures to handle most events.
     * If the Worker has its own scheduling specification, please override this method
     * Bug generally I don't recommend doing this. If you have new ideas, you can express your opinions.
     *
     * @param string     $hash
     * @param mixed|null $data
     * @return bool
     */
    public function resume(string $hash, mixed $data = null): bool
    {
        if (!$collaborativeFiber = CollaborativeFiberMap::$collaborativeFiberMap[$hash] ?? null) {
            return false;
        }
        try {
            if ($collaborativeFiber->terminated()) {
                $collaborativeFiber->destroy();
                return false;
            } elseif ($event = $collaborativeFiber->resume($data)) {
                if (in_array($event->name, $this->subscribes, true)) {
                    $this->queue[] = $event;
                } else {
                    $this->publishAsync($event);
                }
                return true;
            }
        } catch (Throwable $exception) {
            Output::printException($exception);
        }
        return false;
    }

    /**
     * 定义Worker运行模式
     * @param int|null $mode
     * @param int|null $thread
     * @return Worker
     */
    public function mode(int|null $mode = Worker::MODE_COLLABORATIVE, int|null $thread = 1): static
    {
        $this->mode   = $mode;
        $this->thread = $thread;
        return $this;
    }

    /**
     * @param int|null $thread
     * @return Worker|int
     */
    public function thread(int|null $thread = null): static|int
    {
        if ($thread) {
            $this->thread = $thread;
        } else {
            return $this->thread;
        }
        return $this;
    }


    /**
     * 设置客户端名称
     * @param TCPConnection $client
     * @param string        $name
     * @return void
     */
    public function setClientName(TCPConnection $client, string $name): void
    {
        $client->setName($name);
        $this->clientNameMap[$name] = $client;
    }

    /**
     * 通过客户端名称获取连接
     * @param string $name
     * @return TCPConnection|null
     */
    public function getClientByName(string $name): TCPConnection|null
    {
        return $this->clientNameMap[$name] ?? null;
    }

    /**
     * 序列化自身,跨进程构建用到
     * @return string
     */
    public function serialize(): string
    {
        //TODO: Implement serialize() method.
        return '';
    }

    /**
     * 获取门面类
     * @return Worker|null
     */
    public static function getInstance(): static|null
    {
        if (isset(static::$facadeClass)) {
            return call_user_func([static::$facadeClass, 'getInstance']);
        }
        try {
            throw new Exception('Facade class not found.');
        } catch (Exception $exception) {
            Output::printException($exception);
            return null;
        }
    }

    /**
     * 解析地址
     * @param string $addressFull
     * @return array
     * @throws Exception
     */
    public static function parseAddress(string $addressFull): array
    {
        return [
            $type = match (true) {
                str_contains($addressFull, 'unix://') => SocketUnix::class,
                str_contains($addressFull, 'tcp://') => SocketInet::class,
                default => throw new Exception('Invalid address')
            },
            $addressFull = strtolower(str_replace(['unix://', 'tcp://'], '', $addressFull)),
            $addressInfo = explode(':', $addressFull),
            $address = $addressInfo[0],
            $port = intval(($addressInfo[1] ?? 0))
        ];
    }

    /**
     * 便捷创建
     * @param string $name
     * @return static
     */
    public static function new(string $name): static
    {
        return new static($name);
    }

    /**
     * 反序列化自身,跨进程构建用到
     * @param string $serialized
     * @return static
     */
    public static function unSerialize(string $serialized): static
    {
        //TODO: Implement unSerialize() method.
        return new static();
    }

    /**
     * @return bool
     */
    public function isFork(): bool
    {
        return $this->isFork;
    }

    /**
     * @return bool
     */
    public function isRoot(): bool
    {
        return $this->root;
    }

    /**
     * @param string  $event
     * @param Closure $closure
     * @return int
     */
    public function hook(string $event, Closure $closure): int
    {
        $this->hooks[$event][] = $closure;
        return count($this->hooks[$event]);
    }

    /**
     * @param string $event
     * @param int    $index
     * @return void
     */
    public function unHook(string $event, int $index): void
    {
        unset($this->hooks[$event][$index]);
    }

    /**
     * 支持动态配置Worker
     * @param string $method
     * @param array  $params
     * @return void
     */
    public function callWorkerEvent(string $method, mixed ...$params): void
    {
        call_user_func_array([$this, $method], $params);
        foreach ($this->hooks[$method] ?? [] as $hook) {
            call_user_func_array($hook, $params);
        }
    }

    /**
     * RPC服务上线
     * @param array $data
     * @return void
     */
    public function rpcServiceOnline(array $data): void
    {

    }

    /**
     * 有连接到达到达
     * @param TCPConnection $client
     * @return void
     */
    abstract public function onConnect(TCPConnection $client): void;

    /**
     * 关闭一个连接
     * @param TCPConnection $client
     * @return void
     */
    abstract public function onClose(TCPConnection $client): void;

    /**
     * 握手成功
     * @param TCPConnection $client
     * @return void
     */
    abstract public function onHandshake(TCPConnection $client): void;

    /**
     * 接收到一段报文
     * @param string        $context
     * @param TCPConnection $client
     * @return void
     */
    abstract public function onMessage(string $context, TCPConnection $client): void;

    /**
     * 必须处理事件
     * @param Build $event
     * @return void
     */
    abstract public function handleEvent(Build $event): void;

    /**
     * 心跳
     * @return void
     */
    abstract public function heartbeat(): void;
}
