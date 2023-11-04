<?php
declare(strict_types=1);

namespace Cclilshy\PRipple\App\ProcessManager;

use Cclilshy\PRipple\PRipple;
use Cclilshy\PRipple\Std\ProtocolStd;
use Cclilshy\PRipple\Worker\Build;
use Cclilshy\PRipple\Worker\NetWorker;
use Cclilshy\PRipple\Worker\NetWorker\Client;
use Cclilshy\PRipple\Worker\Worker;

class ProcessManager extends NetWorker
{
    public const UNIX_PATH = '/tmp/pripple_process_manager.sock';
    public const LOCK_PATH = '/tmp/pripple_process_manager.lock';

    /**
     * 映射:进程=>守护ID
     * @var array
     */
    protected array $processObserverHashMap = [];

    /**
     * 映射 守护ID=>守护映射
     * @var Client[]
     */
    protected array $observerHashmap = [];

    /**
     * @var array 守护计数
     */
    protected array $observerCountMap = [];

    protected ProtocolStd $protocol;

    public static function instance(): ProcessManager|Worker
    {
        return PRipple::worker(ProcessManager::class);
    }

    public function signal(int $processId, int $signalNo): void
    {
        if ($observerId = $this->processObserverHashMap[$processId]) {
            $this->commandToObserver($observerId, 'signal', $processId, $signalNo);
        }
    }

    protected function commandToObserver(int $observerProcessId, string $command, mixed ...$arguments): void
    {
        if ($observerProcessId === Process::$observerProcessId) {
            call_user_func([Process::class, $command], ...$arguments);
        } elseif ($client = $this->observerHashmap[$observerProcessId]) {
            $this->protocol->send($client, Build::new('process.observer.command', [
                'action' => $command,
                'arguments' => $arguments
            ], ProcessManager::class)->__toString());
        }
    }

    protected function initialize(): void
    {
        unlink(ProcessManager::UNIX_PATH);
        unlink(ProcessManager::LOCK_PATH);
        parent::initialize();
    }

    protected function onConnect(Client $client): void
    {
    }

    protected function destroy(): void
    {
    }

    protected function onMessage(string $context, Client $client): void
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

    protected function guardCounter(int $observerProcessId, int $num): void
    {
        if (!isset($this->observerCountMap[$observerProcessId])) {
            $this->observerCountMap[$observerProcessId] = 0;
        }
        $this->observerCountMap[$observerProcessId] += $num;
        if ($this->observerCountMap[$observerProcessId] === 0) {
            ProcessManager::commandToObserver($observerProcessId, 'exit');
        }
    }

    protected function splitMessage(Client $client): string|false
    {
        return $this->protocol->cut($client);
    }

    protected function onClose(Client $client): void
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

    protected function heartbeat(): void
    {
    }

    protected function onHandshake(Client $client): void
    {
    }
}
