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
use Cclilshy\PRipple\Core\Map\CoroutineMap;
use Cclilshy\PRipple\Core\Output;
use Cclilshy\PRipple\Core\Standard\WorkerInterface;
use Cclilshy\PRipple\PRipple;
use Cclilshy\PRipple\Protocol\Slice;
use Cclilshy\PRipple\Utils\JsonRPC;
use Cclilshy\PRipple\Worker\Built\JsonRPC\Attribute\RPCMethod;
use Cclilshy\PRipple\Worker\Built\JsonRPC\Exception\RPCException;
use Cclilshy\PRipple\Worker\Built\JsonRPC\Publisher;
use Closure;
use Exception;
use RuntimeException;
use stdClass;
use Throwable;
use function Co\async;
use function Co\sleep;
use function file_exists;
use function posix_getpid;
use function posix_getppid;
use function unlink;
use const SIGUSR2;

/**
 * @class ProcessService 进程守护者
 */
final class ProcessService extends BuiltRPC implements WorkerInterface
{
    public const string PROCESS_RESULT    = 'core.process.service.result';
    public const string PROCESS_EXCEPTION = 'core.process.service.exception';
    public const string PROCESS_CALL      = 'core.process.service.call';

    /**
     * 初始化守护者
     * @return void
     */
    public function initialize(): void
    {
        $processUnixPath = ProcessService::generatePathByProcessId(posix_getpid());
        if (file_exists($processUnixPath)) {
            unlink($processUnixPath);
        }
        $this->bind("unix://{$processUnixPath}");
        $this->protocol(Slice::class);
        parent::initialize();
    }

    /**
     * 获取指定IP的unix路径
     * @param int $processId
     * @return string
     */
    protected static function generatePathByProcessId(int $processId): string
    {
        return PRipple::getArgument('PP_RUNTIME_PATH', '/tmp') . '/pg_' . $processId . '.socket';
    }

    /**
     * 守护者启动时触发
     * @return void
     */
    public function forkPassive(): void
    {
        parent::forkPassive();
        $this->listenAddressList = [];
        $processUnixPath         = ProcessService::generatePathByProcessId(posix_getpid());
        if (file_exists($processUnixPath)) {
            unlink($processUnixPath);
        }
        $parentUnixPath = ProcessService::generatePathByProcessId(posix_getppid());
        try {
            $this->bind("unix://{$processUnixPath}");
            $this->listen();
        } catch (Exception $exception) {
            try {
                JsonRPC::call([ProcessManager::class, 'outputInfo'], $exception->getMessage());
            } catch (RPCException $exception) {
                Output::error($exception);
            }
            exit(0);
        }
        JsonRPC::addService(ProcessService::class, "unix://{$parentUnixPath}", ProcessService::class);
    }

    /**
     * 释放资源
     * @return void
     */
    public function destroy(): void
    {
        parent::destroy();
        if ($this->isFork()) {
            unlink(ProcessService::generatePathByProcessId(posix_getpid()));
        }
    }

    /**
     * @param Closure  $closure
     * @param int|null $timeout
     * @return int
     */
    public function process(Closure $closure, int|null $timeout = null): int
    {
        if ($coroutine = CoroutineMap::this()) {
            $coroutine->flag(ProcessService::PROCESS_CALL);
        } else {
            return -1;
        }
        $processId = $this->fork();
        if ($processId === 0) {
            try {
                $result = $coroutine->callUserFunction($closure);
                JsonRPC::call([ProcessService::class, 'processResult'], $result, posix_getppid(), $coroutine->hash);
            } catch (Throwable $exception) {
                try {
                    JsonRPC::call([ProcessService::class, 'processException'], $exception, posix_getppid(), $coroutine->hash);
                } catch (RPCException $exception) {
                    Output::error($exception);
                }
            } finally {
                $this->destroy();
                exit(0);
            }
        } elseif ($processId > 0 && $timeout > 0) {
            async(function () use ($processId, $timeout, $coroutine) {
                sleep($timeout);
                if ($this->isFork()) {
                    JsonRPC::call([ProcessManager::class, 'signal'], $processId, SIGUSR2);
                } else {
                    ProcessManager::getInstance()->signal($processId, SIGUSR2);
                }
                if (!$coroutine->terminated()) {
                    $coroutine->erase(ProcessService::PROCESS_CALL);
                    $coroutine->resume(Event::build(
                        ProcessService::PROCESS_EXCEPTION,
                        new RuntimeException('runtime process timeout'),
                        $processId
                    ));
                }
                return false;
            });
        }
        return $processId;
    }

    /**
     * 子进程异常
     * @param stdClass  $exceptions
     * @param int       $processId
     * @param string    $clientHash
     * @param Publisher $jsonRPCPublisher
     * @return void
     */
    #[RPCMethod('子进程异常')] protected function processException(stdClass $exceptions, int $processId, string $clientHash, Publisher $jsonRPCPublisher): void
    {
        try {
            CoroutineMap::resume(
                $clientHash,
                Event::build(ProcessService::PROCESS_EXCEPTION, $exceptions, $processId),
                ProcessService::PROCESS_CALL
            );
        } catch (Throwable $exception) {
            Output::error($exception);
        }
    }

    /**
     * 子进程返回
     * @param mixed     $result
     * @param int       $processId
     * @param string    $clientHash
     * @param Publisher $jsonRPCPublisher
     * @return void
     */
    #[RPCMethod('子进程返回')] protected function processResult(mixed $result, int $processId, string $clientHash, Publisher $jsonRPCPublisher): void
    {
        try {
            CoroutineMap::resume(
                $clientHash,
                Event::build(ProcessService::PROCESS_RESULT, $result, $processId),
                ProcessService::PROCESS_CALL
            );
        } catch (Throwable $exception) {
            Output::error($exception);
        }
    }
}
