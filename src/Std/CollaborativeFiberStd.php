<?php
declare(strict_types=1);

namespace Std;

use Closure;
use Core\Map\CollaborativeFiberMap;
use Core\Map\WorkerMap;
use Core\Output;
use Fiber;
use ReflectionClass;
use Throwable;
use Worker\Build;

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
        $this->hash = spl_object_hash($this->fiber);
        CollaborativeFiberMap::addCollaborativeFiber($this);
        return $this;
    }

    /**
     * @return mixed
     * @throws Throwable
     */
    public function executeFiber(): mixed
    {
        return $this->fiber->start();
    }

    /**
     * @return bool
     */
    public function checkIfTerminated(): bool
    {
        return $this->fiber->isTerminated();
    }

    /**
     * @param mixed|null $value
     * @return mixed
     * @throws Throwable
     */
    public function resumeFiberExecution(mixed $value = null): mixed
    {
        return $this->fiber->resume($value);
    }

    /**
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
     * @param string $eventName
     * @param mixed $eventData
     * @return mixed
     * @throws Throwable
     */
    public function publishAwait(string $eventName, mixed $eventData): mixed
    {
        return Fiber::suspend(Build::new($eventName, $eventData, $this->hash));
    }

    /**
     * @param string $eventName
     * @param mixed $eventData
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
     * @param Build $event
     * @return void
     */
    abstract protected function handleEvent(Build $event): void;
}
