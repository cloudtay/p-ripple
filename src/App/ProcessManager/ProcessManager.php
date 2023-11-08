<?php
declare(strict_types=1);

namespace PRipple\App\ProcessManager;

use PRipple\App\Facade\Process;
use PRipple\PRipple;
use PRipple\Protocol\CCL;
use PRipple\Std\ProtocolStd;
use PRipple\Worker\Build;
use PRipple\Worker\NetWorker\Client;
use PRipple\Worker\NetworkWorkerInterface;
use PRipple\Worker\WorkerInterface;

class ProcessManager extends NetworkWorkerInterface
{
    public static string $UNIX_PATH;
    public static string $LOCK_PATH;

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

    public static function instance(): ProcessManager|WorkerInterface
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
            call_user_func([ProcessContainer::class, $command], ...$arguments);
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
        ProcessManager::$UNIX_PATH = PRipple::getArgument('RUNTIME_PATH') . '/p_ripple_process_manager.sock';
        ProcessManager::$LOCK_PATH = PRipple::getArgument('RUNTIME_PATH') . '/p_ripple_process_manager.lock';

        file_exists(ProcessManager::$UNIX_PATH) && unlink(ProcessManager::$UNIX_PATH);
        file_exists(ProcessManager::$LOCK_PATH) && unlink(ProcessManager::$LOCK_PATH);
        $this->bind('unix://' . ProcessManager::$UNIX_PATH);
        $this->protocol(CCL::class);
        parent::initialize();
        Process::setInstance($this);
    }

    /**
     * @param string $context
     * @param Client $client
     * @return void
     */
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
                PRipple::info('[Process]', 'new process:', $processId . '=>' . $observerProcessId);
                break;
            case 'process.observer':
                $processId = intval($build->data['processId']);
                $this->observerHashmap[$processId] = $client;
                $client->setIdentity($processId);
                $client->setName('process.observer');
                PRipple::info('[Process]', 'new observer:', strval($processId));
                break;
            case 'process.observer.count':
                $this->guardCounter(intval(
                    $build->data['observerProcessId']),
                    intval($build->data['guardCount'])
                );
                break;
        }
    }

    /**
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
     * @param Client $client
     * @return string|false
     */
    public function splitMessage(Client $client): string|false
    {
        return $this->protocol->cut($client);
    }

    /**
     * @param Client $client
     * @return void
     */
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

    /**
     * @return void
     */
    public function destroy(): void
    {
    }
}
