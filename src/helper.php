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


namespace Co;

use Cclilshy\PRipple\Core\Coroutine\Coroutine;
use Cclilshy\PRipple\Utils\Process;
use Cclilshy\PRipple\Utils\Timer;
use Cclilshy\PRipple\Worker\Built\JsonRPC\Exception\RPCException;
use Closure;
use Throwable;

/**
 * 延时
 * @param int $second
 * @return void
 * @throws Throwable
 */
function sleep(int $second): void
{
    Timer::sleep($second);
}


/**
 * 重复执行一个闭包
 * @param Closure  $closure
 * @param int|null $second
 * @return void
 */
function repeat(Closure $closure, int|null $second = 1): void
{
    Timer::repeat($closure, $second);
}

/**
 * 向进程发布信号
 * @param int $processId
 * @param int $signalNo
 * @return bool
 * @throws RPCException
 */
function signal(int $processId, int $signalNo): bool
{
    return Process::signal($processId, $signalNo);
}

/**
 * 关闭某个进程
 * @param int $processId
 * @return bool
 * @throws RPCException
 */
function kill(int $processId): bool
{
    return Process::kill($processId);
}

/**
 * 创建一个进程
 * @param Closure  $closure
 * @param int|null $timeout
 * @return int
 */
function process(Closure $closure, int|null $timeout = null): int
{
    return Process::fork($closure, $timeout);
}

/**
 * 异步执行
 * @param Closure $closure
 * @return Coroutine
 */
function async(Closure $closure): Coroutine
{
    return new class($closure) extends Coroutine {
        /**
         * @param Closure $closure
         */
        public function __construct(Closure $closure)
        {
            parent::__construct();
            $this->setup($closure)->queue();
        }
    };
}
