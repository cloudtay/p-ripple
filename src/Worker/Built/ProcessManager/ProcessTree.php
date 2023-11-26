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

namespace Worker\Built\ProcessManager;

use Core\FileSystem\FileException;
use Core\Output;
use Facade\Process;
use Worker\Built\JsonRpc\Attribute\Rpc;
use Worker\Built\JsonRpc\JsonRpc;
use Worker\Built\JsonRpc\JsonRpcBuild;
use Worker\Socket\TCPConnection;
use Worker\Worker;

/**
 * The process manager is a standard Rpc Worker in independent running mode.
 * It provides process management services for the entire kernel and cannot be forked or uninstalled.
 * When passively forking, the Worker should be actively uninstalled and the heartbeat/Socket listening subscription-
 * should be logged out to ensure the normal operation of the process manager.
 */
class ProcessTree extends Worker
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
    #[Rpc('设置守护进程ID')] public function setObserverProcessId(int $processId, int $observerProcessId): void
    {
        $this->processObserverIdMap[$processId] = $observerProcessId;
    }

    /**
     * 设置进程ID
     * @param int           $processId
     * @param TCPConnection $tcpConnection
     * @return void
     */
    #[Rpc('设置进程ID')] public function setProcessId(int $processId, TCPConnection $tcpConnection): void
    {
        $this->setClientName($tcpConnection, "process:{$processId}");
    }

    /**
     * 获取守护进程ID
     * @param int $processId
     * @param int $signal
     * @return bool
     */
    #[Rpc('向指定进程发送信号')] public function signal(int $processId, int $signal): bool
    {
        if ($observerProcessId = $this->processObserverIdMap[$processId] ?? null) {
            if ($observerProcessId === posix_getpid()) {
                return posix_kill($processId, $signal);
            } elseif ($tcpConnection = $this->getClientByName("process:{$observerProcessId}")) {
                echo 'find observer process:' . $observerProcessId . PHP_EOL;
                try {
                    $jsonRpcBuild = (new JsonRpcBuild('anonymous'))
                        ->method('posix_kill')
                        ->params([$processId, $signal]);
                    $this->slice->send($tcpConnection, $jsonRpcBuild->request());
                } catch (FileException $exception) {
                    Output::printException($exception);
                }
            }
        }
        return true;
    }

    /**
     * @return void
     */
    public function forking(): void
    {
        parent::forking();
        $this->forkPassive();
        //TODO: 重构矫正
//        $this->unsubscribeSocket($this->rpcServiceListenSocket);
    }
}
