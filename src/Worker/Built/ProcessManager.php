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


namespace Cclilshy\PRipple\Worker\Built;

use Cclilshy\PRipple\Core\Event\Event;
use Cclilshy\PRipple\Core\Map\EventMap;
use Cclilshy\PRipple\Core\Map\WorkerMap;
use Cclilshy\PRipple\Core\Output;
use Cclilshy\PRipple\Core\Standard\WorkerInterface;
use Cclilshy\PRipple\Facade\Process;
use Cclilshy\PRipple\Facade\RPC;
use Cclilshy\PRipple\Filesystem\Exception\FileException;
use Cclilshy\PRipple\Protocol\Slice;
use Cclilshy\PRipple\Utils\JsonRPC;
use Cclilshy\PRipple\Worker\Built\JsonRPC\Attribute\RPCMethod;
use Cclilshy\PRipple\Worker\Built\JsonRPC\Exception\RPCException;
use Cclilshy\PRipple\Worker\Built\JsonRPC\Publisher;
use Cclilshy\PRipple\Worker\Built\JsonRPC\Server;
use Exception;
use Revolt\EventLoop;
use function array_pop;
use function array_search;
use function call_user_func_array;
use function json_encode;
use function pcntl_waitpid;
use function posix_getpid;
use function posix_kill;
use const SIGCHLD;
use const SIGINT;
use const SIGQUIT;
use const SIGTERM;
use const SIGUSR2;

/**
 * The process manager is a standard Rpc Worker in independent running mode.
 * It provides process management services for the entire kernel and cannot be forked or uninstalled.
 * When passively forking, the Worker should be actively uninstalled and the heartbeat/Socket listening subscription-
 * should be logged out to ensure the normal operation of the process manager.
 */
final class ProcessManager extends BuiltRPC implements WorkerInterface
{
    /**
     * 进程管理器门面
     * @var string $facadeClass
     */
    public static string $facadeClass = Process::class;
    /**
     * 映射进程ID守护ID
     * @var array
     */
    public array $processObserverIdMap = [];
    /**
     * 子进程ID列表
     * @var array $childrenProcessIds
     */
    public array $childrenProcessIds = [];

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
    public function registerSignalHandler(): void
    {
        try {
            EventLoop::onSignal(SIGCHLD, function () {
                while (($childrenProcessId = pcntl_waitpid(-1, $status, WNOHANG)) > 0) {
                    if ($this->isFork()) {
                        try {
                            JsonRPC::call([ProcessManager::class, 'isDie'], $childrenProcessId);
                        } catch (RPCException $exception) {
                            Output::error($exception);
                        }
                    } else {
                        $this->isDie($childrenProcessId);
                    }
                    unset($this->childrenProcessIds[array_search($childrenProcessId, $this->childrenProcessIds)]);
                }
            });
        } catch (EventLoop\UnsupportedFeatureException $exception) {
            Output::error($exception);
        }
        if ($this->isFork()) {
            try {
                EventLoop::onSignal(SIGINT, fn() => $this->processSignalHandler());
                EventLoop::onSignal(SIGTERM, fn() => $this->processSignalHandler());
                EventLoop::onSignal(SIGQUIT, fn() => $this->processSignalHandler());
                EventLoop::onSignal(SIGUSR2, fn() => $this->processSignalHandler());
            } catch (EventLoop\UnsupportedFeatureException $exception) {
                Output::error($exception);
                exit(0);
            }
        }
    }

    /**
     * 进程退出
     * @param int $processId
     * @return void
     */
    #[RPCMethod('进程退出')] public function isDie(int $processId): void
    {
        unset($this->processObserverIdMap[$processId]);
        Output::info('process:', 'exit:' . $processId);
    }

    /**
     * @return void
     */
    public function initialize(): void
    {
        $this->registerSignalHandler();
        $this->protocol(Slice::class);
        try {
            $this->bind($this->getRPCServiceAddress(), [SO_REUSEADDR => 1, SO_REUSEPORT => 1]);
        } catch (Exception $exception) {
            Output::error($exception);
        }
        parent::initialize();
    }

    /**
     * @return void
     */
    public function processSignalHandler(): void
    {
        if (!$this->isFork()) {
            Output::info('process:', 'exit:' . posix_getpid());
        }
        foreach ($this->childrenProcessIds as $childrenProcessId) {
            $this->signal($childrenProcessId, SIGUSR2);
        }
        if ($this->isFork()) {
            foreach (WorkerMap::$workerMap as $worker) {
                if ($worker->name !== $this->name) {
                    $worker->destroy();
                }
            }
            exit(0);
        } else {
            foreach ($this->childrenProcessIds as $processId) {
                pcntl_waitpid($processId, $status);
            }
            foreach (WorkerMap::$workerMap as $worker) {
                $worker->destroy();
            }
        }
    }

    /**
     * 获取守护进程ID
     * @param int $processId
     * @param int $signal
     * @return bool
     */
    #[RPCMethod('向指定进程发送信号')] public function signal(int $processId, int $signal): bool
    {
        if ($observerProcessId = $this->processObserverIdMap[$processId] ?? null) {
            if ($observerProcessId === posix_getpid()) {
                return posix_kill($processId, $signal);
            } elseif ($TCPConnection = $this->getClientByName("process:{$observerProcessId}")) {
                try {
                    $this->slice->send($TCPConnection, json_encode([
                        'version' => '2.0',
                        'method'  => 'posix_kill',
                        'params'  => [$processId, $signal]
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                } catch (\Cclilshy\PRipple\Core\Net\Exception|FileException $exception) {
                    Output::error($exception);
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * 设置守护进程ID
     * @param int $processId
     * @param int $observerProcessId
     * @return void
     */
    #[RPCMethod('设置守护进程ID')] public function setObserverProcessId(int $processId, int $observerProcessId): void
    {
        $this->processObserverIdMap[$processId] = $observerProcessId;
        Output::info('Process running: ', $processId . ' [Guard:' . $observerProcessId . ']');
    }

    /**
     * 设置进程ID
     * @param int       $processId
     * @param Publisher $jsonRPCPublisher
     * @return array
     */
    #[RPCMethod('设置进程ID')] public function setProcessId(int $processId, Publisher $jsonRPCPublisher): array
    {
        $TCPConnection = $jsonRPCPublisher->TCPConnection;
        $this->setClientName($TCPConnection, "process:{$processId}");
        $rpcServiceList = [];
        foreach (RPC::getInstance()->rpcServiceAddressList as $name => $info) {
            $rpcServiceList[] = [
                'name'    => $name,
                'address' => $info['address'],
                'type'    => $info['type']
            ];
        }
        return $rpcServiceList;
    }

    /**
     * 关闭进程
     * @param int $processId
     * @return bool
     */
    #[RPCMethod('关闭进程')] public function kill(int $processId): bool
    {
        $result = $this->signal($processId, SIGUSR2);
        unset($this->processObserverIdMap[$processId]);
        return $result;
    }

    /**
     * RPC服务上线
     * @param string    $name
     * @param string    $address
     * @param string    $type
     * @param Publisher $jsonRPCPublisher
     * @return void
     */
    #[RPCMethod('RPC服务上线')] public function registerRPCService(string $name, string $address, string $type, Publisher $jsonRPCPublisher): void
    {
        $connection = $jsonRPCPublisher->TCPConnection;
        try {
            EventMap::push(Event::build(Server::EVENT_ONLINE, [
                'name'    => $name,
                'address' => $address,
                'type'    => $type
            ], $this->name));
        } catch (Exception $exception) {
            Output::error($exception);
        }
        foreach ($this->getClients() as $client) {
            if ($client !== $connection) {
                try {
                    $this->slice->send($client, json_encode([
                        'version' => '2.0',
                        'method'  => 'noticeRpcServiceOnline',
                        'params'  => [$name, $address, $type]
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                } catch (\Cclilshy\PRipple\Core\Net\Exception|FileException $exception) {
                    Output::error($exception);
                }
            }
        }
    }

    /**
     * @param ...$arguments
     * @return void
     */
    #[RPCMethod('子进程输出')] protected function outputInfo(...$arguments): void
    {
        array_pop($arguments);
        call_user_func_array([Output::class, 'info'], $arguments);
    }

    /**
     * 子进程发布事件
     * @param Event $event
     * @return void
     */
    #[RPCMethod('子进程发布事件')] protected function event(Event $event): void
    {
        EventMap::push($event);
    }
}
