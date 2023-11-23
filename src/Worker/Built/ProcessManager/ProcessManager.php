<?php
declare(strict_types=1);

namespace Worker\Built\ProcessManager;

use Core\Output;
use Core\Std\ProtocolStd;
use Facade\Process;
use Protocol\CCL;
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
     * 映射:进程=>守护ID
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
     * CCL协议
     * @var ProtocolStd
     */
    protected ProtocolStd $protocol;

    /**
     * 进程管理器门面
     * @var string $facadeClass
     */
    protected static string $facadeClass = Process::class;

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
        $this->protocol(CCL::class);
        parent::initialize();
    }

    /**
     * 守护计数器
     * @param string        $context
     * @param TCPConnection $client
     * @return void
     */
    protected function onMessage(string $context, TCPConnection $client): void
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
     * 发送指令
     * @param int    $observerProcessId
     * @param string $command
     * @param mixed  ...$arguments
     * @return void
     */
    public function commandToObserver(int $observerProcessId, string $command, mixed ...$arguments): void
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

    /**
     * 客户端断开连接
     * @param TCPConnection $client
     * @return void
     */
    protected function onClose(TCPConnection $client): void
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
}
