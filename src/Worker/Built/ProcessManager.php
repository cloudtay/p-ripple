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
use Facade\Process;
use Socket;
use Worker\Built\JsonRpc\Attribute\RPC;
use Worker\Built\JsonRpc\JsonRpc;
use Worker\Built\JsonRpc\JsonRpcClient;
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
    use JsonRpc;

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
     * @return void
     */
    #[RPC('设置进程ID')] public function setProcessId(int $processId, TCPConnection $tcpConnection): void
    {
        foreach (JsonRpcClient::getInstance()->rpcServices as $rpcService) {
            try {
                $this->slice->send($tcpConnection, json_encode([
                    'version' => '2.0',
                    'method'  => 'rpcServiceIsOnline',
                    'params'  => [$rpcService->name]
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            } catch (FileException $exception) {
                Output::printException($exception);
            }
        }
        $this->setClientName($tcpConnection, "process:{$processId}");
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
     * @return void
     */
    public function forkPassive(): void
    {
        parent::forkPassive();
        $this->registerSignalHandler();
        $this->rpcService->listenSocketHashMap = array_filter($this->rpcService->listenSocketHashMap, function (Socket $socket) {
            $this->unsubscribeSocket($socket);
            socket_close($socket);
            return false;
        });
    }

    /**
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->registerSignalHandler();
    }

    /**
     * @return void
     */
    public function registerSignalHandler(): void
    {
        pcntl_signal(SIGCHLD, function () {
            while (($childrenProcessId = pcntl_waitpid(-1, $status, WNOHANG)) > 0) {
                if ($this->isFork()) {
                    JsonRpcClient::getInstance()->call(ProcessManager::class, 'isDie', $childrenProcessId);
                } else {
                    $this->isDie($childrenProcessId);
                }
                unset($this->childrenProcessIds[array_search($childrenProcessId, $this->childrenProcessIds)]);
            }
        });
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

    public function onMessage(string $context, TCPConnection $client): void
    {
        // TODO: Implement onMessage() method.
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
