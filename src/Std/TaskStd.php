<?php
declare(strict_types=1);

namespace PRipple\Std;

use Fiber;
use PRipple\PRipple;
use PRipple\Worker\Build;
use Throwable;

/**
 * 任务标准
 */
abstract class TaskStd
{
    public string $hash;
    public Fiber $fiber;

    public function __construct()
    {
        $this->hash = PRipple::instance()->uniqueHash();
        $this->fiber = Fiber::getCurrent();
    }

    /**
     * @param string $eventName
     * @param mixed $eventData
     * @return mixed
     * @throws Throwable
     */
    public function publishAwait(string $eventName, mixed $eventData): mixed
    {
        return Fiber::suspend(Build::new($eventName, $eventData, "task:{$this->hash}"));
    }

    /**
     * @param string $eventName
     * @param mixed $eventData
     * @return void
     * @throws Throwable
     */
    public function publishAsync(string $eventName, mixed $eventData): void
    {
        if ($response = Fiber::suspend(Build::new($eventName, $eventData, "task:{$this->hash}"))) {
            $this->handleEvent($response);
        }
    }

    /**
     * @param Build $event
     * @return void
     */
    abstract protected function handleEvent(Build $event): void;
}
