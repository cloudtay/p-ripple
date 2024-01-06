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

namespace Cclilshy\PRipple\Worker;

use Cclilshy\PRipple\Core\Net\Socket;
use Cclilshy\PRipple\Core\Net\Stream;
use Cclilshy\PRipple\Core\Output;
use Cclilshy\PRipple\Core\Standard\ProtocolStd;
use Cclilshy\PRipple\Facade\Kernel;
use Cclilshy\PRipple\Protocol\TCPProtocol;
use Cclilshy\PRipple\Worker\Built\JsonRPC\JsonRPC;
use Cclilshy\PRipple\Worker\Built\JsonRPC\Server;
use Cclilshy\PRipple\Worker\Built\ProcessManager;
use Cclilshy\PRipple\Worker\Socket\SocketUnix;
use Cclilshy\PRipple\Worker\Socket\TCPConnection;
use Exception;
use Throwable;
use function array_filter;
use function array_values;
use function class_uses;
use function file_exists;
use function in_array;
use function socket_set_option;
use function str_replace;
use function unlink;

/**
 * @class WorkerNet 网络工作器
 */
class WorkerNet extends Worker
{
    public const string HOOK_ON_CONNECT   = 'system.worker.net.connect';
    public const string HOOK_ON_HANDSHAKE = 'system.worker.net.handshake';
    public const string HOOK_ON_MESSAGE   = 'system.worker.net.message';
    public const string HOOK_ON_CLOSE     = 'system.worker.net.close';

    /**
     * The type of service connection
     * @var string
     */
    public string $socketType;

    /**
     * ProtocolStd
     * @var ProtocolStd
     */
    public ProtocolStd $protocol;

    /**
     * A list of listening addresses: [] = $address=>$options
     * @var array $listenAddressList
     */
    public array $listenAddressList = [];

    /**
     * Address-to-socket hash table: [] = $address=>$TCPConnection
     * @var TCPConnection[] $listenSocketAddressMap
     */
    public array $listenSocketAddressMap = [];

    /**
     * A list of client names
     * @var TCPConnection[] $TCPConnectionNameMap
     */
    public array $TCPConnectionNameMap = [];

    /**
     * RPC listener address
     * @var string $RPCServiceListenAddress
     */
    public string $RPCServiceListenAddress;

    /**
     * @param string                  $name
     * @param ProtocolStd|string|null $protocol
     */
    public function __construct(string $name, ProtocolStd|string|null $protocol = TCPProtocol::class)
    {
        parent::__construct($name);

        $this->protocol($protocol);

        $this->hook(
            WorkerNet::HOOK_ON_CONNECT,
            fn(TCPConnection $TCPConnection) => $this->onConnect($TCPConnection)
        );

        $this->hook(
            WorkerNet::HOOK_ON_HANDSHAKE,
            fn(TCPConnection $TCPConnection) => $this->onHandshake($TCPConnection)
        );

        $this->hook(
            WorkerNet::HOOK_ON_MESSAGE,
            fn(string $context, TCPConnection $TCPConnection) => $this->onMessage($context, $TCPConnection)
        );

        $this->hook(
            WorkerNet::HOOK_ON_CLOSE,
            fn(TCPConnection $TCPConnection) => $this->onClose($TCPConnection)
        );
    }

    /**
     * @param Stream $stream
     * @return void
     */
    final protected function handleStreamRead(Stream $stream): void
    {
        if ($stream instanceof TCPConnection) {
            try {
                $this->handleTCPConnection($stream, Kernel::EVENT_STREAM_READ);
            } catch (Throwable $exception) {
                Output::printException($exception);
                $this->removeTCPConnection($stream);
            }
        } else {
            parent::handleStreamRead($stream);
        }
    }

    /**
     * Binding Protocol
     * @param ProtocolStd|string|null $protocol
     * @return $this
     */
    final public function protocol(ProtocolStd|string|null $protocol = TCPProtocol::class): static
    {
        if ($protocol instanceof ProtocolStd) {
            $this->protocol = $protocol;
        } else {
            $this->protocol = new $protocol();
        }
        return $this;
    }


    /**
     * Listening address
     * @param string     $address
     * @param array|null $options
     * @return $this
     */
    final public function bind(string $address, array|null $options = []): static
    {
        $this->listenAddressList[$address] = $options;
        return $this;
    }


    /**
     * @param string     $address
     * @param array|null $options
     * @return TCPConnection|null
     */
    final public function connect(string $address, array|null $options = []): TCPConnection|null
    {
        try {
            [$type, $host, $port] = Socket::parseAddress($address);
            if (!$connect = stream_socket_client($address)) {
                return null;
            }
            $TCPConnection = new TCPConnection($connect, clone $this->protocol);
            foreach ($options as $option => $value) {
                socket_set_option($TCPConnection->socket, SOL_SOCKET, $option, $value);
            }
            $this->addTCPConnection($TCPConnection);
            return $TCPConnection;
        } catch (Exception $exception) {
            Output::printException($exception);
            return null;
        }
    }

    /**
     * Listen to the service address
     * @return void
     */
    final public function listen(): void
    {
        try {
            foreach ($this->listenAddressList as $address => $options) {
                [$type, $host, $port] = Socket::parseAddress($address);

                $type === Socket::TYPE_UNIX
                && file_exists($host)
                && unlink($host);

                $server                                 = stream_socket_server($address);
                $TCPConnection                          = new TCPConnection($server, clone $this->protocol);
                $this->listenSocketAddressMap[$address] = $TCPConnection;
                $this->streams[$TCPConnection->id]      = $TCPConnection;
                $this->subscribeStream($TCPConnection);
                if (!$this->isFork()) {
                    Output::info('[listen]', $address);
                } else {
                    \Cclilshy\PRipple\Utils\JsonRPC::call([ProcessManager::class, 'outputInfo'], '[listen]', $address);
                }
            }
        } catch (Exception $exception) {
            Output::printException($exception);
        }
    }


    /**
     * Handle client requests
     * @param TCPConnection $TCPConnection
     * @param string        $event
     * @return void
     */
    final public function handleTCPConnection(TCPConnection $TCPConnection, string $event): void
    {
        if (in_array($TCPConnection, array_values($this->listenSocketAddressMap), true)) {
            if ($clientStream = $TCPConnection->accept()) {
                try {
                    $this->addTCPConnection(new TCPConnection($clientStream, clone $this->protocol));
                } catch (Exception $exception) {
                    Output::printException($exception);
                }
            }
            return;
        } elseif (!$TCPConnection->readToBuffer()) {
            if ($TCPConnection->isHandshake()) {
                $this->splitMessage($TCPConnection);
            } elseif ($TCPConnection->protocol->handshake($TCPConnection)) {
                $TCPConnection->handshake();
                $this->callWorkerEvent(WorkerNet::HOOK_ON_HANDSHAKE, $TCPConnection);
                $this->splitMessage($TCPConnection);
            }
            $this->removeTCPConnection($TCPConnection);
            return;
        } elseif (!$TCPConnection->isHandshake()) {
            if ($handshake = $TCPConnection->protocol->handshake($TCPConnection)) {
                $TCPConnection->handshake();
                $this->callWorkerEvent(WorkerNet::HOOK_ON_HANDSHAKE, $TCPConnection);
                $this->splitMessage($TCPConnection);
            } elseif ($handshake === false) {
                $this->removeTCPConnection($TCPConnection);
            }
            return;
        }
        $this->splitMessage($TCPConnection);
    }

    /**
     * Set the client name
     * @param TCPConnection $TCPConnection
     * @param string        $name
     * @return void
     */
    final public function setClientName(TCPConnection $TCPConnection, string $name): void
    {
        unset($this->TCPConnectionNameMap[$TCPConnection->getName()]);
        $TCPConnection->setName($name);
        $this->TCPConnectionNameMap[$name] = $TCPConnection;
    }

    /**
     * Get the connection by the client name
     * @param string $name
     * @return TCPConnection|null
     */
    final public function getClientByName(string $name): TCPConnection|null
    {
        return $this->TCPConnectionNameMap[$name] ?? null;
    }


    /**
     * Whether to use the RPC service
     * @return bool
     */
    final public function checkRPCService(): bool
    {
        return in_array(JsonRPC::class, class_uses($this), true);
    }


    /**
     * Get the client by the connection name
     * @param string $name
     * @return TCPConnection|null
     */
    final public function getClientSocketByName(string $name): TCPConnection|null
    {
        return $this->TCPConnectionNameMap[$name] ?? null;
    }

    /**
     * Get the list of clients
     * @return array
     */
    final public function getClients(): array
    {
        return array_filter($this->streams, function (TCPConnection $TCPConnection) {
            return !in_array($TCPConnection, array_values($this->listenSocketAddressMap), true);
        });
    }

    /**
     * Cut packets
     * @param TCPConnection $TCPConnection
     * @return void
     */
    final public function splitMessage(TCPConnection $TCPConnection): void
    {
        foreach ($TCPConnection->generatePayload() as $context) {
            if ($context === null) {
                break;
            } elseif ($context === false) {
                $this->removeTCPConnection($TCPConnection);
                break;
            }
            $this->callWorkerEvent(WorkerNet::HOOK_ON_MESSAGE, $context, $TCPConnection);
        }
    }

    /**
     * Workers are born in parallel
     * @return int $count
     */
    final public function fork(): int
    {
        if ($this->checkRPCService() || $this instanceof Server) {
            return -1;
        }
        return parent::fork();
    }

    /**
     * @return void
     */
    public function forking(): void
    {
        foreach ($this->getClients() as $TCPConnection) {
            $this->removeTCPConnection($TCPConnection);
        }
        parent::forking();
    }

    /**
     * @return void
     */
    public function forkPassive(): void
    {
        foreach ($this->getClients() as $TCPConnection) {
            $this->removeTCPConnection($TCPConnection);
        }
        foreach ($this->listenAddressList as $address => $option) {
            $TCPConnection = $this->listenSocketAddressMap[$address];
            unset($this->listenSocketAddressMap[$address]);
            unset($this->listenAddressList[$address]);
            $this->removeTCPConnection($TCPConnection);
        }
        parent::forkPassive();
    }

    /**
     * Add a client
     * @param TCPConnection $TCPConnection
     * @return void
     */
    final public function addTCPConnection(TCPConnection $TCPConnection): void
    {
        $this->callWorkerEvent(WorkerNet::HOOK_ON_CONNECT, $TCPConnection);
        $this->addStream($TCPConnection);
    }

    /**
     * Remove a TCP connection object
     * @param TCPConnection $TCPConnection
     * @return void
     */
    final public function removeTCPConnection(TCPConnection $TCPConnection): void
    {
        $TCPConnection->deprecated = true;
        $TCPConnection->destroy();
        $this->callWorkerEvent(WorkerNet::HOOK_ON_CLOSE, $TCPConnection);
        unset($this->TCPConnectionNameMap[$TCPConnection->getName()]);
        $this->removeStream($TCPConnection);
    }

    /**
     * Obtain the RPC service address
     * @return string
     */
    final public function getRPCServiceAddress(): string
    {
        if (!isset($this->RPCServiceListenAddress)) {
            $name                          = strtolower(str_replace(['\\', '/'], '_', $this->name));
            $path                          = 'unix://' . PP_RUNTIME_PATH . FS . "{$name}.rpc.socket";
            $this->RPCServiceListenAddress = $path;
        }
        return $this->RPCServiceListenAddress;
    }

    /**
     * There is a connection to Dada
     * @param TCPConnection $TCPConnection
     * @return void
     * #abstract
     */
    protected function onConnect(TCPConnection $TCPConnection): void
    {
    }

    /**
     * Close a connection
     * @param TCPConnection $TCPConnection
     * @return void
     * #abstract
     */
    protected function onClose(TCPConnection $TCPConnection): void
    {
    }

    /**
     * The handshake was successful
     * @param TCPConnection $TCPConnection
     * @return void
     * #abstract
     */
    protected function onHandshake(TCPConnection $TCPConnection): void
    {
    }

    /**
     * A packet is received
     * @param string        $context
     * @param TCPConnection $TCPConnection
     * @return void
     * #abstract
     */
    protected function onMessage(string $context, TCPConnection $TCPConnection): void
    {
    }

    /**
     * @return void
     */
    public function destroy(): void
    {
        foreach ($this->getClients() as $client) {
            $this->removeTCPConnection($client);
        }
        foreach ($this->listenAddressList as $addressOriginal => $options) {
            if ($TCPConnection = $this->listenSocketAddressMap[$addressOriginal] ?? null) {
                try {
                    [$type, $address, $port] = Socket::parseAddress($addressOriginal);
                } catch (Exception $exception) {
                    Output::printException($exception);
                    continue;
                }
                $this->removeTCPConnection($TCPConnection);
                if ($type === SocketUnix::class && ($this->isRoot() || !$this->isFork())) {
                    unlink($address);
                }
            }
        }
        parent::destroy();
    }
}
