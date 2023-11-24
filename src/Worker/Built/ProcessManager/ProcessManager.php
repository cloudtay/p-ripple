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

use Core\Output;
use Facade\Process;
use Protocol\Slice;
use Worker\Built\JsonRpc\Attribute\Rpc;
use Worker\Built\JsonRpc\JsonRpc;
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

    public const EVENT_PROCESS_FORK           = 'process.fork';
    public const EVENT_PROCESS_EXIT           = 'process.exit';
    public const EVENT_PROCESS_OBSERVER       = 'process.observer';
    public const EVENT_PROCESS_OBSERVER_COUNT = 'process.observer.count';
    public static string $UNIX_PATH;
    public static string $LOCK_PATH;

    /**
     * 映射 进程=>守护ID
     * @var array
     */
    public array $processObserverHashMap = [];

    /**
     * 映射 守护ID=>守护映射
     * @var TCPConnection[]
     */
    public array $observerHashmap = [];

    /**
     * 守护计数
     * @var array $observerCountMap
     */
    public array $observerCountMap = [];

    /**
     * 进程管理器门面
     * @var string $facadeClass
     */
    public static string $facadeClass = Process::class;

    /**
     * 资源释放
     * @return void
     */
    public function destroy(): void
    {
        foreach ($this->processObserverHashMap as $processId => $observerProcessId) {
            if ($observerProcessId !== posix_getpid()) {
                $this->signal($processId, SIGUSR2);
            }
        }
        foreach ($this->observerHashmap as $processId => $_) {
            ProcessManager::commandToObserver($processId, 'exit');
        }
        parent::destroy();
    }

    /**
     * 发送信号
     * @param int $processId
     * @param int $signalNo
     * @return void
     */
    public function signal(int $processId, int $signalNo): void
    {
        if ($observerId = $this->processObserverHashMap[$processId]) {
            $this->commandToObserver($observerId, 'signal', $processId, $signalNo);
        }
    }

    /**
     * 初始化
     * @return void
     */
    public function initialize(): void
    {
        ProcessManager::$UNIX_PATH = PP_RUNTIME_PATH . '/p_ripple_process_manager.sock';
        ProcessManager::$LOCK_PATH = PP_RUNTIME_PATH . '/p_ripple_process_manager.lock';
        file_exists(ProcessManager::$UNIX_PATH) && unlink(ProcessManager::$UNIX_PATH);
        file_exists(ProcessManager::$LOCK_PATH) && unlink(ProcessManager::$LOCK_PATH);
        $this->bind('unix://' . ProcessManager::$UNIX_PATH);
        $this->protocol(Slice::class);
        parent::initialize();
    }

    /**
     * 守护计数器
     * @param string        $context
     * @param TCPConnection $client
     * @return void
     */
    public function onMessage(string $context, TCPConnection $client): void
    {
        /**
         * @var Build $build
         */
        $build = unserialize($context);
        switch ($build->name) {
            case ProcessManager::EVENT_PROCESS_FORK:
                $observerProcessId                        = intval($build->data['observerProcessId']);
                $processId                                = intval($build->data['processId']);
                $this->processObserverHashMap[$processId] = $observerProcessId;
                $client->setIdentity($processId);
                $client->setName('process.fork');
                Output::info('[Process]', 'new process:', $processId . '=>' . $observerProcessId);
                break;
            case ProcessManager::EVENT_PROCESS_OBSERVER:
                $processId                         = intval($build->data['processId']);
                $this->observerHashmap[$processId] = $client;
                $client->setIdentity($processId);
                $client->setName('process.observer');
                Output::info('[Process]', 'new observer:', strval($processId));
                break;
            case ProcessManager::EVENT_PROCESS_OBSERVER_COUNT:
                $this->guardCounter(intval(
                    $build->data['observerProcessId']),
                    intval($build->data['guardCount'])
                );
                break;
        }
    }

    /**
     * 守护计数
     * @param int $observerProcessId
     * @param int $num
     * @return void
     */
    public function guardCounter(int $observerProcessId, int $num): void
    {
        if (!isset($this->observerCountMap[$observerProcessId])) {
            $this->observerCountMap[$observerProcessId] = 0;
        }
        $this->observerCountMap[$observerProcessId] += $num;
        if ($this->observerCountMap[$observerProcessId] === 0) {
            ProcessManager::commandToObserver($observerProcessId, 'exit');
        }
    }

    /**
     * 客户端断开连接
     * @param TCPConnection $client
     * @return void
     */
    public function onClose(TCPConnection $client): void
    {
        switch ($client->getName()) {
            case ProcessManager::EVENT_PROCESS_FORK:
                $processId         = $client->getIdentity();
                $observerProcessId = $this->processObserverHashMap[$processId];
                $this->guardCounter($observerProcessId, -1);
                unset($this->processObserverHashMap[$processId]);
                break;
            case ProcessManager::EVENT_PROCESS_OBSERVER:
                $processId = $client->getIdentity();
                unset($this->observerHashmap[$processId]);
                break;
        }
    }

    /**
     * 发送指令
     * @param int    $observerProcessId
     * @param string $command
     * @param mixed  ...$arguments
     * @return void
     */
    #[Rpc("向指定进程发送指令")] public function commandToObserver(int $observerProcessId, string $command, mixed ...$arguments): void
    {
        if ($observerProcessId === posix_getpid()) {
            call_user_func([ProcessContainer::class, $command], ...$arguments);
        } elseif ($client = $this->observerHashmap[$observerProcessId]) {
            $this->protocol->send($client, Build::new('process.observer.command', [
                'action'    => $command,
                'arguments' => $arguments
            ], ProcessManager::class)->__toString());
        }
    }
}
