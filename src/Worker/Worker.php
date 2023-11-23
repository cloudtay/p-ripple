<?php
declare(strict_types=1);

namespace Worker;

use Core\Constants;
use Core\FileSystem\FileException;
use Core\Map\CollaborativeFiberMap;
use Core\Map\EventMap;
use Core\Output;
use Core\Std\ProtocolStd;
use Core\Std\WorkerInterface;
use Exception;
use Fiber;
use JetBrains\PhpStorm\NoReturn;
use PRipple;
use Protocol\CCL;
use Protocol\TCPProtocol;
use Socket;
use Throwable;
use Worker\Built\JsonRpc\JsonRpc;
use Worker\Built\JsonRpc\JsonRpcBuild;
use Worker\Built\ProcessManager\ProcessContainer;
use Worker\Map\RpcServices;
use Worker\Prop\Build;
use Worker\Socket\SocketInet;
use Worker\Socket\SocketUnix;
use Worker\Socket\TCPConnection;

/**
 * WorkerInterface
 */
class Worker implements WorkerInterface
{
    /**
     * 协同工作模式
     */
    public const MODE_COLLABORATIVE = 1;

    /**
     * 门面表
     * @var string $facadeClass
     */
    protected static string $facadeClass;

    /**
     * 运行模式
     * @var int $mode
     */
    public int $mode = 1;

    /**
     * 客户端名称
     * @var string
     */
    public string $name;

    /**
     * 活跃的工作者
     * @var bool $busy
     */
    public bool $busy = false;

    /**
     * 是否为分叉进程
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
    public array $clients = [];

    /**
     * 事件列表
     * @var Build[] $queue
     */
    protected array $queue = [];

    /**
     * 订阅事件列表
     * @var array $subscribes
     */
    protected array $subscribes = [];

    /**
     * Rpc服务连接
     * @var array $rpcServiceSockets
     */
    protected array $rpcServiceSockets = [];

    /**
     * 是否允许分叉
     * @var bool $allowFork
     */
    protected bool $allowFork = true;

    /**
     * 连接类型
     * @var string
     */
    protected string $socketType;

    /**
     * 协议
     * @var ProtocolStd
     */
    protected ProtocolStd $protocol;

    /**
     * 监听地址列表
     * @var array[] $bindAddressList
     */
    protected array $bindAddressList = [];

    /**
     * 监听地址哈希表
     * @var Socket[] $bindAddressHashMap
     */
    protected array $bindAddressHashMap = [];

    /**
     * Rpc监听地址
     * @var string $rpcServiceListenAddress
     */
    protected string $rpcServiceListenAddress;

    /**
     * Rpc监听连接
     * @var Socket $rpcServiceListenSocket
     */
    protected Socket $rpcServiceListenSocket;

    /**
     * Rpc客户端连接列表
     * @var Socket[] $rpcClientSocketSockets
     */
    protected array $rpcClientSocketSockets = [];

    /**
     * Rpc客户端列表
     * @var TCPConnection[] $rpcClientSocketClients
     */
    protected array $rpcClientSocketClients = [];

    /**
     * 报文切割器
     * @var CCL $CCL
     */
    protected CCL $CCL;

    /**
     * 构造函数
     * @param string      $name
     * @param string|null $protocol
     */
    public function __construct(string $name, string|null $protocol = TCPProtocol::class)
    {
        $this->name = $name;
        $this->protocol($protocol);
        $this->CCL = new CCL;
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
     * 设置门面类
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
     * 启动服务
     * @return void
     */
    #[NoReturn] public function launch(): void
    {
        $this->initialize();
        if ($this->mode === Worker::MODE_COLLABORATIVE) {
            $this->launchCollaborative();
        } else {
            $this->launchAlone();
        }
    }

    /**
     * 初始化时执行
     * @return void
     */
    public function initialize(): void
    {
        if ($this->checkRpcService()) {
            $this->initializeRpcService();
        }
        $this->listen();
        if (isset(static::$facadeClass)) {
            var_dump(static::$facadeClass);;
            call_user_func([static::$facadeClass, 'setInstance'], $this);
        }
    }

    /**
     * 是否使用Rpc服务
     * @return bool
     */
    private function checkRpcService(): bool
    {
        return in_array(JsonRpc::class, class_uses($this));
    }

    /**
     * Rpc服务初始化
     * @return void Rpc服务初始化
     */
    private function initializeRpcService(): void
    {
        try {
            [$type, $addressFull, $addressInfo, $address, $port] = Worker::parseAddress($this->getRpcServiceAddress());
            switch ($type) {
                case SocketInet::class:
                    $this->socketType             = SocketInet::class;
                    $this->rpcServiceListenSocket = SocketInet::create($address, $port, [
                        SO_REUSEADDR => 1
                    ]);
                    $this->subscribeSocket($this->rpcServiceListenSocket);
                    break;
                case SocketUnix::class:
                    $this->socketType             = SocketInet::class;
                    $this->rpcServiceListenSocket = SocketUnix::create($address, [
                        SO_REUSEADDR => 1
                    ]);
                    $this->subscribeSocket($this->rpcServiceListenSocket);
                    break;
                default:
                    return;
            }
            RpcServices::register($this);
        } catch (Exception $exception) {
            Output::printException($exception);
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
            $addressFull = str_replace(['unix://', 'tcp://'], '', $addressFull),
            $addressInfo = explode(':', $addressFull),
            $address = $addressInfo[0],
            $port = intval(($addressInfo[1] ?? 0)),
        ];
    }

    /**
     * 获取Rpc服务地址
     * @return string
     */
    public function getRpcServiceAddress(): string
    {
        if (!isset($this->rpcServiceListenAddress)) {
            $address                       = str_replace(['\\', '/'], '_', $this->name);
            $this->rpcServiceListenAddress =
                'unix://'
                . PRipple::getArgument('RUNTIME_PATH', '/tmp') . FS
                . 'rpc' . UL
                . $address . '.sock';
        }
        return $this->rpcServiceListenAddress;
    }

    /**
     * 创建监听
     * @return void
     */
    protected function listen(): void
    {
        try {
            foreach ($this->bindAddressList as $addressFull => $options) {
                Output::info("    |_ ", $addressFull);
                [$type, $addressFull, $addressInfo, $address, $port] = Worker::parseAddress($addressFull);
                switch ($type) {
                    case SocketInet::class:
                        $this->socketType = SocketInet::class;
                        $listenSocket     = SocketInet::create($address, $port, $options);
                        break;
                    case SocketUnix::class:
                        $this->socketType = SocketInet::class;
                        $listenSocket     = SocketUnix::create($address, $options);
                        break;
                    default:
                        return;
                }
                $this->bindAddressHashMap[$addressFull] = $listenSocket;
                $this->subscribeSocket($listenSocket);
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
    protected function subscribeSocket(Socket $socket): void
    {
        try {
            socket_set_nonblock($socket);
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
    protected function publishAsync(Build $event): void
    {
        EventMap::push($event);
    }

    /**
     * 快捷创建
     * @param string $name
     * @return static
     */
    public static function new(string $name): static
    {
        return new static($name);
    }

    /**
     * 协作模式运行
     * @return void
     */
    private function launchCollaborative(): void
    {
        while (true) {
            while ($build = array_shift($this->queue)) {
                $this->consumption($build);
            }
            $this->publishAwait();
        }
    }

    /**
     * 处理返回
     * @param Build $build
     * @return void
     * @throws Throwable
     */
    private function consumption(Build $build): void
    {
        switch ($build->name) {
            case Constants::EVENT_SOCKET_READ:
                $this->handleSocket($build->data);
                break;
            case Constants::EVENT_SOCKET_EXPECT:
                $this->expectSocket($build->data);
                break;
            case Constants::EVENT_SOCKET_WRITE:
                break;
            case Constants::EVENT_HEARTBEAT:
                $this->heartbeat();
                break;
            default:
                $this->handleEvent($build);
        }
    }

    /**
     * 处理连接请求
     * @param Socket $socket
     * @return void
     */
    public function handleSocket(Socket $socket): void
    {
        if (in_array($socket, array_values($this->bindAddressHashMap), true)) {
            if ($client = $this->accept($socket)) {
                $client->setIdentity('user');
            }
            return;
        } elseif ($this->checkRpcService() && $socket === $this->rpcServiceListenSocket) {
            echo 188;
            if ($client = $this->accept($socket)) {
                $client->setIdentity('rpc');
                $this->subscribeSocket($client->getSocket());
            }
            return;
        }
        echo 'onconnect';

        if (!$client = $this->getClientBySocket($socket)) {
            return;
        }

        if (!$context = $client->read(0, $_)) {
            if ($client->cache === '') {
                $this->removeClient($client);
                $this->onClose($client);
                $client->deprecated = true;
                return;
            }
        }
        $client->cache .= $context;
        if ($client->getIdentity() === 'user') {
            if (!$client->verify) {
                if ($handshake = $this->protocol->handshake($client)) {
                    $client->handshake($this->protocol);
                    $this->onHandshake($client);
                } elseif ($handshake === false) {
                    $this->expectSocket($socket);
                }
            }
            $this->splitMessage($client);
        } elseif ($client->getIdentity() === 'rpc') {
            while ($content = $this->CCL->parse($client)) {
                $build      = unserialize($content);
                $rpcRequest = $build->data;
                if (method_exists($this, $rpcRequest->method)) {
                    $build->data = $rpcRequest->result(
                        call_user_func_array([$this, $rpcRequest->method], $rpcRequest->params)
                    );
                    try {
                        $this->CCL->send($client, $build->serialize());
                    } catch (FileException $exception) {
                        Output::printException($exception);
                    }
                }
            }
        }
    }

    /**
     * 同意一个连接
     * @param Socket $listenSocket
     * @return TCPConnection|false
     */
    protected function accept(Socket $listenSocket): TCPConnection|false
    {
        try {
            if ($socket = socket_accept($listenSocket)) {
                socket_set_option($socket, SOL_SOCKET, SO_KEEPALIVE, 1);
                if ($this->socketType === SocketInet::class) {
                    socket_set_option($socket, SOL_TCP, TCP_NODELAY, 1);
                }
                return $this->addSocket($socket);
            }
        } catch (Exception $exception) {
            Output::printException($exception);
        }
        return false;
    }

    /**
     * 添加一个客户端
     * @param Socket $socket
     * @return TCPConnection
     */
    protected function addSocket(Socket $socket): TCPConnection
    {
        $name                       = Worker::getNameBySocket($socket);
        $this->clientSockets[$name] = $socket;
        $this->clients[$name]       = $client = new TCPConnection($socket, $this->socketType);
        $this->clients[$name]->setNoBlock();
        $this->onConnect($this->clients[$name]);
        $this->subscribeSocket($socket);
        return $client;
    }

    /**
     * 获取客户端HASH
     * @param mixed $socket
     * @return string
     */
    public static function getNameBySocket(mixed $socket): string
    {
        return (spl_object_hash($socket));
    }

    /**
     * 有连接到达到达
     * @param TCPConnection $client
     * @return void
     */
    protected function onConnect(TCPConnection $client): void
    {

    }

    /**
     * 通过连接获取客户端
     * @param mixed $clientSocket
     * @return TCPConnection|null
     */
    public function getClientBySocket(mixed $clientSocket): TCPConnection|null
    {
        $name = Worker::getNameBySocket($clientSocket);
        return $this->getClientByName($name);
    }

    /**
     * 通过名称获取客户端
     * @param string $name
     * @return TCPConnection|null
     */
    public function getClientByName(string $name): TCPConnection|null
    {
        return $this->clients[$name] ?? null;
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
        unset($this->clients[$client->getHash()]);
        $this->unsubscribeSocket($client->getSocket());
    }

    /**
     * 销毁
     * @return void
     */
    public function destroy(): void
    {
        if ($this->checkRpcService()) {
            socket_close($this->rpcServiceListenSocket);
            try {
                [$type, $addressFull, $addressInfo, $address, $port] = Worker::parseAddress($this->getRpcServiceAddress());
                $type === SocketUnix::class && unlink($address);
            } catch (Exception $exception) {
                Output::printException($exception);
            }
        }
    }

    /**
     * 取消订阅一个连接
     * @param Socket $socket
     * @return void
     */
    protected function unsubscribeSocket(Socket $socket): void
    {
        try {
            $this->publishAsync(Build::new(Constants::EVENT_SOCKET_UNSUBSCRIBE, $socket, $this->name));
        } catch (Throwable $exception) {
            Output::printException($exception);
        }
    }

    /**
     * 关闭一个连接
     * @param TCPConnection $client
     * @return void
     */
    protected function onClose(TCPConnection $client): void
    {

    }

    /**
     * 握手成功
     * @param TCPConnection $client
     * @return void
     */
    protected function onHandshake(TCPConnection $client): void
    {

    }

    /**
     * 处理异常连接
     * @param Socket $socket
     * @return void
     */
    public function expectSocket(Socket $socket): void
    {
        $this->removeClient($this->getClientBySocket($socket));
    }

    /**
     * 切割报文
     * @param TCPConnection $client
     * @return void
     */
    protected function splitMessage(TCPConnection $client): void
    {
        while ($content = $client->getPlaintext()) {
            $this->onMessage($content, $client);
        }
    }

    /**
     * 接收到一个报文
     * @param string        $context
     * @param TCPConnection $client
     * @return void
     */
    protected function onMessage(string $context, TCPConnection $client): void
    {

    }

    /**
     * 发送一个报文
     * @return void
     */
    public function heartbeat(): void
    {

    }

    /**
     * 必须处理事件
     * @param Build $event
     * @return void
     */
    protected function handleEvent(Build $event): void
    {
    }

    /**
     * 等待驱动
     * @param Build|null $event
     * @return void
     */
    protected function publishAwait(Build|null $event = null): void
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
     * 独占模式运行
     * @return void
     */
    private function launchAlone(): void
    {
        ProcessContainer::fork(function () {
            $this->launchCollaborative();
        });
    }

    /**
     * 进程分叉
     * @param int|null $count
     * @return int $count
     */
    public function fork(int|null $count = 1): int
    {
        $kernel       = PRipple::kernel();
        $successCount = 0;
        for ($index = 0; $index < $count; $index++) {
            $processId = $kernel->fork($this);
            if ($processId > 0) {
                $successCount++;
            } elseif ($processId === 0) {
                break;
            }
        }
        return $successCount;
    }

    /**
     * Worker进程分叉前执行
     * @return bool 返回true则允许进程分叉,返回false则禁止进程分叉,禁止进程分叉的worker会在子进程中自动被卸载
     */
    public function forkBefore(): bool
    {
        return $this->allowFork;
    }

    /**
     * 被卸载时执行
     * @param bool $isFork
     * @return void
     */
    public function unload(bool $isFork): void
    {
    }

    /**
     * 进程分叉时执行
     * 默认会取消接管父进程的所有客户端连接
     * @return void
     */
    public function forking(): void
    {
        echo "forking : {$this->name}\n";
        $this->isFork = true;
        // 取消接管父进程的客户端连接
        foreach ($this->getClients() as $client) {
            $this->unsubscribeSocket($client->getSocket());
            unset($this->subscribes[$client->getHash()]);
            unset($this->clients[$client->getHash()]);
        }
    }

    /**
     * 获取客户端列表
     * @return TCPConnection[]
     */
    public function getClients(): array
    {
        return $this->clients ?? [];
    }

    /**
     * 被动分叉会触发器,默认会取消接管父进程的所有客户端连接和监听
     * @return void
     */
    public function forkPassive(): void
    {
        echo "forkPassive : {$this->name}\n";
        $this->isFork = true;
        foreach ($this->getClients() as $client) {
            $this->unsubscribeSocket($client->getSocket());
            unset($this->subscribes[$client->getHash()]);
            unset($this->clients[$client->getHash()]);
        }
        foreach ($this->bindAddressHashMap as $socket) {
            $this->unsubscribeSocket($socket);
        }
        $this->bindAddressHashMap = array_filter($this->bindAddressHashMap, function (Socket $socket) {
            $this->unsubscribeSocket($socket);
            return false;
        });
    }

    /**
     * Worker进程分叉后执行
     * @return void
     */
    public function forkAfter(): void
    {

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
        $this->bindAddressList[$address] = $options;
        return $this;
    }

    /**
     * 订阅一个事件
     * @param string $event
     * @return void
     */
    protected function subscribe(string $event): void
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
    protected function unsubscribe(string $event): void
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
     * worker自带的纤程恢复方法,自动遵循规范处流程理大多数的事件
     * 如果Worker有自己的调度规范,请重写此方法
     * 一般我不建议这么做,如果有新的想法可以发表你的意见
     *
     * @param string     $hash
     * @param mixed|null $data
     * @return bool
     */
    protected function resume(string $hash, mixed $data = null): bool
    {
        if (!$collaborativeFiber = CollaborativeFiberMap::$collaborativeFiberMap[$hash] ?? null) {
            return false;
        }
        try {
            if ($collaborativeFiber->checkIfTerminated()) {
                $collaborativeFiber->destroy();
                return false;
            } elseif ($event = $collaborativeFiber->resumeFiberExecution($data)) {
                if (in_array($event->name, $this->subscribes)) {
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
     * 多路复用模式运行
     * @param int|null $mode
     * @return void
     */
    protected function mode(int|null $mode = Worker::MODE_COLLABORATIVE): void
    {
        $this->mode = $mode;
    }
}
