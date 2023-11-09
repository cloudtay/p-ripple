<?php
declare(strict_types=1);

namespace Worker;

use Exception;
use PRipple;
use Protocol\TCPProtocol;
use Socket;
use Std\ProtocolStd;
use Worker\NetWorker\Client;
use Worker\NetWorker\SocketType\SocketInet;
use Worker\NetWorker\SocketType\SocketUnix;

/**
 *
 */
class NetworkWorkerInterface extends WorkerInterface
{
    /**
     * 客户端套接字列表
     * @var array
     */
    public array $clientSockets = [];
    /**
     * 客户端列表
     * @var Client []
     */
    public array $clients;
    /**
     * 身份标识映射表
     * @var array
     */
    public array $identityHashMap;
    /**
     * 套接字类型
     * @var string
     */
    public string $socketType;
    public string $name = NetworkWorkerInterface::class;

    /**
     * 协议
     * @var ProtocolStd
     */
    public ProtocolStd $protocol;
    /**
     * @var string[]
     */
    private array $bindAddressList = [];
    private array $bindAddressHashMap = [];
    private array $socketServiceOptions = [];

    /**
     * @param string $name
     * @param string $protocol
     */
    public function __construct(string $name = NetworkWorkerInterface::class, string $protocol = TCPProtocol::class)
    {
        parent::__construct($name);
        $this->protocol = new $protocol();
    }

    /**
     * 设置套接字身份 当一个客户端被赋予身份后 将在ServerSocket对象中建立索引 可以快速查找
     * @param string $name
     * @param string $identity
     * @return bool
     */
    public function setIdentityByName(string $name, string $identity): bool
    {
        if ($client = $this->getClientSocketByName($name)) {
            $client->setIdentity($identity);
            $this->identityHashMap[$identity] = $client;
            return true;
        }
        return false;
    }

    /**
     * 通过套接字名称获取客户端
     * @param string $name
     * @return mixed
     */
    public function getClientSocketByName(string $name): mixed
    {
        return $this->clientSockets[$name] ?? null;
    }

    /**
     * 设置套接字身份 当一个客户端被赋予身份后 将在ServerSocket对象中建立索引 可以快速查找
     * @param mixed $socket
     * @param string $identity
     * @return mixed
     */
    public function setIdentityBySocket(mixed $socket, string $identity): bool
    {
        if ($client = $this->getClientBySocket($socket)) {
            $client->setIdentity($identity);
            $this->identityHashMap[$identity] = $client;
            return true;
        }
        return false;
    }

    /**
     * 通过套接字获取客户端
     * @param mixed $clientSocket
     * @return Client|null
     */
    public function getClientBySocket(mixed $clientSocket): Client|null
    {
        $name = NetworkWorkerInterface::getNameBySocket($clientSocket);
        return $this->getClientByName($name);
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
     * 通过名称获取客户端
     * @param string $name
     * @return Client|null
     */
    public function getClientByName(string $name): Client|null
    {
        return $this->clients[$name] ?? null;
    }

    /**
     * 通过身份标识获取客户端
     * @param string $name
     * @return Client|null
     */
    public function getClientByIdentity(string $name): Client|null
    {
        return $this->identityHashMap[$name] ?? null;
    }

    /**
     * 获取所有客户端套接字列表
     * @return array|null
     */
    public function getClientSockets(): array|null
    {
        return $this->clientSockets ?? null;
    }

    /**
     * 获取客户端列表
     * @return Client[]
     */
    public function getClients(): array
    {
        return $this->clients ?? [];
    }

    /**
     * 绑定协议
     * @param string $protocol
     * @return $this
     */
    public function protocol(string $protocol = TCPProtocol::class): static
    {
        $this->protocol = new $protocol();
        return $this;
    }

    /**
     * 绑定地址
     * @param string $address
     * @param array|null $options
     * @return $this
     */
    public function bind(string $address, array|null $options = []): static
    {
        $this->bindAddressList[] = $address;
        $this->socketServiceOptions = $options;
        return $this;
    }

    /**
     * 必须处理事件
     * @param Build $event
     * @return void
     */
    public function handleEvent(Build $event): void
    {
        // TODO: Implement handleEvent() method.
    }

    /**
     * 必须处理套接字
     * @param Socket $socket
     * @return void
     */
    public function handleSocket(Socket $socket): void
    {
        if (in_array($socket, array_values($this->bindAddressHashMap), true)) {
            $this->accept($socket);
            return;
        }
        if (!$client = $this->getClientBySocket($socket)) {
            return;
        }
        if (!$client->verify) {
            if ($handshake = $this->protocol->handshake($client)) {
                $client->handshake($this->protocol);
                $this->onHandshake($client);
            } elseif ($handshake === false) {
                $this->expectSocket($socket);
                return;
            } else {
                return;
            }
        }

        if (!$context = $client->read(0, $_)) {
            $this->onClose($client);
            $this->removeClient($client);
            $client->deprecated = true;
            return;
        }
        $client->cache .= $context;
        $this->splitMessage($client);
    }

    /**
     * 同意一个连接
     */
    public function accept(Socket $listenSocket): bool
    {
        try {
            if ($socket = socket_accept($listenSocket)) {
                socket_set_option($socket, SOL_SOCKET, SO_KEEPALIVE, 1);
                if ($this->socketType === SocketInet::class) {
                    socket_set_option($socket, SOL_TCP, TCP_NODELAY, 1);
                }
                $this->addSocket($socket);
                return true;
            }
        } catch (Exception $exception) {
            PRipple::printExpect($exception);
        }
        return false;
    }

    /**
     * 添加一个客户端
     * @param Socket $socket
     * @return void
     */
    public function addSocket(Socket $socket): void
    {
        $name = NetworkWorkerInterface::getNameBySocket($socket);
        $this->clientSockets[$name] = $socket;
        $this->clients[$name] = new Client($socket, $this->socketType);
        $this->clients[$name]->setNoBlock();
        $this->onConnect($this->clients[$name]);
        $this->subscribeSocket($socket);
    }

    /**
     * 有连接到达到达
     * @param Client $client
     * @return void
     */
    public function onConnect(Client $client): void
    {

    }

    /**
     * @param Client $client
     * @return void
     */
    public function onHandshake(Client $client): void
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
     * 移除某个客户端
     * @param Client $client
     * @return void
     */
    public function removeClient(Client $client): void
    {
        $client->destroy();
        unset($this->clientSockets[$client->getHash()]);
        unset($this->clients[$client->getHash()]);
        $this->unsubscribeSocket($client->getSocket());
    }

    /**
     * @return void
     */
    public function destroy(): void
    {

    }

    /**
     * @param Client $client
     * @return void
     */
    public function splitMessage(Client $client): void
    {
        // 默认通过协议切割
        while ($content = $client->getPlaintext()) {
            $this->onMessage($content, $client);
        }
    }

    /**
     * @param string $context
     * @param Client $client
     * @return void
     */
    public function onMessage(string $context, Client $client): void
    {

    }

    /**
     * @param Client $client
     * @return void
     */
    public function onClose(Client $client): void
    {

    }

    /**
     * @return void
     */
    public function heartbeat(): void
    {

    }

    /**
     * @return void
     */
    public function initialize(): void
    {
        $this->listen();
    }

    /**
     * 创建监听
     * @return void
     */
    public function listen(): void
    {
        try {
            while ($addressFull = array_shift($this->bindAddressList)) {
                PRipple::info("    |_ ", $addressFull);
                $type = match (true) {
                    str_contains($addressFull, 'unix://') => SocketUnix::class,
                    str_contains($addressFull, 'tcp://') => SocketInet::class,
                    default => throw new Exception('Invalid address')
                };
                $addressFull = str_replace(['unix://', 'tcp://'], '', $addressFull);
                $addressInfo = explode(':', $addressFull);
                $address = $addressInfo[0];
                $port = intval(($addressInfo[1] ?? 0));
                switch ($type) {
                    case SocketInet::class:
                        $this->socketType = SocketInet::class;
                        $listenSocket = SocketInet::create($address, $port, SOCK_STREAM, $this->socketServiceOptions);
                        break;
                    case SocketUnix::class:
                        $this->socketType = SocketInet::class;
                        $listenSocket = SocketUnix::create($address);
                        break;
                    default:
                        return;
                }
                $this->bindAddressHashMap[$addressFull] = $listenSocket;
                $this->subscribeSocket($listenSocket);
            }
        } catch (Exception $exception) {
            PRipple::printExpect($exception);
        }
    }
}
