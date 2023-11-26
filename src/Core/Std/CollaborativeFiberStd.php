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

namespace Core\Std;

use Closure;
use Core\Map\CollaborativeFiberMap;
use Core\Map\WorkerMap;
use Core\Output;
use Fiber;
use ReflectionClass;
use Throwable;
use Worker\Prop\Build;

/**
 * 任务标准
 */
abstract class CollaborativeFiberStd
{
    /**
     * @var string $hash
     */
    public string $hash;

    /**
     * @var Fiber $fiber
     */
    public Fiber $fiber;

    /**
     * 关联映射
     * @var array $dependenceMap
     */
    public array $dependenceMap = [];

    /**
     * @param Closure $callable
     * @return $this
     */
    public function setupWithCallable(Closure $callable): CollaborativeFiberStd
    {
        $this->fiber = new Fiber($callable);
        $this->hash  = spl_object_hash($this->fiber);
        CollaborativeFiberMap::addCollaborativeFiber($this);
        return $this;
    }

    /**
     * 启动纤程
     * @return mixed
     * @throws Throwable
     */
    public function executeFiber(): mixed
    {
        return $this->fiber->start();
    }

    /**
     * 验证纤程是否已终止
     * @return bool
     */
    public function checkIfTerminated(): bool
    {
        return $this->fiber->isTerminated();
    }

    /**
     * 恢复纤程执行
     * @param mixed|null $value
     * @return mixed
     * @throws Throwable
     */
    public function resumeFiberExecution(mixed $value = null): mixed
    {
        return $this->fiber->resume($value);
    }

    /**
     * 向纤程抛出一个异常,由纤程的最后一个调用者捕获
     * @param Throwable $exception
     * @return void
     */
    public function throwExceptionInFiber(Throwable $exception): void
    {
        try {
            $this->fiber->throw($exception);
        } catch (Throwable $exception) {
            Output::printException($exception);
        }
    }

    /**
     * 异步发出一个事件
     * @param string $eventName
     * @param mixed  $eventData
     * @return mixed
     * @throws Throwable
     */
    public function publishAwait(string $eventName, mixed $eventData): mixed
    {
        return Fiber::suspend(Build::new($eventName, $eventData, $this->hash));
    }

    /**
     * 异步发出一个事件,由最后一个调用者捕获
     * @param string $eventName
     * @param mixed  $eventData
     * @return void
     * @throws Throwable
     */
    public function publishAsync(string $eventName, mixed $eventData): void
    {
        if ($response = Fiber::suspend(Build::new($eventName, $eventData, $this->hash))) {
            $this->handleEvent($response);
        }
    }


    /**
     * 解析类依赖
     * @param string $class
     * @return false|object
     * @throws Throwable
     */
    public function resolveDependencies(string $class): object|false
    {
        if ($object = $this->dependenceMap[$class] ?? null) {
            $this->injectDependencies($class, $object);
            return $object;
        } elseif ($worker = WorkerMap::get($class)) {
            $this->injectDependencies($class, $worker);
            return $worker;
        } elseif ($constructor = (new ReflectionClass($class))->getConstructor()) {
            $params = [];
            foreach ($constructor->getParameters() as $parameter) {
                if ($paramClass = $parameter->getType()?->getName()) {
                    $paramObject = $this->resolveDependencies($paramClass);
                    if ($paramObject) {
                        $params[] = $paramObject;
                    } else {
                        return false;
                    }
                } elseif ($parameter->isDefaultValueAvailable()) {
                    $params[] = $parameter->getDefaultValue();
                } else {
                    return false;
                }
            }
            return new $class(...$params);
        }
        return false;
    }

    /**
     * 依赖注入
     * @param string $class
     * @param object $instance
     * @return object
     */
    public function injectDependencies(string $class, object $instance): object
    {
        return $this->dependenceMap[$class] = $instance;
    }

    /**
     * @param string $class
     * @return object|null
     */
    public function getDependencies(string $class): object|null
    {
        return $this->dependenceMap[$class] ?? null;
    }

    /**
     * 销毁自身
     * @return true
     */
    public function destroy(): true
    {
        CollaborativeFiberMap::removeCollaborativeFiber($this);
        return true;
    }

    /**
     * 处理事件
     * @param Build $event
     * @return void
     */
    public function handleEvent(Build $event): void
    {

    }

    /**
     * 向纤程抛出一个异常,由纤程自身处理
     * @param Throwable $exception
     * @return true
     */
    public function exceptionHandler(Throwable $exception): true
    {
        $this->destroy();
        Output::printException($exception);
        return true;
    }
}
