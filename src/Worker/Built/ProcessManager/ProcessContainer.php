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

use Closure;
use Core\FileSystem\FileException;
use Core\Output;
use Exception;
use JetBrains\PhpStorm\NoReturn;
use Protocol\Slice;
use Worker\Prop\Build;
use Worker\Socket\SocketUnix;
use Worker\Socket\TCPConnection;

/**
 * 进程载体
 */
class ProcessContainer
{
    /**
     * @var array
     */
    public static array $childrenIds = [];

    /**
     * @var bool
     */
    public static bool $isMaster = true;

    /**
     * @var bool
     */
    public static bool $hasObserver = true;

    /**
     * @var int
     */
    public static int $observerProcessId = 0;

    /**
     * @var int
     */
    public static int $guardCount = 0;

    /**
     * @var TCPConnection
     */
    public static TCPConnection $managerTunnel;

    /**
     * 创建子进程
     * @param Closure   $callable
     * @param bool|null $exit
     * @return false|int
     */
    public static function fork(Closure $callable, bool|null $exit = true): false|int
    {
        if (ProcessContainer::$isMaster) {
            ProcessContainer::$hasObserver       = false;
            ProcessContainer::$observerProcessId = posix_getpid();
        } elseif (!ProcessContainer::$hasObserver) {
            if (!ProcessContainer::$observerProcessId = ProcessContainer::startObserver()) {
                return false;
            }
            try {
                ProcessContainer::$managerTunnel = new TCPConnection(SocketUnix::connect(ProcessManager::$UNIX_PATH), SocketUnix::class);
            } catch (Exception $exception) {
                Output::printException($exception);
                return false;
            }
            ProcessContainer::$hasObserver = true;
        }
        $pid = pcntl_fork();
        if ($pid === -1) {
            return false;
        } elseif ($pid === 0) {
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, function () {
                exit;
            });
            pcntl_signal(SIGTERM, function () {
                exit;
            });
            pcntl_signal(SIGQUIT, function () {
                exit;
            });
            pcntl_signal(SIGUSR1, function () {
                exit;
            });
            pcntl_signal(SIGUSR2, function () {
                exit;
            });
            ProcessContainer::$isMaster    = false;
            ProcessContainer::$childrenIds = [];
            ProcessContainer::$hasObserver = false;
            ProcessContainer::$guardCount  = 0;
            try {
                if ($socket = SocketUnix::connect(ProcessManager::$UNIX_PATH)) {
                    $aisle = new TCPConnection($socket, SocketUnix::class);
                    $slice   = new Slice();
                    $slice->send($aisle, Build::new('process.fork', [
                        'observerProcessId' => ProcessContainer::$observerProcessId,
                        'processId'         => posix_getpid(),
                    ], ProcessContainer::class)->__toString());
                    $callable();
                }
            } catch (Exception $exception) {
                Output::printException($exception);
            }
            if ($exit) {
                exit;
            }
            return 0;
        } else {
            ProcessContainer::$childrenIds[] = $pid;
            ProcessContainer::$guardCount++;
            if (ProcessContainer::$isMaster) {
                ProcessManager::getInstance()->processObserverHashMap[$pid] = posix_getpid();
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
                    $aisle = new TCPConnection($socket, SocketUnix::class);
                    $slice   = new Slice();
                    $slice->send($aisle, Build::new('process.observer', [
                        'processId' => posix_getpid(),
                    ], ProcessContainer::class)->__toString());
                    while (true) {
                        if ($build = $slice->cut($aisle)) {
                            $build = unserialize($build);
                            call_user_func_array([ProcessContainer::class, $build->data['action']], $build->data['arguments']);
                        }
                    }
                }
            } catch (Exception $exception) {
                Output::printException($exception);
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
     * @throws FileException
     */
    public static function guarded(): void
    {
        if (ProcessContainer::$isMaster) {
            return;
        }
        $slice = new Slice();
        $slice->send(ProcessContainer::$managerTunnel, Build::new('process.observer.count', [
            'observerProcessId' => ProcessContainer::$observerProcessId,
            'guardCount'        => ProcessContainer::$guardCount,
        ], ProcessContainer::class)->__toString());

        ProcessContainer::$hasObserver = false;
        ProcessContainer::$guardCount  = 0;
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
