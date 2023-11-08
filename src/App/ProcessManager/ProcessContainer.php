<?php
declare(strict_types=1);

namespace PRipple\App\ProcessManager;

use Closure;
use Exception;
use JetBrains\PhpStorm\NoReturn;
use PRipple\PRipple;
use PRipple\Protocol\CCL;
use PRipple\Worker\Build;
use PRipple\Worker\NetWorker\Client;
use PRipple\Worker\NetWorker\SocketType\SocketUnix;

class ProcessContainer
{
    public static array $childrenIds = [];
    public static bool $isMaster = true;
    public static bool $hasObserver = true;
    public static int $observerProcessId = 0;
    public static int $guardCount = 0;
    public static Client $managerTunnel;

    /**
     * 创建子进程
     * @param Closure $callable
     * @return false|int
     */
    public static function fork(Closure $callable): false|int
    {
        if (ProcessContainer::$isMaster) {
            ProcessContainer::$hasObserver = false;
            ProcessContainer::$observerProcessId = posix_getpid();
        } elseif (!ProcessContainer::$hasObserver) {
            if (!ProcessContainer::$observerProcessId = ProcessContainer::startObserver()) {
                return false;
            }
            try {
                ProcessContainer::$managerTunnel = new Client(SocketUnix::connect(ProcessManager::$UNIX_PATH), SocketUnix::class);
            } catch (Exception $exception) {
                PRipple::printExpect($exception);
                return false;
            }
            ProcessContainer::$hasObserver = true;
        }
        $pid = pcntl_fork();
        if ($pid === -1) {
            return false;
        } elseif ($pid === 0) {
            ProcessContainer::$isMaster = false;
            ProcessContainer::$childrenIds = [];
            ProcessContainer::$hasObserver = false;
            ProcessContainer::$guardCount = 0;
            try {
                if ($socket = SocketUnix::connect(ProcessManager::$UNIX_PATH)) {
                    $aisle = new Client($socket, SocketUnix::class);
                    $ccl = new CCL;
                    $ccl->send($aisle, Build::new('process.fork', [
                        'observerProcessId' => ProcessContainer::$observerProcessId,
                        'processId' => posix_getpid(),
                    ], ProcessContainer::class)->__toString());
                    $callable();
                }
            } catch (Exception $exception) {
                PRipple::printExpect($exception);
            }
            exit;
        } else {
            ProcessContainer::$childrenIds[] = $pid;
            ProcessContainer::$guardCount++;
            if (ProcessContainer::$isMaster) {
                ProcessManager::instance()->processObserverHashMap[$pid] = posix_getpid();
            }
            return $pid;
        }
    }

    /**
     * 启动兄弟进程
     * @return false|int
     */
    public static function startObserver(): false|int
    {
        $pid = pcntl_fork();
        if ($pid === -1) {
            return false;
        } elseif ($pid === 0) {
            try {
                if ($socket = SocketUnix::connect(ProcessManager::$UNIX_PATH)) {
                    $aisle = new Client($socket, SocketUnix::class);
                    $ccl = new CCL;
                    $ccl->send($aisle, Build::new('process.observer', [
                        'processId' => posix_getpid(),
                    ], ProcessContainer::class)->__toString());
                    while (true) {
                        if ($build = $ccl->cut($aisle)) {
                            $build = unserialize($build);
                            call_user_func_array([ProcessContainer::class, $build->data['action']], $build->data['arguments']);
                        }
                    }
                }
            } catch (Exception $exception) {
                PRipple::printExpect($exception);
            }
            exit;
        } else {
            ProcessContainer::$childrenIds[] = $pid;
            return $pid;
        }
    }

    /**
     * 声明守护计数
     * @return void
     */
    public static function guarded(): void
    {
        if (ProcessContainer::$isMaster) {
            return;
        }
        $ccl = new CCL;
        $ccl->send(ProcessContainer::$managerTunnel, Build::new('process.observer.count', [
            'observerProcessId' => ProcessContainer::$observerProcessId,
            'guardCount' => ProcessContainer::$guardCount,
        ], ProcessContainer::class)->__toString());
        ProcessContainer::$hasObserver = false;
        ProcessContainer::$guardCount = 0;
    }

    /**
     * 发送信号
     * @param int $processId
     * @param int $signNo
     * @return void
     */
    public static function signal(int $processId, int $signNo): void
    {
        posix_kill($processId, $signNo);
    }

    /**
     * 退出进程
     * @return void
     */
    #[NoReturn] public static function exit(): void
    {
        exit;
    }
}
