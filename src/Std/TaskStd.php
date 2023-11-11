<?php
declare(strict_types=1);

namespace Std;

use Fiber;
use PRipple;
use Throwable;
use Worker\Build;

/**
 * 任务标准
 */
abstract class TaskStd
{
    /**
     * @var string
     */
    public string $hash;

    /**
     * @var Fiber
     */
    public Fiber $fiber;

    public function __construct()
    {
        $this->hash = PRipple::uniqueHash();
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
     * @param Build $event
     * @return void
     */
    abstract protected function handleEvent(Build $event): void;
}
