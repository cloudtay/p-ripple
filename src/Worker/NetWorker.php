<?php
declare(strict_types=1);

namespace Cclilshy\PRipple\Worker;

use Cclilshy\PRipple\Build;
use Cclilshy\PRipple\Protocol\TCPProtocol;
use Cclilshy\PRipple\Service\Client;
use Cclilshy\PRipple\Service\SocketType\SocketInet;
use Cclilshy\PRipple\Service\SocketType\SocketUnix;
use Cclilshy\PRipple\Std\ProtocolStd;
use Cclilshy\PRipple\Worker;
use Exception;
use Socket;

abstract class NetWorker extends Worker
{
    /**
     * 监听的入口套接字
     * @var mixed
     */
    public mixed $listenSocket;
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
    public string $name = NetWorker::class;
    /**
     * 协议
     * @var ProtocolStd
     */
    private ProtocolStd $protocol;
    private string $bindAddress;
    private array $socketServiceOptions = [];

    public function __construct(string $name = NetWorker::class, $protocol = TCPProtocol::class)
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
        $name = NetWorker::getNameBySocket($clientSocket);
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

    abstract public function heartbeat(): void;

    public function protocol(string $protocol = TCPProtocol::class): static
    {
        $this->protocol = new $protocol();
        return $this;
    }

    public function bind(string $address, array|null $options = []): static
    {
        $this->bindAddress = $address;
        if ($options !== null) {
            $this->socketServiceOptions = $options;
        }
        return $this;
    }

    /**
     * 必须处理事件
     * @param Build $event
     * @return void
     */
    protected function handleEvent(Build $event): void
    {
        // TODO: Implement handleEvent() method.
    }

    /**
     * 必须处理套接字
     * @param Socket $socket
     * @return void
     */
    protected function handleSocket(Socket $socket): void
    {
        if ($socket === $this->listenSocket) {
            $this->accept();
        } elseif ($client = $this->getClientBySocket($socket)) {
            if (!$client->verify) {
                if ($handshake = $this->handshake($client)) {
                    $this->onConnect($client);
                } elseif ($handshake === false) {
                    $this->expectSocket($socket);
                }
            } else {
                if ($content = $client->getPlaintext()) {
                    $this->onMessage($content, $client);
                } else {
                    $this->onClose($client);
                    $this->removeClient($client);
                }
            }
        }

    }

    /**
     * 同意一个连接
     */
    public function accept(): bool
    {
        try {
            if ($socket = socket_accept($this->listenSocket)) {
                $this->addSocket($socket);
                return true;
            }
        } catch (Exception $exception) {
            echo $exception->getMessage() . PHP_EOL;
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
        $name = NetWorker::getNameBySocket($socket);
        $this->clientSockets[$name] = $socket;
        $this->clients[$name] = new Client($socket, $this->socketType);
        if ($handshake = $this->handshake($this->clients[$name])) {
            $this->onConnect($this->clients[$name]);
        } elseif ($handshake === false) {
            $this->expectSocket($socket);
        }
        $this->subscribeSocket($socket);
    }

    /**
     * 尝试握手
     * @param Client $client
     * @return bool|null
     */
    public function handshake(Client $client): bool|null
    {
        if ($result = $this->protocol->handshake($client)) {
            $client->handshake($this->protocol);
        }
        return $result;
    }

    abstract public function onConnect(Client $client): void;

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

    abstract public function destroy(): void;

    abstract protected function onMessage(string $context, Client $client): void;

    abstract protected function onClose(Client $client): void;

    protected function initialize(): void
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
            if (!isset($this->bindAddress)) {
                return;
            }
            $type = match (true) {
                str_contains($this->bindAddress, 'unix://') => SocketUnix::class,
                str_contains($this->bindAddress, 'tcp://') => SocketInet::class,
                default => throw new Exception('Invalid address')
            };
            $this->bindAddress = str_replace(['unix://', 'tcp://'], '', $this->bindAddress);
            $addressInfo = explode(':', $this->bindAddress);
            $this->bindAddress = $addressInfo[0];
            $port = (int)($addressInfo[1] ?? 0);
            switch ($type) {
                case SocketInet::class:
                    $this->socketType = SocketInet::class;
                    $this->listenSocket = SocketInet::create($this->bindAddress, $port, SOCK_STREAM, $this->socketServiceOptions);
                    break;
                case SocketUnix::class:
                    $this->socketType = SocketInet::class;
                    $this->listenSocket = SocketUnix::create($this->bindAddress);
                    break;
                default:
                    return;
            }
            $this->subscribeSocket($this->listenSocket);
        } catch (Exception $e) {
            echo $e->getMessage() . PHP_EOL;
        }
    }
}
