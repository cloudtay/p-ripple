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

namespace Core\Map;

use Core\Constants;
use Core\MapInterface;
use Core\Output;
use Core\Std\CollaborativeFiberStd;
use Fiber;
use Throwable;
use Worker\Prop\Build;

/**
 *
 */
class CollaborativeFiberMap implements MapInterface
{
    /**
     * @var CollaborativeFiberStd[] $collaborativeFiberMap
     */
    public static array $collaborativeFiberMap = [];

    /**
     * @param CollaborativeFiberStd $collaborative
     * @return void
     */
    public static function addCollaborativeFiber(CollaborativeFiberStd $collaborative): void
    {
        CollaborativeFiberMap::$collaborativeFiberMap[spl_object_hash($collaborative->fiber)] = $collaborative;
    }

    /**
     * @param CollaborativeFiberStd $collaborative
     * @return void
     */
    public static function removeCollaborativeFiber(CollaborativeFiberStd $collaborative): void
    {
        unset(CollaborativeFiberMap::$collaborativeFiberMap[spl_object_hash($collaborative->fiber)]);
    }

    /**
     * @param string $hash
     * @return CollaborativeFiberStd|null
     */
    public static function getCollaborativeFiber(string $hash): CollaborativeFiberStd|null
    {
        return CollaborativeFiberMap::$collaborativeFiberMap[$hash] ?? null;
    }

    /**
     * @return CollaborativeFiberStd|null
     */
    public static function current(): CollaborativeFiberStd|null
    {
        return CollaborativeFiberMap::$collaborativeFiberMap[spl_object_hash(Fiber::getCurrent())];
    }

    /**
     * @param string $hash
     * @param mixed  $data
     * @return mixed
     * @throws Throwable
     */
    public static function resume(string $hash, mixed $data): mixed
    {
        if ($collaborativeFiber = CollaborativeFiberMap::$collaborativeFiberMap[$hash] ?? null) {
            return $collaborativeFiber->resumeFiberExecution($data);
        }
        return null;
    }

    /**
     * @param Throwable $exception
     * @return void
     */
    public static function currentThrowExceptionInFiber(Throwable $exception): void
    {
        try {
            Fiber::suspend(Build::new(
                Constants::EVENT_FIBER_THROW_EXCEPTION,
                $exception,
                CollaborativeFiberMap::current())
            );
        } catch (Throwable $exception) {
            Output::printException($exception);
        }
    }
}
