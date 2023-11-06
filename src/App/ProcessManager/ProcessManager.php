<?php
declare(strict_types=1);

namespace PRipple\App\ProcessManager;

use PRipple\PRipple;
use PRipple\Protocol\CCL;
use PRipple\Std\ProtocolStd;
use PRipple\Worker\Build;
use PRipple\Worker\NetWorker;
use PRipple\Worker\NetWorker\Client;
use PRipple\Worker\Worker;

class ProcessManager extends NetWorker
{
    public const UNIX_PATH = '/tmp/p_ripple_process_manager.sock';
    public const LOCK_PATH = '/tmp/p_ripple_process_manager.lock';

    /**
     * 映射:进程=>守护ID
     * @var array
     */
    public array $processObserverHashMap = [];

    /**
     * 映射 守护ID=>守护映射
     * @var Client[]
     */
    public array $observerHashmap = [];

    /**
     * @var array 守护计数
     */
    public array $observerCountMap = [];

    /**
     * CCL协议
     * @var ProtocolStd
     */
    public ProtocolStd $protocol;

    public static function instance(): ProcessManager|Worker
    {
        return PRipple::worker(ProcessManager::class);
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
     * 发送指令
     * @param int $observerProcessId
     * @param string $command
     * @param mixed ...$arguments
     * @return void
     */
    public function commandToObserver(int $observerProcessId, string $command, mixed ...$arguments): void
    {
        if ($observerProcessId === posix_getpid()) {
            call_user_func([Process::class, $command], ...$arguments);
        } elseif ($client = $this->observerHashmap[$observerProcessId]) {
            $this->protocol->send($client, Build::new('process.observer.command', [
                'action' => $command,
                'arguments' => $arguments
            ], ProcessManager::class)->__toString());
        }
    }

    /**
     * 初始化
     * @return void
     */
    public function initialize(): void
    {
        unlink(ProcessManager::UNIX_PATH);
        unlink(ProcessManager::LOCK_PATH);
        $this->bind('unix://' . ProcessManager::UNIX_PATH);
        $this->protocol(CCL::class);
        parent::initialize();
        \PRipple\App\Facade\Process::setInstance($this);
    }

    /**
     * @param Client $client
     * @return void
     */
    public function onConnect(Client $client): void
    {
    }

    public function destroy(): void
    {
    }

    public function onMessage(string $context, Client $client): void
    {
        /**
         * @var Build $build
         */
        $build = unserialize($context);
        switch ($build->name) {
            case 'process.fork':
                $observerProcessId = intval($build->data['observerProcessId']);
                $processId = intval($build->data['processId']);
                $this->processObserverHashMap[$processId] = $observerProcessId;
                $client->setIdentity($processId);
                $client->setName('process.fork');
                break;
            case 'process.observer':
                $processId = intval($build->data['processId']);
                $this->observerHashmap[$processId] = $client;
                $client->setIdentity($processId);
                $client->setName('process.observer');
                break;
            case 'process.observer.count':
                $this->guardCounter(intval(
                    $build->data['observerProcessId']),
                    intval($build->data['guardCount']
                    ));
                break;
        }
    }

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

    public function splitMessage(Client $client): string|false
    {
        return $this->protocol->cut($client);
    }

    public function onClose(Client $client): void
    {
        switch ($client->getName()) {
            case 'process.fork':
                $processId = $client->getIdentity();
                $observerProcessId = $this->processObserverHashMap[$processId];
                $this->guardCounter($observerProcessId, -1);
                unset($this->processObserverHashMap[$processId]);
                break;
            case 'process.observer':
                $processId = $client->getIdentity();
                unset($this->observerHashmap[$processId]);
                break;
        }
    }

    public function heartbeat(): void
    {
    }

    public function onHandshake(Client $client): void
    {
    }
}
