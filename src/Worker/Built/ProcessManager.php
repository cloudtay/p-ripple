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

namespace Worker\Built;

use Core\FileSystem\FileException;
use Core\Output;
use Exception;
use Facade\JsonRpc;
use Facade\Process;
use Protocol\Slice;
use Worker\Built\JsonRpc\Attribute\RPC;
use Worker\Prop\Build;
use Worker\Socket\TCPConnection;
use Worker\Worker;

/**
 * The process manager is a standard Rpc Worker in independent running mode.
 * It provides process management services for the entire kernel and cannot be forked or uninstalled.
 * When passively forking, the Worker should be actively uninstalled and the heartbeat/Socket listening subscription-
 * should be logged out to ensure the normal operation of the process manager.
 */
class ProcessManager extends Worker
{
    /**
     * 映射进程ID守护ID
     * @var array
     */
    public array $processObserverIdMap = [];

    /**
     * 进程管理器门面
     * @var string $facadeClass
     */
    public static string $facadeClass = Process::class;

    /**
     * 子进程ID列表
     * @var array $childrenProcessIds
     */
    public array $childrenProcessIds = [];

    /**
     * 设置守护进程ID
     * @param int $processId
     * @param int $observerProcessId
     * @return void
     */
    #[RPC('设置守护进程ID')] public function setObserverProcessId(int $processId, int $observerProcessId): void
    {
        $this->processObserverIdMap[$processId] = $observerProcessId;
        Output::info('Process running: ', $processId . ' [Guard:' . $observerProcessId . ']');
    }

    /**
     * 设置进程ID
     * @param int           $processId
     * @param TCPConnection $tcpConnection
     * @return array
     * @throws Exception
     */
    #[RPC('设置进程ID')] public function setProcessId(int $processId, TCPConnection $tcpConnection): array
    {
        $this->setClientName($tcpConnection, "process:{$processId}");
        $rpcServiceList = [];
        foreach (JsonRpc::getInstance()->rpcServiceAddressList as $name => $info) {
            $rpcServiceList[] = [
                'name'    => $name,
                'address' => $info['address'],
                'type'    => $info['type']
            ];
        }
        return $rpcServiceList;
    }

    /**
     * 获取守护进程ID
     * @param int $processId
     * @param int $signal
     * @return bool
     */
    #[RPC('向指定进程发送信号')] public function signal(int $processId, int $signal): bool
    {
        if ($observerProcessId = $this->processObserverIdMap[$processId] ?? null) {
            if ($observerProcessId === posix_getpid()) {
                return posix_kill($processId, $signal);
            } elseif ($tcpConnection = $this->getClientByName("process:{$observerProcessId}")) {
                try {
                    $this->slice->send($tcpConnection, json_encode([
                        'version' => '2.0',
                        'method'  => 'posix_kill',
                        'params'  => [$processId, $signal]
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                } catch (FileException $exception) {
                    Output::printException($exception);
                }
            }
        }
        return true;
    }

    /**
     * 关闭进程
     * @param int $processId
     * @return bool
     */
    #[RPC('关闭进程')] public function kill(int $processId): bool
    {
        $result = $this->signal($processId, SIGUSR2);
        unset($this->processObserverIdMap[$processId]);
        return $result;
    }

    /**
     * 进程退出
     * @param int $processId
     * @return void
     */
    #[RPC('进程退出')] public function isDie(int $processId): void
    {
        unset($this->processObserverIdMap[$processId]);
        Output::info('Process:', 'Exit:' . $processId);
    }

    /**
     * RPC服务上线
     * @param string        $name
     * @param string        $address
     * @param string        $type
     * @param TCPConnection $connection
     * @return void
     */
    #[RPC('RPC服务上线')] public function registerRpcService(string $name, string $address, string $type, TCPConnection $connection): void
    {
        try {
            $this->publishAsync(Build::new('rpcServiceOnline', [
                'name'    => $name,
                'address' => $address,
                'type'    => $type
            ], $this->name));
        } catch (Exception $exception) {
            Output::printException($exception);
        }
        foreach ($this->getClients() as $client) {
            if ($client !== $connection) {
                try {
                    $this->slice->send($client, json_encode([
                        'version' => '2.0',
                        'method'  => 'noticeRpcServiceOnline',
                        'params'  => [$name, $address, $type]
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                } catch (FileException $exception) {
                    Output::printException($exception);
                }
            }
        }
    }

    #[RPC('子进程输出')] private function outputInfo(...$arguments): void
    {
        array_pop($arguments);
        call_user_func_array([Output::class, 'info'], $arguments);
    }

    /**
     * 注册信号处理器
     * @return void
     */
    public function forkPassive(): void
    {
        parent::forkPassive();
        $this->registerSignalHandler();
        $this->childrenProcessIds = [];
    }

    /**
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->protocol(Slice::class);
        try {
            $this->bind($this->getRpcServiceAddress());
        } catch (Exception $exception) {
            Output::printException($exception);
        }
    }

    /**
     * @return void
     */
    public function registerSignalHandler(): void
    {
        pcntl_async_signals(true);
        pcntl_signal(SIGCHLD, function () {
            while (($childrenProcessId = pcntl_waitpid(-1, $status, WNOHANG)) > 0) {
                if ($this->isFork()) {
                    JsonRpc::call([ProcessManager::class, 'isDie'], $childrenProcessId);
                } else {
                    $this->isDie($childrenProcessId);
                }
                unset($this->childrenProcessIds[array_search($childrenProcessId, $this->childrenProcessIds)]);
            }
        });
        pcntl_signal(SIGINT, [$this, 'processSignalHandler']);
        pcntl_signal(SIGTERM, [$this, 'processSignalHandler']);
        pcntl_signal(SIGQUIT, [$this, 'processSignalHandler']);
        pcntl_signal(SIGUSR2, [$this, 'processSignalHandler']);
    }

    /**
     * @return void
     */
    public function processSignalHandler(): void
    {
        if (!$this->isFork()) {
            Output::info('Process:', 'Exit:' . posix_getpid());
        }
        foreach ($this->childrenProcessIds as $childrenProcessId) {
            $this->signal($childrenProcessId, SIGUSR2);
        }
        if ($this->isFork()) {
            exit(0);
        }
    }

    /**
     * @param TCPConnection $client
     * @return void
     */
    public function onConnect(TCPConnection $client): void
    {
        $client->handshake($this->protocol);
    }

    /**
     * @param TCPConnection $client
     * @return void
     */
    public function onClose(TCPConnection $client): void
    {
        // TODO: Implement onClose() method.
    }

    /**
     * @param TCPConnection $client
     * @return void
     */
    public function onHandshake(TCPConnection $client): void
    {
        // TODO: Implement onHandshake() method.
    }

    /**
     * @param string        $context
     * @param TCPConnection $client
     * @return void
     */
    public function onMessage(string $context, TCPConnection $client): void
    {
        $jsonRequest = json_decode($context);
        if (isset($jsonRequest->method)) {
            if (method_exists($this, $jsonRequest->method)) {
                $jsonRequest->params[] = $client;
                $result                = call_user_func_array([$this, $jsonRequest->method], $jsonRequest->params ?? []);
                try {
                    $this->slice->send($client, json_encode([
                        'version' => '2.0',
                        'result'  => $result,
                        'id'      => $jsonRequest->id ?? null
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                } catch (FileException $exception) {
                    Output::printException($exception);
                }
            }
        }
    }

    /**
     * @return void
     */
    public function heartbeat(): void
    {
        // TODO: Implement heartbeat() method.
    }

    /**
     * @param Build $event
     * @return void
     */
    public function handleEvent(Build $event): void
    {
        // TODO: Implement handleEvent() method.
    }
}
